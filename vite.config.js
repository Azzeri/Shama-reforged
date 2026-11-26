import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/passkeys.js',
            ],
            refresh: true,
            fonts: [
                // Self-hosted (instead of the Google Fonts stylesheet) so they are
                // served from our origin, cached by the service worker, and get
                // metric-matched fallbacks — no layout jump when they swap in.
                // No preload: the API ignores `subsets` and the plugin would
                // preload every unicode-range file (cyrillic, greek, ...);
                // unicode-range alone fetches only what the page needs.
                google('Fraunces', {
                    variable: '--font-fraunces-family',
                    weights: [500, 600, 700],
                    styles: ['normal', 'italic'],
                    subsets: ['latin', 'latin-ext'],
                    preload: false,
                }),
                google('Inter', {
                    variable: '--font-inter-family',
                    weights: [400, 500, 600, 700],
                    subsets: ['latin', 'latin-ext'],
                    preload: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
