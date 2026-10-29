// Собирает итоговое видео из clips/*.mp4 + scenes.json:
//   1) рендер графики (overlays.mjs)  2) сцены по отдельности  3) переходы xfade  4) музыка + сведение
// Запуск: node video/scripts/build.mjs [--placeholder]
//   --placeholder — если клипа нет, подставить серую заглушку (для проверки текстов до записи).
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const CLIPS = process.env.CLIPS_DIR || path.join(ROOT, 'clips');
const BUILD = path.join(ROOT, 'build');
const OV = path.join(BUILD, 'overlays');
const PLACEHOLDER = process.argv.includes('--placeholder');
const cfg = JSON.parse(fs.readFileSync(path.join(ROOT, 'scenes.json'), 'utf8'));
const OUT = path.resolve(ROOT, process.env.OUT || cfg.output);
const FPS = 30;
const T = cfg.transitionSeconds;

const run = (cmd, args) => execFileSync(cmd, args, { stdio: ['ignore', 'ignore', 'inherit'] });
const ff = (args) => run('ffmpeg', ['-hide_banner', '-loglevel', 'error', '-y', ...args]);

run('node', [path.join(ROOT, 'scripts', 'overlays.mjs')]);
const L = JSON.parse(fs.readFileSync(path.join(OV, 'layout.json'), 'utf8'));
const S = L.screen;

// Надпись выезжает снизу и проявляется; если текст тот же, что в прошлой сцене, — без анимации.
const textAnim = (animate) => animate
  ? `format=rgba,fade=in:st=0.05:d=0.45:alpha=1[t];[b][t]overlay=0:'if(lt(t,0.5),60*pow(1-t/0.5,2),0)'`
  : `format=rgba[t];[b][t]overlay=0:0`;

const segs = [];
for (const [i, s] of cfg.scenes.entries()) {
  const nn = String(i).padStart(2, '0');
  const seg = path.join(BUILD, `seg-${nn}.mp4`);
  const text = path.join(OV, `text-${nn}.png`);
  const animate = i === 0 || s.title !== cfg.scenes[i - 1].title;
  const enc = ['-t', String(s.duration), '-r', String(FPS), '-c:v', 'libx264', '-crf', '14', '-preset', 'fast', '-pix_fmt', 'yuv420p', '-an', seg];

  if (s.final) {
    ff(['-loop', '1', '-i', path.join(OV, 'bg-final.png'), '-loop', '1', '-i', text,
      '-filter_complex', `[0:v]format=rgba[b];[1:v]${textAnim(true)},format=yuv420p[v]`, '-map', '[v]', ...enc]);
  } else {
    const clip = path.join(CLIPS, `${s.clip}.mp4`);
    let input;
    if (fs.existsSync(clip)) {
      const meta = path.join(CLIPS, `${s.clip}.json`);
      // кадры берём от метки клипа (ready — страница готова; submitted, saved и т. п. — см. record.mjs)
      const marks = fs.existsSync(meta) ? JSON.parse(fs.readFileSync(meta, 'utf8')).marks || {} : {};
      const at = marks[s.mark || 'ready'];
      if (at === undefined) throw new Error(`В клипе ${s.clip} нет метки «${s.mark}». Есть: ${Object.keys(marks).join(', ')}`);
      input = ['-ss', String(Math.max(0, at + (s.start || 0))), '-i', clip];
    } else if (PLACEHOLDER) {
      input = ['-f', 'lavfi', '-i', `color=c=#dfe7ea:s=${S.w}x${S.h}:r=${FPS}`];
    } else {
      throw new Error(`Нет клипа ${clip}. Запишите: node video/scripts/record.mjs ${s.clip}  (или --placeholder)`);
    }
    ff(['-loop', '1', '-i', path.join(OV, 'bg-phone.png'), ...input,
      '-loop', '1', '-i', path.join(OV, 'mask.png'), '-loop', '1', '-i', text,
      '-filter_complex',
      `[1:v]setpts=PTS-STARTPTS,fps=${FPS},scale=${S.w}:${S.h}:flags=lanczos,tpad=stop_mode=clone:stop_duration=60,format=rgba[c];` +
      `[2:v]format=gray[m];[c][m]alphamerge[cm];` +
      `[0:v][cm]overlay=${S.x}:${S.y}[b];[3:v]${textAnim(animate)},format=yuv420p[v]`,
      '-map', '[v]', ...enc]);
  }
  segs.push(seg);
  console.log(`✓ сцена ${nn} ${s.id} (${s.duration} c)`);
}

// Цепочка переходов
const total = cfg.scenes.reduce((a, s) => a + s.duration, 0) - T * (segs.length - 1);
let graph = '', prev = '[0:v]', offset = 0;
for (let i = 1; i < segs.length; i++) {
  offset += cfg.scenes[i - 1].duration - T;
  const out = i === segs.length - 1 ? '[v]' : `[x${i}]`;
  graph += `${prev}[${i}:v]xfade=transition=${cfg.scenes[i].transition || 'fade'}:duration=${T}:offset=${offset.toFixed(3)}${out};`;
  prev = out;
}

const music = path.join(BUILD, 'music.wav');
run('python3', [path.join(ROOT, 'scripts', 'music.py'), String(total + 0.5), music]);

ff([...segs.flatMap((s) => ['-i', s]), '-i', music,
  '-filter_complex', `${graph}[${segs.length}:a]loudnorm=I=-16:TP=-1.5,atrim=0:${total.toFixed(3)}[a]`,
  '-map', '[v]', '-map', '[a]',
  '-c:v', 'libx264', '-preset', 'slow', '-crf', '18', '-profile:v', 'high', '-level', '4.1', '-pix_fmt', 'yuv420p', '-r', String(FPS),
  '-c:a', 'aac', '-b:a', '192k', '-ar', '44100', '-movflags', '+faststart', '-t', total.toFixed(3), OUT]);

console.log(`\n✓ Готово: ${path.relative(process.cwd(), OUT)} — ${total.toFixed(1)} c, 1080x1920`);
if (total < 45 || total > 60) console.warn(`⚠ Длительность ${total.toFixed(1)} c вне диапазона 45–60 c — поправьте duration в scenes.json`);
if (JSON.stringify(cfg).includes('000-00-00')) console.warn('⚠ В финальной сцене остались контакты-заглушки (000-00-00)');
