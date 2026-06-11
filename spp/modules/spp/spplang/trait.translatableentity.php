<?php

namespace SPPMod\SPPLang;

/**
 * Trait TranslatableEntity
 * Can be used by SPP Models (ActiveRecord classes) to automatically hook into EAV translations.
 */
trait TranslatableEntity
{
    /**
     * Override this in the using class to define which fields are translatable.
     * e.g., protected array $translatable = ['title', 'description'];
     */
    // protected array $translatable = [];

    /**
     * Get a field's value, preferring the translated version if available in the current locale.
     *
     * @param string $field
     * @param mixed $defaultFallback The base/default language value
     * @return mixed
     */
    public function getTranslated(string $field, $defaultFallback)
    {
        if (property_exists($this, 'translatable') && in_array($field, $this->translatable)) {
            // Assume the model has a getEntityTypeName() or table name, and primary key
            $type = method_exists($this, 'getTableName') ? $this->getTableName() : get_class($this);
            $id = isset($this->id) ? $this->id : null;
            
            if ($id !== null) {
                $translated = \SPPMod\SPPLang\ContentTranslator::getTranslation($type, $id, $field);
                if ($translated !== null) {
                    return $translated;
                }
            }
        }
        
        return $defaultFallback;
    }

    /**
     * Save translations for this entity. Typically called after the main entity saves.
     * 
     * @param array $translations Array mapping field => translation
     * @param string|null $locale Target locale
     */
    public function saveTranslations(array $translations, ?string $locale = null): void
    {
        $type = method_exists($this, 'getTableName') ? $this->getTableName() : get_class($this);
        $id = isset($this->id) ? $this->id : null;
        
        if ($id === null) {
            return;
        }

        foreach ($translations as $field => $val) {
            if (property_exists($this, 'translatable') && in_array($field, $this->translatable)) {
                \SPPMod\SPPLang\ContentTranslator::setTranslation($type, $id, $field, $val, $locale);
            }
        }
    }
}
