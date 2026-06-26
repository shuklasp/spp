<?php
namespace Lekhak\Modules\Sankhyaki\Controller;

use SPPMod\SPPDB\SPPDB;

class StatsController
{

    private $db;

    public function __construct()
    {
        $this->db = new SPPDB();
    }

    public function getStats()
    {
        try {
            $total_pageviews = $this->db->execute_query("SELECT COUNT(*) as count FROM lek_sankhyaki_log")[0]['count'];
            $unique_visitors = $this->db->execute_query("SELECT COUNT(DISTINCT session_id) as count FROM lek_sankhyaki_log")[0]['count'];
            $logged_in_visits = $this->db->execute_query("SELECT COUNT(*) as count FROM lek_sankhyaki_log WHERE user_id > 0")[0]['count'];

            $top_pages = $this->db->execute_query("SELECT url, COUNT(*) as views FROM lek_sankhyaki_log GROUP BY url ORDER BY views DESC LIMIT 10");

            $search_engines = $this->db->execute_query("SELECT search_engine, COUNT(*) as visits FROM lek_sankhyaki_log WHERE search_engine != '' GROUP BY search_engine ORDER BY visits DESC");

            $top_referrers = $this->db->execute_query("SELECT referrer, COUNT(*) as visits FROM lek_sankhyaki_log WHERE referrer != '' AND search_engine = '' GROUP BY referrer ORDER BY visits DESC LIMIT 10");

            $search_phrases = $this->db->execute_query("SELECT search_query, COUNT(*) as count FROM lek_sankhyaki_log WHERE search_query != '' GROUP BY search_query ORDER BY count DESC LIMIT 10");

            $devices = $this->db->execute_query("SELECT device_type, COUNT(*) as count FROM lek_sankhyaki_log GROUP BY device_type ORDER BY count DESC");
            $browsers = $this->db->execute_query("SELECT browser, COUNT(*) as count FROM lek_sankhyaki_log GROUP BY browser ORDER BY count DESC LIMIT 10");
            $countries = $this->db->execute_query("SELECT country, COUNT(*) as count FROM lek_sankhyaki_log GROUP BY country ORDER BY count DESC LIMIT 10");
            $utm_sources = $this->db->execute_query("SELECT utm_source, COUNT(*) as count FROM lek_sankhyaki_log WHERE utm_source != '' GROUP BY utm_source ORDER BY count DESC LIMIT 10");

            // Bounce Rate (Percentage of sessions with only 1 pageview)
            $total_sessions = (int) $unique_visitors;
            $bounced_sessions = 0;
            if ($total_sessions > 0) {
                $bounces = $this->db->execute_query("SELECT COUNT(*) as count FROM (SELECT session_id FROM lek_sankhyaki_log GROUP BY session_id HAVING COUNT(*) = 1) as t");
                $bounced_sessions = (int) ($bounces[0]['count'] ?? 0);
            }
            $bounce_rate = $total_sessions > 0 ? round(($bounced_sessions / $total_sessions) * 100, 2) : 0;

            // Average Time on Page
            $time_stats = $this->db->execute_query("SELECT AVG(time_on_page) as avg_time FROM lek_sankhyaki_log WHERE time_on_page > 0");
            $avg_time_on_page = round((float) ($time_stats[0]['avg_time'] ?? 0), 2);

            return json_encode([
                'success' => true,
                'data' => [
                    'overview' => [
                        'pageviews' => (int) $total_pageviews,
                        'unique_visitors' => (int) $unique_visitors,
                        'logged_in_visits' => (int) $logged_in_visits,
                        'bounce_rate' => $bounce_rate,
                        'avg_time_on_page' => $avg_time_on_page
                    ],
                    'top_pages' => $top_pages,
                    'search_engines' => $search_engines,
                    'search_phrases' => $search_phrases,
                    'top_referrers' => $top_referrers,
                    'devices' => $devices,
                    'browsers' => $browsers,
                    'countries' => $countries,
                    'utm_sources' => $utm_sources
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
