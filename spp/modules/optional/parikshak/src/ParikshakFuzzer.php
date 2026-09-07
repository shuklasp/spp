<?php
namespace SPPMod\Parikshak;

/**
 * Class ParikshakFuzzer
 * Intelligent Data Generator for Parikshak testing framework.
 */
class ParikshakFuzzer
{
    /**
     * Generates fuzzed test data based on schema type and hints.
     */
    public function fuzz(string $type, string $hint = '', bool $security = false, bool $unicode = false, array $rules = []): mixed
    {
        // Boundary Fuzzing logic
        if (isset($rules['min']) || isset($rules['max'])) {
            $min = $rules['min'] ?? 0;
            $max = $rules['max'] ?? 1000000;
            $boundaries = [$min - 1, $min, $min + 1, $max - 1, $max, $max + 1];
            return $boundaries[array_rand($boundaries)];
        }

        $type = strtolower($type);

        if ($unicode && (strpos($type, 'varchar') !== false || strpos($type, 'string') !== false || strpos($type, 'text') !== false)) {
            $chars = ["🚀", "漢", "الشروق", "✨", "ñ", "ü", "©️"];
            return $chars[array_rand($chars)] . "_" . substr(md5(uniqid()), 0, 5);
        }

        if ($security && (strpos($type, 'varchar') !== false || strpos($type, 'string') !== false || strpos($type, 'text') !== false)) {
            $payloads = [
                "<script>alert(';;;XSS_TEST_PAYLOAD;;;')</script>",
                "' OR ';;;SQLI_TEST_PAYLOAD;;;'=';;;SQLI_TEST_PAYLOAD;;;' --",
                ";;;SHELL_INJECT_TEST;;;",
                ";;;PATH_TRAVERSAL_TEST;;;",
                "{\"json\":\"malicious_;;;JSON_TEST_PAYLOAD;;;\"}",
                str_repeat("A", 1000) // Buffer overflow test
            ];
            return $payloads[array_rand($payloads)];
        }

        if (strpos($type, 'varchar') !== false || strpos($type, 'string') !== false) {
            $len = 10;
            if (preg_match('/\((\d+)\)/', $type, $m)) {
                $len = (int)$m[1];
            }
            $str = "PARIKSHAK_" . strtoupper($hint) . "_" . substr(md5(uniqid()), 0, 5);
            return substr($str, 0, $len);
        }

        if (strpos($type, 'int') !== false) {
            return rand(1, 1000000);
        }

        if (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false) {
            return (float)(rand(1, 1000) . '.' . rand(0, 99));
        }

        if (strpos($type, 'date') !== false || strpos($type, 'timestamp') !== false) {
            return date($type === 'datetime' || $type === 'timestamp' ? 'Y-m-d H:i:s' : 'Y-m-d');
        }

        if (strpos($type, 'time') !== false) {
            return date('H:i:s');
        }

        if (strpos($type, 'bool') !== false) {
            return rand(0, 1) ? true : false;
        }

        return "UNKNOWN_TYPE_" . $type;
    }
}
