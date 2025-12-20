import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/vendor.css',
                'resources/css/styles.css',
                'resources/js/app.js'
            ],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    let extType = assetInfo.name.split('.').pop();
                    if (/png|jpe?g|svg|gif|tiff|bmp|ico/i.test(extType)) {
                        return `images/[name]-[hash][extname]`;
                    }
                    if (/woff|woff2|eot|ttf|otf/i.test(extType)) {
                        return `fonts/[name]-[hash][extname]`;
                    }
                    return `[ext]/[name]-[hash][extname]`;
                },
            },
        },
    },
});
