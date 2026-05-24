<?php
namespace App\Lekhak\Serv;

use SPPMod\SPPAuth\SPPAuth;
use SPPMod\Lekhak\Core\ModuleRegistry;

class ModuleController extends AdminController
{
    public function index()
    {
        if (!SPPAuth::check() || !SPPAuth::hasRight('administer modules')) {
            redirect('/admin/login');
        }

        $modules = ModuleRegistry::getModules();
        $message = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'bulk') {
                $action = $_POST['bulk_action'] ?? '';
            }
            $modulesParam = $_POST['modules'] ?? [];
            $singleModule = $_POST['module'] ?? $_GET['module'] ?? '';
            
            // If it's a bulk action, use checkboxes.
            // If it's a single action, use ONLY the single module.
            if (strpos($action, 'bulk_') !== 0 && !empty($singleModule)) {
                $modulesParam = [$singleModule];
            } elseif (!empty($singleModule) && !in_array($singleModule, $modulesParam)) {
                $modulesParam[] = $singleModule;
            }

            if (!empty($modulesParam) && in_array($action, ['enable', 'disable', 'uninstall', 'bulk_enable', 'bulk_disable', 'bulk_uninstall'])) {
                $count = 0;
                foreach ($modulesParam as $moduleName) {
                    if ($moduleName === 'lekhak' && in_array($action, ['disable', 'bulk_disable', 'uninstall', 'bulk_uninstall'])) {
                        continue;
                    }
                    if ($action === 'enable' || $action === 'bulk_enable') {
                        ModuleRegistry::enableModule($moduleName);
                        $count++;
                    } elseif ($action === 'disable' || $action === 'bulk_disable') {
                        ModuleRegistry::disableModule($moduleName);
                        $count++;
                    } elseif ($action === 'uninstall' || $action === 'bulk_uninstall') {
                        ModuleRegistry::uninstallModule($moduleName);
                        $count++;
                    }
                }
                
                $actionDisplay = str_replace('bulk_', '', $action) . 'd';
                if ($actionDisplay == 'enabledd') $actionDisplay = 'enabled'; // Grammar correction
                if ($actionDisplay == 'disabledd') $actionDisplay = 'disabled';
                $message = "Successfully {$actionDisplay} {$count} module(s).";
            }

            // Refresh module list
            $modules = ModuleRegistry::getModules();
        }

        // Group modules by package
        $grouped = [];
        foreach ($modules as $machineName => $info) {
            $pkg = $info['package'];
            if (!isset($grouped[$pkg])) {
                $grouped[$pkg] = [];
            }
            $grouped[$pkg][$machineName] = $info;
        }

        ksort($grouped);

        return $this->render('modules', [
            'groupedModules' => $grouped,
            'message' => $message,
            'title' => 'Extend',
            'subtitle' => 'Manage modules and extensions for your site.'
        ]);
    }

    public function update()
    {
        if (!SPPAuth::check() || !SPPAuth::hasRight('administer modules')) {
            redirect('/admin/login');
        }

        ModuleRegistry::runUpdates();
        
        return $this->render('modules_update', [
            'title' => 'Database Updates',
            'subtitle' => 'Ran database updates successfully.',
            'message' => 'All pending updates have been executed successfully.'
        ]);
    }
}
