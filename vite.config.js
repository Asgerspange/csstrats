import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'path';
import viteCompression from 'vite-plugin-compression';
import tailwindcss from '@tailwindcss/vite'

import dotenv from 'dotenv';
dotenv.config();

export default defineConfig({
    plugins: [laravel({
        input: ['resources/js/app.js', 'resources/js/welcome.js', 'resources/js/invite.js', 'resources/js/partner.js'],
        refresh: true,
    }), vue({
        template: {
            transformAssetUrls: {
                base: null,
                includeAbsolute: false
            }
        }
    }), tailwindcss(), viteCompression({
        algorithm: 'gzip',
        // Add CSS files to compression
        filter: /\.(js|json|html|css)$/i,
        threshold: 1024, // Compress files larger than 1KB (was 10KB)
        deleteOriginFile: false,
    }), // Add Brotli compression for even better compression
    viteCompression({
        algorithm: 'brotliCompress',
        ext: '.br',
        filter: /\.(js|json|html|css)$/i,
        threshold: 1024,
        deleteOriginFile: false,
    })],
    resolve: {
        alias: {
            "@": resolve(__dirname, "resources/js"),
            "ziggy-js": resolve(__dirname, "vendor/tightenco/ziggy/dist"),
        },
    },
    build: {
        sourcemap: true,
        // Increase chunk size limit to reduce number of chunks
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                manualChunks: {
                    // More granular chunking
                    vue: ['vue'],
                    axios: ['axios'],
                    // Add other large dependencies
                    primevue: ['primevue'],
                }
            }
        },
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
                // More aggressive compression
                passes: 2,
                pure_funcs: ['console.log', 'console.info', 'console.debug', 'console.warn'],
            },
            mangle: {
                safari10: true,
            },
        },
        // Enable CSS code splitting
        cssCodeSplit: true,
        // Optimize asset inlining
        assetsInlineLimit: 4096, // Inline assets smaller than 4KB
    },
    define: {
        'process.env': JSON.stringify(process.env)
    },
    css: {
        preprocessorOptions: {
            scss: {
                additionalData: `@import "@/../scss/variables.scss";`,
            },
        },
        // Enable CSS minification
        devSourcemap: false,
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors: true,
        allowedHosts: ['csstrats-asger-p5173.stoldt.dk', '.stoldt.dk'],
        hmr: {
            protocol: 'wss',
            host: 'csstrats-asger-p5173.stoldt.dk',
            clientPort: 443,
        },
        watch: {
            ignored: ['**/storage/**', '**/vendor/**', '**/app/**'],
        },
    },
    // Optimize dependencies
    optimizeDeps: {
        include: ['vue', 'axios'],
    }
});