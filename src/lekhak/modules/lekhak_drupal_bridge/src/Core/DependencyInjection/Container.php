<?php
namespace Lekhak\Modules\LekhakDrupalBridge\Core\DependencyInjection;

use SPPMod\SPPDB\SPPDB;
use SPP\SPPConfig;

/**
 * A basic Dependency Injection container mock for Drupal 8+ compatibility.
 */
class Container
{

    protected $services = [];

    public function __construct()
    {
        // Register default mocked services
        $this->services['database'] = new \Lekhak\Modules\LekhakDrupalBridge\Core\Database\DatabaseWrapper();
        $this->services['config.factory'] = new ConfigFactoryWrapper();
        $this->services['logger.factory'] = new LoggerFactoryWrapper();
        $this->services['state'] = new StateWrapper();
        $this->services['module_handler'] = new ModuleHandlerWrapper();
        $this->services['current_user'] = new CurrentUserWrapper();
        $this->services['datetime.time'] = new TimeWrapper();
        $this->services['entity_type.manager'] = new EntityTypeManagerWrapper();
        $this->services['form_builder'] = new \Lekhak\Modules\LekhakDrupalBridge\Core\Form\FormBuilder();
        $this->services['renderer'] = new \Lekhak\Modules\LekhakDrupalBridge\Core\Render\Renderer();
    }

    public function get($id)
    {
        if (!isset($this->services[$id])) {
            // For unrecognized services, we return a generic mock object
            // to prevent fatal errors when modules query obscure services.
            return new GenericServiceMock($id);
        }
        return $this->services[$id];
    }
}

require_once __DIR__ . '/../Database/QueryBuilders.php';

class ConfigFactoryWrapper
{
    public function get($name)
    {
        return new ConfigObjectWrapper($name);
    }
    public function getEditable($name)
    {
        return new ConfigObjectWrapper($name);
    }
}

class ConfigObjectWrapper
{
    protected $name;
    public function __construct($name)
    {
        $this->name = $name;
    }
    public function get($key)
    {
        return SPPConfig::get("drupal:{$this->name}:{$key}");
    }
    public function set($key, $value)
    {
        SPPConfig::set("drupal:{$this->name}:{$key}", $value);
        return $this;
    }
    public function save()
    {
        return $this;
    }
}

class LoggerFactoryWrapper
{
    public function get($channel)
    {
        return new LoggerChannelWrapper($channel);
    }
}

class LoggerChannelWrapper
{
    protected $channel;
    public function __construct($channel)
    {
        $this->channel = $channel;
    }
    public function notice($message, array $context = [])
    {
        $this->log('notice', $message, $context);
    }
    public function warning($message, array $context = [])
    {
        $this->log('warning', $message, $context);
    }
    public function error($message, array $context = [])
    {
        $this->log('error', $message, $context);
    }
    protected function log($level, $message, $context)
    {
        error_log("[Drupal Bridge] [$level] [{$this->channel}] " . strtr($message, $context));
    }
}

class StateWrapper
{
    public function get($key, $default = null)
    {
        return SPPConfig::get("drupal_state:{$key}", $default);
    }
    public function set($key, $value)
    {
        SPPConfig::set("drupal_state:{$key}", $value);
    }
}

class ModuleHandlerWrapper
{
    public function invokeAll($hook, $args = [])
    {
        // Tie into Lekhak's event kernel
        if (class_exists('\SPP\EventKernel')) {
            \SPP\EventKernel::trigger("drupal_hook_{$hook}", $args);
        }
    }
    public function moduleExists($module)
    {
        if (class_exists('\SPPMod\Lekhak\Core\ModuleRegistry')) {
            return \SPPMod\Lekhak\Core\ModuleRegistry::isModuleEnabled($module);
        }
        return false;
    }
}

class CurrentUserWrapper
{
    public function id()
    {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    }
    public function isAuthenticated()
    {
        return $this->id() > 0;
    }
    public function hasPermission($permission)
    {
        return true; // Simplified for bridge
    }
}

class TimeWrapper
{
    public function getRequestTime()
    {
        return $_SERVER['REQUEST_TIME'];
    }
    public function getCurrentTime()
    {
        return time();
    }
}

class EntityTypeManagerWrapper
{
    public function getStorage($entity_type)
    {
        if (in_array($entity_type, ['node', 'user', 'taxonomy_term'])) {
            return new MockEntityStorage($entity_type);
        }
        return new GenericServiceMock("storage_$entity_type");
    }
}

class MockEntityStorage
{
    protected $type;
    public function __construct($type)
    {
        $this->type = $type;
    }
    public function load($id)
    {
        return new MockEntity($this->type, $id);
    }
    public function loadMultiple(array $ids = null)
    {
        $res = [];
        if ($ids) {
            foreach ($ids as $id)
                $res[$id] = $this->load($id);
        }
        return $res;
    }
    public function create(array $values = [])
    {
        $entity = new MockEntity($this->type, null);
        foreach ($values as $k => $v) {
            $entity->{$k} = $v;
        }
        return $entity;
    }
}

class MockEntity
{
    protected $type;
    protected $id;
    public function __construct($type, $id)
    {
        $this->type = $type;
        $this->id = $id;
    }
    public function id()
    {
        return $this->id;
    }
    public function __call($name, $arguments)
    {
        return null;
    }
    public function __get($name)
    {
        return null;
    }
    public function save()
    {
        return true;
    }
}

class GenericServiceMock
{
    protected $id;
    public function __construct($id)
    {
        $this->id = $id;
    }
    public function __call($name, $arguments)
    {
        // Silently swallow calls to unknown services
        return null;
    }
}
