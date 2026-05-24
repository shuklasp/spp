<?php
namespace App\Lekhak\Serv;

use SPPMod\SPPAuth\SPPAuth;
use SPPMod\Lekhak\Core\CacheManager;

class CacheController
{
    public function settings()
    {
        if (!SPPAuth::check() || !\SPPMod\Lekhak\Core\LekhakUser::hasRight('administer site configuration')) {
            redirect('/admin/login');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            
            if ($action === 'clear_all') {
                CacheManager::clearAll();
                $message = "All caches have been cleared.";
            } elseif ($action === 'invalidate_tags') {
                $tags = array_map('trim', explode(',', $_POST['tags'] ?? ''));
                if (!empty($tags)) {
                    CacheManager::invalidateTags($tags);
                    $message = "Cache tags invalidated: " . implode(', ', $tags);
                }
            }
        }

        return view('cache_settings', [
            'message' => $message ?? null,
            'title' => 'Performance & Caching',
            'subtitle' => 'Manage dynamic page caching and cache tags.'
        ]);
    }
}
