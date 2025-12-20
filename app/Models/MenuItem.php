<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'super_admin_only' => 'boolean',
        'tenant_specific' => 'boolean',
        'active' => 'boolean',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id', 'id');
    }

    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }
    public function permission()
    {
        return $this->belongsTo(\App\Models\Permission::class);
    }

    /**
     * Get the tenants that have access to this menu item
     */
    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'tenant_menu_items', 'menu_item_id', 'tenant_id')
            ->withTimestamps();
    }
}

