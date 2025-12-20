# Bootstrap Icons Setup - RAINBO

## Status: ✅ Working!

Bootstrap Icons are now properly configured and working in RAINBO.

## What Was Fixed

### 1. Font Files Copied to Public Directory
The Bootstrap Icons font files were in `resources/fonts/BootstrapIcons/` but needed to be in `public/fonts/` to be accessible by the browser.

**Files copied:**
- `bootstrap-icons.woff2`
- `bootstrap-icons.woff`

**Location:** `/var/www/html/rainbo/public/fonts/`

### 2. CSS Files Compiled
Since we're not using Vite/npm yet, the CSS `@import` statements don't work in the browser. We compiled all vendor CSS files into a single file.

**Compiled files:**
- `public/css/vendor.css` - Contains Bootstrap + Bootstrap Icons + Daterangepicker
- `public/css/styles.css` - Contains custom RAI styles

### 3. Build Script Created
Created `build-assets.sh` to easily rebuild assets when source files change.

## Using Bootstrap Icons

Bootstrap Icons work with simple class names:

```html
<!-- Basic icon -->
<i class="bi bi-house"></i>

<!-- Icon with size -->
<i class="bi bi-gear fs-4"></i>

<!-- Icon with color -->
<i class="bi bi-check-circle text-success"></i>

<!-- Icon in button -->
<button class="btn btn-primary">
    <i class="bi bi-save me-2"></i>
    Save
</button>
```

## Available Icons

Bootstrap Icons includes 2000+ icons. Common ones used in RAINBO:

- `bi-house` - Home
- `bi-gear` - Settings/Admin
- `bi-people` - Users
- `bi-building` - Tenants/Buildings
- `bi-box` - Sandbox
- `bi-list` - Menu
- `bi-check-circle` - Success/Check
- `bi-x-circle` - Error/Close
- `bi-pencil` - Edit
- `bi-trash` - Delete
- `bi-plus` - Add
- `bi-search` - Search
- `bi-arrow-left` - Back
- `bi-arrow-right` - Forward

Full list: https://icons.getbootstrap.com/

## Rebuilding Assets

If you modify any CSS source files in `resources/css/`, rebuild the compiled files:

```bash
# Run the build script
./build-assets.sh

# Or manually:
cat resources/css/vendor/bootstrap.min.css \
    resources/css/vendor/bootstrap-icons.min.css \
    resources/css/vendor/daterangepicker.css \
    > public/css/vendor.css
```

## File Structure

```
rainbo/
├── resources/
│   ├── css/
│   │   └── vendor/
│   │       └── bootstrap-icons.min.css (source)
│   └── fonts/
│       └── BootstrapIcons/
│           ├── bootstrap-icons.woff2
│           └── bootstrap-icons.woff
├── public/
│   ├── css/
│   │   └── vendor.css (compiled - includes Bootstrap Icons CSS)
│   └── fonts/
│       ├── bootstrap-icons.woff2 (copied from resources)
│       └── bootstrap-icons.woff (copied from resources)
└── build-assets.sh (build script)
```

## Troubleshooting

### Icons Not Showing (Display as Squares)

1. **Check font files exist:**
   ```bash
   ls -la public/fonts/bootstrap-icons.*
   ```

2. **Check CSS is loaded:**
   - View page source
   - Look for `<link href="/css/vendor.css">`
   - Check browser dev tools Network tab

3. **Check font paths in CSS:**
   ```bash
   grep "bootstrap-icons" public/css/vendor.css
   ```
   Should reference `/fonts/bootstrap-icons.woff2`

4. **Rebuild assets:**
   ```bash
   ./build-assets.sh
   ```

5. **Clear browser cache:**
   - Hard refresh: Ctrl+Shift+R (Windows/Linux) or Cmd+Shift+R (Mac)

### Icons Missing After Update

If you update Bootstrap Icons source files:

```bash
# Rebuild compiled CSS
./build-assets.sh

# Or manually copy fonts
cp resources/fonts/BootstrapIcons/* public/fonts/
```

## When Vite/npm Is Installed

Once you install Node.js/npm and build with Vite, the font files will be automatically handled by Vite. The current manual setup will no longer be needed.

Vite will:
- Process `@import` statements
- Copy font files to `public/build/assets/`
- Update CSS paths automatically
- Version files for cache busting

Until then, use the `build-assets.sh` script! 🎸

---

**Last Updated**: December 6, 2025
**Status**: Working with static assets ✅

