# Vite Setup Guide - RAINBO

## Current Status

✅ **Application is working with static assets!**

The layout now intelligently falls back to static CSS/JS files when Vite hasn't been built yet.

## How It Works

The layout (`resources/views/layouts/rai.blade.php`) checks for the Vite manifest:

```blade
@if(app()->environment('testing') || !file_exists(public_path('build/manifest.json')))
    <!-- Use static assets from public/css/ -->
    <link href="{{ asset('css/vendor.css') }}" rel="stylesheet">
    <link href="{{ asset('css/styles.css') }}" rel="stylesheet">
@else
    <!-- Use Vite-compiled assets -->
    @vite(['resources/css/vendor.css', 'resources/css/styles.css'])
@endif
```

### Asset Loading Strategy

1. **No Vite Build**: Uses static assets from `public/css/` and `public/js/`
2. **Vite Built**: Uses optimized, versioned assets from `public/build/`
3. **Testing**: Always uses static assets (no Vite dependency)

## Installing Node.js & npm

When you're ready to use Vite for optimized asset compilation:

### Option 1: Using Package Manager (Ubuntu/Debian)

```bash
# Update package list
sudo apt update

# Install Node.js and npm
sudo apt install -y nodejs npm

# Verify installation
node --version
npm --version
```

### Option 2: Using NVM (Recommended)

NVM allows you to manage multiple Node.js versions:

```bash
# Install NVM
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash

# Reload your shell
source ~/.bashrc

# Install latest LTS version
nvm install --lts

# Verify installation
node --version
npm --version
```

### Option 3: Using NodeSource Repository

For the latest version:

```bash
# Download and run the setup script for Node.js 20.x
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -

# Install Node.js
sudo apt-get install -y nodejs

# Verify installation
node --version
npm --version
```

## Building Assets with Vite

Once Node.js and npm are installed:

### 1. Install Dependencies

```bash
cd /var/www/html/rainbo
npm install
```

This will install all the packages listed in `package.json`:
- Vite
- Laravel Vite Plugin
- Axios
- Tailwind CSS (for Jetstream compatibility)
- And other dev dependencies

### 2. Build for Development

```bash
# Run Vite dev server with hot module replacement
npm run dev
```

This starts a dev server (usually on `http://localhost:5173`) that provides:
- Hot Module Replacement (HMR)
- Fast rebuilds on file changes
- Source maps for debugging

**Note**: Keep this running in a separate terminal while developing.

### 3. Build for Production

```bash
# Build and optimize assets for production
npm run build
```

This creates:
- Minified CSS and JS files
- Versioned filenames for cache busting
- Optimized bundle sizes
- Manifest file for asset resolution

Output goes to: `public/build/`

### 4. Watch Mode (Alternative to Dev Server)

```bash
# Build and watch for changes (without HMR)
npm run watch
```

## Checking If Vite Is Active

```bash
# Check if manifest exists
ls -la /var/www/html/rainbo/public/build/manifest.json

# If it exists, Vite assets are built and will be used
# If it doesn't exist, static assets will be used
```

## Switching Between Static and Vite Assets

### Use Static Assets (Current)
Just make sure `public/build/manifest.json` doesn't exist:

```bash
# Remove Vite build (if needed)
rm -rf public/build/
```

The app will automatically use static assets from `public/css/` and `public/js/`.

### Use Vite Assets
Run the build command:

```bash
npm run build
```

The app will automatically detect the manifest and use Vite assets.

## Troubleshooting

### "npm: command not found"

Node.js/npm is not installed. See installation instructions above.

### "Unable to locate file in Vite manifest"

This error occurred before we added the fallback logic. If you still see it:

1. Clear caches:
   ```bash
   php artisan view:clear
   php artisan config:clear
   ```

2. Make sure the layout has the fallback logic (should already be there)

3. Refresh your browser

### "EACCES: permission denied" during npm install

Fix npm permissions:

```bash
# Option 1: Fix permissions for global packages
sudo chown -R $USER:$(id -gn $USER) ~/.npm
sudo chown -R $USER:$(id -gn $USER) ~/.config

# Option 2: Use a different global directory
mkdir ~/.npm-global
npm config set prefix '~/.npm-global'
echo 'export PATH=~/.npm-global/bin:$PATH' >> ~/.bashrc
source ~/.bashrc
```

### Vite build fails with "ENOENT: no such file or directory"

Make sure all source files exist:

```bash
# Check if source files exist
ls -la resources/css/vendor.css
ls -la resources/css/styles.css
ls -la resources/js/app.js
```

If they don't exist, they should have been created. Check the `ASSET_COMPILATION.md` document.

## Static Assets (Current Setup)

While Vite isn't built, the app uses these static files:

### CSS Files (`public/css/`)
- `vendor.css` - Bootstrap, Bootstrap Icons, Daterangepicker
- `styles.css` - Custom RAI styles, themes, navigation

### JavaScript Files (`public/js/vendor/`)
- `jquery-3.7.1.slim.min.js`
- `bootstrap.bundle.min.js`
- `moment.min.js`
- `daterangepicker.min.js`

These are pre-compiled and work perfectly fine for development and production!

## Benefits of Using Vite

Once you build with Vite, you'll get:

1. **Better Performance**
   - Minified CSS/JS
   - Code splitting
   - Tree shaking (removes unused code)

2. **Better Caching**
   - Versioned filenames (e.g., `app.abc123.js`)
   - Browser cache invalidation on updates

3. **Development Features**
   - Hot Module Replacement
   - Fast rebuilds
   - Source maps

4. **Production Optimizations**
   - Asset compression
   - Automatic vendor chunking
   - CSS purging (if configured)

## Recommendation

For now, keep using static assets! They work great. Consider switching to Vite when:

1. You want faster development with HMR
2. You're ready to deploy to production
3. You want better asset optimization
4. You need to modify CSS/JS source files

The app will work perfectly either way! 🎸

---

**Last Updated**: December 6, 2025
**Status**: Working with static assets, Vite optional ✅

