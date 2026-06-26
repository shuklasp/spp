<?php
namespace Lekhak\Modules\Sankhyaki;

use SPPMod\SPPDB\SPPDB;

class GeoLocator
{

    public static function getCountry($ip)
    {
        if ($ip === '127.0.0.1' || $ip === '::1')
            return 'Localhost';

        $db = new SPPDB();

        // Ensure cache table exists
        try {
            $db->execute_query("SELECT 1 FROM lek_sankhyaki_geoip LIMIT 1");
        } catch (\Exception $e) {
            $schema = "ip_address VARCHAR(100) PRIMARY KEY, country VARCHAR(100)";
            $db->execute_query("CREATE TABLE IF NOT EXISTS lek_sankhyaki_geoip ({$schema})");
        }

        // Check local cache
        $cached = $db->execute_query("SELECT country FROM lek_sankhyaki_geoip WHERE ip_address = ?", [$ip]);
        if (!empty($cached)) {
            return $cached[0]['country'];
        }

        // Fetch from free API and cache locally
        $country = 'Unknown';
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country", false, $ctx);
            if ($json) {
                $data = json_decode($json, true);
                if (!empty($data['country'])) {
                    $country = $data['country'];
                }
            }
        } catch (\Exception $e) {
        }

        // Save to local cache
        try {
            $db->execute_query("INSERT INTO lek_sankhyaki_geoip (ip_address, country) VALUES (?, ?)", [$ip, $country]);
        } catch (\Exception $e) {
        }

        return $country;
    }
}
