<?php

namespace App\Livewire\Nav;

use Livewire\Attributes\Session;
use Livewire\Component;

class SidebarMenu extends Component
{
    #[Session(key: 'navigation_state')]
    public string $navigationState = 'open-on-load';

    #[Session(key: 'expanded_submenus')]
    public array $expandedSubmenus = [];

    #[Session(key: 'activeChildId')]
    public ?int $activeChildId = null;

    public bool $isMobile = false;
    public array $menuItems = [];
    public string $currentUrl = '';

    public function mount(): void
    {
        $this->currentUrl = request()->path();

        // Initial mobile detection
        $ua = request()->header('User-Agent', '');
        $this->isMobile = str_contains($ua, 'Mobile')
            || str_contains($ua, 'Android')
            || str_contains($ua, 'iPhone');

        if ($this->isMobile) {
            $this->navigationState = 'collapsed';
            $this->expandedSubmenus = [];
        }

        if ($this->navigationState === 'open-on-load') {
            $this->navigationState = 'expanded';
        }

        // Build menu items from the extracted super admin menu structure
        $this->buildMenuItems();
        $this->setActiveSubmenuForCurrentUrl();
    }

    private function buildMenuItems(): void
    {
        // Load menu items from the extracted super admin menu structure
        $this->menuItems = \App\Services\MenuService::getSuperAdminMenuItems();
        
        // If no menu items found, show at least Tenant Management
        if (empty($this->menuItems)) {
            $this->menuItems = [
                [
                    'type' => 'link',
                    'url' => '/admin/tenants',
                    'icon' => 'building',
                    'title' => 'Tenant Management',
                ],
            ];
        }
    }

    protected function setActiveSubmenuForCurrentUrl(): void
    {
        if (!empty($this->expandedSubmenus)) {
            return;
        }

        $currentPath = '/' . trim($this->currentUrl, '/');

        foreach ($this->menuItems as $menuItem) {
            if (($menuItem['type'] ?? null) === 'collapsible') {
                foreach ($menuItem['children'] ?? [] as $child) {
                    // Check if current URL matches a grandchild
                    if (($child['has_grandchildren'] ?? false) && !empty($child['grandchildren'])) {
                        foreach ($child['grandchildren'] as $grandchild) {
                            $grandchildPath = '/' . trim($grandchild['url'], '/');
                            if ($currentPath === $grandchildPath || str_starts_with($currentPath, $grandchildPath . '/')) {
                                // Expand both parent and child (grandchild's parent)
                                $this->expandedSubmenus = [$menuItem['id'], $child['child_id']];
                                $this->activeChildId = $grandchild['grandchild_id'];
                                return;
                            }
                        }
                    }

                    // Check if current URL matches a child
                    $childPath = '/' . trim($child['url'], '/');
                    if ($currentPath === $childPath || str_starts_with($currentPath, $childPath . '/')) {
                        $this->expandedSubmenus = [$menuItem['id']];
                        $this->activeChildId = $child['child_id'] ?? null;
                        return;
                    }
                }
            } elseif (($menuItem['type'] ?? null) === 'link') {
                $itemPath = '/' . trim($menuItem['url'], '/');
                if ($currentPath === $itemPath || str_starts_with($currentPath, $itemPath . '/')) {
                    $this->activeChildId = -1;
                    return;
                }
            }
        }
    }

    public function toggleSubmenu(int $submenuId): void
    {
        if (!$this->isMobile && !in_array($this->navigationState, ['expanded','open-on-load'], true)) {
            return;
        }

        // Check if this is a parent-level menu or child-level menu
        $isParentMenu = false;
        $parentMenuId = null;

        // Find if this submenuId is a top-level parent menu
        foreach ($this->menuItems as $menuItem) {
            if (($menuItem['type'] ?? null) === 'collapsible' && $menuItem['id'] === $submenuId) {
                $isParentMenu = true;
                break;
            }

            // Check if it's a child menu with grandchildren
            if (($menuItem['type'] ?? null) === 'collapsible') {
                foreach ($menuItem['children'] ?? [] as $child) {
                    if (($child['child_id'] ?? null) === $submenuId && ($child['has_grandchildren'] ?? false)) {
                        $isParentMenu = false;
                        $parentMenuId = $menuItem['id'];
                        break 2;
                    }
                }
            }
        }

        if ($isParentMenu) {
            // Toggling a parent-level menu - single-open behavior
            if (in_array($submenuId, $this->expandedSubmenus, true)) {
                $this->expandedSubmenus = [];
            } else {
                $this->expandedSubmenus = [$submenuId];
            }
        } else {
            // Toggling a child menu (grandchildren) - single-open behavior for grandchildren
            if (in_array($submenuId, $this->expandedSubmenus, true)) {
                // Closing this child's grandchildren - remove child but keep parent
                $this->expandedSubmenus = array_values(array_diff($this->expandedSubmenus, [$submenuId]));
            } else {
                // Opening this child's grandchildren - close any other open grandchildren
                // Keep only the parent menu IDs, remove all other child menu IDs
                $this->expandedSubmenus = array_filter($this->expandedSubmenus, function($id) {
                    // Check if this ID is a parent menu (not a child)
                    foreach ($this->menuItems as $menuItem) {
                        if (($menuItem['type'] ?? null) === 'collapsible' && $menuItem['id'] === $id) {
                            return true; // Keep parent IDs
                        }
                    }
                    return false; // Remove child IDs
                });

                // Add the parent if not already there
                if ($parentMenuId && !in_array($parentMenuId, $this->expandedSubmenus, true)) {
                    $this->expandedSubmenus[] = $parentMenuId;
                }

                // Add the new child (single-open: only one child at a time)
                $this->expandedSubmenus[] = $submenuId;
                $this->expandedSubmenus = array_values($this->expandedSubmenus); // Re-index
            }
        }
    }

    public function getNavigationClasses(): string
    {
        if ($this->isMobile) {
            return $this->navigationState === 'expanded'
                ? 'navigation'
                : 'navigation close';
        }

        return $this->navigationState === 'collapsed'
            ? 'navigation close'
            : 'navigation';
    }

    public function setActiveChildId(int $id): void
    {
        $this->activeChildId = $id;

        if ($this->isMobile) {
            $this->navigationState = 'collapsed';
        }
    }

    public function setIsMobile(bool $isMobile): void
    {
        $this->isMobile = $isMobile;

        if ($this->isMobile) {
            $this->navigationState = 'collapsed';
        }
    }

    #[On('nav-toggle-request')]
    public function toggleNav(): void
    {
        if ($this->isMobile) {
            $this->navigationState = $this->navigationState === 'expanded' ? 'collapsed' : 'expanded';
        } else {
            if ($this->navigationState === 'open-on-load') {
                $this->navigationState = 'expanded';
            } elseif ($this->navigationState === 'expanded') {
                $this->navigationState = 'collapsed';
            } else {
                $this->navigationState = 'expanded';
            }
        }
    }

    public function render()
    {
        return view('livewire.nav.sidebar-menu');
    }
}

