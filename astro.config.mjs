import { defineConfig } from 'astro/config';
import react from '@astrojs/react';
import tailwindcss from '@tailwindcss/vite';
import sitemap from '@astrojs/sitemap';

export default defineConfig({
  site: 'https://hielosdelsur.cl',
  // Muestra: SITE_BASE=/muestra (lo define el workflow). Go-live en la raiz: quitar SITE_BASE del workflow.
  base: process.env.SITE_BASE || undefined,
  output: 'static',
  integrations: [
    react(),
    sitemap(),
  ],
  vite: {
    plugins: [tailwindcss()],
  },
});
