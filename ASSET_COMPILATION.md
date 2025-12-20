# Asset Compilation Guide

## Overview

RAINBO uses Vite for asset compilation. The project has been configured to compile Bootstrap-based CSS and JavaScript files.

## Quick Start

### Prerequisites

Make sure Node.js and npm are installed on your system:

```bash
# Check if Node.js is installed
node --version

# Check if npm is installed
npm --version

# If not installed, install Node.js (includes npm):
# Ubuntu/Debian:
sudo apt update
sudo apt install nodejs npm

# Or use nvm (recommended):
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
nvm install --lts
```

### Install Dependencies

```bash
npm install
```

### Development Mode

Run Vite in development mode with hot module replacement:

```bash
npm run dev
```

This will start the Vite dev server on `http://localhost:5173` (or another port if 5173 is busy).

### Build for Production

Compile and minify assets for production:

```bash
npm run build
```

Or use the explicit production command:

```bash
npm run prod
```

### Watch Mode

Build assets and watch for changes (without hot reload):

```bash
npm run watch
```

## Asset Structure

### CSS Files

The project uses two main CSS entry points:

1. **`resources/css/vendor.css`** - Third-party libraries
   - Bootstrap 5.3
   - Bootstrap Icons
   - Daterangepicker

2. **`resources/css/styles.css`** - Custom RAI styles
   - Fonts
   - Body styles
   - Navigation
   - Tables
   - RAI Chat
   - Themes (RAI, Slate)

### JavaScript Files

- **`resources/js/app.js`** - Main JavaScript entry point
  - Imports `bootstrap.js` which sets up Axios

### Vendor JavaScript (Not Compiled by Vite)

These are loaded directly from `public/js/vendor/`:
- jQuery 3.7.1
- Bootstrap Bundle
- Moment.js
- Daterangepicker

## Vite Configuration

The `vite.config.js` is configured to:
- Compile `vendor.css`, `styles.css`, and `app.js`
- Organize built assets by type (images, fonts, etc.)
- Enable hot module replacement in development
- Support Laravel's Vite plugin

## Using Compiled Assets in Blade Templates

### Main Layout (`resources/views/layouts/rai.blade.php`)

```blade
<!-- CSS -->
@vite(['resources/css/vendor.css', 'resources/css/styles.css'])

<!-- JS -->
@vite(['resources/js/app.js'])
```

### Development vs Production

- **Development**: Vite serves assets from the dev server with HMR
- **Production**: Vite builds optimized, versioned assets to `public/build/`

## Fallback to Static Assets

If you don't want to run Vite during development, you can temporarily switch back to static assets:

```blade
<!-- Replace @vite directives with: -->
<link href="{{ asset('css/vendor.css') }}" rel="stylesheet">
<link href="{{ asset('css/styles.css') }}" rel="stylesheet">
```

The pre-compiled versions still exist in `public/css/` as a fallback.

## Troubleshooting

### "Vite manifest not found"

Run `npm run build` to generate the manifest file.

### Port Already in Use

Vite will automatically try the next available port. Check the terminal output for the actual dev server URL.

### CSS Not Loading

1. Make sure Vite dev server is running (`npm run dev`)
2. Check that `APP_URL` in `.env` matches your local domain
3. Clear Laravel caches: `php artisan cache:clear`

### Changes Not Appearing

1. Hard refresh your browser (Ctrl+Shift+R or Cmd+Shift+R)
2. Clear browser cache
3. Restart Vite dev server

## Adding New Assets

### Adding CSS

Add imports to `resources/css/styles.css` or `resources/css/vendor.css`:

```css
@import './stylesheets/your-new-file.css';
```

### Adding JavaScript

Add imports to `resources/js/app.js`:

```javascript
import './your-new-file.js';
```

## Notes

- The project uses Bootstrap 5.3, not Tailwind CSS (though Tailwind is installed for Jetstream compatibility)
- jQuery and Bootstrap JS are loaded separately as vendor files (not through Vite)
- Theme switching is handled via inline JavaScript in the layout file
- Livewire styles and scripts are loaded via `@livewireStyles` and `@livewireScripts` directives

---

**Last Updated**: December 6, 2025

