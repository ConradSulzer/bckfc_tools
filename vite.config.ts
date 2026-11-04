import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import prefixSelector from 'postcss-prefix-selector';
import path from 'node:path';
export default defineConfig({
  plugins: [react(), tailwindcss()],
  define: { 'process.env.NODE_ENV': JSON.stringify('production') },
  resolve: { alias: { '@': path.resolve(import.meta.dirname, 'src') } },
  css: {
    postcss: {
      plugins: [
        {
          postcssPlugin: 'flatten-plugin-layers',
          OnceExit(root) {
            root.walkAtRules('layer', (rule) => {
              if (rule.nodes) rule.replaceWith(rule.nodes);
              else rule.remove();
            });
          },
        },
        prefixSelector({
          prefix: '#bckfc-tools-root',
          transform(prefix, selector, prefixed) {
            if (
              selector === ':root' ||
              selector === ':host' ||
              selector === 'html' ||
              selector === 'body'
            )
              return prefix;
            return prefixed;
          },
        }),
      ],
    },
  },
  build: {
    outDir: 'build',
    lib: {
      entry: 'src/main.tsx',
      name: 'BckfcTools',
      formats: ['iife'],
      fileName: () => 'admin.js',
      cssFileName: 'admin',
    },
  },
});
