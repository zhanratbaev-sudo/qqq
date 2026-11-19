<?php
if (!defined('ABSPATH')) {
    exit;
}

/* ============================================================
   PDF ДЛЯ ДОГОВОРА: свой небольшой генератор (A4, шрифт Inter с кириллицей и казахскими буквами).
   Без сторонних библиотек: буквы встраиваются в файл, текст в PDF можно выделить и найти.
   Шрифты собраны tools/build-pdf-fonts.py (лицензия OFL — includes/pdf-fonts/OFL.txt).
   ============================================================ */
class PetGo_PDF {
    public $w = 595.28;
    public $h = 841.89;
    public $ml = 58;
    public $mr = 58;
    public $mt = 62;
    public $mb = 70;
    public $y = 0;
    public $foot = '';
    private $pages = array();
    private $cur = null;
    private $fonts = array();
    private $used = array();
    private $info = array();

    public function __construct($info = array()) {
        $this->info = $info;
        foreach (array('r', 'sb') as $k) {
            $this->fonts[$k] = require __DIR__ . '/pdf-fonts/inter-' . $k . '.php';
            $this->used[$k]  = array();
        }
    }

    /* ---------- буквы и ширины ---------- */
    private function chars($s) {
        $s = (string) $s;
        if (function_exists('mb_str_split')) {
            return mb_str_split($s, 1, 'UTF-8');
        }
        return preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
    }
    private function cp($ch) {
        if (function_exists('mb_ord')) {
            return mb_ord($ch, 'UTF-8');
        }
        $u = unpack('N', mb_convert_encoding($ch, 'UCS-4BE', 'UTF-8'));
        return $u[1];
    }
    private function glyph($k, $u) {
        $m = $this->fonts[$k]['map'];
        if (isset($m[$u])) {
            return $m[$u];
        }
        /* нет буквы: неразрывный пробел → пробел, похожие кавычки и тире → обычные, иначе «?» */
        $alt = array(0xA0 => 0x20, 0x2011 => 0x2D, 0x2010 => 0x2D, 0x202F => 0x20, 0x2009 => 0x20, 0x2212 => 0x2D);
        if (isset($alt[$u]) && isset($m[$alt[$u]])) {
            return $m[$alt[$u]];
        }
        return isset($m[0x3F]) ? $m[0x3F] : array(0, $this->fonts[$k]['dw']);
    }
    public function width($s, $k, $size) {
        $w = 0;
        foreach ($this->chars($s) as $ch) {
            $g  = $this->glyph($k, $this->cp($ch));
            $w += $g[1];
        }
        return $w * $size / 1000;
    }
    private function hex($s, $k) {
        $o = '';
        foreach ($this->chars($s) as $ch) {
            $u = $this->cp($ch);
            $g = $this->glyph($k, $u);
            if (!isset($this->used[$k][$g[0]])) {
                $this->used[$k][$g[0]] = $u;
            }
            $o .= sprintf('%04X', $g[0]);
        }
        return $o;
    }

    /* ---------- страницы ---------- */
    public function page() {
        if ($this->cur !== null) {
            $this->pages[] = $this->cur;
        }
        $this->cur = '';
        $this->y   = $this->h - $this->mt;
    }
    private function room($need) {
        if ($this->cur === null || $this->y - $need < $this->mb) {
            $this->page();
        }
    }
    private function color($rgb) {
        return sprintf('%.3F %.3F %.3F rg ', $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255);
    }
    public function text($x, $y, $s, $k = 'r', $size = 10, $rgb = array(26, 26, 26)) {
        if ($s === '') {
            return;
        }
        $this->cur .= 'BT ' . $this->color($rgb) . '/F' . $k . ' ' . sprintf('%.2F', $size) . ' Tf ' . sprintf('%.2F %.2F', $x, $y) . ' Td <' . $this->hex($s, $k) . "> Tj ET\n";
    }
    public function line($x1, $y1, $x2, $y2, $rgb = array(210, 214, 210), $wd = 0.6) {
        $this->cur .= sprintf("%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S\n", $rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255, $wd, $x1, $y1, $x2, $y2);
    }
    public function rect($x, $y, $w, $h, $rgb) {
        $this->cur .= $this->color($rgb) . sprintf("%.2F %.2F %.2F %.2F re f\n", $x, $y, $w, $h);
    }
    public function gap($pt) {
        $this->y -= $pt;
    }

    /* «**жирный** обычный» → кусочки текста со своим шрифтом */
    public static function runs($s, $base = 'r') {
        $out = array();
        foreach (preg_split('/(\*\*.+?\*\*)/u', (string) $s, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $p) {
            if (strlen($p) > 4 && substr($p, 0, 2) === '**' && substr($p, -2) === '**') {
                $out[] = array(substr($p, 2, -2), 'sb');
            } else {
                $out[] = array($p, $base);
            }
        }
        return $out;
    }

    /* абзац: перенос по словам, новая страница, когда место кончилось */
    public function para($s, $o = array()) {
        $o    = array_merge(array('size' => 10, 'lead' => 14.6, 'font' => 'r', 'rgb' => array(26, 26, 26), 'after' => 6, 'indent' => 0, 'keep' => 0), $o);
        $maxw = $this->w - $this->ml - $this->mr - $o['indent'];
        $toks = array();
        foreach (is_array($s) ? $s : self::runs($s, $o['font']) as $r) {
            foreach (preg_split('/( +)/u', $r[0], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $t) {
                $toks[] = array($t, $r[1], trim($t) === '');
            }
        }
        $lines = array();
        $ln    = array();
        $lw    = 0;
        foreach ($toks as $t) {
            $tw = $this->width($t[0], $t[1], $o['size']);
            if ($t[2]) {
                if ($ln) {
                    $ln[] = array(' ', $t[1], $tw);
                    $lw  += $tw;
                }
                continue;
            }
            if ($lw + $tw > $maxw && $ln) {
                while ($ln && $ln[count($ln) - 1][0] === ' ') {
                    array_pop($ln);
                }
                $lines[] = $ln;
                $ln      = array();
                $lw      = 0;
            }
            /* слово длиннее строки — режем по буквам */
            if ($tw > $maxw) {
                $part = '';
                foreach ($this->chars($t[0]) as $ch) {
                    if ($this->width($part . $ch, $t[1], $o['size']) > $maxw && $part !== '') {
                        $lines[] = array(array($part, $t[1], 0));
                        $part    = '';
                    }
                    $part .= $ch;
                }
                $ln = array(array($part, $t[1], $this->width($part, $t[1], $o['size'])));
                $lw = $ln[0][2];
                continue;
            }
            $ln[] = array($t[0], $t[1], $tw);
            $lw  += $tw;
        }
        if ($ln) {
            $lines[] = $ln;
        }
        /* заголовок не остаётся один внизу страницы */
        $this->room($o['lead'] * max(1, min(count($lines), 2)) + $o['keep']);
        foreach ($lines as $l) {
            $this->room($o['lead']);
            $x = $this->ml + $o['indent'];
            $this->y -= $o['lead'];
            foreach ($l as $p) {
                $this->text($x, $this->y + ($o['lead'] - $o['size']) * 0.35, $p[0], $p[1], $o['size'], $o['rgb']);
                $x += $p[2];
            }
        }
        $this->y -= $o['after'];
    }

    /* ---------- сборка файла ---------- */
    public function output() {
        if ($this->cur !== null) {
            $this->pages[] = $this->cur;
            $this->cur     = null;
        }
        $n = count($this->pages);
        foreach ($this->pages as $i => $c) {
            if ($this->foot !== '') {
                $this->cur = '';
                $this->line($this->ml, 46, $this->w - $this->mr, 46);
                $this->text($this->ml, 32, $this->foot, 'r', 7.5, array(120, 128, 126));
                $pg = 'стр. ' . ($i + 1) . ' из ' . $n;
                $this->text($this->w - $this->mr - $this->width($pg, 'r', 7.5), 32, $pg, 'r', 7.5, array(120, 128, 126));
                $this->pages[$i] = $c . $this->cur;
                $this->cur       = null;
            }
        }
        $objs  = array();
        $add   = function ($body) use (&$objs) {
            $objs[] = $body;
            return count($objs);
        };
        $stream = function ($dict, $data) {
            $z = gzcompress($data, 9);
            return '<< ' . $dict . ' /Filter /FlateDecode /Length ' . strlen($z) . " >>\nstream\n" . $z . "\nendstream";
        };
        $catalog = $add('');
        $pagesId = $add('');
        $fontIds = array();
        foreach ($this->fonts as $k => $f) {
            /* встроенная часть шрифта: имя с меткой из 6 заглавных букв */
            $base = ($k === 'r' ? 'PGKRAA' : 'PGKSBA') . '+' . $f['name'];
            $ttf  = file_get_contents(__DIR__ . '/pdf-fonts/inter-' . $k . '.ttf');
            $file = $add($stream('/Length1 ' . strlen($ttf), $ttf));
            $desc = $add('<< /Type /FontDescriptor /FontName /' . $base . ' /Flags 32 /FontBBox [' . implode(' ', $f['bbox']) . '] /ItalicAngle 0 /Ascent ' . $f['asc'] . ' /Descent ' . $f['desc'] . ' /CapHeight ' . $f['cap'] . ' /StemV ' . ($k === 'sb' ? 120 : 80) . ' /FontFile2 ' . $file . ' 0 R >>');
            ksort($this->used[$k]);
            $w = '';
            foreach ($this->used[$k] as $gid => $u) {
                $w .= $gid . ' [' . (isset($f['gw'][$gid]) ? $f['gw'][$gid] : $f['dw']) . '] ';
            }
            $cid = $add('<< /Type /Font /Subtype /CIDFontType2 /BaseFont /' . $base . ' /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor ' . $desc . ' 0 R /DW ' . $f['dw'] . ' /W [' . trim($w) . '] /CIDToGIDMap /Identity >>');
            /* ToUnicode: текст из PDF копируется и ищется */
            $cm  = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n";
            $chunks = array_chunk($this->used[$k], 100, true);
            foreach ($chunks as $ch) {
                $cm .= count($ch) . " beginbfchar\n";
                foreach ($ch as $gid => $u) {
                    $cm .= sprintf('<%04X> <%s>', $gid, strtoupper(bin2hex(mb_convert_encoding(self::chr($u), 'UTF-16BE', 'UTF-8')))) . "\n";
                }
                $cm .= "endbfchar\n";
            }
            $cm .= "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
            $tu  = $add($stream('', $cm));
            $fontIds[$k] = $add('<< /Type /Font /Subtype /Type0 /BaseFont /' . $base . ' /Encoding /Identity-H /DescendantFonts [' . $cid . ' 0 R] /ToUnicode ' . $tu . ' 0 R >>');
        }
        $res  = '<< /Font << /Fr ' . $fontIds['r'] . ' 0 R /Fsb ' . $fontIds['sb'] . ' 0 R >> /ProcSet [/PDF /Text] >>';
        $kids = array();
        foreach ($this->pages as $c) {
            $cs     = $add($stream('', $c));
            $kids[] = $add('<< /Type /Page /Parent ' . $pagesId . ' 0 R /MediaBox [0 0 ' . sprintf('%.2F %.2F', $this->w, $this->h) . '] /Resources ' . $res . ' /Contents ' . $cs . ' 0 R >>');
        }
        $objs[$pagesId - 1] = '<< /Type /Pages /Kids [' . implode(' 0 R ', $kids) . ' 0 R] /Count ' . count($kids) . ' >>';
        $objs[$catalog - 1] = '<< /Type /Catalog /Pages ' . $pagesId . ' 0 R /Lang (ru-KZ) >>';
        $info = array();
        foreach (array('Title' => 'title', 'Author' => 'author', 'Subject' => 'subject', 'Creator' => 'creator') as $pk => $ik) {
            if (!empty($this->info[$ik])) {
                $info[] = '/' . $pk . ' <FEFF' . strtoupper(bin2hex(mb_convert_encoding((string) $this->info[$ik], 'UTF-16BE', 'UTF-8'))) . '>';
            }
        }
        $ts     = isset($this->info['time']) ? (int) $this->info['time'] : time();
        $info[] = '/Producer (PetGo)';
        $info[] = '/CreationDate (D:' . gmdate('YmdHis', $ts) . "Z)";
        $infoId = $add('<< ' . implode(' ', $info) . ' >>');
        $pdf    = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $off    = array();
        foreach ($objs as $i => $b) {
            $off[] = strlen($pdf);
            $pdf  .= ($i + 1) . " 0 obj\n" . $b . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($off as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $id   = md5($pdf);
        $pdf .= 'trailer << /Size ' . (count($objs) + 1) . ' /Root ' . $catalog . ' 0 R /Info ' . $infoId . ' 0 R /ID [<' . $id . '> <' . $id . "> ] >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $pdf;
    }

    public static function chr($u) {
        if (function_exists('mb_chr')) {
            return mb_chr($u, 'UTF-8');
        }
        return mb_convert_encoding(pack('N', $u), 'UTF-8', 'UCS-4BE');
    }
}
