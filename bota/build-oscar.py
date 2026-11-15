#!/usr/bin/env python3
"""Собирает один файл dist/oscar-clinic.js: компонент + картинки Oscar внутри (base64).
Запуск:  python3 bota/build-oscar.py
"""
import base64, pathlib

ROOT = pathlib.Path(__file__).resolve().parent
src = (ROOT / 'bota.js').read_text(encoding='utf-8')


def data_uri(name):
    raw = (ROOT / 'oscar' / name).read_bytes()
    return 'data:image/webp;base64,' + base64.b64encode(raw).decode()


embed = "{ body: '%s', star: '%s' }" % (data_uri('body.webp'), data_uri('star.webp'))
for old, new in [
    ("const OSCAR_EMBED = null;", f"const OSCAR_EMBED = {embed};"),
    ("const DEFAULT_VARIANT = 'bota';", "const DEFAULT_VARIANT = 'oscar';"),
]:
    assert src.count(old) == 1, old
    src = src.replace(old, new)

HEADER = """/*!
 * ============================================================================
 *  OSCAR — анимированный ИИ-ассистент Oscar Clinic (один файл, без зависимостей)
 * ============================================================================
 *
 *  ИНСТРУКЦИЯ ДЛЯ ВСТРАИВАНИЯ НА САЙТ OSCAR CLINIC
 *
 *  1) Положить этот файл в проект (например, public/oscar-clinic.js) и подключить:
 *       <script src="/oscar-clinic.js"></script>
 *     Картинки уже внутри файла, больше ничего не нужно.
 *
 *  2) Плавающая кнопка ассистента в правом нижнем углу (поверх сайта):
 *       <oscar-assistant floating></oscar-assistant>
 *     Клик по Oscar генерирует событие 'bota-click' — в нём открыть окно чата:
 *       document.querySelector('oscar-assistant')
 *         .addEventListener('bota-click', () => openChat());
 *
 *  3) Внутри окна чата (в шапке) поставить второго Oscar, без floating:
 *       <oscar-assistant id="oscarHead" style="--bota-size:60px"></oscar-assistant>
 *     и переключать его состояние по ходу диалога:
 *       oscarHead.setState('thinking'); // запрос к ИИ отправлен — звезда краснеет и крутится
 *       oscarHead.setState('talking');  // пошёл ответ — звезда пульсирует
 *       oscarHead.setState('idle');     // ответ готов — спокойное парение
 *     Пример:
 *       async function ask(text) {
 *         oscarHead.setState('thinking');
 *         try {
 *           const answer = await sendToAI(text);   // существующий вызов ассистента
 *           oscarHead.setState('talking');
 *           await showAnswer(answer);              // вывод текста в чат
 *         } finally {
 *           oscarHead.setState('idle');
 *         }
 *       }
 *
 *  4) Настройки (CSS-переменные на самом теге):
 *       --bota-size          размер (по умолчанию 160px, в режиме floating 104px)
 *       --bota-accent        цвет свечения звезды в покое (по умолчанию золотой #FFC861)
 *       --bota-accent-think  цвет свечения, когда думает (по умолчанию красный #FF4D5E)
 *       --bota-think-hue     сдвиг цвета звезды, когда думает (по умолчанию -42deg)
 *
 *  React: <oscar-assistant floating ref={ref} /> и
 *         useEffect(() => ref.current.addEventListener('bota-click', open), []);
 *         ref.current.setState('thinking') и т.д.
 *  Тег <bota-assistant variant="oscar"> работает так же (это тот же компонент).
 *  Анимации отключаются у пользователей с настройкой «уменьшить движение».
 * ============================================================================
 */
"""

# Тег <oscar-assistant> — удобный псевдоним
ALIAS = """
(() => {
  const Base = customElements.get('bota-assistant');
  if (Base && !customElements.get('oscar-assistant')) {
    customElements.define('oscar-assistant', class extends Base {});
  }
})();
"""

out = ROOT / 'dist' / 'oscar-clinic.js'
out.parent.mkdir(exist_ok=True)
out.write_text(HEADER + src + ALIAS, encoding='utf-8')
print(out, round(out.stat().st_size / 1024), 'KB')
