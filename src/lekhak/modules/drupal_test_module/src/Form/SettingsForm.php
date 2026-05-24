<?php
namespace Drupal\drupal_test_module\Form;

use Lekhak\Modules\LekhakDrupalBridge\Core\Form\ConfigFormBase;
use Lekhak\Modules\LekhakDrupalBridge\Core\Form\FormState;

class SettingsForm extends ConfigFormBase {

    public function getFormId() {
        return 'drupal_test_module_settings';
    }

    protected function getEditableConfigNames() {
        return ['drupal_test_module.settings'];
    }

    public function buildForm(array $form, FormState $form_state) {
        $config = $this->config('drupal_test_module.settings');

        $form['api_key'] = [
            '#type' => 'textfield',
            '#title' => t('API Key'),
            '#default_value' => $config->get('api_key'),
            '#required' => true,
        ];

        $form['debug_mode'] = [
            '#type' => 'checkbox',
            '#title' => t('Enable Debug Mode'),
            '#default_value' => $config->get('debug_mode'),
        ];

        return parent::buildForm($form, $form_state);
    }

    public function submitForm(array &$form, FormState $form_state) {
        $this->config('drupal_test_module.settings')
            ->set('api_key', $form_state->getValue('api_key'))
            ->set('debug_mode', $form_state->getValue('debug_mode'))
            ->save();

        parent::submitForm($form, $form_state);
    }
}
