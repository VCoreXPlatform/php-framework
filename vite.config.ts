import type { UserConfig } from 'vite';

export default {
  root: 'resources/frontend',
  base: '/assets/',
  build: { outDir: '../../public/assets', emptyOutDir: true, assetsDir: 'build' },
  server: { host: "127.0.0.1", port: 15173, strictPort: true, proxy: { '/api': 'http://127.0.0.1:18080' } },
} satisfies UserConfig;
