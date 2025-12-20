#!/bin/bash
# Build static assets for RAINBO
# This script compiles CSS files until Vite/npm is set up

echo "🎸 Building RAINBO static assets..."

# Build vendor.css (Bootstrap + Bootstrap Icons + Daterangepicker)
echo "📦 Compiling vendor.css..."
cat resources/css/vendor/bootstrap.min.css \
    resources/css/vendor/bootstrap-icons.min.css \
    resources/css/vendor/daterangepicker.css \
    > public/css/vendor.css

# Build styles.css (Custom RAI styles)
echo "🎨 Compiling styles.css..."
cat resources/css/stylesheets/fonts.css \
    resources/css/stylesheets/body.css \
    resources/css/stylesheets/navigation.css \
    resources/css/stylesheets/tables.css \
    resources/css/stylesheets/rai-chat.css \
    resources/css/stylesheets/themes/theme-rai.css \
    resources/css/stylesheets/themes/theme-slate.css \
    > public/css/styles.css

# Copy Bootstrap Icons fonts to public directory
echo "🔤 Copying Bootstrap Icons fonts..."
mkdir -p public/fonts
cp resources/fonts/BootstrapIcons/* public/fonts/

# Clear Laravel caches
echo "🧹 Clearing caches..."
php artisan view:clear
php artisan cache:clear

echo "✅ Build complete!"
echo ""
echo "📊 Asset sizes:"
ls -lh public/css/vendor.css public/css/styles.css
echo ""
echo "🎵 Ready to rock!"

