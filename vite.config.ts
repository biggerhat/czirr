import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: false,
            buildBase: '/',
            manifest: {
                name: 'Czirr Family',
                short_name: 'Czirr',
                description: 'Family management app',
                theme_color: '#ffffff',
                background_color: '#ffffff',
                display: 'standalone',
                icons: [
                    {
                        src: '/pwa-64x64.png',
                        sizes: '64x64',
                        type: 'image/png',
                    },
                    {
                        src: '/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                    {
                        src: '/maskable-icon-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                navigateFallback: '/offline',
                navigateFallbackDenylist: [/^\/api/, /^\/sanctum/, /^\/_ignition/, /^\/telescope/],
                runtimeCaching: [
                    {
                        urlPattern: ({ request }) => request.mode === 'navigate',
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'pages',
                            networkTimeoutSeconds: 3,
                            expiration: {
                                maxAgeSeconds: 24 * 60 * 60,
                            },
                        },
                    },
                    {
                        urlPattern: ({ request }) =>
                            request.headers.get('X-Inertia') === 'true',
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'inertia-responses',
                            networkTimeoutSeconds: 3,
                            expiration: {
                                maxAgeSeconds: 60 * 60,
                            },
                        },
                    },
                    {
                        urlPattern: /^https:\/\/fonts\.bunny\.net\/css/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'bunny-font-stylesheets',
                            expiration: {
                                maxAgeSeconds: 365 * 24 * 60 * 60,
                            },
                        },
                    },
                    {
                        urlPattern: /^https:\/\/fonts\.bunny\.net\/.*\.(woff2?|ttf|otf|eot)$/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'bunny-font-files',
                            expiration: {
                                maxAgeSeconds: 365 * 24 * 60 * 60,
                                maxEntries: 30,
                            },
                        },
                    },
                    {
                        urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp|ico)$/,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'images',
                            expiration: {
                                maxAgeSeconds: 30 * 24 * 60 * 60,
                                maxEntries: 100,
                            },
                        },
                    },
                ],
            },
        }),
    ],
});
