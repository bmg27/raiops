# Copying Data from RAI Production Database

## Overview

The RAIOPS back office needs to copy certain data from the RAI production database (`linkrg_prod_test`). This includes:

1. **Permissions** - All permissions (filtered by `super_admin_only` where applicable)
2. **Roles** - Global roles (especially Super Admin)
3. **Menu Items** - Only super admin menu items (already extracted to JSON)
4. **Users** - Super admin users (with `is_super_admin = 1`)

## Database Configuration

Make sure your `.env` file has the correct RAI database connection:

```env
RAI_DB_HOST=127.0.0.1
RAI_DB_DATABASE=linkrg_prod_test
RAI_DB_USERNAME=your_username
RAI_DB_PASSWORD=your_password
RAI_DB_PORT=3306
```

## Running the Seeders

### Option 1: Run All Seeders (Recommended)

```bash
php artisan db:seed
```

This will run in order:
1. CopyPermissionsSeeder - Copies all permissions
2. CopyRolesSeeder - Copies global roles and their permissions
3. CopySuperAdminUsersSeeder - Copies super admin users

### Option 2: Run Individual Seeders

```bash
# Copy permissions
php artisan db:seed --class=CopyPermissionsSeeder

# Copy roles (and their permission relationships)
php artisan db:seed --class=CopyRolesSeeder

# Copy super admin users
php artisan db:seed --class=CopySuperAdminUsersSeeder
```

## What Gets Copied

### Permissions
- **All permissions** from RAI database
- Includes `super_admin_only` flag
- Includes `description` field

### Roles
- **Only global roles** (where `tenant_id IS NULL`)
- Includes Super Admin role
- **Role-permission relationships** are preserved

### Users
- **Only users with `is_super_admin = 1`**
- Must have `status = 'Active'`
- Must have `deleted = 0`
- Passwords are copied (hashed)
- Users are assigned Super Admin role automatically

## Notes

- Existing data is **not deleted** - seeders will skip or update existing records
- Menu items are **not copied** - they're loaded from `super_admin_menu_structure.json`
- Tenant-specific roles are **not copied** - only global roles
- The seeder will show what was copied and what was skipped

## Troubleshooting

### "Column not found" errors
- Make sure you're connecting to the correct database (`linkrg_prod_test`)
- Verify the database connection in `.env`

### "Permission denied" errors
- Check database user has SELECT permissions on RAI database
- Check database user has INSERT/UPDATE permissions on RAIOPS database

### Users not copying
- Verify users have `is_super_admin = 1` in RAI database
- Check users have `status = 'Active'` and `deleted = 0`

