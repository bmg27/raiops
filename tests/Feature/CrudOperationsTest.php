<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Models\Permission;
use App\Models\MenuItem;
use App\Models\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a super admin user
        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'is_super_admin' => true,
        ]);

        // Create Super Admin role and permission
        $permission = Permission::create([
            'name' => 'tenant.manage',
            'guard_name' => 'web',
        ]);

        $role = Role::create([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);
        $this->superAdmin->assignRole($role);
    }

    /** @test */
    public function super_admin_can_access_tenant_management_page()
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/tenants');

        $response->assertStatus(200);
        $response->assertSeeLivewire('admin.tenant-management');
    }

    /** @test */
    public function super_admin_can_create_tenant()
    {
        $tenantData = [
            'name' => 'Test Restaurant',
            'primary_contact_name' => 'John Doe',
            'primary_contact_email' => 'john@testrestaurant.com',
            'status' => 'trial',
        ];

        $tenant = Tenant::create($tenantData);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Restaurant',
            'primary_contact_email' => 'john@testrestaurant.com',
        ]);

        $this->assertEquals('Test Restaurant', $tenant->name);
    }

    /** @test */
    public function super_admin_can_update_tenant()
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Old Name',
        ]);

        $tenant->update([
            'name' => 'New Name',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'New Name',
        ]);
    }

    /** @test */
    public function super_admin_can_delete_tenant()
    {
        $tenant = Tenant::factory()->create();

        $tenant->delete();

        $this->assertDatabaseMissing('tenants', [
            'id' => $tenant->id,
        ]);
    }

    /** @test */
    public function super_admin_can_access_user_management_page()
    {
        $userManagePermission = Permission::create([
            'name' => 'user.manage',
            'guard_name' => 'web',
        ]);

        $this->superAdmin->givePermissionTo($userManagePermission);

        $response = $this->actingAs($this->superAdmin)
            ->get('/um');

        $response->assertStatus(200);
        $response->assertSeeLivewire('permissions.manage-master');
    }

    /** @test */
    public function super_admin_can_create_user()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => bcrypt('password'),
            'is_super_admin' => false,
        ];

        $user = User::create($userData);

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
        ]);
    }

    /** @test */
    public function super_admin_can_update_user()
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
        ]);

        $user->update([
            'name' => 'New Name',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    /** @test */
    public function super_admin_can_delete_user()
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    /** @test */
    public function super_admin_can_create_role()
    {
        $role = Role::create([
            'name' => 'Test Role',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Test Role',
        ]);
    }

    /** @test */
    public function super_admin_can_update_role()
    {
        $role = Role::create([
            'name' => 'Old Role Name',
            'guard_name' => 'web',
        ]);

        $role->update([
            'name' => 'New Role Name',
        ]);

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'New Role Name',
        ]);
    }

    /** @test */
    public function super_admin_can_delete_role()
    {
        $role = Role::create([
            'name' => 'Deletable Role',
            'guard_name' => 'web',
        ]);

        $role->delete();

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }

    /** @test */
    public function super_admin_can_create_permission()
    {
        $permission = Permission::create([
            'name' => 'test.permission',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'test.permission',
        ]);
    }

    /** @test */
    public function super_admin_can_update_permission()
    {
        $permission = Permission::create([
            'name' => 'old.permission',
            'guard_name' => 'web',
        ]);

        $permission->update([
            'name' => 'new.permission',
        ]);

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'name' => 'new.permission',
        ]);
    }

    /** @test */
    public function super_admin_can_delete_permission()
    {
        $permission = Permission::create([
            'name' => 'deletable.permission',
            'guard_name' => 'web',
        ]);

        $permission->delete();

        $this->assertDatabaseMissing('permissions', [
            'id' => $permission->id,
        ]);
    }

    /** @test */
    public function super_admin_can_assign_role_to_user()
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Test Role',
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        $this->assertTrue($user->hasRole('Test Role'));
    }

    /** @test */
    public function super_admin_can_assign_permission_to_role()
    {
        $role = Role::create([
            'name' => 'Test Role',
            'guard_name' => 'web',
        ]);

        $permission = Permission::create([
            'name' => 'test.permission',
            'guard_name' => 'web',
        ]);

        $role->givePermissionTo($permission);

        $this->assertTrue($role->hasPermissionTo('test.permission'));
    }

    /** @test */
    public function super_admin_can_create_menu_item()
    {
        $menu = Menu::create(['name' => 'Test Menu']);

        $menuItem = MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Test Menu Item',
            'url' => '/test',
            'order' => 1,
            'active' => true,
            'super_admin_only' => true,
        ]);

        $this->assertDatabaseHas('menu_items', [
            'title' => 'Test Menu Item',
            'url' => '/test',
        ]);
    }

    /** @test */
    public function super_admin_can_update_menu_item()
    {
        $menu = Menu::create(['name' => 'Test Menu']);

        $menuItem = MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Old Title',
            'url' => '/old',
            'order' => 1,
            'active' => true,
        ]);

        $menuItem->update([
            'title' => 'New Title',
            'url' => '/new',
        ]);

        $this->assertDatabaseHas('menu_items', [
            'id' => $menuItem->id,
            'title' => 'New Title',
            'url' => '/new',
        ]);
    }

    /** @test */
    public function super_admin_can_delete_menu_item()
    {
        $menu = Menu::create(['name' => 'Test Menu']);

        $menuItem = MenuItem::create([
            'menu_id' => $menu->id,
            'title' => 'Deletable Item',
            'url' => '/delete',
            'order' => 1,
            'active' => true,
        ]);

        $menuItem->delete();

        $this->assertDatabaseMissing('menu_items', [
            'id' => $menuItem->id,
        ]);
    }

    /** @test */
    public function non_super_admin_cannot_access_tenant_management()
    {
        $regularUser = User::factory()->create([
            'is_super_admin' => false,
        ]);

        $response = $this->actingAs($regularUser)
            ->get('/admin/tenants');

        $response->assertStatus(403);
    }

    /** @test */
    public function guest_cannot_access_protected_routes()
    {
        $response = $this->get('/admin/tenants');
        $response->assertRedirect('/login');

        $response = $this->get('/um');
        $response->assertRedirect('/login');
    }
}

