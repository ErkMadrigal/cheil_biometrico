export const environment = {
  production: true,
  // erk sirve el backend con Apache en la RAIZ de htdocs (sin VirtualHost dedicado),
  // por eso el tunel de Cloudflare (api.centinal.org -> http://localhost:80) necesita
  // la ruta completa /cheil_biometrico/public/... igual que en environment.ts (LAN).
  apiUrl: 'https://api.centinal.org/cheil_biometrico/public/api/v1',
  mediaUrl: 'https://api.centinal.org/cheil_biometrico/public',
};
