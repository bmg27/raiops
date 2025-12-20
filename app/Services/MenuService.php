<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class MenuService
{
    /**
     * Get super admin menu items
     * 
     * By default, loads from database. Set MENU_SOURCE=json in .env to use JSON file.
     */
    public static function getSuperAdminMenuItems(): array
    {
        $menuSource = config('app.menu_source', 'database');

        if ($menuSource === 'database') {
            return MenuServiceDatabase::getSuperAdminMenuItems();
        }

        return self::getSuperAdminMenuItemsFromJson();
    }

    /**
     * Get super admin menu items from the extracted JSON structure
     */
    public static function getSuperAdminMenuItemsFromJson(): array
    {
        $menuFile = base_path('super_admin_menu_structure.json');
        
        if (!File::exists($menuFile)) {
            return [];
        }

        $menuData = json_decode(File::get($menuFile), true);
        
        if (!is_array($menuData)) {
            return [];
        }

        // Build menu structure
        $menuItems = [];
        
        // 1. Admin Group (with sub-groups: Sales, Tagging)
        $adminGroup = self::buildAdminGroup($menuData);
        if (!empty($adminGroup['children'])) {
            $menuItems[] = $adminGroup;
        }

        // 2. Sandbox Group
        $sandboxGroup = self::buildSandboxGroup($menuData);
        if (!empty($sandboxGroup['children'])) {
            $menuItems[] = $sandboxGroup;
        }

        // 3. Tenants Group
        $tenantsGroup = self::buildTenantsGroup($menuData);
        if (!empty($tenantsGroup['children'])) {
            $menuItems[] = $tenantsGroup;
        }

        return $menuItems;
    }

    private static function buildAdminGroup(array $menuData): array
    {
        $adminGroup = [
            'type' => 'collapsible',
            'id' => 1,
            'icon' => 'gear',
            'title' => 'Admin',
            'children' => [],
        ];

        // Find Admin parent items
        $adminItems = [];
        foreach ($menuData as $group) {
            if (($group['parent_title'] ?? '') === 'Admin') {
                $adminItems = $group['items'] ?? [];
                break;
            }
        }

        // Process each admin item
        foreach ($adminItems as $item) {
            // Check if this is a sub-group (Sales, Tagging)
            if ($item['url'] === '#') {
                // This is a sub-parent (Sales or Tagging)
                $subGroupTitle = $item['title'];
                $subGroupChildren = [];
                
                // Find children of this sub-group
                foreach ($menuData as $subGroupData) {
                    if (($subGroupData['parent_title'] ?? '') === $subGroupTitle) {
                        foreach ($subGroupData['items'] ?? [] as $grandchild) {
                            $subGroupChildren[] = [
                                'title' => $grandchild['title'],
                                'url' => $grandchild['url'],
                                'grandchild_id' => $grandchild['id'],
                                'permission_name' => $grandchild['permission_name'] ?? null,
                            ];
                        }
                        break;
                    }
                }

                if (!empty($subGroupChildren)) {
                    $adminGroup['children'][] = [
                        'title' => $item['title'],
                        'url' => $item['url'],
                        'child_id' => $item['id'],
                        'has_grandchildren' => true,
                        'grandchildren' => $subGroupChildren,
                        'order' => $item['order'] ?? 0,
                    ];
                }
            } else {
                // Regular admin item
                $adminGroup['children'][] = [
                    'title' => $item['title'],
                    'url' => $item['url'],
                    'child_id' => $item['id'],
                    'has_grandchildren' => false,
                    'order' => $item['order'] ?? 0,
                    'permission_name' => $item['permission_name'] ?? null,
                ];
            }
        }

        // Add Shift Notes items to Admin group
        foreach ($menuData as $group) {
            if (($group['parent_title'] ?? '') === 'Shift Notes') {
                foreach ($group['items'] ?? [] as $item) {
                    $adminGroup['children'][] = [
                        'title' => $item['title'],
                        'url' => $item['url'],
                        'child_id' => $item['id'],
                        'has_grandchildren' => false,
                        'order' => $item['order'] ?? 0,
                        'permission_name' => $item['permission_name'] ?? null,
                    ];
                }
                break;
            }
        }

        // Sort children by order
        usort($adminGroup['children'], fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return $adminGroup;
    }

    private static function buildSandboxGroup(array $menuData): array
    {
        $sandboxGroup = [
            'type' => 'collapsible',
            'id' => 2,
            'icon' => 'box',
            'title' => 'Sandbox',
            'children' => [],
        ];

        foreach ($menuData as $group) {
            if (($group['parent_title'] ?? '') === 'Sandbox') {
                foreach ($group['items'] ?? [] as $item) {
                    $sandboxGroup['children'][] = [
                        'title' => $item['title'],
                        'url' => $item['url'],
                        'child_id' => $item['id'],
                        'has_grandchildren' => false,
                        'order' => $item['order'] ?? 0,
                        'permission_name' => $item['permission_name'] ?? null,
                    ];
                }
                break;
            }
        }

        // Sort children by order
        usort($sandboxGroup['children'], fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        return $sandboxGroup;
    }

    private static function buildTenantsGroup(array $menuData): array
    {
        $tenantsGroup = [
            'type' => 'collapsible',
            'id' => 3,
            'icon' => 'building',
            'title' => 'Tenants',
            'children' => [],
        ];

        foreach ($menuData as $group) {
            if (($group['parent_title'] ?? '') === 'Tenants') {
                foreach ($group['items'] ?? [] as $item) {
                    $tenantsGroup['children'][] = [
                        'title' => $item['title'],
                        'url' => $item['url'],
                        'child_id' => $item['id'],
                        'has_grandchildren' => false,
                        'order' => $item['order'] ?? 0,
                        'permission_name' => $item['permission_name'] ?? null,
                    ];
                }
                break;
            }
        }

        return $tenantsGroup;
    }
}
