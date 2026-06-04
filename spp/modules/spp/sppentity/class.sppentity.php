<?php

namespace SPPMod\SPPEntity;

use SPP\Exceptions\AttributeNotFoundException;
use SPP\Exceptions\EntityNotFoundException;
use SPP\Exceptions\EntityConfigurationException;

require_once('entityexceptions.php');
require_once('class.sppentityrelations.php');
/**
 * class Entity
 * Defines an entity
 */

class SPPEntity implements \JsonSerializable
{
    protected $id = null;
    protected static $_metadata = [];         /** Static registry for entity configuration */

    protected $_values = [];                      /** attribute-value pairs */
    protected $_dynamic_values = [];              /** dynamic polymorphic field values */
    protected $_snapshot = [];                    /** data snapshot for auditing */
    protected $_relatedCaches = [];               /** lazy-loaded relations cache */

    protected $currentLanguage = 'en';                 /** Active translation language */
    protected $_translations = [];                /** Cached translation data */

    /**
     * Set the active translation language for the entity.
     */
    public function setLanguage(string $langCode)
    {
        $this->currentLanguage = $langCode;
        $this->_loadTranslations();
        return $this;
    }

    /**
     * Load translations from the database.
     */
    protected function _loadTranslations()
    {
        if ($this->id !== null && $this->currentLanguage !== 'en') {
            $db = new \SPPMod\SPPDB\SPPDB();
            $result = $db->execute_query(
                "SELECT translated_data FROM spp_entity_translations WHERE entity_class = ? AND entity_id = ? AND language_code = ?",
                [static::class, $this->id, $this->currentLanguage]
            );
            if (!empty($result)) {
                $this->_translations = json_decode($result[0]['translated_data'], true) ?: [];
            } else {
                $this->_translations = [];
            }
        }
    }

    /**
     * Save current translations to the database.
     */
    protected function _saveTranslations()
    {
        if ($this->id !== null && $this->currentLanguage !== 'en' && !empty($this->_translations)) {
            $db = new \SPPMod\SPPDB\SPPDB();
            $json = json_encode($this->_translations, JSON_UNESCAPED_UNICODE);
            $db->execute_query(
                "INSERT INTO spp_entity_translations (entity_class, entity_id, language_code, translated_data) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE translated_data = ?",
                [static::class, $this->id, $this->currentLanguage, $json, $json]
            );
        }
    }

    /**
     * public function __construct($id, $name)
     * Constructor
     * @param int $id
     */
    public function __construct($id = null)
    {
        $this->_values = [];
        $class = static::class;

        if (!isset(self::$_metadata[$class])) {
            static::loadEntityConfig($class);
        }

        $this->after_creation();
        $this->id = $id;
        if ($id != null) {
            $this->load($id);
        }
    }

    /**
     * Loads the entity configuration from YAML, supporting inheritance.
     */
    protected static function loadEntityConfig(string $class)
    {
        $reflection = new \ReflectionClass($class);
        $shortName = $reflection->getShortName();
        $yml_file = static::getEntityConfigFile($shortName);

        $config = [
            'table' => strtolower($shortName) . 's',
            'id_field' => 'id',
            'sequence' => strtolower($shortName) . '_seq',
            'login_enabled' => false,
            'profile' => null,
            'attributes' => []
        ];

        // Check for hardcoded properties on the class as higher-priority defaults
        $defaultProps = $reflection->getDefaultProperties();
        if (isset($defaultProps['table'])) {
            $config['table'] = $defaultProps['table'];
        }
        if (isset($defaultProps['id_field'])) {
            $config['id_field'] = $defaultProps['id_field'];
        }
        if (isset($defaultProps['sequence'])) {
            $config['sequence'] = $defaultProps['sequence'];
        }

        self::$_metadata[$class] = &$config;

        if ($yml_file !== false) {
            $ymlData = self::parseYaml($yml_file);

            // Handle recursion for 'extends'
            if (isset($ymlData['extends'])) {
                $parentClass = $ymlData['extends'];
                // Ensure parent is loaded
                if (!isset(self::$_metadata[$parentClass])) {
                    static::loadEntityConfig($parentClass);
                }
                // Inherit from parent
                $parentConfig = self::$_metadata[$parentClass];
                $config = array_merge($config, $parentConfig);
                // For attributes, we want a deep merge.
                if (isset($parentConfig['attributes'])) {
                    $config['attributes'] = $parentConfig['attributes'];
                }
            }

            // Override with current YAML values
            if (isset($ymlData['table'])) {
                $config['table'] = $ymlData['table'];
            }
            if (isset($ymlData['id_field'])) {
                $config['id_field'] = $ymlData['id_field'];
            }
            if (isset($ymlData['sequence'])) {
                $config['sequence'] = $ymlData['sequence'];
            }
            if (isset($ymlData['login_enabled'])) {
                $config['login_enabled'] = (bool) $ymlData['login_enabled'];
            }
            if (isset($ymlData['profile'])) {
                $config['profile'] = $ymlData['profile'];
            }

            if (isset($ymlData['attributes']) && is_array($ymlData['attributes'])) {
                foreach ($ymlData['attributes'] as $k => $v) {
                    $config['attributes'][$k] = $v;
                }
            }

            if (isset($ymlData['dynamic_attributes']) && is_array($ymlData['dynamic_attributes'])) {
                $config['dynamic_attributes'] = [];
                foreach ($ymlData['dynamic_attributes'] as $k => $v) {
                    $config['dynamic_attributes'][$k] = $v;
                }
            }

            if (isset($ymlData['validation']) && is_array($ymlData['validation'])) {
                $config['validation'] = $ymlData['validation'];
            }

            // Merge profile attribute
            if ($config['profile'] !== null) {
                $config['attributes']['profid'] = 'bigint';
            }

            // Register relations (only for the current entity level to avoid duplicate registrations if parent also registered them)
            if (isset($ymlData['relations']) && is_array($ymlData['relations'])) {
                foreach ($ymlData['relations'] as $rel) {
                    \SPPMod\SPPEntity\SPPEntityRelations::registerEntityRelation(
                        $rel['name'] ?? null,
                        $rel['parent_entity'] ?? $class,
                        $rel['parent_entity_field'] ?? 'id',
                        $rel['child_entity'] ?? $class,
                        $rel['child_entity_field'] ?? 'parent_id',
                        $rel['relation_type'] ?? 'OneToMany'
                    );
                }
            }

            // Sugar support for modern keys
            $sugar = [
                'hasMany'   => 'OneToMany',
                'hasOne'    => 'OneToOne',
                'belongsTo' => 'ManyToOne'
            ];

            foreach ($sugar as $key => $type) {
                if (isset($ymlData[$key]) && is_array($ymlData[$key])) {
                    foreach ($ymlData[$key] as $relName => $relData) {
                        $childClass = is_array($relData) ? ($relData['entity'] ?? null) : $relData;
                        if (!$childClass) {
                            continue;
                        }

                        // Resolve Namespaced child class if needed
                        if (strpos($childClass, '\\') === false) {
                            $ns = $reflection->getNamespaceName();
                            $childClass = $ns . '\\' . $childClass;
                        }

                        $pField = 'id';
                        $cField = 'parent_id';

                        if ($type === 'ManyToOne') {
                            $pField = 'id';
                            $cField = strtolower($reflection->getShortName()) . '_id'; // Default FK
                        }

                        if (is_array($relData)) {
                            if (isset($relData['parent_field'])) {
                                $pField = $relData['parent_field'];
                            }
                            if (isset($relData['child_field'])) {
                                $cField = $relData['child_field'];
                            }
                            if (isset($relData['fk'])) {
                                $cField = $relData['fk'];
                            }
                        }

                        \SPPMod\SPPEntity\SPPEntityRelations::registerEntityRelation(
                            $relName,
                            $type === 'ManyToOne' ? $childClass : $class,
                            $pField,
                            $type === 'ManyToOne' ? $class : $childClass,
                            $cField,
                            $type
                        );
                    }
                }
            }

            // Re-register parent relations for this child if they were "relative" (no entity specified)
            // Actually, if we just store relations in config, we can handle them better.
            // For now, I'll stick to what's in the YAML.

        } else {
            // Fallback to define_attributes if no YAML
            try {
                $instance = $reflection->newInstanceWithoutConstructor();
                $config['attributes'] = $instance->define_attributes();
            } catch (\Exception $e) {
                // Methods might not be there if it's a dynamic class
            }
        }

        // Post-load validation: If we ended up with an empty table name, it's a critical configuration failure
        if (empty($config['table'])) {
            throw new EntityConfigurationException("Entity configuration error: Class '{$class}' has no database table defined and no default could be resolved.");
        }
    }

    public static function getMetadata(string $key, $default = null)
    {
        if (!isset(self::$_metadata[static::class])) {
            static::loadEntityConfig(static::class);
        }
        return self::$_metadata[static::class][$key] ?? $default;
    }

    public static function setMetadata(string $key, $value)
    {
        self::$_metadata[static::class][$key] = $value;
    }

    /**
     * Helper to parse YAML using PECL extension or Symfony fallback.
     */
    protected static function parseYaml(string $file)
    {
        if (function_exists('yaml_parse_file')) {
            return yaml_parse_file($file);
        }
        if (class_exists('\Symfony\Component\Yaml\Yaml')) {
            return \Symfony\Component\Yaml\Yaml::parseFile($file);
        }
        throw new \SPP\SPPException("No YAML parser found (PECL yaml or Symfony Yaml required)");
    }

    public function define_attributes()
    {
        return null;
    }

    public function after_creation()
    {
        // To be implemented in derived classes
    }

    public function after_load()
    {
        if (class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            \SPPMod\SPPCache\SPPCacheManager::addTag(static::getEntityName(static::class) . ':' . $this->id);
            \SPPMod\SPPCache\SPPCacheManager::addTag(static::getEntityName(static::class) . '_list');
        }
    }

    public function before_save()
    {
        // To be implemented in derived classes
    }

    public function after_save()
    {
        if (class_exists('\\SPPMod\\SPPCache\\SPPCacheManager')) {
            \SPPMod\SPPCache\SPPCacheManager::invalidateTags([
                static::getEntityName(static::class) . ':' . $this->id,
                static::getEntityName(static::class) . '_list'
            ]);
        }
    }

    /**
     * public function __toString()
     * Returns the name of the entity
     * @return string
     */
    public function __toString()
    {
        return strval($this->id);
    }

    /**
     * Serializes the entity specifically for JSON conversion.
     */
    public function jsonSerialize(): mixed
    {
        $data = array_merge(
            ['id' => $this->id],
            $this->_values
        );

        // Flatten dynamic fields from fields_data JSON
        if (isset($data['fields_data'])) {
            $fieldsData = $data['fields_data'];
            if (is_string($fieldsData)) {
                $decoded = json_decode($fieldsData, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $data = array_merge($data, $decoded);
                }
            } elseif (is_array($fieldsData)) {
                $data = array_merge($data, $fieldsData);
            }
            // We can leave or unset fields_data here. Let's keep it for completeness or unset to be clean.
            unset($data['fields_data']);
        }

        return $data;
    }

    /**
     * public function __isset($arrt)
     * Magic function to check if attribute exists
     * @param string $attr
     * @return bool $attr exists or not
     */
    public function __isset($attr)
    {
        return $this->attributeExists($attr);
    }

    /**
     * Magic function to get attribute value
     * @param string $attribute
     * @return mixed
     */
    /**
     * Magic function to get attribute value
     * @param string $attribute
     * @return mixed
     */
    public function __get($attribute)
    {
        try {
            return $this->get($attribute);
        } catch (AttributeNotFoundException $e) {
            // Check for registered relations
            if (!isset($this->_relatedCaches)) {
                $this->_relatedCaches = [];
            }

            if (isset($this->_relatedCaches[$attribute])) {
                return $this->_relatedCaches[$attribute];
            }

            $related = \SPPMod\SPPEntity\SPPEntityRelations::getRelated($this, $attribute);
            if ($related !== null) {
                $this->_relatedCaches[$attribute] = $related;
                return $related;
            }

            throw $e;
        }
    }

    /**
     * public function getId()
     * Returns the id of the entity
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * public function setId($id)
     * Sets the id of the entity
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * public function getAttributes()
     * Returns the attributes of the entity
     * @return array $_attributes
     */
    public function getAttributes()
    {
        $meta = self::getMetadata('attributes', []);
        $defined = $this->define_attributes();
        if (is_array($defined)) {
            $meta = array_merge($meta, $defined);
        }
        return $meta;
    }

    /**
     * public function getValues()
     * Gets the values of the entity
     * @return array $_values
     */
    public function getValues()
    {
        return $this->_values;
    }



    /****************************************************************
     * STATIC METHODS
     ****************************************************************/

    /**
     * public static function getEntityName($entity)
     * Gets the name of the entity
     * @param
     */
    public static function getEntityConfigFile(string $entity_name)
    {
        $path = explode('\\', $entity_name);
        $short_name = array_pop($path);

        $appname = class_exists('\SPP\Scheduler') ? \SPP\Scheduler::getContext() : 'default';

        $files = [];
        if (defined('APP_ETC_DIR')) {
            $files[] = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'entities' . SPP_DS . strtolower($short_name) . '.yml';
            $files[] = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'entities' . SPP_DS . $short_name . '.yml';
            $files[] = APP_ETC_DIR . SPP_DS . 'entities' . SPP_DS . strtolower($short_name) . '.yml';
            $files[] = APP_ETC_DIR . SPP_DS . 'entities' . SPP_DS . $short_name . '.yml';
        }
        if (defined('SPP_ETC_DIR')) {
            $files[] = SPP_ETC_DIR . SPP_DS . 'entities' . SPP_DS . strtolower($short_name) . '.yml';
            $files[] = SPP_ETC_DIR . SPP_DS . 'entities' . SPP_DS . $short_name . '.yml';
        }

        foreach ($files as $file) {
            if (file_exists($file)) {
                return $file;
            }
        }
        return false;
    }

    public static function entityExists(mixed $entity_name)
    {
        if (class_exists($entity_name)) {
            if (is_a($entity_name, '\SPPMod\SPPEntity\SPPEntity', true)) {
                return true;
            } else {
                return false;
            }
        } else {
            if (self::getEntityConfigFile($entity_name) !== false) {
                return true;
            }
            return false;
        }
    }

    /**
     * Scans all registered etc paths for entity YAML definitions.
     * Returns a deduplicated list indexed by entity name.
     */
    public static function listAvailableEntities(): array
    {
        $appname = class_exists('\SPP\Scheduler') ? \SPP\Scheduler::getContext() : 'default';
        $entities = [];
        $paths = [];

        if (defined('APP_ETC_DIR')) {
            $paths[] = APP_ETC_DIR . SPP_DS . $appname . SPP_DS . 'entities';
            $paths[] = APP_ETC_DIR . SPP_DS . 'entities';
        }
        if (defined('SPP_ETC_DIR')) {
            $paths[] = SPP_ETC_DIR . SPP_DS . 'entities';
        }

        foreach (array_unique($paths) as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '/*.yml');
                foreach ($files as $f) {
                    $name = basename($f, '.yml');
                    if (!isset($entities[$name])) {
                        $content = file_get_contents($f);
                        $config = [];
                        try {
                            if (class_exists('\Symfony\Component\Yaml\Yaml')) {
                                $config = \Symfony\Component\Yaml\Yaml::parse($content);
                            }
                        } catch (\Exception $e) {
                        }

                        $entities[$name] = [
                            'name' => $name,
                            'path' => $f,
                            'table' => $config['table'] ?? '',
                            'modified' => date('Y-m-d H:i', filemtime($f)),
                            'content' => $content
                        ];
                    }
                }
            }
        }
        return $entities;
    }

    /**
     * Saves an entity definition (YAML) and generates a PHP skeleton class if missing.
     * Mirrors the logic from the Admin Workbench but shared via core.
     */
    public static function saveEntityDefinition(string $name, string $appname, array $config): bool
    {
        // 1. Save YAML Definition
        $entitiesDir = APP_ETC_DIR . '/' . $appname . '/entities';
        if (!is_dir($entitiesDir)) {
            mkdir($entitiesDir, 0777, true);
        }

        if (class_exists('\Symfony\Component\Yaml\Yaml')) {
            $yaml = \Symfony\Component\Yaml\Yaml::dump($config, 4, 2);
        } else {
            // Fallback basic serialization
            $yaml = "table: " . ($config['table'] ?? strtolower($name).'s') . "\n";
            $yaml .= "id_field: " . ($config['id_field'] ?? 'id') . "\n";
            if (!empty($config['extends'])) {
                $yaml .= "extends: " . $config['extends'] . "\n";
            }
            $yaml .= "attributes:\n";
            foreach (($config['attributes'] ?? []) as $k => $v) {
                $yaml .= "  $k: $v\n";
            }
        }

        $ymlPath = $entitiesDir . '/' . strtolower($name) . '.yml';
        file_put_contents($ymlPath, $yaml);

        // Trigger Auto-Evolution if enabled
        $globalSettingsPath = SPP_ETC_DIR . '/global-settings.yml';
        if (file_exists($globalSettingsPath)) {
            $settings = \Symfony\Component\Yaml\Yaml::parseFile($globalSettingsPath);
            if (($settings['prototyping']['auto_evolution'] ?? 'manual') === 'automatic') {
                // We need to trigger install but we might need a class name or use static helper
                // For simplicity, we'll try to resolve the class or run a direct install via SPPEntity
                try {
                    // Temporarily load this config to metadata so install() can use it
                    $tempClass = "App\\" . ucfirst($appname) . "\\Entities\\" . ucfirst($name);
                    self::$_metadata[$tempClass] = $config;
                    // Handle install
                    $db = new \SPPMod\SPPDB\SPPDB();
                    $table = $config['table'] ?? strtolower($name).'s';
                    $attributes = $config['attributes'] ?? [];
                    if (!$db->tableExists($table)) {
                        $sql = 'create table %tab% (' . ($config['id_field'] ?? 'id') . ' varchar(20))';
                        $db->exec_squery($sql, $table);
                    }
                    $db->add_columns($table, $attributes);
                } catch (\Exception $e) {
                    error_log("Auto-evolution failed: " . $e->getMessage());
                }
            }
        }

        // 2. Generate PHP Skeleton Class
        $srcDir = SPP_APP_DIR . '/src/' . $appname . '/entities';
        if (!is_dir($srcDir)) {
            mkdir($srcDir, 0777, true);
        }

        $className = ucfirst($name);
        $fileName = 'entity.' . strtolower($name) . '.php';
        $filePath = $srcDir . '/' . $fileName;

        if (!file_exists($filePath)) {
            $namespace = "App\\" . ucfirst($appname) . "\\Entities";
            $parent = !empty($config['extends']) ? $config['extends'] : "\\SPPMod\\SPPEntity\\SPPEntity";

            $phpContent = "<?php\nnamespace $namespace;\n\nuse SPPMod\\SPPEntity\\SPPEntity;\n\n/**\n * Class $className\n * Skeleton generated by SPP Entity Builder\n */\nclass $className extends $parent\n{\n    // Custom domain logic here\n}\n";
            file_put_contents($filePath, $phpContent);
        }

        return true;
    }

    /**
     * Static helper to find all instances of the entity with optional filtering.
     */
    public static function find_all(array $conditions = [], string $sort = null, int $limit = null)
    {
        $instance = new static();
        return $instance->loadFiltered($conditions, $sort, $limit);
    }

    /**
     * Loads entities based on conditions.
     */
    public function loadFiltered(array $conditions = [], string $sort = null, int $limit = null): array
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $sql = 'select * from %tab%';
        $values = [];

        if (!empty($conditions)) {
            $clauses = [];
            foreach ($conditions as $attr => $val) {
                $attr = preg_replace('/[^a-zA-Z0-9_]/', '', $attr);
                $clauses[] = "{$attr} = ?";
                $values[] = $val;
            }
            $sql .= ' where ' . implode(' AND ', $clauses);
        }

        if ($sort) {
            $sql .= ' order by ' . $sort;
        }

        if ($limit) {
            $sql .= ' limit ' . (int)$limit;
        }

        $cacheCid = 'spp_entity_' . md5($this->getTable() . $sql . serialize($values));
        $cacheEnabled = class_exists('\\SPPMod\\SPPCache\\SPPCacheManager') && \SPP\SPPConfig::get('system.auto_cache', true);
        
        if ($cacheEnabled) {
            $cachedEntities = \SPPMod\SPPCache\SPPCacheManager::get($cacheCid);
            if ($cachedEntities !== false) {
                return $cachedEntities;
            }
        }

        $result = $db->exec_squery($sql, $this->getTable(), $values);
        $entities = [];
        foreach ($result as $row) {
            $entity = new static();
            $entity->setId($row[self::getMetadata('id_field')]);
            foreach ($row as $attribute => $value) {
                if (!is_numeric($attribute)) {
                    $entity->set($attribute, $value);
                }
            }
            $entities[] = $entity;
        }
        if (!empty($entities) && class_exists('\\SPPMod\\SPPEntity\\SppDynamicFieldHandler')) {
            \SPPMod\SPPEntity\SppDynamicFieldHandler::loadFields($entities);
        }
        foreach ($entities as $entity) {
            $entity->after_load();
            \SPP\Core\EventManager::trigger('entity:after_load', $entity);
        }

        if (isset($cacheEnabled) && $cacheEnabled) {
            \SPPMod\SPPCache\SPPCacheManager::set($cacheCid, $entities, [static::getEntityName(static::class) . '_list']);
        }

        return $entities;
    }

    /**
     * Static helper to find a single instance based on conditions.
     */
    public static function find_one(array $conditions = [])
    {
        $instance = new static();
        $results = $instance->loadFiltered($conditions, null, 1);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Static helper to count all instances of the entity.
     */
    public static function count()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $instance = new static();
        $sql = 'SELECT COUNT(*) as cnt FROM ' . $instance->getTable();
        $res = $db->execute_query($sql);
        return (int)($res[0]['cnt'] ?? 0);
    }

    /**
     *  public static function getEntityName($entity)
     * Gets the name of the entity
     * @param  $entity
     * @return string $entity_name
     */
    public static function getEntityName($entity)
    {
        return is_object($entity) ? get_class($entity) : (string) $entity;
    }


    /*public static function __set_state($properties){
      $atts = array();
      foreach ($this->_attributes as $att => $type) {
        $atts[] = $att;
      }
      foreach ($this->_values as $att => $val) {
        if (in_array($att, $atts)) {
          $this->_values[$att] = $val;
        } else {
          throw new AttributeNotFoundException('Wrong attribute ' . $att . ' accessed');
        }
      }
    }*/

    /**
     * public function getTable()
     * Gets the table of the entity
     * @return string $_table
     */
    public function getTable()
    {
        $table = self::getMetadata('table');
        if (empty($table)) {
            throw new EntityConfigurationException("Entity " . static::class . " does not have a database table mapping. Check its YAML definition or ensure the class name follows pluralization conventions.");
        }

        if (class_exists('\\SPPMod\\SPPDB\\SPPDB')) {
            return \SPPMod\SPPDB\SPPDB::sppTable($table);
        }
        return $table;
    }

    /**
     * public function setTable($table)
     * Sets the table of the entity
     * @param string $table
     */
    public function setTable($table)
    {
        self::setMetadata('table', $table);
    }

    /**
     * public function set($attribute, $value)
     * Sets the value af an attribute of entity
     * @param string $attribute
     * @param mixed $value
     */
    public function set($attribute, $value)
    {
        $classVar = get_object_vars($this);
        if (array_key_exists($attribute, $classVar)) {
            $this->$attribute = $value;
            return true;
        } else {
            if ($this->currentLanguage !== 'en') {
                $this->_translations[$attribute] = $value;
                return true;
            }

            $attributes = $this->getAttributes();
            $dynamicAttributes = self::getMetadata('dynamic_attributes', []);
            if (array_key_exists($attribute, $attributes)) {
                $this->_values[$attribute] = $value;
                return true;
            } elseif (array_key_exists($attribute, $dynamicAttributes)) {
                $this->_dynamic_values[$attribute] = $value;
                return true;
            } elseif (array_key_exists('fields_data', $attributes)) {
                $fieldsData = $this->_values['fields_data'] ?? '';
                if (is_string($fieldsData)) {
                    $decoded = json_decode($fieldsData, true);
                } else {
                    $decoded = $fieldsData;
                }
                if (!is_array($decoded)) {
                    $decoded = [];
                }
                $decoded[$attribute] = $value;
                $this->_values['fields_data'] = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                return true;
            } else {
                error_log("SPPEntity Warning: Wrong attribute " . $attribute . " accessed on " . get_class($this));
                return false;
            }
        }
    }


    /**
     * public function __set($attribute, $value)
     * Sets the value af an attribute of entity
     * @param string $attribute
     * @param mixed $value
     */
    public function __set($attribute, $value)
    {
        $classVar = get_object_vars($this);
        if (array_key_exists($attribute, $classVar)) {
            $this->$attribute = $value;
        } else {
            if ($this->currentLanguage !== 'en') {
                $this->_translations[$attribute] = $value;
                return $value;
            }

            $attributes = $this->getAttributes();
            $dynamicAttributes = self::getMetadata('dynamic_attributes', []);
            if (array_key_exists($attribute, $attributes)) {
                $this->_values[$attribute] = $value;
            } elseif (array_key_exists($attribute, $dynamicAttributes)) {
                $this->_dynamic_values[$attribute] = $value;
            } elseif (array_key_exists('fields_data', $attributes)) {
                $fieldsData = $this->_values['fields_data'] ?? '';
                if (is_string($fieldsData)) {
                    $decoded = json_decode($fieldsData, true);
                } else {
                    $decoded = $fieldsData;
                }
                if (!is_array($decoded)) {
                    $decoded = [];
                }
                $decoded[$attribute] = $value;
                $this->_values['fields_data'] = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            } else {
                error_log("SPPEntity Warning: Wrong attribute " . $attribute . " accessed on " . get_class($this));
            }
        }
        return $value;
    }

    public function setAttributes($attributes)
    {
        foreach ($attributes as $att => $val) {
            $this->$att = $val;
        }
        return $this->id;
    }

    public function setValues($values)
    {
        $attributes = $this->getAttributes();
        $dynamicAttributes = self::getMetadata('dynamic_attributes', []);
        foreach ($values as $att => $val) {
            if (array_key_exists($att, $attributes)) {
                $this->_values[$att] = $val;
            } elseif (array_key_exists($att, $dynamicAttributes)) {
                $this->_dynamic_values[$att] = $val;
            } else {
                throw new AttributeNotFoundException('Wrong attribute ' . $att . ' accessed');
            }
        }
    }

    public function _setDynamicFields(array $fields)
    {
        $this->_dynamic_values = $fields;
    }


    /**
     * public function attributeExists($attribute)
     * Checks if an attribute exists
     * @param string $attribute
     * @return bool $exists
     */
    public function attributeExists($attribute)
    {
        $exists = false;
        $classVar = get_object_vars($this);
        if (array_key_exists($attribute, $classVar)) {
            $exists = true;
        } else {
            $attributes = $this->getAttributes();
            $dynamicAttributes = self::getMetadata('dynamic_attributes', []);
            if (array_key_exists($attribute, $attributes) || array_key_exists($attribute, $dynamicAttributes)) {
                $exists = true;
            } elseif (array_key_exists('fields_data', $attributes)) {
                $fieldsData = $this->_values['fields_data'] ?? '';
                if (is_string($fieldsData)) {
                    $decoded = json_decode($fieldsData, true);
                } else {
                    $decoded = $fieldsData;
                }
                if (is_array($decoded) && array_key_exists($attribute, $decoded)) {
                    $exists = true;
                }
            }
        }
        return $exists;
    }

    /**
     * public function get($attribute)
     * Gets the value of an attribute of entity.
     * @param string $attribute
     * @return mixed
     */
    public function get($attribute)
    {
        $classVar = get_object_vars($this);
        if (array_key_exists($attribute, $classVar)) {
            return $this->$attribute;
        } else {
            if ($this->currentLanguage !== 'en' && array_key_exists($attribute, $this->_translations)) {
                return $this->_translations[$attribute];
            }

            $attributes = $this->getAttributes();
            $dynamicAttributes = self::getMetadata('dynamic_attributes', []);
            if (array_key_exists($attribute, $attributes)) {
                return $this->_values[$attribute] ?? null;
            } elseif (array_key_exists($attribute, $dynamicAttributes)) {
                return $this->_dynamic_values[$attribute] ?? null;
            } elseif (array_key_exists('fields_data', $attributes)) {
                $fieldsData = $this->_values['fields_data'] ?? '';
                if (is_string($fieldsData)) {
                    $decoded = json_decode($fieldsData, true);
                } else {
                    $decoded = $fieldsData;
                }
                if (is_array($decoded) && array_key_exists($attribute, $decoded)) {
                    return $decoded[$attribute];
                }
                return null;
            } else {
                error_log("SPPEntity Warning: Wrong attribute " . $attribute . " accessed on " . get_class($this));
                return null;
            }
        }
    }

    public static function addAttributes($attributes)
    {
        $currentAttributes = self::getMetadata('attributes', []);
        foreach ($attributes as $key => $value) {
            $currentAttributes[$key] = $value;
        }
        self::setMetadata('attributes', $currentAttributes);
        static::install();
    }

    protected function removeAttribute($attribute)
    {
        $attributes = $this->getAttributes();
        if (isset($attributes[$attribute])) {
            unset($attributes[$attribute]);
            self::setMetadata('attributes', $attributes);
        }
    }

    /**
     * public static function install()
     * installs the entity and creates table for entity.
     */
    public static function install()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $tableBase = self::getMetadata('table');
        $table = (class_exists('\\SPPMod\\SPPDB\\SPPDB')) ? \SPPMod\SPPDB\SPPDB::sppTable($tableBase) : $tableBase;

        $id_field = self::getMetadata('id_field', 'id');
        $attributes = self::getMetadata('attributes', []);
        $profile = self::getMetadata('profile');

        if (!$db->tableExists($table)) {
            $sql = 'create table ' . $table . ' (' . $id_field . ' varchar(20))';
            $db->exec($sql);
        }
        $db->add_columns($table, $attributes);

        // Profile DB Compilation Routine
        if ($profile !== null) {
            $profName = static::getEntityName(static::class) . '_prof';
            if (!\SPPMod\SPPProfile\SPPProfile::doesProfileExist($profName)) {
                $flds = $profile;
                if (!isset($flds['userid'])) {
                    $flds['userid'] = 100;
                }
                \SPPMod\SPPProfile\SPPProfile::createProfile($profName, $flds);
                \SPPMod\SPPLogger\SPP_Logger::info("Automated Profile schema successfully compiled onto Database for Entity: " . static::getEntityName(static::class));
            }
        }
    }

    /**
     * protected function uninstall()
     * uninstalls the entity and drops all the columns except id and name
     */
    protected function uninstall()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $db->remove_columns($this->getTable(), $this->getAttributes());
    }

    /**
     * pubic function save()
     * Save the current entity.
     * Insert if new entity.
     */
    public function save()
    {
        $this->defensiveCleanup();
        $this->before_save();
        \SPP\Core\EventManager::trigger('entity:before_save', $this);

        // Model-level Validation
        $vResult = $this->validate();
        if (!$vResult->isValid()) {
            throw new \SPP\Exceptions\EntityValidationException($vResult);
        }

        if ($this->id == null) {
            $new_id = $this->insert();
            \SPPMod\SPPAudit\SPPAudit::log(static::class, $new_id, 'create', null, $this->_values);
            $this->after_save();
            \SPP\Core\EventManager::trigger('entity:after_save', $this);
            return $new_id;
        } else {
            \SPPMod\SPPAudit\SPPAudit::log(static::class, $this->id, 'update', $this->_snapshot, $this->_values);
            $this->update();
            $this->after_save();
            \SPP\Core\EventManager::trigger('entity:after_save', $this);
            return $this->id;
        }
    }

    /**
     * Validates the entity based on metadata rules.
     * @return \SPPMod\SPPView\ValidationResult
     */
    public function validate(): \SPPMod\SPPView\ValidationResult
    {
        $rules = self::getMetadata('validation', []);
        if (empty($rules)) {
            return new \SPPMod\SPPView\ValidationResult();
        }

        // Use modernized ViewValidator in silent mode
        $validator = new \SPPMod\SPPView\ViewValidator();
        $validator->setSilent(true);

        return $validator->validateAll($this->_values, $rules);
    }

    /**
     * protected function createId()
     * Creates a new id for the entity.
     * @return int $new_id
     */
    protected function createId()
    {
        $sequence = self::getMetadata('sequence');
        $initial_id = self::getMetadata('initial_id', 1);

        if (!\SPPMod\SPPDB\SPPSequence::sequenceExists($sequence)) {
            \SPPMod\SPPDB\SPPSequence::createSequence($sequence, $initial_id, 1);
        }
        $new_id = \SPPMod\SPPDB\SPPSequence::next($sequence);
        return $new_id;
    }

    /**
     * public function insert()
     * inserts a new entity into the table.
     * @return mixed $new_id
     */
    public function insert()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $new_id = $this->createId();
        $this->id = $new_id;
        $attributes = $this->getAttributes();
        $val_array = [$this->getMetadata('id_field') => $new_id];
        foreach ($this->_values as $k => $v) {
            if (array_key_exists($k, $attributes)) {
                $val_array[$k] = $v;
            }
        }

        if (class_exists('\\SPPMod\\SPPAI\\SPPAI') && $this->attributeExists('ai_vector')) {
            $val_array['ai_vector'] = json_encode(\SPPMod\SPPAI\SPPAI::createEmbedding(json_encode($this->_values)));
        }

        try {
            $db->insertValues($this->getTable(), $val_array);
        } catch (\PDOException $e) {
            $this->handleMagicDatabase($e, $db);
            $db->insertValues($this->getTable(), $val_array);
        }

        if (class_exists('\\SPPMod\\SPPEntity\\SppDynamicFieldHandler')) {
            \SPPMod\SPPEntity\SppDynamicFieldHandler::saveFields($this, $this->_dynamic_values);
        }
        $this->_saveTranslations();
        return $new_id;
    }

    /**
     * "No-Schema" Magic Databases feature.
     * Intercepts table/column not found errors and alters schema on-the-fly in dev mode.
     */
    protected function handleMagicDatabase(\PDOException $e, \SPPMod\SPPDB\SPPDB $db)
    {
        $env = getenv('APP_ENV') ?: 'local';
        if ($env !== 'local') {
            throw $e; // Only auto-migrate in local dev
        }
        
        $errorCode = $e->getCode();
        $table = $this->getTable();
        $attributes = $this->getAttributes();

        if ($errorCode == '42S02') { // Base table or view not found
            $idField = self::getMetadata('id_field', 'id');
            $sql = 'create table ' . $table . ' (' . $idField . ' varchar(20))';
            $db->exec($sql);
            $db->add_columns($table, $attributes);
            error_log("Magic DB: Created missing table {$table}");
        } elseif ($errorCode == '42S22') { // Column not found
            $db->add_columns($table, $attributes);
            error_log("Magic DB: Added missing columns to {$table}");
        } else {
            throw $e;
        }
    }

    /**
     * Generates physical system login credentials tethered natively to this entity instance.
     */
    public function enableLogin(string $username, string $password)
    {
        if (!self::getMetadata('login_enabled', false)) {
            throw new \SPP\SPPException('Logins are disabled natively for this Entity YAML payload.');
        }
        if ($this->id == null) {
            throw new \SPP\SPPException('Entity must be physically stored to database tables dynamically before authenticating mappers.');
        }
        if (\SPPMod\SPPAuth\SPPUser::userExists($username)) {
            throw new \SPP\SPPException("Username '$username' is already claimed inside database.");
        }

        // 1. Generate Auth System User Tracker
        \SPPMod\SPPAuth\SPPUser::createUser($username, $password);

        // 2. Bind Authenticated Profile physically to Dynamic Entity Extractor
        $profileConfig = self::getMetadata('profile');
        if ($profileConfig !== null) {
            $profName = static::getEntityName(static::class) . '_prof';
            $profile = new \SPPMod\SPPProfile\SPPProfile($profName);
            $profid = $profile->appendSave(['userid' => $username]);

            $this->setValues(['profid' => $profid]);
            $this->update();
        }

        // 3. Log Architectural Integration Execution
        \SPPMod\SPPLogger\SPP_Logger::info("Login credentials directly compiled for entity {entity} ({id}) under user {uname}", [
            'entity' => static::getEntityName(static::class),
            'id' => $this->id,
            'uname' => $username
        ]);
    }

    public function disableLogin()
    {
        $username = $this->getLoginIdentity();
        if ($username) {
            \SPPMod\SPPAuth\SPPUser::dropUser($username);

            if (self::getMetadata('profile') !== null && isset($this->_values['profid'])) {
                $profName = static::getEntityName(static::class) . '_prof';
                $profile = new \SPPMod\SPPProfile\SPPProfile($profName);
                if ($profile->seekProfile($this->_values['profid'])) {
                    $profile->deleteMe();
                }
                $this->setValues(['profid' => null]);
                $this->update();
            }

            \SPPMod\SPPLogger\SPP_Logger::warning("Login credentials natively suspended for entity {entity} ({id})", [
                'entity' => static::getEntityName(static::class),
                'id' => $this->id
            ]);
        }
    }

    public function getLoginIdentity()
    {
        if (self::getMetadata('profile') !== null && isset($this->_values['profid'])) {
            $profName = static::getEntityName(static::class) . '_prof';
            $profile = new \SPPMod\SPPProfile\SPPProfile($profName);
            if ($profile->seekProfile($this->_values['profid'])) {
                return $profile->get('userid');
            }
        }
        return null;
    }

    /**
     * Automatically intercepts raw natural queries into vector models efficiently smoothly dynamically actively seamlessly fluently organically elegantly properly cleanly instinctively fluently.
     */
    public static function searchNatural(string $query)
    {
        if (class_exists('\\SPPMod\\SPPAI\\SPPAI')) {
            return \SPPMod\SPPAI\SPPAI::search($query);
        }
        return [];
    }

    /**
     * public function update()
     * Updates the entity.
     * @return boolean
     */
    public function update()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        if ($this->id != null) {
            // Revisions Tracking Implementation
            if (self::getMetadata('track_revisions')) {
                $delta = [];
                foreach ($this->_values as $key => $newVal) {
                    $oldVal = $this->_snapshot[$key] ?? null;
                    if ($oldVal !== $newVal) {
                        $delta[$key] = ['old' => $oldVal, 'new' => $newVal];
                    }
                }
                if (!empty($delta)) {
                    $author_id = class_exists('\SPPMod\SPPAuth\SPPAuth') && \SPPMod\SPPAuth\SPPAuth::check() ? \SPPMod\SPPAuth\SPPAuth::user()->id : null;
                    $db->execute_query(
                        "INSERT INTO spp_entity_revisions (entity_class, entity_id, revision_date, author_id, delta_data, log_message) VALUES (?, ?, NOW(), ?, ?, ?)",
                        [static::class, $this->id, $author_id, json_encode($delta), 'System update']
                    );
                }
            }

            $values = array_values($this->_values);
            $values[] = $this->id;
            
            try {
                $db->updateValues($this->getTable(), array_keys($this->_values), self::getMetadata('id_field') . '=?', $values);
            } catch (\PDOException $e) {
                $this->handleMagicDatabase($e, $db);
                $db->updateValues($this->getTable(), array_keys($this->_values), self::getMetadata('id_field') . '=?', $values);
            }
            
            if (class_exists('\\SPPMod\\SPPEntity\\SppDynamicFieldHandler')) {
                \SPPMod\SPPEntity\SppDynamicFieldHandler::saveFields($this, $this->_dynamic_values);
            }
            $this->_saveTranslations();
            return true;
        } else {
            return false;
        }
    }

    /**
     * public function delete()
     * Deletes the present entity record.
     */
    public function delete()
    {
        \SPPMod\SPPAudit\SPPAudit::log(static::class, $this->id, 'delete', $this->_values, null);
        if (class_exists('\\SPPMod\\SPPEntity\\SppDynamicFieldHandler')) {
            \SPPMod\SPPEntity\SppDynamicFieldHandler::deleteFields($this);
        }
        $db = new \SPPMod\SPPDB\SPPDB();
        $sql = 'delete from %tab% where ' . self::getMetadata('id_field') . '=?';
        $db->exec_squery($sql, $this->getTable(), [$this->id]);
        $this->id = null;
    }

    /**
     * public function load($id)
     * Loads an entity from the table.
     * @param int $id
     * @throws EntityNotFoundException
     * @return mixed $result
     **/
    public function load($id)
    {
        $this->id = $id;
        $db = new \SPPMod\SPPDB\SPPDB();
        $sql = 'select * from %tab% where ' . self::getMetadata('id_field') . '=?';
        $result = $db->exec_squery($sql, $this->getTable(), [$id]);
        if (sizeof($result) > 0) {
            $row = $result[0];
            foreach ($row as $attribute => $value) {
                if (!is_numeric($attribute) && $this->attributeExists($attribute)) {
                    $this->set($attribute, $value);
                }
            }
            if (class_exists('\\SPPMod\\SPPEntity\\SppDynamicFieldHandler')) {
                \SPPMod\SPPEntity\SppDynamicFieldHandler::loadFields([$this]);
            }
            $this->after_load();
            \SPP\Core\EventManager::trigger('entity:after_load', $this);
        } else {
            throw new EntityNotFoundException('Entity with id ' . $id . ' not found');
        }
    }

    /**
     * public function loadBy($attribute, $value)
     * Loads an entity from the table.
     * @param string $attribute
     * @param mixed $value
     * @throws EntityNotFoundException
     * @return mixed $result
     */
    public function loadBy($attribute, $value)
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $attribute = preg_replace('/[^a-zA-Z0-9_]/', '', $attribute);
        $sql = 'select * from %tab% where ' . $attribute . '=?';
        $result = $db->exec_squery($sql, $this->getTable(), [$value]);
        if (sizeof($result) > 0) {
            $row = $result[0];
            foreach ($row as $attribute => $value) {
                if (!is_numeric($attribute) && $this->attributeExists($attribute)) {
                    $this->set($attribute, $value);
                }
            }
            if (class_exists('\\SPPMod\\SPPEntity\\SppDynamicFieldHandler')) {
                \SPPMod\SPPEntity\SppDynamicFieldHandler::loadFields([$this]);
            }
            $this->after_load();
            $this->_snapshot = $this->_values;
            \SPP\Core\EventManager::trigger('entity:after_load', $this);
        } else {
            throw new EntityNotFoundException('Entity with ' . $attribute . '=' . $value . ' not found');
        }
    }

    /**
     * public function loadAll()
     * Loads all entities from the table.
     * @return mixed $entities
     */
    public function loadAll()
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $sql = 'select * from %tab%';
        $result = $db->exec_squery($sql, $this->getTable());
        $entities = [];
        foreach ($result as $row) {
            $entity = new static();
            $entity->setId($row[self::getMetadata('id_field')]);
            foreach ($row as $attribute => $value) {
                if (!is_numeric($attribute)) {
                    $entity->set($attribute, $value);
                }
            }
            $entities[] = $entity;
        }
        if (!empty($entities) && class_exists('\\SPPMod\\SPPEntity\\SppDynamicFieldHandler')) {
            \SPPMod\SPPEntity\SppDynamicFieldHandler::loadFields($entities);
        }
        foreach ($entities as $entity) {
            $entity->after_load();
            \SPP\Core\EventManager::trigger('entity:after_load', $entity);
        }
        return $entities;
    }

    /**
     * public function loadMultiple($attributes, $values)
     * Loads multiple entities from the table.
     * @param array $attributes
     * @param array $values
     * @return mixed $entities
     */
    public function loadMultiple(array $attributes, array $values)
    {
        $db = new \SPPMod\SPPDB\SPPDB();
        $sanitized_attributes = [];
        foreach ($attributes as $attr) {
            $sanitized_attributes[] = preg_replace('/[^a-zA-Z0-9_]/', '', $attr);
        }
        $sql = 'select * from %tab% where ' . implode('=? AND ', $sanitized_attributes) . '=? ';
        $result = $db->exec_squery($sql, $this->getTable(), $values);
        $entities = [];
        foreach ($result as $row) {
            $entity = new static();
            $entity->setId($row[self::getMetadata('id_field')]);
            foreach ($row as $attribute => $value) {
                if (!is_numeric($attribute)) {
                    $entity->set($attribute, $value);
                }
            }
            $entities[] = $entity;
        }
        if (!empty($entities) && class_exists('\\SPPMod\\SPPEntity\\SppDynamicFieldHandler')) {
            \SPPMod\SPPEntity\SppDynamicFieldHandler::loadFields($entities);
        }
        foreach ($entities as $entity) {
            $entity->after_load();
            \SPP\Core\EventManager::trigger('entity:after_load', $entity);
        }
        return $entities;
    }

    /**
     * Internal Defensive Logic: Sanitizes and truncates data to prevent DB crashes.
     */
    protected function defensiveCleanup()
    {
        $attributes = $this->getAttributes();
        foreach ($this->_values as $key => &$value) {
            if (!isset($attributes[$key])) {
                continue;
            }
            $type = strtolower($attributes[$key]);

            // 1. Unicode Resilience: Ensure valid UTF-8 and strip dangerous control chars
            if (is_string($value)) {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                // If it's a date field, and it has emojis/trash, clean it strictly!
                if (strpos($key, 'at') !== false || strpos($key, 'date') !== false || strpos($type, 'date') !== false) {
                    $value = preg_replace('/[^\x20-\x7E]/', '', $value); // Strict ASCII for dates
                }
            }

            // 2. Soft Truncation: Based on DB type (heuristics)
            if (strpos($type, 'varchar') !== false || strpos($type, 'string') !== false) {
                $len = 255; // Default safe length
                if (preg_match('/\((\d+)\)/', $type, $matches)) {
                    $len = (int)$matches[1];
                }
                if (strlen((string)$value) > $len) {
                    $value = substr((string)$value, 0, $len);
                }
            }
        }
    }

    /**
     * Get all revisions for this entity.
     */
    public function getRevisions()
    {
        if ($this->id === null) {
            return [];
        }
        $db = new \SPPMod\SPPDB\SPPDB();
        return $db->execute_query(
            "SELECT id, revision_date, author_id, log_message FROM spp_entity_revisions WHERE entity_id = ? AND entity_class = ? ORDER BY revision_date DESC",
            [$this->id, static::class]
        );
    }

    /**
     * Restore this entity from a specific revision ID.
     */
    public function restoreRevision($rev_id)
    {
        if ($this->id === null) {
            return false;
        }
        $db = new \SPPMod\SPPDB\SPPDB();
        $rev = $db->execute_query(
            "SELECT delta_data FROM spp_entity_revisions WHERE id = ? AND entity_id = ? AND entity_class = ?",
            [$rev_id, $this->id, static::class]
        )[0] ?? null;

        if ($rev) {
            $delta = json_decode($rev['delta_data'], true);
            if (is_array($delta)) {
                foreach ($delta as $key => $val) {
                    $this->$key = $val;
                }
                return $this->save();
            }
        }
        return false;
    }
}
