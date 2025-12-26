<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Auth;

/**
 * RAIOPS Menu Service
 * 
 * Provides the navigation menu structure for RAIOPS Command Central.
 * Loads menu items from the database with proper hierarchy and permission filtering.
 */
class MenuServiceDatabase
{
    /**
     * Get RAIOPS admin menu items from database
     * 
     * Returns the main navigation items for the RAIOPS admin panel.
     * Supports parent/child/grandchild hierarchy with permission-based filtering.
     */
    public static function getSuperAdminMenuItems(): array
    {
        $user = Auth::user();
        // Super admins (is_super_admin = 1) have full access like RAI
        $isSuperAdmin = $user && $user->isSuperAdmin();
        $userPermissions = $user ? $user->getAllPermissions()->pluck('name')->toArray() : [];

        // Get the main menu (RAIOPS should have one menu)
        $menus = Menu::with(['items' => function ($q) use ($isSuperAdmin) {
            $q->where('active', 1);
            if (!$isSuperAdmin) {
                $q->where(function($query) {
                    $query->where('super_admin_only', false)
                          ->orWhereNull('super_admin_only');
                });
            }
        }])->limit(1)->get();

        $processed = collect();

        foreach ($menus as $menu) {
            // Get top-level items (no parent)
            $itemsQuery = $menu->items()
                ->whereNull('parent_id')
                ->where('active', 1);
            
            if (!$isSuperAdmin) {
                $itemsQuery->where(function($query) {
                    $query->where('super_admin_only', false)
                          ->orWhereNull('super_admin_only');
                });
            }
            
            $items = $itemsQuery->with([
                    'children.permission',
                    'children.children.permission',
                    'permission',
                    'children' => function ($q) use ($isSuperAdmin) {
                        $q->orderBy('order', 'asc')->where('active', 1);
                        if (!$isSuperAdmin) {
                            $q->where(function($query) {
                                $query->where('super_admin_only', false)
                                      ->orWhereNull('super_admin_only');
                            });
                        }
                    },
                    'children.children' => function ($q) use ($isSuperAdmin) {
                        $q->orderBy('order', 'asc')->where('active', 1);
                        if (!$isSuperAdmin) {
                            $q->where(function($query) {
                                $query->where('super_admin_only', false)
                                      ->orWhereNull('super_admin_only');
                            });
                        }
                    }
                ])
                ->orderBy('order', 'asc')
                ->get();

            foreach ($items as $item) {
                // Filter out super admin only items unless user is super admin
                if (!$isSuperAdmin && $item->super_admin_only) {
                    continue;
                }
                
                $hasChildren = $item->children->isNotEmpty();

                if ($hasChildren) {
                    // Parent with children - build authorized children
                    $authorizedChildren = $item->children->filter(function ($child) use ($userPermissions, $isSuperAdmin) {
                        if (!$isSuperAdmin && $child->super_admin_only) {
                            return false;
                        }
                        
                        if ($isSuperAdmin) {
                            return true;
                        }

                        $hasGrandchildren = $child->children->isNotEmpty();

                        if ($hasGrandchildren) {
                            // Child has grandchildren - include if ANY grandchild is authorized
                            $authorizedGrandchildren = $child->children->filter(function ($grandchild) use ($userPermissions, $isSuperAdmin) {
                                if (!$isSuperAdmin && $grandchild->super_admin_only) {
                                    return false;
                                }
                                
                                if ($isSuperAdmin) {
                                    return true;
                                }
                                
                                if (!$grandchild->permission || empty($grandchild->permission->name)) {
                                    return false;
                                }
                                return in_array($grandchild->permission->name ?? null, $userPermissions);
                            })->where('active', '1');

                            return $authorizedGrandchildren->isNotEmpty();
                        } else {
                            // Child has no grandchildren - check child's own permission
                            if (!$child->permission || empty($child->permission->name)) {
                                return false;
                            }
                            return in_array($child->permission->name ?? null, $userPermissions);
                        }
                    })->where('active', '1');

                    // Show parent if ANY authorized children exist
                    if ($authorizedChildren->isNotEmpty()) {
                        $processed->push([
                            'type'     => 'collapsible',
                            'id'       => (int) $item->id,
                            'icon'     => $item->icon,
                            'title'    => $item->title,
                            'children' => $authorizedChildren->map(function ($c) use ($userPermissions, $isSuperAdmin) {
                                $hasGrandchildren = $c->children->isNotEmpty();

                                $authorizedGrandchildren = $hasGrandchildren
                                    ? $c->children->filter(function ($grandchild) use ($userPermissions, $isSuperAdmin) {
                                        if (!$isSuperAdmin && $grandchild->super_admin_only) {
                                            return false;
                                        }
                                        
                                        if ($isSuperAdmin) {
                                            return true;
                                        }
                                        
                                        if (!$grandchild->permission || empty($grandchild->permission->name)) {
                                            return false;
                                        }
                                        return in_array($grandchild->permission->name ?? null, $userPermissions);
                                    })->where('active', '1')
                                    : collect();

                                $childData = [
                                    'title'    => $c->title,
                                    'url'      => $c->url,
                                    'child_id' => (int) $c->id,
                                ];

                                // Add grandchildren if they exist and are authorized
                                if ($authorizedGrandchildren->isNotEmpty()) {
                                    $childData['has_grandchildren'] = true;
                                    $childData['grandchildren'] = $authorizedGrandchildren->map(fn ($gc) => [
                                        'title'         => $gc->title,
                                        'url'           => $gc->url,
                                        'grandchild_id' => (int) $gc->id,
                                    ])->toArray();
                                } else {
                                    $childData['has_grandchildren'] = false;
                                }

                                return $childData;
                            })->toArray(),
                        ]);
                    }
                } else {
                    // Item has no children - only display if it has a valid route
                    $url = $item->url ?? '';
                    $hasValidRoute = !empty($url) && $url !== '#' && str_contains($url, '/');
                    
                    if (!$hasValidRoute) {
                        continue; // Suppress parent with no children and no valid route
                    }
                    
                    if (!$isSuperAdmin && $item->super_admin_only) {
                        continue;
                    }
                    
                    // Check item's own permission
                    $allowItem = !$item->permission
                        || ($isSuperAdmin || in_array($item->permission->name ?? null, $userPermissions));

                    if ($allowItem) {
                        $processed->push([
                            'type'  => 'link',
                            'url'   => $item->url,
                            'icon'  => $item->icon,
                            'title' => $item->title,
                        ]);
                    }
                }
            }
        }

        return $processed->toArray();
    }
}
