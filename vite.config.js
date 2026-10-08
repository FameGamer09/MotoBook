import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import fs from 'node:fs';

const pageStyles = fs.existsSync('resources/css/pages')
    ? fs.readdirSync('resources/css/pages')
        .filter((file) => file.endsWith('.css'))
        .map((file) => `resources/css/pages/${file}`)
    : [];

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                ...pageStyles,
                'resources/js/customer-order-tracking.js',
                'resources/js/rider-location.js',
            ],
            refresh: true,
        }),
    ],
});
