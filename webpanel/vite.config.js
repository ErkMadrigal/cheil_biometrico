import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
  // OJO: "base" SI afecta al dev server, no solo al build (a diferencia de lo que
  // dice un comentario viejo por ahi). Por eso va condicionado a "command": solo en
  // `npm run build` usamos /panel/ (porque el compilado va a vivir en htdocs/panel/,
  // no en la raiz del dominio) -- en `npm run dev` se queda en "/" como siempre, para
  // no romper el tunel de Cloudflare (panel.centinal.org -> localhost:5173 raiz).
  base: command === 'build' ? '/panel/' : '/',
  plugins: [vue(), tailwindcss()],
  server: {
    port: 5173,
    // host:true = escucha en 0.0.0.0, asi cualquiera en la misma red (wifi/LAN) puede
    // entrar usando tu IP local (ej. http://192.168.100.63:5173), no solo localhost.
    host: true,
    // Vite bloquea por default cualquier peticion cuyo Host no sea localhost/IP local
    // (proteccion anti DNS-rebinding). Como vamos a entrar via un tunel de Cloudflare,
    // hay que permitirlo explicitamente aqui. .trycloudflare.com era para las ligas
    // temporales de prueba; centinal.org es el dominio real y permanente.
    allowedHosts: ['.trycloudflare.com', '.centinal.org'],
    proxy: {
      // En dev, todo lo que pegue a /api o /iclock se manda al backend CI4 en XAMPP.
      // Ajusta el target si tu backend vive en otro host/puerto.
      '/api': {
        target: 'http://localhost/cheil_biometrico/public',
        changeOrigin: true,
      },
      // Fotos de empleados / selfies de checadas (ver FilesController en el backend)
      '/uploads': {
        target: 'http://localhost/cheil_biometrico/public',
        changeOrigin: true,
      },
    },
  },
}))
