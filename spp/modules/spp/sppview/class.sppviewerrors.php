<?php

namespace SPPMod\SPPView;

class SPP_ViewErrors
{
    protected static $errorHolders = [];
    public static function addError(string $errorHolder, string $errorMessage, string $errorType = 'error')
    {
        if (!in_array($errorType, ['error', 'warning', 'info'])) {
            $errorType = 'error';
        }
        if (!isset(self::$errorHolders[$errorHolder])) {
            self::$errorHolders[$errorHolder] = [];
        }
        self::$errorHolders[$errorHolder][$errorType][] = $errorMessage;
    }
    public static function getErrors(?string $errorHolder = null)
    {
        if ($errorHolder === null) {
            return self::$errorHolders;
        }
        if (isset(self::$errorHolders[$errorHolder])) {
            return self::$errorHolders[$errorHolder];
        }
        return [];
    }

    public static function hasErrors(string $errorHolder): bool
    {
        return !empty(self::getErrors($errorHolder));
    }

    public static function getErrorsForField(string $errorHolder): array
    {
        $errs = self::getErrors($errorHolder);
        return $errs['error'] ?? (is_array($errs) ? $errs : []);
    }

    public static function clearErrors(string $errorHolder)
    {
        if (isset(self::$errorHolders[$errorHolder])) {
            unset(self::$errorHolders[$errorHolder]);
        }
    }

    public static function clearAllErrors()
    {
        self::$errorHolders = [];
    }

    public static function displayErrors(string $errorHolder)
    {
        $errors = self::getErrors($errorHolder);
        if (count($errors) > 0) {
            include __DIR__ . '/errors/display-errors.php';
        }
    }

    public static function displayGlobalErrors()
    {
        self::displayErrors('global');
    }
    public static function displayFormErrors(string $formName)
    {
        self::displayErrors($formName);
    }
    public static function displayBlockErrors(string $blockName)
    {
        self::displayErrors($blockName);
    }
    public static function displayModuleErrors(string $moduleName)
    {
        self::displayErrors($moduleName);
    }
    public static function displayAllErrors()
    {
        foreach (self::$errorHolders as $errorHolder => $errors) {
            self::displayErrors($errorHolder);
        }
    }
}
