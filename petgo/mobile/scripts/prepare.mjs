// Перед сборкой: всё из petgo.config.json — в приложение.
// Адрес кабинета → экран «Нет интернета» (www/config.js); версия, номер сборки и ID → проект iOS (Android читает файл сам).
import { readFileSync, writeFileSync, existsSync } from 'fs';
const root = new URL('../', import.meta.url);
const pg = JSON.parse(readFileSync(new URL('petgo.config.json', root), 'utf8'));
/* в облачной сборке номер сборки растёт сам (каждая загрузка в App Store / Google Play — новый номер) */
if (process.env.PETGO_BUILD) pg.build = parseInt(process.env.PETGO_BUILD, 10);
writeFileSync(new URL('www/config.js', root), 'window.PETGO_URL = ' + JSON.stringify(pg.url) + ';\n');
const pbx = new URL('ios/App/App.xcodeproj/project.pbxproj', root);
if (existsSync(pbx)) {
  let s = readFileSync(pbx, 'utf8');
  s = s.replace(/MARKETING_VERSION = [^;]+;/g, 'MARKETING_VERSION = ' + pg.version + ';')
       .replace(/CURRENT_PROJECT_VERSION = [^;]+;/g, 'CURRENT_PROJECT_VERSION = ' + pg.build + ';')
       .replace(/PRODUCT_BUNDLE_IDENTIFIER = [^;]+;/g, 'PRODUCT_BUNDLE_IDENTIFIER = ' + pg.appId + ';');
  writeFileSync(pbx, s);
}
console.log('My PetGo:', pg.url, '·', pg.appId, '·', pg.version + ' (' + pg.build + ')');
