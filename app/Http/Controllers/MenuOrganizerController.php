<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MenuOrganizerController extends Controller
{
    /**
     * Update menu item order via AJAX
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*' => 'integer|exists:menu_items,id',
            'parent_id' => 'nullable|integer|exists:menu_items,id',
        ]);

        $items = $request->input('items');
        $parentId = $request->input('parent_id');

        // Update the order for each item
        foreach ($items as $index => $itemId) {
            MenuItem::where('id', $itemId)->update([
                'order' => $index + 1,
                // Don't update parent_id here - only update order within existing hierarchy
            ]);
        }

        // Clear menu cache for all users
        $this->clearMenuCache();

        return response()->json([
            'success' => true,
            'message' => 'Menu order updated successfully!',
        ]);
    }

    /**
     * Clear all menu-related caches
     * 
     * @return void
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

