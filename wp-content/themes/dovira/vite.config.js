// View your website at your own local server
// for example http://dovira.test

// http://localhost:3000 is serving Vite on development
// but accessing it directly will be empty

import { defineConfig, loadEnv, normalizePath } from 'vite'
import liveReload from 'vite-plugin-live-reload'
import { viteStaticCopy } from 'vite-plugin-static-copy'
import { basename, join } from "path";
import postcssPresetEnv from "postcss-preset-env";

const themeDirName = basename(__dirname);
const staticAssets = ['images', 'fonts'];

// https://vitejs.dev/config
export default ({ mode }) => {
  process.env = { ...process.env, ...loadEnv(mode, process.cwd()) };

  return defineConfig({
    plugins: [
      liveReload(__dirname + '/**/*.php'),
      viteStaticCopy({
        targets: staticAssets.map(asset => ({
          src: normalizePath(join(__dirname, `./source/${asset}/*`)),
          dest: asset,
        })),
      }),
    ],

    root: '',
    base: process.env.NODE_ENV === 'development' ? '/' : `/wp-content/themes/${themeDirName}/assets/`,
    build: {
      outDir: normalizePath(join(__dirname, './assets')),
      emptyOutDir: true,
      manifest: true,
      target: 'esnext',
      rollupOptions: {
        input: {
          main: normalizePath(join(__dirname, process.env.VITE_ENTRY_POINT ? `.${process.env.VITE_ENTRY_POINT}` : './source/main.js')),
          admin: normalizePath(join(__dirname, './source/admin.js')),
        },
        output: {
          assetFileNames: '[name].[hash][extname]',
          entryFileNames: '[name].[hash].js',
          chunkFileNames: '_chunk.[hash].js',
          dir: normalizePath(join(__dirname, './assets')),
        },
      },
    },

    server: {
      cors: true,
      strictPort: true,
      port: process.env.VITE_SERVER_PORT ? process.env.VITE_SERVER_PORT : 3000,
    },

    resolve: {
      alias: {
        '@': normalizePath(join(__dirname, 'node_modules')),
        '@styles': normalizePath(join(__dirname, './source/styles')),
        '@scripts': normalizePath(join(__dirname, './source/scripts')),
      }
    }
  })
}
