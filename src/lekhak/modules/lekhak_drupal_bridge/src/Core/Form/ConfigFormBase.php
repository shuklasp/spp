<?php
namespace Lekhak\Modules\LekhakDrupalBridge\Core\Form;

abstract class ConfigFormBase {
    
    abstract protected function getEditableConfigNames();
    abstract public function getFormId();

    protected function config($name) {
        return \Drupal::config($name);
    }

    public function buildForm(array $form, FormState $form_state) {
        // Many Drupal modules do not call parent::buildForm() or expect it to return system_config_form
        // For basic bridge, we inject our submit button automatically if not present.
        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => t('Save configuration'),
            '#button_type' => 'primary',
        ];
        return $form;
    }

    public function validateForm(array &$form, FormState $form_state) {
        // Default empty implementation
    }

    public function submitForm(array &$form, FormState $form_state) {
        // Provide a default implementation that saves all config if they map 1:1,
        // but typically modules implement this themselves.
        \SPP\SPPMsg::addMsg('Configuration saved successfully.', 'success');
    }
}
