<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class MenuServiceDatabase
{
    /**
     * Get super admin menu items from the database
     */
    public static function getSuperAdminMenuItems(): array
    {
        // Get the main menu (assuming there's a menu named 'Super Admin' or 'Main')
        $menu = Menu::where('name', 'Super Admin')
            ->orWhere('name', 'Main')
            ->first();

        if (!$menu) {
            // Fallback: get the first menu
            $menu = Menu::first();
        }

        if (!$menu) {
            return self::getFallbackMenu();
        }

        // Get all menu items for this menu that are super admin only
        $menuItems = MenuItem::where('menu_id', $menu->id)
            ->where('super_admin_only', true)
            ->where('active', true)
            ->orderBy('order')
            ->get();

        if ($menuItems->isEmpty()) {
            return self::getFallbackMenu();
        }

        // Build hierarchical structure
        return self::buildHierarchy($menuItems);
    }

    /**
     * Build hierarchical menu structure from flat menu items
     */
    private static function buildHierarchy(Collection $menuItems): array
    {
        $hierarchy = [];
        
        // Get top-level items (parent_id is null)
        $topLevelItems = $menuItems->where('parent_id', null);

        foreach ($topLevelItems as $topItem) {
            $menuItem = [
                'type' => 'collapsible',
                'id' => $topItem->id,
                'icon' => $topItem->icon ?? 'gear',
                'title' => $topItem->title,
                'url' => $topItem->url,
                'children' => [],
            ];

            // Get children of this top-level item
            $children = $menuItems->where('parent_id', $topItem->id);
            
            foreach ($children as $child) {
                $childItem = [
                    'title' => $child->title,
                    'url' => $child->url,
                    'child_id' => $child->id,
                    'has_grandchildren' => false,
                    'order' => $child->order,
                    'permission_name' => $child->permission ? $child->permission->name : null,
                ];

                // Check if this child has grandchildren
                $grandchildren = $menuItems->where('parent_id', $child->id);
                
                if ($grandchildren->isNotEmpty()) {
                    $childItem['has_grandchildren'] = true;
                    $childItem['grandchildren'] = [];

                    foreach ($grandchildren as $grandchild) {
                        $childItem['grandchildren'][] = [
                            'title' => $grandchild->title,
                            'url' => $grandchild->url,
                            'grandchild_id' => $grandchild->id,
                            'permission_name' => $grandchild->permission ? $grandchild->permission->name : null,
                        ];
                    }
                }

                // Check permissions if user is authenticated
                if (self::hasPermission($childItem['permission_name'])) {
                    $menuItem['children'][] = $childItem;
                }
            }

            // Only add parent if it has visible children or is a direct link
            if (!empty($menuItem['children']) || $topItem->url !== '#') {
                // If it's a direct link (no children), change type to 'link'
                if (empty($menuItem['children']) && $topItem->url !== '#') {
                    $menuItem['type'] = 'link';
                }
                $hierarchy[] = $menuItem;
            }
        }

        return $hierarchy;
    }

    /**
     * Check if user has permission
     */
    private static function hasPermission(?string $permissionName): bool
    {
        if (!$permissionName) {
            return true; // No permission required
        }

        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();

        // Super admins have all permissions
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Check if user has the specific permission
        return $user->hasPermissionTo($permissionName);
    }

    /**
     * Fallback menu if no database menu items exist
     */
    private static function getFallbackMenu(): array
    {
        return [
            [
                'type' => 'link',
                'url' => '/admin/tenants',
                'icon' => 'building',
                'title' => 'Tenant Management',
            ],
            [
                'type' => 'link',
                'url' => '/um',
                'icon' => 'people',
                'title' => 'User Management',
            ],
        ];
    }

    /**
     * Get tenant-specific menu items (for future use)
     */
    public static function getTenantMenuItems(int $tenantId): array
    {
        // Get the main menu
        $menu = Menu::where('name', 'Tenant')
            ->orWhere('name', 'Main')
            ->first();

        if (!$menu) {
            return [];
        }

        // Get menu items that are:
        // 1. Not super admin only
        // 2. Either not tenant-specific OR associated with this tenant
        $menuItems = MenuItem::where('menu_id', $menu->id)
            ->where('super_admin_only', false)
            ->where('active', true)
            ->where(function ($query) use ($tenantId) {
                $query->where('tenant_specific', false)
                    ->orWhereHas('tenants', function ($q) use ($tenantId) {
                        $q->where('tenant_id', $tenantId);
                    });
            })
            ->orderBy('order')
            ->get();

        return self::buildHierarchy($menuItems);
    }
}

