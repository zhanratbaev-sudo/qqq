#!/usr/bin/env bash
# Озвучка Vet.izi: женский голос Piper ru_RU irina + обработка (тепло, присутствие, компрессия).
#   VOICE=/путь/к/ru_RU-irina-medium.onnx ./make-vo.sh
set -euo pipefail
cd "$(dirname "$0")"
VOICE="${VOICE:?Укажите VOICE=/путь/к/ru_RU-irina-medium.onnx}"
mkdir -p assets/vo
python3 - "$VOICE" <<'PY'
import json, subprocess, sys
voice = sys.argv[1]
for line in json.load(open('vo-script.json')):
    raw = f"assets/vo/{line['id']}.raw.wav"
    subprocess.run(['python3', '-m', 'piper', '-m', voice, '-f', raw,
                    '--length-scale', '0.8', '--noise-scale', '0.72', '--noise-w-scale', '0.9', '--sentence-silence', '0.08'],
                   input=line['text'].encode(), check=True, capture_output=True)
    subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', raw, '-af',
                    'highpass=f=75,equalizer=f=220:t=q:w=1:g=2,equalizer=f=3400:t=q:w=1.2:g=2.5,'
                    'acompressor=threshold=-20dB:ratio=2.5:attack=8:release=120:makeup=2,'
                    'aecho=0.8:0.5:22:0.12,loudnorm=I=-16:TP=-1.5:LRA=7',
                    '-ar', '48000', f"assets/vo/{line['id']}.wav"], check=True)
    subprocess.run(['rm', '-f', raw])
    d = subprocess.run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', f"assets/vo/{line['id']}.wav"], capture_output=True, text=True).stdout.strip()
    print(line['id'], d)
PY
