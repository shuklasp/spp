<?php
namespace SPPMod\Contrib\AppDrivers;

use SPPMod\SPPIntegrations\IntegrationFactory;

/**
 * Class AppDrivers
 * 
 * Bootstrap for the Contrib App Drivers module.
 * Registers all included drivers with the core IntegrationFactory.
 */
class AppDrivers
{
    public static function init(): void
    {
        // Load the drivers
        require_once __DIR__ . '/class.drupal.php';
        require_once __DIR__ . '/class.wordpress.php';
        require_once __DIR__ . '/class.joomla.php';
        require_once __DIR__ . '/class.phpbb.php';
        require_once __DIR__ . '/class.moodle.php';
        require_once __DIR__ . '/class.coursera.php';
        require_once __DIR__ . '/class.magento.php';
        require_once __DIR__ . '/class.discourse.php';

        // Register them
        IntegrationFactory::registerDriver('drupal', DrupalDriver::class);
        IntegrationFactory::registerDriver('wordpress', WordPressDriver::class);
        IntegrationFactory::registerDriver('joomla', JoomlaDriver::class);
        IntegrationFactory::registerDriver('phpbb', PhpBbDriver::class);
        IntegrationFactory::registerDriver('moodle', MoodleDriver::class);
        IntegrationFactory::registerDriver('coursera', CourseraDriver::class);
        IntegrationFactory::registerDriver('magento', MagentoDriver::class);
        IntegrationFactory::registerDriver('discourse', DiscourseDriver::class);
    }
}

// Auto-init for SPP module lifecycle
AppDrivers::init();
