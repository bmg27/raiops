<?php

namespace App\Services;

/**
 * RAIOPS Menu Service
 * 
 * Provides the navigation menu structure for RAIOPS Command Central.
 * RAIOPS uses its own dedicated menu system, separate from RAI.
 */
class MenuServiceDatabase
{
    /**
     * Get RAIOPS admin menu items
     * 
     * Returns the main navigation items for the RAIOPS admin panel.
     * This is a flat structure of top-level links - simple and focused.
     */
    public static function getSuperAdminMenuItems(): array
    {
        return self::getRaiOpsMenu();
    }

    /**
     * RAIOPS Admin Menu - The main navigation for RAIOPS Command Central
     */
    private static function getRaiOpsMenu(): array
    {
        return [
            [
                'type' => 'link',
                'id' => 1,
                'url' => '/admin/health',
                'icon' => 'heart-pulse',
                'title' => 'System Health',
            ],
            [
                'type' => 'link',
                'id' => 2,
                'url' => '/admin/analytics',
                'icon' => 'graph-up',
                'title' => 'Analytics',
                'permission_name' => 'reports.view',
            ],
            [
                'type' => 'link',
                'id' => 3,
                'url' => '/admin/rds',
                'icon' => 'database',
                'title' => 'RDS Instances',
                'permission_name' => 'rds.manage',
            ],
            [
                'type' => 'link',
                'id' => 4,
                'url' => '/admin/tenants',
                'icon' => 'building',
                'title' => 'Tenants',
                'permission_name' => 'tenant.view',
            ],
            [
                'type' => 'link',
                'id' => 5,
                'url' => '/admin/user-routing',
                'icon' => 'signpost-split',
                'title' => 'User Routing',
                'permission_name' => 'user.view',
            ],
            [
                'type' => 'link',
                'id' => 6,
                'url' => '/admin/billing',
                'icon' => 'credit-card',
                'title' => 'Billing',
                'permission_name' => 'billing.view',
            ],
            [
                'type' => 'link',
                'id' => 7,
                'url' => '/admin/audit-logs',
                'icon' => 'journal-text',
                'title' => 'Audit Logs',
                'permission_name' => 'audit.view',
            ],
        ];
    }
}
