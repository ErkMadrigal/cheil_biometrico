export const environment = {
  production: false,
  // En desarrollo (ionic serve / navegador): pega directo al backend local.
  // OJO: si vas a probar desde tu celular (no localhost), pon aqui la IP de tu compu
  // en la red WiFi (la que te da "ipconfig" -> IPv4), no "localhost" -- desde el
  // celular "localhost" significa el propio celular, no tu compu.
  apiUrl: 'http://192.168.100.63/cheil_biometrico/public/api/v1',
  mediaUrl: 'http://192.168.100.63/cheil_biometrico/public',
};
