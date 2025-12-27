<?php

namespace App\Plans;

final class PlanPermissionChecker
{
    /**
     * Check if a plan grants a permission.
     *
     * A permission is granted if the permission key EXISTS in the plan's permissions array.
     * The boolean value in the config is display-only and ignored.
     *
     * @param string $planKey Plan key (e.g., 'starter', 'pro', 'business')
     * @param string $permissionKey Permission key (e.g., 'vies_validation')
     * @return bool True if the permission is granted, false otherwise
     */
    public function planGrantsPermission(string $planKey, string $permissionKey): bool
    {
        $permissions = config("plans.plans.{$planKey}.permissions", []);

        // Check if the permission key exists (presence only, ignore boolean value)
        return array_key_exists($permissionKey, $permissions);
    }
}

