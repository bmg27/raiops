<?php

namespace App\Livewire\Permissions;

use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use App\Models\MenuItem;

class MenuOrganizer extends Component
{
    public $selectedParentId = null;
    public $selectedChildId = null;
    
    public $parents = [];
    public $children = [];
    public $grandchildren = [];

    public function render()
    {
        // Load all parents (top-level items)
        $this->parents = MenuItem::whereNull('parent_id')
            ->where('active', 1)
            ->orderBy('order')
            ->get();
        
        // Load children if a parent is selected
        if ($this->selectedParentId) {
            $this->children = MenuItem::where('parent_id', $this->selectedParentId)
                ->where('active', 1)
                ->orderBy('order')
                ->get();
        } else {
            $this->children = collect();
        }
        
        // Load grandchildren if a child is selected
        if ($this->selectedChildId) {
            $this->grandchildren = MenuItem::where('parent_id', $this->selectedChildId)
                ->where('active', 1)
                ->orderBy('order')
                ->get();
        } else {
            $this->grandchildren = collect();
        }
        
        return view('livewire.permissions.menu-organizer');
    }

    /**
     * Select a parent to show its children
     */
    public function selectParent($parentId)
    {
        $this->selectedParentId = $parentId;
        $this->selectedChildId = null; // Reset child selection
    }

    /**
     * Select a child to show its grandchildren
     */
    public function selectChild($childId)
    {
        $this->selectedChildId = $childId;
    }

    /**
     * Move item up in order
     */
    public function moveUp($itemId)
    {
        $item = MenuItem::findOrFail($itemId);
        $parentId = $item->parent_id;
        
        // Get all siblings (items with same parent_id) - handle NULL properly
        $siblings = MenuItem::where(function($query) use ($parentId) {
                if ($parentId === null) {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $parentId);
                }
            })
            ->where('active', 1)
            ->orderBy('order', 'asc')
            ->orderBy('id', 'asc') // Secondary sort for consistency
            ->get();
        
        // Normalize orders first to ensure sequential values
        $this->normalizeOrders($siblings);
        
        // Reload to get fresh order values
        $siblings = $siblings->fresh();
        $item = MenuItem::findOrFail($itemId);
        
        // Find current item index
        $currentIndex = $siblings->search(fn($i) => $i->id == $itemId);
        
        if ($currentIndex !== false && $currentIndex > 0) {
            // Swap with previous sibling
            $previousItem = $siblings[$currentIndex - 1];
            
            $itemOrder = $item->order;
            $previousOrder = $previousItem->order;
            
            $item->order = $previousOrder;
            $previousItem->order = $itemOrder;
            
            $item->save();
            $previousItem->save();
            
            $this->clearMenuCache();
        }
    }

    /**
     * Move item down in order
     */
    public function moveDown($itemId)
    {
        $item = MenuItem::findOrFail($itemId);
        $parentId = $item->parent_id;
        
        // Get all siblings (items with same parent_id) - handle NULL properly
        $siblings = MenuItem::where(function($query) use ($parentId) {
                if ($parentId === null) {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $parentId);
                }
            })
            ->where('active', 1)
            ->orderBy('order', 'asc')
            ->orderBy('id', 'asc') // Secondary sort for consistency
            ->get();
        
        // Normalize orders first to ensure sequential values
        $this->normalizeOrders($siblings);
        
        // Reload to get fresh order values
        $siblings = $siblings->fresh();
        $item = MenuItem::findOrFail($itemId);
        
        // Find current item index
        $currentIndex = $siblings->search(fn($i) => $i->id == $itemId);
        
        if ($currentIndex !== false && $currentIndex < $siblings->count() - 1) {
            // Swap with next sibling
            $nextItem = $siblings[$currentIndex + 1];
            
            $itemOrder = $item->order;
            $nextOrder = $nextItem->order;
            
            $item->order = $nextOrder;
            $nextItem->order = $itemOrder;
            
            $item->save();
            $nextItem->save();
            
            $this->clearMenuCache();
        }
    }
    
    /**
     * Normalize order values to ensure they're sequential
     */
    private function normalizeOrders($items)
    {
        $order = 0;
        foreach ($items as $item) {
            if ($item->order != $order) {
                $item->order = $order;
                $item->save();
            }
            $order++;
        }
    }

    /**
     * Move an item to a new parent and position (drag & drop handler)
     */
    public function moveItem($itemId, $newParentId, $newIndex)
    {
        $item = MenuItem::findOrFail($itemId);
        
        // Convert "null" string to actual null for parents
        $newParentId = ($newParentId === 'null' || $newParentId === '' || $newParentId === null) ? null : (int)$newParentId;
        
        // Prevent circular references (item can't be its own parent or grandparent)
        if ($newParentId === $itemId) {
            session()->flash('error', 'Cannot move item to itself!');
            return;
        }
        
        // Check if trying to move a parent into one of its own children/grandchildren
        if ($newParentId !== null) {
            $newParent = MenuItem::find($newParentId);
            if ($newParent && $this->isDescendant($itemId, $newParentId)) {
                session()->flash('error', 'Cannot move parent into its own child!');
                return;
            }
        }
        
        // Update parent_id
        $item->parent_id = $newParentId;
        $item->save();
        
        // Get all siblings in the new location
        $siblings = MenuItem::where(function($query) use ($newParentId) {
                if ($newParentId === null) {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $newParentId);
                }
            })
            ->where('active', 1)
            ->orderBy('order', 'asc')
            ->get();
        
        // Reorder all siblings based on new index
        $order = 0;
        $moved = false;
        foreach ($siblings as $sibling) {
            if ($order == $newIndex && !$moved && $sibling->id != $itemId) {
                // Insert the moved item at this position
                $item->order = $order;
                $item->save();
                $order++;
                $moved = true;
            }
            
            if ($sibling->id != $itemId) {
                $sibling->order = $order;
                $sibling->save();
                $order++;
            }
        }
        
        // If we haven't moved it yet, put it at the end
        if (!$moved) {
            $item->order = $order;
            $item->save();
        }
        
        $this->clearMenuCache();
        session()->flash('message', 'Menu item moved successfully!');
    }
    
    /**
     * Check if an item is a descendant of another (prevent circular references)
     */
    private function isDescendant($itemId, $potentialDescendantId)
    {
        $item = MenuItem::find($potentialDescendantId);
        
        while ($item && $item->parent_id) {
            if ($item->parent_id == $itemId) {
                return true;
            }
            $item = MenuItem::find($item->parent_id);
        }
        
        return false;
    }
    
    /**
     * Clear all menu-related caches
     */
    private function clearMenuCache()
    {
        // Clear menu cache for all users
        $userIds = \App\Models\User::pluck('id');
        foreach ($userIds as $userId) {
            $cacheKey = 'menu-items-' . $userId;
            Cache::forget($cacheKey);
        }
        
        // Also clear guest cache
        Cache::forget('menu-items-guest');
    }
}

