import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
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
})
