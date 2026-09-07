import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.cheil.checador',
  appName: 'Cheil Checador',
  webDir: 'dist/mobileapp/browser',
  server: {
    androidScheme: 'https',
  },
};

export default config;
