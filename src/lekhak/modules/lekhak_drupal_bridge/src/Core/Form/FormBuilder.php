<?php
namespace Lekhak\Modules\LekhakDrupalBridge\Core\Form;

class FormBuilder {
    public function getForm($form_arg) {
        if (is_string($form_arg) && class_exists($form_arg)) {
            $form = new $form_arg();
            $formState = new FormState();
            return $form->buildForm([], $formState);
        } elseif (is_object($form_arg)) {
            $formState = new FormState();
            return $form_arg->buildForm([], $formState);
        }
        return [];
    }
}
