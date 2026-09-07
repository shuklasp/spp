<?php

namespace App\SPPDocs\Services;

/**
 * QrCodeGenerator
 * Lightweight, zero-dependency, pure-PHP QR Code vector SVG generator.
 * Produces standard ISO/IEC 18004 compliant QR codes (Model 2, Byte Mode)
 * for instant smartphone camera scanning of printed certificates and URLs.
 */
class QrCodeGenerator
{
    private const MODE_BYTE = 0b0100;

    public const ECC_L = 1; // ~7% error correction
    public const ECC_M = 0; // ~15% error correction

    private static array $exp = [];
    private static array $log = [];
    private static bool $gfInit = false;

    private static function initGF(): void
    {
        if (self::$gfInit) return;
        self::$exp = array_fill(0, 512, 0);
        self::$log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D; // Primitive polynomial x^8 + x^4 + x^3 + x^2 + 1
            }
        }
        for ($i = 255; $i < 512; $i++) {
            self::$exp[$i] = self::$exp[$i - 255];
        }
        self::$gfInit = true;
    }

    private static function gmul(int $x, int $y): int
    {
        if ($x === 0 || $y === 0) return 0;
        return self::$exp[self::$log[$x] + self::$log[$y]];
    }

    // Version specifications for ECC Level L (7%)
    private static array $versionSpecsL = [
        1 => [26,  [[1, 19]]],
        2 => [44,  [[1, 34]]],
        3 => [70,  [[1, 55]]],
        4 => [100, [[1, 80]]],
        5 => [134, [[1, 108]]],
        6 => [172, [[2, 68]]],
        7 => [196, [[2, 78]]],
        8 => [242, [[2, 97]]],
        9 => [292, [[2, 116]]],
        10 => [346, [[2, 68], [2, 69]]],
    ];

    // Alignment pattern center coordinates
    private static array $alignmentPatterns = [
        1 => [],
        2 => [6, 18],
        3 => [6, 22],
        4 => [6, 26],
        5 => [6, 30],
        6 => [6, 34],
        7 => [6, 22, 38],
        8 => [6, 24, 42],
        9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    /**
     * Generate pure SVG markup representing the QR code.
     */
    public static function svg(string $text, int $size = 140, int $eccLevel = self::ECC_L, string $fg = '#0f172a', string $bg = 'transparent'): string
    {
        self::initGF();
        $matrix = self::generateMatrix($text, $eccLevel);
        $moduleCount = count($matrix);
        $quietZone = 2; // modules
        $totalModules = $moduleCount + ($quietZone * 2);

        $path = '';
        for ($r = 0; $r < $moduleCount; $r++) {
            for ($c = 0; $c < $moduleCount; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c + $quietZone;
                    $y = $r + $quietZone;
                    $path .= "M{$x},{$y}h1v1h-1z ";
                }
            }
        }

        $bgRect = ($bg !== 'transparent') ? "<rect width='{$totalModules}' height='{$totalModules}' fill='{$bg}'/>" : "";

        return "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {$totalModules} {$totalModules}' width='{$size}' height='{$size}' shape-rendering='crispEdges'>" .
               $bgRect .
               "<path d='{$path}' fill='{$fg}'/>" .
               "</svg>";
    }

    private static function generateMatrix(string $text, int $eccLevel): array
    {
        $len = strlen($text);
        $version = 1;
        $found = false;
        for ($v = 1; $v <= 10; $v++) {
            $totalDataCapacity = 0;
            foreach (self::$versionSpecsL[$v][1] as $block) {
                $totalDataCapacity += $block[0] * $block[1];
            }
            $reqCodewords = $len + 2;
            if ($reqCodewords <= $totalDataCapacity) {
                $version = $v;
                $found = true;
                break;
            }
        }
        if (!$found) $version = 10;

        // 1. Encode bitstream
        $bits = sprintf('%04b%08b', self::MODE_BYTE, $len);
        for ($i = 0; $i < $len; $i++) {
            $bits .= sprintf('%08b', ord($text[$i]));
        }

        $spec = self::$versionSpecsL[$version];
        $totalDataCodewords = 0;
        foreach ($spec[1] as $b) {
            $totalDataCodewords += $b[0] * $b[1];
        }
        $totalDataBits = $totalDataCodewords * 8;

        // Terminator & byte padding
        $termLen = min(4, $totalDataBits - strlen($bits));
        if ($termLen > 0) {
            $bits .= str_repeat('0', $termLen);
        }
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        // Padding bytes 0xEC, 0x11
        $pad = ['11101100', '00010001'];
        $pIdx = 0;
        while (strlen($bits) < $totalDataBits) {
            $bits .= $pad[$pIdx];
            $pIdx ^= 1;
        }

        $dataCodewords = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $dataCodewords[] = bindec(substr($bits, $i, 8));
        }

        // 2. Block partitioning & Reed-Solomon EC computation
        $blocks = [];
        $cwIdx = 0;
        foreach ($spec[1] as $group) {
            $blockCount = $group[0];
            $dataPerBlock = $group[1];
            for ($b = 0; $b < $blockCount; $b++) {
                $blocks[] = array_slice($dataCodewords, $cwIdx, $dataPerBlock);
                $cwIdx += $dataPerBlock;
            }
        }

        $totalCodewords = $spec[0];
        $totalEcCodewords = $totalCodewords - $totalDataCodewords;
        $numBlocks = count($blocks);
        $ecCodewordsPerBlock = (int)($totalEcCodewords / $numBlocks);

        $ecBlocks = [];
        $generator = self::buildGenerator($ecCodewordsPerBlock);
        foreach ($blocks as $block) {
            $ecBlocks[] = self::calculateEc($block, $generator);
        }

        // 3. Interleaving data and EC
        $finalCodewords = [];
        $maxDataLen = max(array_map('count', $blocks));
        for ($i = 0; $i < $maxDataLen; $i++) {
            foreach ($blocks as $block) {
                if (isset($block[$i])) {
                    $finalCodewords[] = $block[$i];
                }
            }
        }

        for ($i = 0; $i < $ecCodewordsPerBlock; $i++) {
            foreach ($ecBlocks as $ecBlock) {
                if (isset($ecBlock[$i])) {
                    $finalCodewords[] = $ecBlock[$i];
                }
            }
        }

        $allBits = '';
        foreach ($finalCodewords as $cw) {
            $allBits .= sprintf('%08b', $cw);
        }

        $remainderBits = [1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 0, 8 => 0, 9 => 0, 10 => 0];
        $allBits .= str_repeat('0', $remainderBits[$version] ?? 0);

        // 4. Matrix layout
        $size = 17 + ($version * 4);
        $matrix = array_fill(0, $size, array_fill(0, $size, null));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        self::placeFinder($matrix, $reserved, 0, 0);
        self::placeFinder($matrix, $reserved, $size - 7, 0);
        self::placeFinder($matrix, $reserved, 0, $size - 7);

        for ($i = 8; $i < $size - 8; $i++) {
            $val = ($i % 2 === 0);
            if (!$reserved[6][$i]) {
                $matrix[6][$i] = $val;
                $reserved[6][$i] = true;
            }
            if (!$reserved[$i][6]) {
                $matrix[$i][6] = $val;
                $reserved[$i][6] = true;
            }
        }

        $alignCoords = self::$alignmentPatterns[$version] ?? [];
        foreach ($alignCoords as $r) {
            foreach ($alignCoords as $c) {
                if ($reserved[$r][$c]) continue;
                self::placeAlignment($matrix, $reserved, $r - 2, $c - 2);
            }
        }

        // Dark module
        $matrix[(4 * $version) + 9][8] = true;
        $reserved[(4 * $version) + 9][8] = true;

        // Reserved format info areas
        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }
        for ($i = $size - 8; $i < $size; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }

        // 5. Zigzag data placement
        $bitIdx = 0;
        $totalBitsLen = strlen($allBits);
        $up = true;
        for ($right = $size - 1; $right > 0; $right -= 2) {
            if ($right === 6) $right--;
            $rows = $up ? range($size - 1, 0) : range(0, $size - 1);
            foreach ($rows as $r) {
                foreach ([$right, $right - 1] as $c) {
                    if (!$reserved[$r][$c]) {
                        $matrix[$r][$c] = ($bitIdx < $totalBitsLen) ? ($allBits[$bitIdx] === '1') : false;
                        $bitIdx++;
                    }
                }
            }
            $up = !$up;
        }

        // 6. Mask pattern 0 ((r + c) % 2 == 0)
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if (!$reserved[$r][$c]) {
                    if (($r + $c) % 2 === 0) {
                        $matrix[$r][$c] = !$matrix[$r][$c];
                    }
                }
            }
        }

        // 7. Format Information for Level L + Mask 0
        $formatBits = [1, 1, 1, 0, 0, 1, 0, 1, 1, 1, 1, 0, 0, 1, 1];
        $coordsTopLeft = [
            [8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5],
            [8, 7], [8, 8], [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8]
        ];
        foreach ($coordsTopLeft as $idx => [$r, $c]) {
            $matrix[$r][$c] = ($formatBits[$idx] === 1);
        }

        for ($i = 0; $i < 7; $i++) {
            $matrix[$size - 1 - $i][8] = ($formatBits[$i] === 1);
        }
        for ($i = 0; $i < 8; $i++) {
            $matrix[8][$size - 8 + $i] = ($formatBits[7 + $i] === 1);
        }

        return $matrix;
    }

    private static function placeFinder(array &$matrix, array &$reserved, int $startR, int $startC): void
    {
        for ($r = 0; $r < 7; $r++) {
            for ($c = 0; $c < 7; $c++) {
                $isBlack = ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
                $matrix[$startR + $r][$startC + $c] = $isBlack;
                $reserved[$startR + $r][$startC + $c] = true;
            }
        }
        $size = count($matrix);
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                if ($r === -1 || $r === 7 || $c === -1 || $c === 7) {
                    $mr = $startR + $r;
                    $mc = $startC + $c;
                    if ($mr >= 0 && $mr < $size && $mc >= 0 && $mc < $size) {
                        $matrix[$mr][$mc] = false;
                        $reserved[$mr][$mc] = true;
                    }
                }
            }
        }
    }

    private static function placeAlignment(array &$matrix, array &$reserved, int $startR, int $startC): void
    {
        for ($r = 0; $r < 5; $r++) {
            for ($c = 0; $c < 5; $c++) {
                $isBlack = ($r === 0 || $r === 4 || $c === 0 || $c === 4 || ($r === 2 && $c === 2));
                $matrix[$startR + $r][$startC + $c] = $isBlack;
                $reserved[$startR + $r][$startC + $c] = true;
            }
        }
    }

    private static function buildGenerator(int $degree): array
    {
        $g = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($g) + 1, 0);
            $factor = self::$exp[$i];
            for ($j = 0; $j < count($g); $j++) {
                $next[$j] ^= self::gmul($g[$j], $factor);
                $next[$j + 1] ^= $g[$j];
            }
            $g = $next;
        }
        return array_reverse($g);
    }

    private static function calculateEc(array $data, array $generator): array
    {
        $ecLen = count($generator) - 1;
        $poly = array_merge($data, array_fill(0, $ecLen, 0));

        for ($i = 0; $i < count($data); $i++) {
            $coef = $poly[$i];
            if ($coef !== 0) {
                for ($j = 0; $j < count($generator); $j++) {
                    $poly[$i + $j] ^= self::gmul($generator[$j], $coef);
                }
            }
        }
        return array_slice($poly, count($data));
    }
}
