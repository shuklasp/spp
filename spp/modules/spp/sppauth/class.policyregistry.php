<?php

namespace SPPMod\SPPAuth;

use SPPMod\SPPDB\SPPDB;

class PolicyRegistry
{
    /**
     * Evaluates an ABAC policy based on user, permission, and context.
     * If a policy exists for the permission, it evaluates the condition.
     * Returns true if the policy permits access, or if no policy exists (fallback to RBAC).
     */
    public static function evaluate(SPPUser $user, string $permission, $context): bool
    {
        $db = new SPPDB();
        $sql = "SELECT condition_logic FROM " . SPPDB::sppTable('abac_policies') . " WHERE permission = ? AND status = 'active'";
        $policies = $db->execute_query($sql, [$permission]);

        if (empty($policies)) {
            return true; // No ABAC policy restricts this permission, fallback to RBAC success
        }

        // Load the context array. Context can be an array, object, or entity.
        $contextData = self::resolveContext($context);
        $userData = [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->get('status'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ];

        // We only require ONE policy to pass (OR logic across multiple policies for same permission)
        // If we want AND logic, we would require all to pass. Usually, ABAC policies grant access if ONE matches.
        foreach ($policies as $policy) {
            $logic = json_decode($policy['condition_logic'], true);
            if (!$logic)
                continue;

            if (self::evaluateCondition($logic, $userData, $contextData)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursive condition evaluator.
     * Example logic: 
     * { "field": "user.id", "operator": "equals", "value": "context.owner_id" }
     * { "AND": [ condition1, condition2 ] }
     */
    private static function evaluateCondition(array $logic, array $userData, array $contextData): bool
    {
        if (isset($logic['AND'])) {
            foreach ($logic['AND'] as $subLogic) {
                if (!self::evaluateCondition($subLogic, $userData, $contextData))
                    return false;
            }
            return true;
        }

        if (isset($logic['OR'])) {
            foreach ($logic['OR'] as $subLogic) {
                if (self::evaluateCondition($subLogic, $userData, $contextData))
                    return true;
            }
            return false;
        }

        // Leaf node: field, operator, value
        $fieldVal = self::extractValue($logic['field'] ?? '', $userData, $contextData);
        $targetVal = self::extractValue($logic['value'] ?? '', $userData, $contextData);

        // If the value doesn't start with user. or context., treat it as literal
        if (is_string($logic['value']) && !str_starts_with($logic['value'], 'user.') && !str_starts_with($logic['value'], 'context.')) {
            $targetVal = $logic['value'];
        }

        switch ($logic['operator'] ?? 'equals') {
            case 'equals':
                return $fieldVal == $targetVal;
            case 'not_equals':
                return $fieldVal != $targetVal;
            case 'in':
                return is_array($targetVal) && in_array($fieldVal, $targetVal);
            case 'not_in':
                return is_array($targetVal) && !in_array($fieldVal, $targetVal);
            case 'greater_than':
                return $fieldVal > $targetVal;
            case 'less_than':
                return $fieldVal < $targetVal;
            default:
                return false;
        }
    }

    private static function extractValue(string $path, array $user, array $context)
    {
        if (str_starts_with($path, 'user.')) {
            $key = substr($path, 5);
            return $user[$key] ?? null;
        }
        if (str_starts_with($path, 'context.')) {
            $key = substr($path, 8);
            return $context[$key] ?? null;
        }
        return $path;
    }

    private static function resolveContext($context): array
    {
        if (is_array($context))
            return $context;
        if (is_object($context)) {
            if (method_exists($context, 'toArray'))
                return $context->toArray();
            return (array) $context;
        }
        return [];
    }
}
