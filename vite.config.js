import { defineConfig } from 'vite';
import { resolve } from 'path';

/**
 * Converts a kebab-case string to camelCase.
 * e.g., 'error-reporter' -> 'errorReporter'
 * @param {string} str The string to convert.
 * @returns {string}
 */
const toCamelCase = (str) => str.replace(/-([a-z])/g, g => g[1].toUpperCase());

export default defineConfig({
    base: '/vendor/g-serverless-support/',
    build: {
        outDir: 'src/resources/js/dist',

        manifest: true,
        sourcemap: true,

        rollupOptions: {
            input: {
                'error-reporter': resolve(__dirname, 'src/resources/js/error-reporter.js'),
                // 'another-module': resolve(__dirname, 'src/resources/js/another-module.js'),
            },
            output: {
                entryFileNames: `[name].[hash].js`,
                chunkFileNames: `[name].[hash].js`,
                assetFileNames: `[name].[hash].[ext]`,
                
                format: 'umd',

                name: (chunkInfo) => toCamelCase(chunkInfo.name),
            },
        },
    },
});