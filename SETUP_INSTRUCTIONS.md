# RAINBO Setup Instructions

## 🚀 Quick Start

### 1. Database Configuration

Add these to your `.env` file:

```env
# RAINBO Database (main database)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rainbo
DB_USERNAME=your_username
DB_PASSWORD=your_password

# RAI Database (for copying super admin users)
RAI_DB_HOST=127.0.0.1
RAI_DB_PORT=3306
RAI_DB_DATABASE=rai
RAI_DB_USERNAME=your_username
RAI_DB_PASSWORD=your_password
```

### 2. Run Migrations

```bash
cd /var/www/html/rainbo
php artisan migrate
```

### 3. Seed Database

This will:
- Create Super Admin role and `tenant.manage` permission
- Copy super admin users from RAI database

```bash
php artisan db:seed
```

Or run seeders individually:

```bash
# Create role and permission
php artisan db:seed --class=CreateSuperAdminRoleAndPermissionSeeder

# Copy super admin users from RAI
php artisan db:seed --class=CopySuperAdminUsersSeeder
```

### 4. Install Frontend Dependencies

```bash
npm install
```

### 5. Compile Assets

```bash
npm run dev
# or for production
npm run build
```

### 6. Copy Vendor Assets

Copy these files from RAI project to RAINBO:

```bash
# Bootstrap CSS
cp /var/www/html/rai/public/css/vendor/bootstrap.min.css /var/www/html/rainbo/public/css/vendor/
cp /var/www/html/rai/public/css/vendor/bootstrap-icons.min.css /var/www/html/rainbo/public/css/vendor/
cp /var/www/html/rai/public/css/vendor/daterangepicker.css /var/www/html/rainbo/public/css/vendor/

# JavaScript
cp /var/www/html/rai/public/js/vendor/*.js /var/www/html/rainbo/public/js/vendor/

# Fonts
cp -r /var/www/html/rai/public/fonts/* /var/www/html/rainbo/public/fonts/
```

### 7. Set Up Storage Link

```bash
php artisan storage:link
```

### 8. Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

## 📋 Menu System

The menu system loads from `super_admin_menu_structure.json` which contains all 16 super admin menu items extracted from the RAI database.

Menu structure:
- **Admin** (with sub-groups: Sales, Tagging)
  - Sales → Prospects
  - Change Log Manager
  - Tagging → GPT Tagger, Menu Item Tagger, Role Tagger, Vendor Item Tagger
  - Bugs/Feature Reqs
  - Dashboard Builder
  - Global (Shift Notes)
- **Sandbox**
  - Time Entry Search
  - Menu/Perm Sync
  - Schedule Runner
  - Dashboard Manager
- **Tenants**
  - Manage ⭐ (First screen)

## 🔐 Authentication

Super admin users copied from RAI will have:
- `is_super_admin = true`
- `Super Admin` role assigned
- `tenant.manage` permission

You can log in with any of the copied super admin user credentials.

## 🎨 Styling

The project uses:
- Bootstrap 5.3
- Bootstrap Icons
- Custom RAI theme (light/dark mode support)
- Laravel Mix for asset compilation

## 📁 Key Files

- **Menu Service**: `app/Services/MenuService.php`
- **Sidebar Menu**: `app/Livewire/Nav/SidebarMenu.php`
- **Layout**: `resources/views/layouts/rai.blade.php`
- **Tenant Management**: `app/Livewire/Admin/TenantManagement.php`

## 🐛 Troubleshooting

### Menu not showing?
- Check that `super_admin_menu_structure.json` exists in project root
- Clear view cache: `php artisan view:clear`

### Can't connect to RAI database?
- Verify RAI database credentials in `.env`
- Check that RAI database connection is configured in `config/database.php`

### Assets not loading?
- Run `npm run dev` to compile assets
- Check that vendor CSS/JS files are in `public/css/vendor/` and `public/js/vendor/`
- Clear cache: `php artisan config:clear`

### Permission denied errors?
- Make sure user has `Super Admin` role
- Check that `tenant.manage` permission exists and is assigned to Super Admin role

## 🎯 Next Steps

1. Test tenant management screen
2. Build out remaining super admin screens
3. Implement tenant/user impersonation
4. Add logo image
5. Set up email templates

