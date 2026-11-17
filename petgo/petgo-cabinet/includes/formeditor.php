<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   ФОРМА ЗАЯВКИ: настройки (редактор в кабинете), безопасная проверка, вывод [petgo_request]
   Все настройки хранятся в одной опции petgo_form и проходят строгую очистку:
   цвета только #rrggbb, числа в пределах, шрифты и стили только из списка.
   ============================================================ */
function petgo_form_builtin() {
    /* id => array(подпись, обязательное по умолчанию, можно скрыть, можно менять обязательность, ширина) */
    return array(
        'name'    => array('Ваше имя', 1, 0, 0, 'full'),
        'phone'   => array('Телефон', 1, 0, 0, 'full'),
        'contact' => array('Как с вами связаться', 1, 1, 1, 'full'),
        'species' => array('Кто у вас', 1, 1, 1, 'full'),
        'weight'  => array('Вес, кг', 1, 1, 1, 'half'),
        'breed'   => array('Порода', 1, 1, 1, 'half'),
        'note'    => array('Комментарий (необязательно)', 0, 1, 1, 'full'),
    );
}

function petgo_form_fonts() {
    return array(
        'site'    => array('Как на сайте', 'inherit'),
        'manrope' => array('Manrope', 'Manrope,system-ui,-apple-system,"Segoe UI",sans-serif'),
        'system'  => array('Системный', 'system-ui,-apple-system,"Segoe UI",Roboto,sans-serif'),
        'rounded' => array('Округлый', 'ui-rounded,"SF Pro Rounded",Nunito,"Segoe UI",system-ui,sans-serif'),
        'serif'   => array('С засечками', 'Georgia,"Times New Roman",serif'),
        'mono'    => array('Моноширинный', 'ui-monospace,SFMono-Regular,Menlo,Consolas,monospace'),
    );
}

function petgo_form_ctypes() {
    return array('text' => 'Текст', 'textarea' => 'Длинный текст', 'number' => 'Число', 'select' => 'Список', 'pills' => 'Кнопки выбора', 'checkbox' => 'Галочка');
}

function petgo_form_defaults() {
    $f = array();
    foreach (petgo_form_builtin() as $id => $b) {
        $f[] = array('id' => $id, 'on' => 1, 'label' => $b[0], 'req' => (int) $b[1], 'w' => $b[4]);
    }
    return array(
        't' => array(
            'title' => 'Оставьте заявку', 'text' => 'Мы свяжемся с вами и подберём няню для питомца.', 'btn' => 'Оставить заявку',
            'ok' => 'Спасибо! Заявка отправлена, мы скоро свяжемся с вами.',
            'consent' => 'Я даю согласие на обработку моих данных. Их увидят только сотрудники PetGo.',
            'other_label' => 'Какое животное (хомяк, попугай и т. д.)',
            'dog' => 'Собака', 'cat' => 'Кошка', 'other' => 'Другое', 'write' => 'Написать', 'call' => 'Позвонить',
        ),
        's' => array(
            'theme' => 'auto', 'w' => 560, 'pad' => 28, 'gap' => 14, 'rf' => 12, 'rfi' => 12, 'rb' => 12, 'bw' => 1,
            'fs' => 16, 'fsl' => 14, 'fst' => 28, 'fwt' => 800, 'fh' => 48, 'bh' => 50,
            'shadow' => 'none', 'font' => 'site', 'align' => 'left', 'btn' => 'solid', 'pills' => 'fill',
            'c_bg' => '#ffffff', 'c_tx' => '#10312d', 'c_mu' => '#5f8480', 'c_ln' => '#e3e0cc', 'c_ac' => '#024c46', 'c_ax' => '#fbf9e8', 'c_fbg' => '#ffffff', 'c_so' => '#fbf9e8',
            'd_bg' => '#0f1f1d', 'd_tx' => '#f3f1de', 'd_mu' => '#8fb0ac', 'd_ln' => '#244541', 'd_ac' => '#7fd1c4', 'd_ax' => '#06211e', 'd_fbg' => '#0f1f1d', 'd_so' => '#142a27',
        ),
        'fields' => $f,
    );
}

/* строгая очистка любых входящих настроек */
function petgo_form_clean($in) {
    $d   = petgo_form_defaults();
    $out = $d;
    $in  = is_array($in) ? $in : array();
    $lim = array('title' => 120, 'text' => 300, 'btn' => 60, 'ok' => 300, 'consent' => 400, 'other_label' => 100, 'dog' => 30, 'cat' => 30, 'other' => 30, 'write' => 30, 'call' => 30);
    if (isset($in['t']) && is_array($in['t'])) {
        foreach ($lim as $k => $n) {
            if (isset($in['t'][$k]) && is_string($in['t'][$k])) {
                $v = trim(sanitize_text_field($in['t'][$k]));
                if ($v !== '' || $k === 'text') {
                    $out['t'][$k] = mb_substr($v, 0, $n);
                }
            }
        }
    }
    $S   = (isset($in['s']) && is_array($in['s'])) ? $in['s'] : array();
    $num = array('w' => array(280, 900), 'pad' => array(8, 64), 'gap' => array(6, 32), 'rf' => array(0, 48), 'rfi' => array(0, 32), 'rb' => array(0, 40), 'bw' => array(0, 4),
        'fs' => array(12, 22), 'fsl' => array(11, 18), 'fst' => array(18, 48), 'fh' => array(36, 64), 'bh' => array(36, 72));
    foreach ($num as $k => $r) {
        if (isset($S[$k]) && is_numeric($S[$k])) {
            $out['s'][$k] = (int) max($r[0], min($r[1], (float) $S[$k]));
        }
    }
    if (isset($S['fwt']) && in_array((int) $S['fwt'], array(500, 600, 700, 800, 900), true)) {
        $out['s']['fwt'] = (int) $S['fwt'];
    }
    $en = array('theme' => array('auto', 'light', 'dark'), 'shadow' => array('none', 'soft', 'strong'), 'font' => array_keys(petgo_form_fonts()), 'align' => array('left', 'center'), 'btn' => array('solid', 'outline'), 'pills' => array('fill', 'outline'));
    foreach ($en as $k => $list) {
        if (isset($S[$k]) && is_string($S[$k]) && in_array($S[$k], $list, true)) {
            $out['s'][$k] = $S[$k];
        }
    }
    foreach (array('bg', 'tx', 'mu', 'ln', 'ac', 'ax', 'fbg', 'so') as $c) {
        foreach (array('c_', 'd_') as $p) {
            $k = $p . $c;
            if (isset($S[$k]) && is_string($S[$k]) && preg_match('/^#[0-9a-fA-F]{6}$/', $S[$k])) {
                $out['s'][$k] = strtolower($S[$k]);
            }
        }
    }
    $bi   = petgo_form_builtin();
    $def  = array();
    foreach ($d['fields'] as $x) {
        $def[$x['id']] = $x;
    }
    $types  = petgo_form_ctypes();
    $seen   = array();
    $fields = array();
    $cn     = 0;
    $list   = (isset($in['fields']) && is_array($in['fields'])) ? $in['fields'] : array();
    foreach ($list as $f) {
        if (!is_array($f) || empty($f['id']) || !is_string($f['id']) || isset($seen[$f['id']])) {
            continue;
        }
        $id  = $f['id'];
        $lab = trim(sanitize_text_field(isset($f['label']) && is_string($f['label']) ? $f['label'] : ''));
        $w   = (isset($f['w']) && $f['w'] === 'half') ? 'half' : 'full';
        if (isset($bi[$id])) {
            $seen[$id] = 1;
            $x         = $def[$id];
            if ($lab !== '') {
                $x['label'] = mb_substr($lab, 0, 80);
            }
            $x['on']  = $bi[$id][2] ? (empty($f['on']) ? 0 : 1) : 1;
            $x['req'] = $bi[$id][3] ? (empty($f['req']) ? 0 : 1) : (int) $bi[$id][1];
            $x['w']   = $w;
            $fields[] = $x;
        } elseif (preg_match('/^c[a-z0-9]{4,10}$/', $id) && $cn < 12 && $lab !== '') {
            $seen[$id] = 1;
            $cn++;
            $t = (isset($f['type']) && is_string($f['type']) && isset($types[$f['type']])) ? $f['type'] : 'text';
            $o = array();
            if (($t === 'select' || $t === 'pills') && isset($f['opts']) && is_array($f['opts'])) {
                foreach ($f['opts'] as $ov) {
                    $ov = is_string($ov) ? trim(sanitize_text_field($ov)) : '';
                    if ($ov !== '' && count($o) < 12) {
                        $o[] = mb_substr($ov, 0, 60);
                    }
                }
                $o = array_values(array_unique($o));
            }
            if (($t === 'select' || $t === 'pills') && count($o) < 2) {
                $t = 'text';
                $o = array();
            }
            $ph = (isset($f['ph']) && is_string($f['ph'])) ? mb_substr(trim(sanitize_text_field($f['ph'])), 0, 80) : '';
            $fields[] = array('id' => $id, 'type' => $t, 'on' => empty($f['on']) ? 0 : 1, 'label' => mb_substr($lab, 0, 80), 'req' => empty($f['req']) ? 0 : 1, 'w' => $w, 'opts' => $o, 'ph' => $ph);
        }
    }
    foreach ($bi as $id => $b) {
        if (!isset($seen[$id])) {
            $fields[] = $def[$id];
        }
    }
    $out['fields'] = $fields;
    return $out;
}

function petgo_form_cfg() {
    static $c = null;
    if ($c === null) {
        $s = get_option('petgo_form', array());
        $c = petgo_form_clean(is_array($s) ? $s : array());
    }
    return $c;
}

function petgo_form_field($cfg, $id) {
    foreach ($cfg['fields'] as $f) {
        if ($f['id'] === $id) {
            return $f;
        }
    }
    return array('id' => $id, 'on' => 0, 'req' => 0, 'label' => '', 'w' => 'full');
}

/* ---------- CSS формы из настроек ---------- */

function petgo_form_css($cfg) {
    $s  = $cfg['s'];
    $ft = petgo_form_fonts();
    $L  = '';
    $D  = '';
    foreach (array('bg', 'tx', 'mu', 'ln', 'ac', 'ax', 'fbg', 'so') as $k) {
        $L .= '--' . $k . ':' . $s['c_' . $k] . ';';
        $D .= '--' . $k . ':' . $s['d_' . $k] . ';';
    }
    $base = '--w:' . (int) $s['w'] . 'px;--pad:' . (int) $s['pad'] . 'px;--gap:' . (int) $s['gap'] . 'px;--rf:' . (int) $s['rf'] . 'px;--rfi:' . (int) $s['rfi'] . 'px;--rb:' . (int) $s['rb'] . 'px;--bw:' . (int) $s['bw'] . 'px;--fs:' . (int) $s['fs'] . 'px;--fsl:' . (int) $s['fsl'] . 'px;--fst:' . (int) $s['fst'] . 'px;--fwt:' . (int) $s['fwt'] . ';--fh:' . (int) $s['fh'] . 'px;--bh:' . (int) $s['bh'] . 'px;--font:' . $ft[$s['font']][1] . ';';
    $css  = '.pgf{' . $base . $L . '--shadow:none}.pgf.t-dark{' . $D . '}@media(prefers-color-scheme:dark){.pgf.t-auto{' . $D . '}}';
    $css .= '.pgf{max-width:var(--w);margin:0 auto;padding:var(--pad);background:var(--bg);border:var(--bw) solid var(--ln);border-radius:var(--rf);font-family:var(--font);color:var(--tx);box-sizing:border-box;box-shadow:var(--shadow);font-size:var(--fs);line-height:1.4;position:relative}
.pgf *{box-sizing:border-box;font-family:inherit}
.pgf.sh-soft{--shadow:0 10px 30px rgba(0,0,0,.10)}.pgf.sh-strong{--shadow:0 24px 60px rgba(0,0,0,.30)}
.pgf h2{margin:0 0 6px;font-size:var(--fst);line-height:1.2;font-weight:var(--fwt);color:var(--tx)}
.pgf .sub{margin:0 0 20px;font-size:var(--fs);color:var(--mu)}
.pgf.a-center .hd{text-align:center}
.pgf .g2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--gap)}
.pgf .fld{display:block;min-width:0;grid-column:1/-1;margin:0}.pgf .fld.hw{grid-column:auto}.pgf .fld[hidden]{display:none}
.pgf .l{display:block;margin:0 0 6px;font-size:var(--fsl);font-weight:600;color:var(--mu)}
.pgf .rq{display:none;color:var(--ac);font-weight:700}.pgf .req .rq{display:inline}
.pgf input[type=text],.pgf input[type=tel],.pgf input[type=number],.pgf select,.pgf textarea{width:100%;min-height:var(--fh);padding:0 14px;border:var(--bw) solid var(--ln);border-radius:var(--rfi);background:var(--fbg);color:var(--tx);font-size:var(--fs);font-weight:500;outline:0;box-shadow:none;margin:0}
.pgf textarea{min-height:92px;padding:12px 14px;resize:vertical}
.pgf input:focus,.pgf select:focus,.pgf textarea:focus{border-color:var(--ac)}
.pgf .pills{display:flex;gap:8px;flex-wrap:wrap}.pgf .pills label{flex:1 1 0;min-width:96px;position:relative;margin:0}
.pgf .pills input{position:absolute;opacity:0;inset:0;margin:0;cursor:pointer;width:100%;height:100%}
.pgf .pills span{display:block;text-align:center;padding:0 10px;line-height:calc(var(--fh) - 2 * var(--bw));border:var(--bw) solid var(--ln);border-radius:var(--rfi);font-size:var(--fs);font-weight:600;color:var(--tx);background:var(--fbg)}
.pgf.p-fill .pills input:checked+span{background:var(--ac);border-color:var(--ac);color:var(--ax)}
.pgf.p-outline .pills input:checked+span{border-color:var(--ac);color:var(--ac);box-shadow:inset 0 0 0 1px var(--ac)}
.pgf .pills input:focus-visible+span{outline:2px solid var(--ac);outline-offset:2px}
.pgf .chk{display:flex;gap:10px;align-items:center;font-size:var(--fs);color:var(--tx);cursor:pointer}.pgf .chk input{width:20px;height:20px;margin:0;accent-color:var(--ac);flex:none}
.pgf[data-js] .o{display:none}.pgf[data-js].oth .o{display:block;margin-top:var(--gap)}
.pgf .c{display:flex;gap:10px;align-items:flex-start;margin:var(--gap) 0;font-size:var(--fsl);color:var(--mu);line-height:1.45}.pgf .c input{margin:3px 0 0;width:18px;height:18px;accent-color:var(--ac);flex:none}
.pgf button.go{display:block;width:100%;min-height:var(--bh);padding:0 18px;border:var(--bw) solid transparent;border-radius:var(--rb);font-size:var(--fs);font-weight:700;cursor:pointer;text-transform:none;letter-spacing:0;box-shadow:none;text-shadow:none}
.pgf.b-solid button.go,.pgf.b-solid button.go:hover,.pgf.b-solid button.go:focus{background:var(--ac);color:var(--ax);border-color:var(--ac)}
.pgf.b-outline button.go,.pgf.b-outline button.go:hover,.pgf.b-outline button.go:focus{background:transparent;color:var(--ac);border:2px solid var(--ac)}
.pgf button.go:hover{opacity:.92}
.pgf .m{padding:12px 14px;margin:0 0 16px;border-radius:var(--rfi);font-size:15px;font-weight:600;border:1px solid var(--ac);background:var(--so);color:var(--tx)}.pgf .m.e{border-color:#d64545;color:#d64545}
.pgf .hp{position:absolute;left:-9999px;height:0;overflow:hidden}
@media(max-width:480px){.pgf{padding:calc(var(--pad) * .75)}.pgf .g2{grid-template-columns:1fr}.pgf .fld.hw{grid-column:1/-1}.pgf h2{font-size:calc(var(--fst) * .86)}}';
    return $css;
}

function petgo_form_classes($s) {
    return 't-' . $s['theme'] . ' b-' . $s['btn'] . ' p-' . $s['pills'] . ' a-' . $s['align'] . ' sh-' . $s['shadow'];
}

/* ---------- одно поле ---------- */
function petgo_form_field_html($f, $cfg, $v, $preview) {
    $id  = $f['id'];
    $t   = $cfg['t'];
    $cls = 'fld ' . ($f['w'] === 'half' ? 'hw' : 'fw') . ($f['req'] ? ' req' : '');
    $hid = $f['on'] ? '' : ' hidden';
    $lab = '<span class="l"><span data-b="lbl_' . esc_attr($id) . '">' . esc_html($f['label']) . '</span><b class="rq"> *</b></span>';
    $rq  = $f['req'] ? ' required' : '';
    $val = function ($k) use ($v) {
        return isset($v[$k]) ? (string) $v[$k] : '';
    };
    if ($id === 'name') {
        return '<label class="' . $cls . '" data-fld="name">' . $lab . '<input type="text" name="name" value="' . esc_attr($val('name')) . '" maxlength="80" autocomplete="name" required></label>';
    }
    if ($id === 'phone') {
        return '<label class="' . $cls . '" data-fld="phone">' . $lab . '<input type="tel" name="phone" value="' . esc_attr($val('phone')) . '" placeholder="+7 ___ ___ __ __" autocomplete="tel" inputmode="tel" required></label>';
    }
    if ($id === 'contact') {
        $o = '<div class="' . $cls . '"' . $hid . ' data-fld="contact">' . $lab . '<div class="pills">';
        foreach (array('write', 'call') as $k) {
            $o .= '<label><input type="radio" name="contact" value="' . $k . '"' . checked($val('contact'), $k, false) . $rq . '><span data-b="t_' . $k . '">' . esc_html($t[$k]) . '</span></label>';
        }
        return $o . '</div></div>';
    }
    if ($id === 'species') {
        $o = '<div class="' . $cls . '"' . $hid . ' data-fld="species">' . $lab . '<div class="pills">';
        foreach (array('dog', 'cat', 'other') as $k) {
            $o .= '<label><input type="radio" name="species" value="' . $k . '"' . checked($val('species'), $k, false) . $rq . '><span data-b="t_' . $k . '">' . esc_html($t[$k]) . '</span></label>';
        }
        return $o . '</div><div class="o"><span class="l" data-b="t_other_label">' . esc_html($t['other_label']) . '</span><input type="text" name="other" value="' . esc_attr($val('other')) . '" maxlength="60"></div></div>';
    }
    if ($id === 'weight' || $id === 'breed') {
        return '<label class="' . $cls . '"' . $hid . ' data-fld="' . $id . '">' . $lab . '<input type="text" name="' . $id . '" value="' . esc_attr($val($id)) . '" maxlength="' . ($id === 'weight' ? 20 : 80) . '"' . ($id === 'weight' ? ' inputmode="decimal"' : '') . $rq . '></label>';
    }
    if ($id === 'note') {
        return '<label class="' . $cls . '"' . $hid . ' data-fld="note">' . $lab . '<textarea name="note" maxlength="1000"' . $rq . '>' . esc_textarea($val('note')) . '</textarea></label>';
    }
    /* свои поля */
    if (!empty($preview)) {
        return '';
    }
    $nm = 'x_' . $id;
    $xv = (isset($v['x']) && is_array($v['x']) && isset($v['x'][$id])) ? (string) $v['x'][$id] : '';
    $ph = (isset($f['ph']) && $f['ph'] !== '') ? ' placeholder="' . esc_attr($f['ph']) . '"' : '';
    $ty = isset($f['type']) ? $f['type'] : 'text';
    if ($ty === 'checkbox') {
        return '<div class="' . $cls . '" data-fld="' . esc_attr($id) . '"><label class="chk"><input type="checkbox" name="' . esc_attr($nm) . '" value="1"' . checked($xv, '1', false) . $rq . '><span>' . esc_html($f['label']) . '</span><b class="rq"> *</b></label></div>';
    }
    $o = '<div class="' . $cls . '" data-fld="' . esc_attr($id) . '">' . $lab;
    if ($ty === 'textarea') {
        $o .= '<textarea name="' . esc_attr($nm) . '" maxlength="1000"' . $ph . $rq . '>' . esc_textarea($xv) . '</textarea>';
    } elseif ($ty === 'select') {
        $o .= '<select name="' . esc_attr($nm) . '"' . $rq . '><option value="">—</option>';
        foreach ((array) $f['opts'] as $op) {
            $o .= '<option value="' . esc_attr($op) . '"' . selected($xv, $op, false) . '>' . esc_html($op) . '</option>';
        }
        $o .= '</select>';
    } elseif ($ty === 'pills') {
        $o .= '<div class="pills">';
        foreach ((array) $f['opts'] as $op) {
            $o .= '<label><input type="radio" name="' . esc_attr($nm) . '" value="' . esc_attr($op) . '"' . checked($xv, $op, false) . $rq . '><span>' . esc_html($op) . '</span></label>';
        }
        $o .= '</div>';
    } else {
        $o .= '<input type="' . ($ty === 'number' ? 'number' : 'text') . '" name="' . esc_attr($nm) . '" value="' . esc_attr($xv) . '" maxlength="300"' . ($ty === 'number' ? ' step="any"' : '') . $ph . $rq . '>';
    }
    return $o . '</div>';
}

/* ---------- вся форма ---------- */
function petgo_form_render($cfg, $v, $msg, $preview = false) {
    $t   = $cfg['t'];
    $M   = petgo_req_msgs();
    $M['ok'] = $t['ok'];
    $o   = '<style>' . petgo_form_css($cfg) . '</style>';
    $o  .= '<form class="pgf ' . esc_attr(petgo_form_classes($cfg['s'])) . (isset($v['species']) && $v['species'] === 'other' ? ' oth' : '') . '" id="petgo-request" method="post" action="' . esc_url($preview ? '#' : admin_url('admin-post.php')) . '" novalidate>';
    $o  .= '<div class="hd"><h2 data-b="t_title">' . esc_html($t['title']) . '</h2><p class="sub" data-b="t_text">' . esc_html($t['text']) . '</p></div>';
    if ($msg && isset($M[$msg])) {
        $txt = $M[$msg];
        if ($msg === 'e_req' && !empty($v['_miss'])) {
            $txt = 'Заполните поле: ' . $v['_miss'];
        }
        $o .= '<div class="m' . ($msg === 'ok' ? '' : ' e') . '" role="status">' . esc_html($txt) . '</div>';
    }
    if (!$preview) {
        $o .= '<input type="hidden" name="action" value="petgo_req"><input type="hidden" name="pgr_t" value="' . esc_attr(petgo_req_token()) . '">';
        $o .= '<div class="hp" aria-hidden="true"><input type="text" name="pgr_web" tabindex="-1" autocomplete="off"></div>';
    }
    $o .= '<div class="g2">';
    foreach ($cfg['fields'] as $f) {
        if (!$f['on'] && !$preview) {
            continue;
        }
        $o .= petgo_form_field_html($f, $cfg, $v, $preview);
    }
    $o .= '</div>';
    $o .= '<label class="c"><input type="checkbox" name="consent" value="1" required><span data-b="t_consent">' . esc_html($t['consent']) . '</span></label>';
    $o .= '<button type="' . ($preview ? 'button' : 'submit') . '" class="go" data-b="t_btn">' . esc_html($t['btn']) . '</button>';
    $o .= '<script>(function(){var f=document.getElementById("petgo-request");if(!f)return;f.setAttribute("data-js","");function u(){var s=f.querySelector("input[name=species]:checked"),on=s&&s.value==="other",i=f.querySelector("input[name=other]");f.classList.toggle("oth",!!on);if(i)i.required=!!on}f.addEventListener("change",u);u()})();</script>';
    return $o . '</form>';
}

add_shortcode('petgo_request', 'petgo_request_shortcode');
function petgo_request_shortcode($atts) {
    /* с версии 29 форма одна, в стиле сайта, и отправляется без перезагрузки (includes/lead.php); редактор оформления убран */
    if (!apply_filters('petgo_old_request_form', false)) {
        $o = '';
        if (!petgo_site_is_front() && !did_action('petgo_site_assets')) {
            do_action('petgo_site_assets');
            $o .= '<link rel="stylesheet" href="' . esc_url(petgo_asset_url('site.css')) . '">';
            $o .= '<script>window.PG_CONFIG=window.PG_CONFIG||' . wp_json_encode(array('kbd' => false, 'phone_fmt' => petgo_site_phone_fmt())) . ';</script><script src="' . esc_url(petgo_asset_url('ui.js')) . '" defer></script>';
        }
        return $o . '<div class="pg-layer pg-form-card pg-form-solo">' . petgo_lead_form('request') . '</div>';
    }
    $cfg  = petgo_form_cfg();
    $a    = shortcode_atts(array('title' => '', 'text' => ''), $atts);
    if ($a['title'] !== '') {
        $cfg['t']['title'] = sanitize_text_field($a['title']);
    }
    if ($a['text'] !== '') {
        $cfg['t']['text'] = sanitize_text_field($a['text']);
    }
    $code = isset($_GET['pgr']) ? sanitize_key(wp_unslash($_GET['pgr'])) : '';
    $v    = array();
    if ($code) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
        if (isset($_GET['pgr_k'])) {
            $sv = get_transient('petgo_rqv_' . sanitize_key(wp_unslash($_GET['pgr_k'])));
            if (is_array($sv)) {
                $v = $sv;
            }
        }
    }
    return petgo_form_render($cfg, $v, $code, false);
}

/* ---------- приём заявки ---------- */
add_action('admin_post_nopriv_petgo_req', 'petgo_req_send');
add_action('admin_post_petgo_req', 'petgo_req_send');
function petgo_req_send() {
    $ref = wp_get_referer();
    $ref = $ref ? remove_query_arg(array('pgr', 'pgr_k'), $ref) : home_url('/');
    $ref = preg_replace('/#.*$/', '', $ref);
    $go  = function ($code, $key = '') use ($ref) {
        $a = array('pgr' => $code);
        if ($key) {
            $a['pgr_k'] = $key;
        }
        wp_safe_redirect(add_query_arg($a, $ref) . '#petgo-request');
        exit;
    };
    if (!empty($_POST['pgr_web'])) {
        $go('ok');
    }
    if (!petgo_req_token_ok(isset($_POST['pgr_t']) ? $_POST['pgr_t'] : '')) {
        $go('e_tok');
    }
    $cfg = petgo_form_cfg();
    $f   = function ($id) use ($cfg) {
        return petgo_form_field($cfg, $id);
    };
    $pick = function ($k, $max) {
        return mb_substr(sanitize_text_field((string) petgo_p($k)), 0, $max);
    };
    $v = array(
        'name'    => $pick('name', 80),
        'phone'   => (string) petgo_p('phone'),
        'contact' => (string) petgo_p('contact'),
        'species' => (string) petgo_p('species'),
        'other'   => $pick('other', 60),
        'weight'  => $pick('weight', 20),
        'breed'   => $pick('breed', 80),
        'note'    => mb_substr(sanitize_textarea_field((string) petgo_p('note')), 0, 1000),
        'x'       => array(),
    );
    $key = wp_generate_password(12, false);
    $err = function ($code, $miss = '') use (&$v, $key, $go) {
        if ($miss !== '') {
            $v['_miss'] = $miss;
        }
        set_transient('petgo_rqv_' . $key, $v, 600);
        $go($code, $key);
    };
    $ik = 'petgo_rq_' . petgo_req_ip();
    if ((int) get_transient($ik) >= 5) {
        $err('e_many');
    }
    if (mb_strlen($v['name']) < 2) {
        $err('e_name');
    }
    $d = preg_replace('/\D/', '', $v['phone']);
    if (strlen($d) === 11 && $d[0] === '8') {
        $d = '7' . substr($d, 1);
    }
    if (strlen($d) < 10 || strlen($d) > 15) {
        $err('e_phone');
    }
    /* способ связи */
    $fc = $f('contact');
    if (!$fc['on']) {
        $v['contact'] = '';
    } elseif ($v['contact'] === '' && $fc['req']) {
        $err('e_contact');
    } elseif ($v['contact'] !== '' && !in_array($v['contact'], array('write', 'call'), true)) {
        $err('e_contact');
    }
    /* кто у вас */
    $fs = $f('species');
    if (!$fs['on']) {
        $v['species'] = '';
        $v['other']   = '';
    } else {
        if ($v['species'] === '' && $fs['req']) {
            $err('e_species');
        }
        if ($v['species'] !== '' && !in_array($v['species'], array('dog', 'cat', 'other'), true)) {
            $err('e_species');
        }
        if ($v['species'] === 'other' && $v['other'] === '') {
            $err('e_other');
        }
        if ($v['species'] !== 'other') {
            $v['other'] = '';
        }
    }
    foreach (array('weight', 'breed', 'note') as $k) {
        $fk = $f($k);
        if (!$fk['on']) {
            $v[$k] = '';
        } elseif ($fk['req'] && $v[$k] === '') {
            $err($k === 'note' ? 'e_req' : 'e_wb', $fk['label']);
        }
    }
    /* свои поля */
    $extra = array();
    foreach ($cfg['fields'] as $cf) {
        if (isset(petgo_form_builtin()[$cf['id']]) || !$cf['on']) {
            continue;
        }
        $raw = isset($_POST['x_' . $cf['id']]) ? wp_unslash($_POST['x_' . $cf['id']]) : '';
        $raw = is_string($raw) ? $raw : '';
        $val = '';
        switch ($cf['type']) {
            case 'checkbox':
                $val = ($raw === '1') ? '1' : '';
                break;
            case 'select':
            case 'pills':
                $val = in_array($raw, (array) $cf['opts'], true) ? $raw : '';
                break;
            case 'number':
                $raw = trim($raw);
                $val = preg_match('/^-?\d{1,12}([.,]\d{1,6})?$/', $raw) ? str_replace(',', '.', $raw) : '';
                break;
            case 'textarea':
                $val = mb_substr(sanitize_textarea_field($raw), 0, 1000);
                break;
            default:
                $val = mb_substr(sanitize_text_field($raw), 0, 300);
        }
        $v['x'][$cf['id']] = $val;
        if ($cf['req'] && $val === '') {
            $err('e_req', $cf['label']);
        }
        if ($val !== '') {
            $extra[] = array($cf['label'], $cf['type'] === 'checkbox' ? '✓' : $val);
        }
    }
    if (petgo_p('consent') !== '1') {
        $err('e_consent');
    }
    global $wpdb;
    $dup = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . petgo_tb('reqs') . ' WHERE phone=%s AND ts>%d LIMIT 1', '+' . $d, time() - 600));
    if (!$dup) {
        $wpdb->insert(petgo_tb('reqs'), array(
            'name' => $v['name'], 'phone' => '+' . $d, 'contact' => $v['contact'], 'species' => $v['species'],
            'sp_other' => $v['species'] === 'other' ? $v['other'] : '', 'weight' => $v['weight'], 'breed' => $v['breed'],
            'note' => $v['note'], 'extra' => $extra ? mb_substr(wp_json_encode($extra, JSON_UNESCAPED_UNICODE), 0, 6000) : '', 'status' => 'new', 'ts' => time(),
        ), array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'));
    }
    set_transient($ik, (int) get_transient($ik) + 1, HOUR_IN_SECONDS);
    petgo_bump();
    if (!$dup) {
        petgo_push_notify(petgo_push_users(array('owner', 'admin', 'manager')), 'Новая заявка', $v['name'] . ' · +' . $d, petgo_page_url(array('pg' => 'requests')));
    }
    $go('ok');
}

/* ---------- сохранение из редактора ---------- */
add_action('admin_post_petgo_form_save', 'petgo_h_form_save');
function petgo_h_form_save() {
    petgo_guard('admin');
    $raw = (string) petgo_p('cfg_json');
    $j   = (strlen($raw) < 60000) ? json_decode($raw, true) : null;
    if (!is_array($j)) {
        petgo_fail('err_fields', 'form');
    }
    update_option('petgo_form', petgo_form_clean($j), false);
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('form');
}

add_action('admin_post_petgo_form_reset', 'petgo_h_form_reset');
function petgo_h_form_reset() {
    petgo_guard('admin');
    delete_option('petgo_form');
    petgo_flash(petgo_t('ok_saved'));
    petgo_back('form');
}

/* ---------- страница-редактор в кабинете ---------- */
function petgo_form_srcdoc($cfg) {
    $h  = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body{margin:0}body{padding:24px 14px;background:#e9e9ee;font-family:system-ui,sans-serif}</style></head><body>';
    $h .= petgo_form_render($cfg, array(), '', true);
    return $h . '</body></html>';
}

function petgo_admin_form() {
    if (!petgo_can('admin')) {
        echo '<p class="mu">' . esc_html(petgo_t('err_perm')) . '</p>';
        return;
    }
    $cfg   = petgo_form_cfg();
    $ft    = array();
    $fn    = array();
    foreach (petgo_form_fonts() as $k => $x) {
        $ft[$k] = $x[1];
        $fn[$k] = $x[0];
    }
    $bi    = array();
    foreach (petgo_form_builtin() as $k => $b) {
        $bi[$k] = array('hide' => $b[2] ? 0 : 1, 'reqlock' => $b[3] ? 0 : 1);
    }
    $state = array('c' => $cfg, 'd' => petgo_form_defaults(), 'fonts' => $ft, 'fontNames' => $fn, 'types' => petgo_form_ctypes(), 'bi' => $bi);
    $js    = '';
    $f     = __DIR__ . '/../assets/formeditor.js';
    if (is_readable($f)) {
        $js = (string) file_get_contents($f);
    }
    echo '<div class="pgc-fe" id="pgc-fe">';
    echo '<form class="pgc-fe-ctl pgc-panel" id="pgc-fe-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="petgo_form_save">' . wp_nonce_field('petgo_act', '_wpnonce', true, false) . '<input type="hidden" name="cfg_json" id="pgc-fe-json" value="">';
    echo '<div class="pgc-chips" id="pgc-fe-tabs"></div><div id="pgc-fe-pane"></div>';
    echo '<div class="pgc-fe-save"><button type="submit" class="pgc-btn">' . esc_html(petgo_t('save')) . '</button></div></form>';
    echo '<div class="pgc-fe-pv"><div class="pgc-panel"><div class="pgc-row" id="pgc-fe-bar"></div>';
    echo '<div class="pgc-fe-stage" id="pgc-fe-stage"><iframe id="pgc-fe-frame" title="preview" srcdoc="' . esc_attr(petgo_form_srcdoc($cfg)) . '"></iframe></div>';
    echo '<p class="mu" style="margin:12px 0 0">' . esc_html('Вставьте на страницу сайта шорткод: ') . '<code>[petgo_request]</code></p></div>';
    echo petgo_form_reset_html();
    echo '</div></div>';
    echo '<script>window.PGFE=' . wp_json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';</script>';
    echo '<script>' . $js . '</script>';
}

function petgo_form_reset_html() {
    return petgo_form_open('form_reset', 'style="margin-top:12px"') . '<button type="submit" class="pgc-btn sec" onclick="return confirm(\'Вернуть форму к исходному виду?\')">Сбросить к исходному</button></form>';
}
