<?php
namespace SPPMod\SPPReport\Services;

class DataMasker
{
    private array $rules = [];
    private bool $shouldMask = false;

    public function __construct(array $config, array $userRoles = [])
    {
        if (!empty($config['masking_rules']) && is_array($config['masking_rules'])) {
            $this->rules = $config['masking_rules'];
            
            // Determine if masking applies to the current user
            $unmaskRoles = array_map('strtolower', array_map('trim', explode(',', $config['unmask_roles'] ?? 'admin')));
            $userRolesLower = array_map('strtolower', $userRoles);
            
            // If user has NO roles that match unmask_roles, they MUST be masked.
            if (empty(array_intersect($unmaskRoles, $userRolesLower))) {
                $this->shouldMask = true;
            }
        }
    }

    public function isMaskingActive(): bool
    {
        return $this->shouldMask && !empty($this->rules);
    }

    public function maskRow(array $row): array
    {
        if (!$this->isMaskingActive()) {
            return $row;
        }

        foreach ($this->rules as $field => $type) {
            if (isset($row[$field])) {
                $row[$field] = $this->maskValue((string)$row[$field], strtoupper($type));
            }
        }

        return $row;
    }

    private function maskValue(string $value, string $type): string
    {
        if (empty($value)) return $value;

        switch ($type) {
            case 'EMAIL':
                $parts = explode('@', $value);
                if (count($parts) === 2) {
                    $name = $parts[0];
                    $maskedName = substr($name, 0, 1) . str_repeat('*', max(1, strlen($name) - 1));
                    return $maskedName . '@' . $parts[1];
                }
                return '***@***.***';
            
            case 'SSN':
            case 'PHONE':
                // Keep last 4 digits
                $len = strlen($value);
                if ($len > 4) {
                    return str_repeat('*', $len - 4) . substr($value, -4);
                }
                return '****';
                
            case 'STRING':
            case 'NAME':
                return substr($value, 0, 1) . str_repeat('*', 5);

            case 'NUMBER':
            case 'REVENUE':
                // Redact exact numbers but maybe show order of magnitude?
                // Standard enterprise redaction is just a blanket string.
                return '***';

            default:
                return str_repeat('*', strlen($value));
        }
    }
}
