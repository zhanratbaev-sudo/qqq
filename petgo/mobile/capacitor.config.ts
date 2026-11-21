/* My PetGo — настройки приложения.
   Адрес кабинета, ID приложения, название и версия — в petgo.config.json (менять там, здесь ничего трогать не нужно). */
import type { CapacitorConfig } from '@capacitor/cli';
import { existsSync, readFileSync } from 'fs';

const pg = JSON.parse(readFileSync(__dirname + '/petgo.config.json', 'utf8'));
const host = new URL(pg.url).host;
/* сайт узнаёт приложение по этой метке; «push» — уведомления на Android включаются, только когда в сборке есть google-services.json */
const ua = 'MyPetGoApp/' + pg.version;
const fcm = existsSync(__dirname + '/android/app/google-services.json');

const config: CapacitorConfig = {
  appId: pg.appId,
  appName: pg.appName,
  webDir: 'www',
  backgroundColor: '#F2F0E3',
  server: {
    url: pg.url,
    errorPath: 'offline.html',
    allowNavigation: [host],
  },
  android: {
    appendUserAgent: ua + ' (android' + (fcm ? '; push' : '') + ')',
    backgroundColor: '#F2F0E3',
  },
  ios: {
    appendUserAgent: ua + ' (ios; push)',
    backgroundColor: '#F2F0E3',
    contentInset: 'never',
    scrollEnabled: true,
  },
  plugins: {
    SplashScreen: {
      /* заставка до 3 секунд; кабинет убирает её сам, как только загрузится */
      launchAutoHide: true,
      launchShowDuration: 3000,
      launchFadeOutDuration: 200,
      backgroundColor: '#024C46',
      showSpinner: false,
      androidScaleType: 'CENTER_CROP',
      splashFullScreen: false,
      splashImmersive: false,
    },
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert'],
    },
    SystemBars: {
      insetsHandling: 'css',
      initialViewportFitValueHint: 'cover',
    },
  },
};

export default config;
