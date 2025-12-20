# Menu System Documentation

## Overview

RAINBO supports two menu systems:
1. **Database-driven menus** (default, recommended)
2. **JSON-based menus** (legacy fallback)

## Configuration

Set the menu source in your `.env` file:

```env
# Use database (default)
MENU_SOURCE=database

# Or use JSON file
MENU_SOURCE=json
```

If `MENU_SOURCE` is not set, the system defaults to `database`.

## Database Menu System

### Structure

The database menu system uses two tables:
- `menus` - Top-level menu containers
- `menu_items` - Individual menu items with 3-level hierarchy

### Menu Item Hierarchy

1. **Parent** (Level 1) - Top-level menu groups
   - Example: "Admin", "Sandbox", "Tenants"
   - Usually collapsible with children
   - `parent_id` is `NULL`

2. **Child** (Level 2) - Sub-menu items under parents
   - Example: "Sales", "Tagging" under "Admin"
   - Can be links or have grandchildren
   - `parent_id` points to parent item

3. **Grandchild** (Level 3) - Items under children
   - Example: "Manage Sales" under "Sales"
   - Always links (no further nesting)
   - `parent_id` points to child item

### Menu Item Fields

- `menu_id` - Foreign key to `menus` table
- `title` - Display text
- `url` - Route or URL (use `#` for non-clickable parents)
- `parent_id` - Parent menu item ID (NULL for top-level)
- `icon` - Bootstrap icon name (without 'bi-' prefix)
- `order` - Display order (lower numbers first)
- `active` - Boolean, whether item is visible
- `permission_id` - Optional permission required to see item
- `super_admin_only` - Boolean, only show to super admins
- `tenant_specific` - Boolean, can be assigned to specific tenants
- `super_admin_append` - String, append to super admin menus

### Services

#### MenuServiceDatabase

Main service for database-driven menus:

```php
use App\Services\MenuServiceDatabase;

// Get super admin menu items
$menuItems = MenuServiceDatabase::getSuperAdminMenuItems();

// Get tenant-specific menu items
$menuItems = MenuServiceDatabase::getTenantMenuItems($tenantId);
```

#### MenuService

Wrapper service that routes to database or JSON based on config:

```php
use App\Services\MenuService;

// Automatically uses configured source (database or JSON)
$menuItems = MenuService::getSuperAdminMenuItems();
```

### Copying Menu Items from RAI

Use the seeder to copy menu items from the RAI database:

```bash
php artisan db:seed --class=CopyMenuItemsSeeder
```

This will copy:
- Menus
- Menu items with all fields
- Tenant menu item relationships (if table exists)

### Creating Menu Items Manually

#### Via Database

```php
use App\Models\Menu;
use App\Models\MenuItem;

// Create a menu
$menu = Menu::create(['name' => 'Super Admin']);

// Create a parent item
$parent = MenuItem::create([
    'menu_id' => $menu->id,
    'title' => 'Admin',
    'url' => '#',
    'icon' => 'gear',
    'order' => 1,
    'active' => true,
    'super_admin_only' => true,
]);

// Create a child item
$child = MenuItem::create([
    'menu_id' => $menu->id,
    'parent_id' => $parent->id,
    'title' => 'Tenant Management',
    'url' => '/admin/tenants',
    'order' => 1,
    'active' => true,
    'super_admin_only' => true,
    'permission_id' => Permission::where('name', 'tenant.manage')->first()->id,
]);
```

#### Via UI

Use the Menu Organizer in the Permission Management section:
1. Navigate to `/um` (User Management)
2. Click on "Organize Menu" tab
3. Add, edit, or drag-and-drop menu items

### Permission Filtering

Menu items are automatically filtered based on:
1. **Super Admin Flag**: Only shown to super admins if `super_admin_only = true`
2. **Permissions**: Only shown if user has the required permission
3. **Active Status**: Only shown if `active = true`

Super admins see all items regardless of permissions.

## JSON Menu System (Legacy)

### Structure

The JSON menu system uses a file: `super_admin_menu_structure.json`

### Format

```json
[
  {
    "parent_title": "Admin",
    "items": [
      {
        "id": 1,
        "title": "Tenant Management",
        "url": "/admin/tenants",
        "order": 1,
        "permission_name": "tenant.manage"
      }
    ]
  }
]
```

### Service

```php
use App\Services\MenuService;

// Get menu items from JSON
$menuItems = MenuService::getSuperAdminMenuItemsFromJson();
```

## Switching Between Systems

### From JSON to Database

1. Run the menu items seeder:
   ```bash
   php artisan db:seed --class=CopyMenuItemsSeeder
   ```

2. Update `.env`:
   ```env
   MENU_SOURCE=database
   ```

3. Clear config cache:
   ```bash
   php artisan config:clear
   ```

### From Database to JSON

1. Update `.env`:
   ```env
   MENU_SOURCE=json
   ```

2. Clear config cache:
   ```bash
   php artisan config:clear
   ```

## Troubleshooting

### No Menu Items Showing

1. Check menu source configuration:
   ```bash
   php artisan tinker
   >>> config('app.menu_source')
   ```

2. Check if menu items exist in database:
   ```bash
   php artisan tinker
   >>> App\Models\MenuItem::count()
   ```

3. Check if menu items are active and super admin only:
   ```bash
   php artisan tinker
   >>> App\Models\MenuItem::where('super_admin_only', true)->where('active', true)->count()
   ```

### Fallback Menu Showing

If you see only "Tenant Management" and "User Management", the system couldn't find menu items and is using the fallback. This means:
- No menu items in database (if using database mode)
- No JSON file found (if using JSON mode)

### Permission Issues

If menu items aren't showing for a user:
1. Verify user is super admin:
   ```bash
   php artisan tinker
   >>> $user = App\Models\User::find(1);
   >>> $user->isSuperAdmin()
   ```

2. Check user permissions:
   ```bash
   >>> $user->getAllPermissions()->pluck('name')
   ```

3. Clear permission cache:
   ```bash
   php artisan permission:cache-reset
   ```

## Best Practices

1. **Use Database Mode**: More flexible and easier to manage
2. **Set Permissions**: Always assign permissions to menu items for security
3. **Order Items**: Use the `order` field to control display order
4. **Use Icons**: Bootstrap Icons work well (e.g., 'gear', 'building', 'people')
5. **Test Visibility**: Test menu visibility with different user roles
6. **Keep JSON as Backup**: Keep the JSON file as a fallback during migration

---

**Last Updated**: December 6, 2025

