<?php
require_once 'vendor/autoload.php';
require_once 'spp/sppinit.php';

// Force context initialization to bypass auth if needed, or just let SPP boot normally.
// For showcase, we assume we just want to render a raw UI.

require_once 'DashboardComponent.php';
require_once 'TasksComponent.php';

$page = $_GET['page'] ?? 'dashboard';

use SPPMod\SPPView\LiveComponent;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPPLive Showcase</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .hidden { display: none; }
        .block { display: block; }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

    <!-- Offline Indicator -->
    <div wire:offline.class="block" class="hidden bg-red-600 text-white text-center py-2 font-bold transition-all duration-300 fixed top-0 w-full z-50">
        You are offline! Actions will be queued and sent when you reconnect.
    </div>

    <div class="flex h-screen pt-10">
        
        <!-- Sidebar Navigation (wire:navigate) -->
        <div class="w-64 bg-white shadow-lg p-6">
            <h1 class="text-2xl font-black text-indigo-600 mb-8">SPPLive</h1>
            <nav class="space-y-4">
                <a href="showcase.php?page=dashboard" wire:navigate class="block px-4 py-2 rounded <?= $page == 'dashboard' ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-gray-600 hover:bg-gray-50' ?>">
                    📊 Dashboard
                </a>
                <a href="showcase.php?page=tasks" wire:navigate class="block px-4 py-2 rounded <?= $page == 'tasks' ? 'bg-indigo-50 text-indigo-700 font-bold' : 'text-gray-600 hover:bg-gray-50' ?>">
                    ✅ Tasks Board
                </a>
            </nav>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 p-10 overflow-y-auto" id="main-content">
            <?php
            if ($page === 'dashboard') {
                echo LiveComponent::renderComponent(\App\LiveComponents\DashboardComponent::class);
            } elseif ($page === 'tasks') {
                echo LiveComponent::renderComponent(\App\LiveComponents\TasksComponent::class);
            }
            ?>
        </div>

    </div>

    <!-- SPPLive Script -->
    <script src="/res/spp/js/spplive.js"></script>
    <!-- SPPLive DevTools Script -->
    <script src="/res/spp/js/spplive-devtools.js"></script>
</body>
</html>
