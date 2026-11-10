import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";

/**
 * Two entry points, built into ../js/dist/ (committed, so that "uda" works
 * without Node):
 * - ars.js:  views inside ILIAS (editor, presenter, results, join info)
 * - join.js: page for the participants (join.php)
 * One stylesheet ars.css for both.
 */
export default defineConfig({
  plugins: [vue()],
  base: "./",
  define: {
    __VUE_OPTIONS_API__: "false",
    __VUE_PROD_DEVTOOLS__: "false",
    __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: "false",
  },
  build: {
    outDir: "../js/dist",
    emptyOutDir: true,
    cssCodeSplit: false,
    target: "es2020",
    sourcemap: false,
    rollupOptions: {
      input: {
        ars: "src/ars.ts",
        join: "src/join.ts",
      },
      output: {
        entryFileNames: "[name].js",
        chunkFileNames: "chunks/[name]-[hash].js",
        assetFileNames: (info) => (info.names?.some((n) => n.endsWith(".css")) ? "ars.css" : "assets/[name]-[hash][extname]"),
      },
    },
  },
});
