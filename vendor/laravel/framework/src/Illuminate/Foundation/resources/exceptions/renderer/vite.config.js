import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { createRequire } from 'module';

const require = createRequire(import.meta.url);

export default defineConfig({
    plugins: [tailwindcss()],
    build: {
        rollupOptions: {
            input: ['styles.css', 'scripts.js'],
            output: {
                assetFileNames: '[name][extname]',
                entryFileNames: '[name].js',
            },
        },
    },
});                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           