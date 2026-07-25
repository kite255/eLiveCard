import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css',
            ],

            refresh: [
                'app/Filament/**',
                'app/Livewire/**',
                'resources/views/**',
                'routes/**',
            ],
        }),
    ],

    server: {
        watch: {
            ignored: [
                '**/storage/framework/views/**',
            ],
        },
    },
});