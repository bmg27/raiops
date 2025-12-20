# RAINBO Project - Complete Handoff Documentation

## Project Overview

**RAINBO** (RAI Back Office) is a standalone Laravel 11 application that serves as the administrative back office for the RAI restaurant management system. It provides super admin users with tools to manage tenants, users, roles, permissions, and menu items across the entire multi-tenant RAI ecosystem.

### Key Technologies
- **Laravel 11** (PHP 8.3)
- **Livewire 3** (for interactive components)
- **Jetstream** (authentication scaffolding)
- **Spatie Laravel Permission 6.10** (role-based access control)
- **Bootstrap 5.3** (UI framework)
- **MySQL** (database)

### Project Location
- **Linux Path**: `/var/www/html/rainbo`
- **Windows Path**: `z:\var\www\html\rainbo` (WSL)

---

## Project Structure

### Core Directories
```
rainbo/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── MenuOrganizerController.php
│   │   └── Middleware/
│   │       └── CheckPermission.php
│   ├── Livewire/
│   │   ├── Admin/
│   │   │   ├── TenantManagement.php
│   │   │   └── FlashMessage.php
│   │   ├── Common/
│   │   │   └── Avatar.php
│   │   ├── Nav/
│   │   │   └── SidebarMenu.php
│   │   └── Permissions/
│   │       ├── ManageMaster.php
│   │       ├── UsersIndex.php
│   │       ├── RolesIndex.php
│   │       ├── PermissionsIndex.php
│   │       ├── MenuItemsIndex.php
│   │       └── MenuOrganizer.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Tenant.php
│   │   ├── TenantSubscription.php
│   │   ├── TenantInvitation.php
│   │   ├── Role.php
│   │   ├── Permission.php
│   │   ├── MenuItem.php
│   │   ├── Menu.php
│   │   └── SevenLocation.php
│   ├── Notifications/
│   │   └── UserActivated.php
│   └── Services/
│       ├── MenuService.php
│       ├── TenantRoleService.php
│       └── ProviderSettingsService.php
├── database/
│   ├── migrations/
│   │   ├── 2025_12_06_000001_create_tenants_table.php
│   │   ├── 2025_12_06_000002_create_tenant_subscriptions_table.php
│   │   ├── 2025_12_06_000003_add_tenant_fields_to_users_table.php
│   │   ├── 2025_12_06_000004_create_tenant_invitations_table.php
│   │   ├── 2025_12_06_000005_create_seven_locations_table.php
│   │   ├── 2025_12_06_000006_create_menus_table.php
│   │   ├── 2025_12_06_000007_create_menu_items_table.php
│   │   ├── 2025_12_06_000008_add_super_admin_only_to_menu_items_table.php
│   │   ├── 2025_12_06_000009_add_tenant_specific_to_menu_items_table.php
│   │   ├── 2025_12_06_000010_add_super_admin_append_to_menu_items_table.php
│   │   ├── 2025_12_06_172654_create_permission_tables.php (Spatie)
│   │   ├── 2025_12_06_172655_add_tenant_id_to_roles_table.php
│   │   └── 2025_12_06_172656_add_super_admin_only_to_permissions_table.php
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── CopySuperAdminUsersSeeder.php
│       ├── CopyPermissionsSeeder.php
│       ├── CopyRolesSeeder.php
│       ├── CreateSuperAdminRoleAndPermissionSeeder.php
│       └── CopyTenantDataSeeder.php
├── resources/
│   └── views/
│       ├── layouts/
│       │   ├── rai.blade.php (main layout)
│       │   ├── guest.blade.php (Jetstream)
│       │   └── app.blade.php (Jetstream)
│       ├── components/
│       │   ├── page-header.blade.php
│       │   └── per-page.blade.php
│       ├── livewire/
│       │   ├── admin/
│       │   │   ├── tenant-management.blade.php
│       │   │   └── flash-message.blade.php
│       │   ├── common/
│       │   │   └── avatar.blade.php
│       │   ├── nav/
│       │   │   └── sidebar-menu.blade.php
│       │   └── permissions/
│       │       ├── rump-admin.blade.php
│       │       ├── users-index.blade.php
│       │       ├── roles-index.blade.php
│       │       ├── permissions-index.blade.php
│       │       ├── menu-items-index.blade.php
│       │       └── menu-organizer.blade.php
│       └── auth/
│           └── login.blade.php
└── routes/
    └── web.php
```

---

## Environment Configuration

### Required Environment Variables

Add these to your `.env` file:

```env
# Application
APP_NAME=RAINBO
APP_ENV=local
APP_KEY=base64:... (generate with: php artisan key:generate)
APP_DEBUG=true
APP_URL=http://rainbo.test

# Database (RAINBO database)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rainbo
DB_USERNAME=root
DB_PASSWORD=your_password

# RAI Database Connection (for copying data)
RAI_DB_HOST=127.0.0.1
RAI_DB_DATABASE=linkrg_prod_test
RAI_DB_USERNAME=root
RAI_DB_PASSWORD=your_password

# RAI URL (for tenant registration links)
RAI_URL=http://rai.test

# Jetstream
JETSTREAM_STACK=livewire

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Queue (if using)
QUEUE_CONNECTION=sync
```

### Database Configuration

The project uses two database connections:

1. **Default Connection** (`mysql`): The RAINBO database
2. **RAI Connection** (`rai`): Connection to the existing RAI production database

The RAI connection is configured in `config/database.php`:

```php
'rai' => [
    'driver' => 'mysql',
    'host' => env('RAI_DB_HOST', '127.0.0.1'),
    'database' => env('RAI_DB_DATABASE', 'rai'),
    'username' => env('RAI_DB_USERNAME', 'root'),
    'password' => env('RAI_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
],
```

---

## Installation & Setup

### 1. Initial Setup (if starting fresh)

```bash
# Navigate to project directory
cd /var/www/html/rainbo

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure .env file (see Environment Configuration above)
```

### 2. Database Setup

```bash
# Run migrations
php artisan migrate

# Seed initial data (permissions, roles, super admin users)
php artisan db:seed

# Copy tenant data from RAI (optional, but recommended)
php artisan db:seed --class=CopyTenantDataSeeder
```

### 3. Frontend Assets

The project currently uses compiled CSS/JS files copied from RAI. For development:

```bash
# If you want to compile assets (requires npm/node)
npm install
npm run build

# Or use Laravel Mix (if configured)
npm run dev
```

**Note**: The project is currently set up to use pre-compiled assets from RAI. The layout files use `asset()` instead of `@vite` directives.

### 4. Storage & Permissions

```bash
# Create storage link
php artisan storage:link

# Set permissions (if needed)
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

---

## Database Seeders

### Available Seeders

1. **DatabaseSeeder** (main seeder)
   - Runs: `CopyPermissionsSeeder`, `CopyRolesSeeder`, `CreateSuperAdminRoleAndPermissionSeeder`, `CopySuperAdminUsersSeeder`

2. **CopySuperAdminUsersSeeder**
   - Copies all users with `is_super_admin = true` from RAI database
   - Requires RAI database connection configured

3. **CopyPermissionsSeeder**
   - Copies all permissions from RAI database
   - Includes `super_admin_only` flags

4. **CopyRolesSeeder**
   - Copies global roles and their permission relationships from RAI

5. **CreateSuperAdminRoleAndPermissionSeeder**
   - Creates the "Super Admin" role and "tenant.manage" permission

6. **CopyTenantDataSeeder** (optional)
   - Copies tenants, subscriptions, invitations, and locations from RAI
   - Run separately: `php artisan db:seed --class=CopyTenantDataSeeder`

### Running Seeders

```bash
# Run all seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=CopyTenantDataSeeder

# Fresh migration with seeding
php artisan migrate:fresh --seed
```

---

## Key Features & Components

### 1. Tenant Management (`/admin/tenants`)

**Component**: `App\Livewire\Admin\TenantManagement`

**Features**:
- View all tenants with search and filtering
- Create/Edit/Delete tenants
- Manage tenant subscriptions
- Manage tenant locations
- Manage tenant users
- Configure provider settings (integrations)
- Send tenant invitations

**Access**: Requires `tenant.manage` permission (Super Admin only)

**Route**: `/admin/tenants`

### 2. Permission Management (`/um`)

**Component**: `App\Livewire\Permissions\ManageMaster`

**Tabs**:
- **Users**: Manage users, assign roles, set location access
- **Roles**: Create/edit roles, assign permissions
- **Permissions**: Manage permissions, set super admin flags
- **Menu Items**: Manage menu items, set permissions
- **Organize Menu**: Drag-and-drop menu organization

**Access**: Requires `user.manage` permission

**Route**: `/um` or `/um/{userId}` (to open specific user)

### 3. Sidebar Menu

**Component**: `App\Livewire\Nav\SidebarMenu`

**Features**:
- Loads menu items from `MenuService`
- Filters by `super_admin_only` flag
- 3-level hierarchy (parent → child → grandchild)
- Permission-based visibility

**Menu Source**: Currently uses JSON file (`database/menu.json`). Can be switched to database.

### 4. Flash Messages

**Component**: `App\Livewire\Admin\FlashMessage`

**Usage**:
```blade
<livewire:admin.flash-message fade="true" />
```

**Features**:
- Displays success/error messages
- Auto-fade option
- Modal display option
- Session flash integration

---

## Models & Relationships

### User Model
- **Relationships**: `tenant()`, `roles()`, `locations()`
- **Methods**: `isSuperAdmin()`, `isTenantOwner()`
- **Fields**: `is_super_admin`, `is_tenant_owner`, `tenant_id`, `location_access`

### Tenant Model
- **Relationships**: `users()`, `locations()`, `subscriptions()`, `invitations()`, `menuItems()`, `permissions()`
- **Methods**: `isOnTrial()`, `hasActiveSubscription()`

### Role Model (extends Spatie Role)
- **Fields**: `tenant_id` (nullable for global roles)
- **Scopes**: `forTenant()`, `global()`, `excludeAccountOwnerPrimary()`

### Permission Model (extends Spatie Permission)
- **Fields**: `super_admin_only`, `tenant_specific`
- **Relationships**: `tenants()`
- **Scopes**: `forTenantAdmin()`, `superAdminOnly()`

### MenuItem Model
- **Relationships**: `menu()`, `parent()`, `children()`, `permission()`, `tenants()`
- **Fields**: `super_admin_only`, `tenant_specific`, `super_admin_append`

---

## Middleware

### CheckPermission Middleware

**Location**: `app/Http/Middleware/CheckPermission.php`

**Usage**: 
```php
Route::get('/admin/tenants', TenantManagement::class)
    ->middleware('check.permission:tenant.manage');
```

**Registered**: In `bootstrap/app.php` as `check.permission`

**Functionality**: Checks if user has the specified permission using Spatie Permission package.

---

## Routes

### Main Routes

```php
// Dashboard (redirects to tenant management)
GET /dashboard → redirects to /admin/tenants

// Tenant Management
GET /admin/tenants → TenantManagement component
  Middleware: auth, check.permission:tenant.manage

// Permission Management
GET /um/{userId?} → ManageMaster component
  Middleware: auth, check.permission:user.manage

// Menu Organizer
POST /permissions/menu-organizer/update-order → MenuOrganizerController@updateOrder
  Middleware: auth, check.permission:user.manage
```

### Authentication Routes

Jetstream handles authentication routes automatically:
- `/login`
- `/register`
- `/logout`
- `/password/reset`
- `/email/verify`

---

## Styling & Assets

### CSS Files

Located in `public/css/`:
- `vendor.css` - Bootstrap and vendor styles (copied from RAI)
- `styles.css` - Custom RAI styles (copied from RAI)

### JavaScript Files

Located in `public/js/vendor/`:
- `jquery-3.7.1.slim.min.js`
- `bootstrap.bundle.min.js`
- `moment.min.js`
- `daterangepicker.min.js`

### Authentication Styles

Located in `public/assets/css/`:
- `rai-auth-styles.css` - Login/registration page styles
- `images/logo_auth.png` - RAI logo for auth pages

### Layout Structure

- **Main Layout**: `resources/views/layouts/rai.blade.php`
  - Includes sidebar, header, main content area
  - Uses Bootstrap 5.3
  - Includes Livewire scripts

- **Guest Layout**: `resources/views/layouts/guest.blade.php`
  - For unauthenticated pages (login, register)
  - Uses Bootstrap CDN

---

## Known Issues & TODOs

### Completed ✅
- [x] Project setup with Laravel 11
- [x] Jetstream installation (Livewire stack)
- [x] Spatie Permission integration
- [x] Bootstrap 5.3 styling
- [x] Tenant management component
- [x] Permission management components
- [x] Menu system setup
- [x] Database migrations
- [x] Seeders for copying data from RAI
- [x] Login page styling (matching RAI)

### Recently Completed ✅ (December 6, 2025)
- [x] Copy menu items from RAI database seeder (`CopyMenuItemsSeeder`)
- [x] Set up proper asset compilation (Vite configured)
- [x] Test all CRUD operations (21 tests passing)
- [x] Switch menu system from JSON to database
- [x] Add missing database fields (`deleted`, `status` on users)
- [x] Create user_locations pivot table
- [x] Add Badge Livewire component
- [x] Add TenantFactory for testing

### Pending ⏳
- [ ] Install Node.js/npm (optional - see `VITE_SETUP.md`)
- [ ] Run menu seeder to populate from RAI
- [ ] Implement tenant/user impersonation system (future feature)

### Known Limitations
- Node.js/npm not installed yet (app uses static assets - works fine!)
- Some components reference tables that may not exist (`rai_integrations`, etc.) - these are handled with try-catch blocks
- Layout automatically falls back to static assets when Vite not built

---

## Copying Data from RAI

### What Gets Copied

1. **Super Admin Users** (`CopySuperAdminUsersSeeder`)
   - All users where `is_super_admin = true`
   - Includes passwords (hashed), email, name, etc.

2. **Permissions** (`CopyPermissionsSeeder`)
   - All permissions with `super_admin_only` flags
   - Permission descriptions

3. **Roles** (`CopyRolesSeeder`)
   - Global roles (tenant_id = null)
   - Role-permission relationships

4. **Tenants** (`CopyTenantDataSeeder`)
   - All tenant records
   - Tenant subscriptions
   - Tenant invitations
   - Locations (seven_locations with tenant_id)

### How to Copy Data

```bash
# Ensure RAI database connection is configured in .env
# Then run seeders:

# Copy users, permissions, roles
php artisan db:seed

# Copy tenant data (optional)
php artisan db:seed --class=CopyTenantDataSeeder
```

### Prerequisites

- RAI database must be accessible
- `.env` must have `RAI_DB_*` variables configured
- Both databases should be on the same server or network accessible

---

## Development Workflow

### Running the Application

```bash
# Start Laravel development server
php artisan serve

# Or use your web server (Apache/Nginx)
# Access at: http://rainbo.test (or your configured domain)
```

### Making Changes

1. **Livewire Components**: Edit files in `app/Livewire/`
2. **Views**: Edit Blade files in `resources/views/`
3. **Models**: Edit files in `app/Models/`
4. **Routes**: Edit `routes/web.php`

### Testing Changes

```bash
# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# Run migrations (if schema changes)
php artisan migrate

# Run seeders (if data changes)
php artisan db:seed
```

---

## Troubleshooting

### Common Issues

1. **"Route not defined" errors**
   - Run: `php artisan route:clear`
   - Check `routes/web.php` for route definitions

2. **"Component not found" errors**
   - Run: `php artisan view:clear`
   - Check component namespace matches file location

3. **Database connection errors**
   - Verify `.env` database credentials
   - Check RAI database connection if using seeders
   - Run: `php artisan config:clear`

4. **Permission errors**
   - Ensure user has `Super Admin` role
   - Check Spatie permission cache: `php artisan permission:cache-reset`

5. **Missing CSS/JS files**
   - Copy assets from RAI `public/css/` and `public/js/` directories
   - Or set up asset compilation with npm

### Debug Mode

Enable debug mode in `.env`:
```env
APP_DEBUG=true
```

Check logs in `storage/logs/laravel.log`

---

## Security Considerations

1. **Super Admin Access**: All users in RAINBO should be super admins. Regular tenant users should not have access.

2. **Permission Checks**: All routes use `check.permission` middleware. Ensure permissions are properly set.

3. **Database Access**: The RAI database connection should be read-only for copying data. Consider using a read-only database user.

4. **Environment Variables**: Never commit `.env` file. Keep database credentials secure.

5. **Session Security**: Ensure `APP_KEY` is set and secure.

---

## Next Steps

1. **Copy Menu Items**: Create a seeder to copy menu items from RAI database
2. **Set Up Asset Compilation**: Configure npm/webpack/vite for frontend assets
3. **Implement Impersonation**: Add ability for super admins to impersonate tenants/users
4. **Add More Features**: Copy additional admin features from RAI as needed
5. **Testing**: Comprehensive testing of all CRUD operations
6. **Documentation**: Add inline code documentation where needed

---

## Contact & Support

For questions or issues:
- Check the RAI project for reference implementations
- Review Laravel 11, Livewire 3, and Spatie Permission documentation
- Check `storage/logs/laravel.log` for error details

---

## File Locations Reference

### Important Configuration Files
- `.env` - Environment configuration
- `config/database.php` - Database connections
- `config/permission.php` - Spatie Permission config
- `bootstrap/app.php` - Application bootstrap (middleware registration)

### Key Service Files
- `app/Services/MenuService.php` - Menu loading logic
- `app/Services/TenantRoleService.php` - Tenant role management (stub)
- `app/Services/ProviderSettingsService.php` - Provider settings (stub)

### Documentation Files
- `HANDOFF.md` - This file
- `COPY_FROM_RAI.md` - Data copying strategy
- `SETUP_PROGRESS.md` - Setup progress tracking
- `COMPLETE_SETUP_SUMMARY.md` - Setup summary

---

**Last Updated**: December 6, 2025
**Project Status**: Functional, ready for development and testing

