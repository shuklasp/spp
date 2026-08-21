<?php
namespace App\ptable\Commands;

use SPP\Core\CLI\Command;
use SPP\Core\CLI\Console;
use App\ptable\Serv\PeriodicTableData;

class SeedWikiDataCommand extends Command
{
    protected string $name = 'ptable:seed-wiki';
    protected string $description = 'Seed all 118 elements deeply from Periodic-Table-JSON and Wikipedia.';

    public function isCLIOnly(): bool
    {
        return true;
    }

    public function execute(array $args, array $options): int
    {
        Console::info("Fetching deeply structured data for all elements...");

        // 1. Fetch Bowserinator/Periodic-Table-JSON
        $url = 'https://raw.githubusercontent.com/Bowserinator/Periodic-Table-JSON/master/PeriodicTableJSON.json';
        $json = file_get_contents($url);
        if (!$json) {
            Console::error("Failed to fetch PeriodicTableJSON.json");
            return 1;
        }

        $bowserData = json_decode($json, true)['elements'];
        $bowserMap = [];
        foreach ($bowserData as $bEl) {
            $bowserMap[strtoupper($bEl['symbol'])] = $bEl;
        }

        $elements = PeriodicTableData::getElements();
        $masterData = [];
        $imagesDir = SPP_APP_DIR . '/src/ptable/resources/themes/default/images/elements';

        foreach ($elements as $el) {
            $symbol = $el['symbol'];
            $name = $el['name'];
            Console::write("Processing {$name} ({$symbol})... ");

            $bData = $bowserMap[strtoupper($symbol)] ?? [];
            
            // Wikipedia REST API for extract and high-res image
            $restUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . urlencode($name);
            $ch = curl_init($restUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'SPP-PeriodicTable/1.0 (contact@example.com)');
            $restRes = curl_exec($ch);
            curl_close($ch);
            
            $restData = json_decode($restRes, true) ?? [];
            $extractHtml = $restData['extract_html'] ?? '<p>Information not available.</p>';
            $description = $restData['description'] ?? '';
            
            $imageUrl = $restData['originalimage']['source'] ?? $restData['thumbnail']['source'] ?? null;
            $localImagePath = null;
            if ($imageUrl) {
                $imgData = file_get_contents($imageUrl);
                if ($imgData) {
                    $ext = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                    $localFileName = "{$symbol}.{$ext}";
                    file_put_contents($imagesDir . '/' . $localFileName, $imgData);
                    $localImagePath = '/school1/ptable/theme-assets/default/images/elements/' . $localFileName;
                }
            }

            // MediaWiki Action API for deep sections
            $actionUrl = 'https://en.wikipedia.org/w/api.php?action=parse&page=' . urlencode($name) . '&prop=sections|text&format=json';
            $ch = curl_init($actionUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'SPP-PeriodicTable/1.0 (contact@example.com)');
            $actionRes = curl_exec($ch);
            curl_close($ch);

            $actionData = json_decode($actionRes, true) ?? [];
            
            $sectionsData = [];
            if (isset($actionData['parse']['sections'])) {
                // Find target sections (Isotopes, Occurrence, Uses, Significance)
                $targets = ['Isotopes', 'Occurrence', 'Applications', 'Uses', 'Biological role', 'History', 'Characteristics', 'Physical properties', 'Chemical properties'];
                
                $fullHtml = $actionData['parse']['text']['*'] ?? '';
                
                foreach ($actionData['parse']['sections'] as $sec) {
                    $line = $sec['line'];
                    if (in_array(trim($line), $targets)) {
                        $anchor = $sec['anchor'];
                        
                        // Extract HTML for this section using DOM or regex.
                        // Since Wikipedia HTML has `<h2 id="Anchor">`, we can extract everything until the next `<h2>`
                        $pattern = '/<h[2-6][^>]*id="' . preg_quote($anchor, '/') . '"[^>]*>.*?<\/h[2-6]>(.*?)(?=<h[2-6]|$)/is';
                        if (preg_match($pattern, $fullHtml, $matches)) {
                            $sectionsData[$line] = trim($matches[1]);
                        }
                    }
                }
            }

            $masterData[$symbol] = [
                'symbol' => $symbol,
                'name' => $name,
                'atomic' => $el['atomic'],
                'category' => $bData['category'] ?? $el['category'],
                'description' => $description,
                'extract_html' => $extractHtml,
                'local_image' => $localImagePath,
                
                // Deep physical/chemical
                'atomic_mass' => $bData['atomic_mass'] ?? $el['mass'],
                'density' => $bData['density'] ?? null,
                'melt' => $bData['melt'] ?? null,
                'boil' => $bData['boil'] ?? null,
                'phase' => $bData['phase'] ?? null,
                'discovered_by' => $bData['discovered_by'] ?? null,
                'electron_configuration' => $bData['electron_configuration'] ?? null,
                'electronegativity_pauling' => $bData['electronegativity_pauling'] ?? null,
                'electron_affinity' => $bData['electron_affinity'] ?? null,
                'ionization_energies' => $bData['ionization_energies'] ?? [],
                'cpk_hex' => $bData['cpk-hex'] ?? null,
                
                // Deep sections
                'sections' => $sectionsData
            ];
            
            Console::write("Done.\n");
        }

        $dataFile = SPP_APP_DIR . '/src/ptable/data/master_elements.json';
        file_put_contents($dataFile, json_encode($masterData, JSON_PRETTY_PRINT));

        Console::success("Successfully seeded 118 elements to {$dataFile}");
        return 0;
    }
}
