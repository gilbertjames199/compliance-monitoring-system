import { defineConfig } from 'vite'
import path from 'path'

export default defineConfig({
  build: {
    outDir: 'resources/dist',
    emptyOutDir: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'resources/js/plugin.js'),
      // input: {
      //   app: path.resolve(__dirname, 'resources/css/plugin.css'),
      // },
      output: {
        // assetFileNames: `plugin.css`
        entryFileNames: `plugin.js`,
        assetFileNames: `plugin.css`,
      }
    }
  }
})
