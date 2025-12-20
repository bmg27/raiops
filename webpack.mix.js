const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

/**
 * CSS
 */
mix.styles([
    "resources/css/vendor/bootstrap.min.css",
    "resources/css/vendor/bootstrap-icons.min.css",
    "resources/css/vendor/daterangepicker.css",
], "public/css/vendor.css").options({
    processCssUrls: false,
    cssNano: {
        mergeRules: false,
        mergeSemantically: false,
        reduceIdents: false,
    },
}).version();

mix.styles([
    "resources/css/stylesheets/fonts.css",
    "resources/css/stylesheets/tables.css",
    "resources/css/stylesheets/body.css",
    "resources/css/stylesheets/themes/rai.css",
    "resources/css/stylesheets/navigation.css",
], "public/css/styles.css").options({
    processCssUrls: false,
    cssNano: {
        mergeRules: false,
        mergeSemantically: false,
        reduceIdents: false,
    },
}).version();

/**
 * Fonts
 */
mix.copyDirectory("resources/fonts/BootstrapIcons/bootstrap-icons.woff", "public/fonts");
mix.copyDirectory("resources/fonts/BootstrapIcons/bootstrap-icons.woff2", "public/fonts");

