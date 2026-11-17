/*!
 * PetGo ИИ — чат‑помощник (работает вместе с плагином «PetGo Кабинет»).
 * Ключ ИИ хранится на сервере и в браузер не попадает.
 */
(function () {
	'use strict';
	function boot() {
		var layer = document.getElementById('pg-layer');
		var chat = document.getElementById('pga-chat');
		if (!layer || !chat || chat.getAttribute('data-ready')) return;
		chat.setAttribute('data-ready', '1');
		var C = window.PGA || {};
		C.i18n = C.i18n || {};
		var $ = function (s, r) { return (r || document).querySelector(s); };
		var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
		var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var coarse = window.matchMedia('(pointer: coarse)').matches;
		var sstore = {
			get: function (k) { try { return JSON.parse(window.sessionStorage.getItem(k) || 'null'); } catch (e) { return null; } },
			set: function (k, v) { try { window.sessionStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
		};
		/* адреса: REST (может быть «?rest_route=…») и запасной admin-ajax */
		function rest(path, qs) { var b = C.rest || '/wp-json/petgo/v1/'; return b + path + (qs ? (b.indexOf('?') > -1 ? '&' : '?') + qs : ''); }
		function ajax(action, qs) { return (C.ajax || '/wp-admin/admin-ajax.php') + '?action=' + action + (qs ? '&' + qs : ''); }

		var log = $('#pga-log'), form = $('#pga-form'), input = $('#pga-in'), chips = $('#pga-chips');
		var KEY = 'pga_chat_' + (C.lang || 'ru');
		var state = sstore.get(KEY) || { session: Math.random().toString(36).slice(2) + Date.now().toString(36), messages: [] };
		var busy = false;
		function save() { sstore.set(KEY, state); }
		function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
		var L10 = C.lang === 'en'
			? { tr: 'Transcribing…', open: 'Open', mic: 'Allow microphone access in your browser settings.', micNo: 'Voice messages are not available in this browser.', cancel: 'Cancel', short: 'Too short — please say it again.', mgr: 'Manager' }
			: C.lang === 'kz'
			?{ tr: 'Транскрипция…', open: 'Ашу', mic: 'Микрофонға рұқсат беріңіз — браузер баптауларында.', micNo: 'Бұл браузерде дауыстық хабарлама жоқ.', cancel: 'Болдырмау', short: 'Тым қысқа — батырманы басып тұрып сөйлеңіз.', mgr: 'Менеджер' }
			: { tr: 'Расшифровка…', open: 'Открыть', mic: 'Разрешите доступ к микрофону в настройках браузера.', micNo: 'В этом браузере голосовые недоступны.', cancel: 'Отмена', short: 'Слишком коротко — скажите ещё раз.', mgr: 'Менеджер' };
		var ARROW = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8"/></svg>';

		/* подсказки‑вопросы */
		(C.chips || []).forEach(function (t) {
			var b = document.createElement('button');
			b.type = 'button'; b.className = 'pga-chip'; b.textContent = t;
			chips.appendChild(b);
		});

		/* Незаконченная разметка в конце (пока текст ещё печатается) не показывается */
		function clean(t, partial) {
			t = String(t).replace(/\[\[(BOOK|MANAGER|EMERGENCY)\]\]/g, '');
			if (partial) t = t.replace(/\[\[[A-Z]*\]?$/, '').replace(/\[[^\]\n]*(\]\([^)\n]*)?$/, '').replace(/https?:\/\/\S*$/, '').replace(/\*\*?$/, '');
			return t;
		}
		function fmt(t) {
			var tok = [];
			var put = function (h) { tok.push(h); return '\u0001' + (tok.length - 1) + '\u0002'; };
			var safe = function (u) { return /^(https?:\/\/|tel:)/i.test(u) ? u : ''; };
			var x = esc(t)
				.replace(/\[([^\]\n]+)\]\(((?:https?:\/\/|tel:)[^\s)]+)\)/g, function (m, label, u) {
					return put('<a class="pga-a" href="' + safe(u) + '" target="_blank" rel="noopener">' + label + ARROW + '</a>');
				})
				.replace(/(^|[\s(])(https?:\/\/[^\s<)]+[^\s<).,!?;:])/g, function (m, pre, u) {
					return pre + put('<a class="pga-a" href="' + u + '" target="_blank" rel="noopener">' + L10.open + ARROW + '</a>');
				});
			var lines = x.replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').split(/\n/);
			var out = '', list = false;
			lines.forEach(function (l) {
				var m = l.match(/^\s*(?:[-•*]|\d+[.)])\s+(.*)$/);
				if (m) { if (!list) { out += '<ul>'; list = true; } out += '<li>' + m[1] + '</li>'; return; }
				if (list) { out += '</ul>'; list = false; }
				if (l.trim()) out += '<p>' + l + '</p>';
			});
			if (list) out += '</ul>';
			return out.replace(/(\+?7[\s(]*\d{3}[)\s]*\d{3}[\s-]*\d{2}[\s-]*\d{2})/g, function (p) { return '<a href="tel:+' + p.replace(/\D/g, '').replace(/^8/, '7') + '">' + p + '</a>'; })
				.replace(/\u0001(\d+)\u0002/g, function (m, n) { return tok[+n]; })
				.replace(/<p>\s*<\/p>/g, '');
		}
		function cta() {
			var d = document.createElement('div');
			d.className = 'pga-msg__cta';
			d.innerHTML = '<button type="button" class="pga-btn pga-btn--pri" data-chat-book>' + esc(C.i18n.book || 'Оставить заявку') + '</button>' +
				(C.wa ? '<a class="pga-btn pga-btn--wa" target="_blank" rel="noopener" href="https://wa.me/' + esc(C.wa) + '?text=' + encodeURIComponent(C.waText || '') + '">WhatsApp</a>' : '');
			return d;
		}

		function bubble(role, text, book, who) {
			var el = document.createElement('div');
			if (role === 'system') {
				el.className = 'pga-msg pga-msg--sys';
				el.textContent = text;
				log.appendChild(el);
				log.scrollTop = log.scrollHeight;
				return el;
			}
			el.className = 'pga-msg pga-msg--' + (role === 'user' ? 'me' : role === 'err' ? 'err' : role === 'manager' ? 'mgr' : 'bot');
			el.innerHTML = (role === 'manager' ? '<span class="pga-msg__who">' + esc(who || L10.mgr) + '</span>' : '') + (role === 'user' ? '<p>' + esc(text) + '</p>' : fmt(clean(text)));
			if (book) el.appendChild(cta());
			log.appendChild(el);
			log.scrollTop = log.scrollHeight;
			return el;
		}

		function render() {
			log.innerHTML = '';
			bubble('bot', C.greeting || '');
			state.messages.forEach(function (m) { bubble(m.role === 'user' ? 'user' : (m.role === 'manager' || m.role === 'system' ? m.role : 'bot'), m.text, m.book, m.who); });
			chips.hidden = state.messages.length > 0;
		}

		var opened = false;
		function open() {
			if (opened) return;
			opened = true;
			if (window.PGSheet) window.PGSheet.close();
			chat.hidden = false;
			chat.classList.remove('is-closing');
			layer.classList.add('ai-open');
			document.documentElement.classList.add('pga-lock'); // сайт под чатом не прокручивается и не нажимается
			render();
			if (state.waiting) startPoll();
			/* на телефоне сразу открывается клавиатура сайта, на компьютере — курсор в поле */
			setTimeout(function () { input.focus({ preventScroll: true }); }, coarse ? 360 : 200);
		}
		function close() {
			if (!opened) return;
			opened = false;
			stopPoll();
			if (rec) recStop(false);
			input.blur();
			chat.classList.add('is-closing');
			layer.classList.remove('ai-open');
			setTimeout(function () {
				chat.hidden = true;
				chat.classList.remove('is-closing');
				document.documentElement.classList.remove('pga-lock');
			}, reduceMotion ? 0 : 280);
		}
		window.PGCloseAI = close;
		window.PGOpenAI = open;

		/* Живой менеджер: опрос новых сообщений, пока разговор у менеджера */
		var pollT = 0, pollDelay = 4000;
		function pollOnce() {
			var qs = 'session=' + encodeURIComponent(state.session) + '&after=' + (state.lastId || 0);
			var get = function (url) { return fetch(url, { credentials: 'omit' }).then(function (r) { if (!r.ok) throw new Error(); return r.json(); }); };
			return get(rest('chat/poll', qs)).catch(function () { return get(ajax('pga_chat_poll', qs)); })
				.then(function (j) {
					if (!j) return;
					(j.messages || []).forEach(function (m) {
						state.lastId = Math.max(state.lastId || 0, +m.id);
						state.messages.push({ role: m.role, text: m.message, who: j.manager });
						bubble(m.role, m.message, false, j.manager);
					});
					if (j.status === 'ai') { state.waiting = false; stopPoll(); }
					save();
				}).catch(function () {});
		}
		function startPoll() {
			stopPoll();
			pollDelay = 4000;
			var tick = function () {
				var before = state.messages.length;
				var go = (!document.hidden && !chat.hidden) ? pollOnce() : Promise.resolve();
				Promise.resolve(go).then(function () {
					if (!pollT) return;
					pollDelay = state.messages.length > before ? 4000 : Math.min(20000, Math.round(pollDelay * 1.4));
					pollT = setTimeout(tick, pollDelay);
				});
			};
			pollT = setTimeout(tick, 50);
		}
		function stopPoll() { clearTimeout(pollT); pollT = 0; }

		/* Имя и телефон из диалога — чтобы заполнить заявку */
		function extract() {
			var mine = state.messages.filter(function (m) { return m.role === 'user'; }).map(function (m) { return m.text; });
			var txt = mine.join('\n');
			var ph = txt.match(/(\+?[78][\s(-]*\d{3}[)\s-]*\d{3}[\s-]*\d{2}[\s-]*\d{2})/);
			var nm = txt.match(/(?:меня зовут|я\s*[-—–]?|имя[:\s]+|менің атым|атым)\s*([А-ЯЁӘІҢҒҮҰҚӨҺA-Z][а-яёәіңғүұқөһa-z]+)/i);
			var topics = mine.slice(-3).join(' | ');
			return { phone: ph ? ph[1] : '', name: nm ? nm[1] : '', note: topics ? ('Из чата с помощником: ' + topics).slice(0, 400) : '' };
		}
		function openBook(p) {
			if (!window.PGSheet) return;
			window.PGSheet.open('request');
			var f = document.querySelector('#pg-sheet-request form');
			if (!f || !p) return;
			if (p.name && f.elements.name && !f.elements.name.value) f.elements.name.value = p.name;
			if (p.phone && f.elements.phone && !f.elements.phone.value) f.elements.phone.value = p.phone;
			if (p.note && f.elements.note && !f.elements.note.value) f.elements.note.value = p.note;
		}

		/* Текст ИИ «печатается»: появляется постепенно, по мере прихода с сервера */
		function Typer(el) {
			var target = '', shown = 0, acc = 0, done = false, raf = 0, onEnd = null;
			function frame() {
				raf = 0;
				var back = target.length - shown;
				if (back > 0) {
					acc += Math.min(18, Math.max(reduceMotion ? 999 : 1.6, back / 12));
					var n = Math.floor(acc);
					if (n > 0) { acc -= n; shown = Math.min(target.length, shown + n); }
					el.innerHTML = fmt(clean(target.slice(0, shown), shown < target.length || !done)) + '<i class="pga-caret"></i>';
					stick();
				}
				if (shown < target.length || !done) { raf = requestAnimationFrame(frame); return; }
				el.innerHTML = fmt(clean(target));
				stick();
				if (onEnd) onEnd();
			}
			return {
				push: function (t) { target += t; if (!raf) raf = requestAnimationFrame(frame); },
				end: function (full, cb) { if (typeof full === 'string' && full.length >= target.length) target = full; done = true; onEnd = cb; if (!raf) raf = requestAnimationFrame(frame); }
			};
		}
		function stick() { if (log.scrollHeight - log.scrollTop - log.clientHeight < 140) log.scrollTop = log.scrollHeight; }

		/* Ответ по частям (SSE). Если сервер/хостинг не умеет потоком — обычный запрос целиком. */
		function streamCall(url, payload, onDelta) {
			return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream' }, credentials: 'omit', body: payload }).then(function (r) {
				var ct = r.headers.get('content-type') || '';
				if (!r.ok || ct.indexOf('event-stream') < 0 || !r.body || !window.TextDecoder) throw new Error('nostream');
				var rd = r.body.getReader(), dec = new TextDecoder(), buf = '', fin = null;
				return (function pump() {
					return rd.read().then(function (x) {
						if (!x.done) buf += dec.decode(x.value, { stream: true });
						var parts = buf.split('\n\n');
						buf = x.done ? '' : parts.pop();
						parts.forEach(function (ev) {
							ev.split('\n').forEach(function (line) {
								if (line.indexOf('data:') !== 0) return;
								var j = null; try { j = JSON.parse(line.slice(5)); } catch (e) { return; }
								if (j.t) onDelta(j.t);
								else if (j.done || j.error) fin = j;
							});
						});
						if (x.done || fin) { if (!fin) throw new Error('cut'); return fin; }
						return pump();
					});
				})();
			});
		}

		var sendBtn = $('.pga-chat__send', form);
		function send(text, opts) {
			opts = opts || {};
			text = (text || '').trim();
			if (!text || (busy && !opts.el)) return;
			busy = true;
			chips.hidden = true;
			state.messages.push({ role: 'user', text: text });
			save();
			if (!opts.el) bubble('user', text);
			setInput('');
			var typing = document.createElement('div');
			typing.className = 'pga-typing';
			typing.innerHTML = '<i></i><i></i><i></i>';
			log.appendChild(typing);
			log.scrollTop = log.scrollHeight;
			sendBtn.disabled = true;

			var payload = JSON.stringify({
				session: state.session,
				pv: state.pv || '',
				lang: C.lang || 'ru',
				messages: state.messages.slice(-14).map(function (m) { return { role: m.role, text: m.text }; })
			});
			var call = function (url) {
				return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'omit', body: payload })
					.then(function (r) { return r.text().then(function (t) { var j = null; try { j = JSON.parse(t); } catch (e) { } return { ok: r.ok && !!j, j: j, status: r.status }; }); });
			};
			var el = null, ty = null;
			var onDelta = function (t) {
				if (!el) {
					typing.remove();
					el = document.createElement('div');
					el.className = 'pga-msg pga-msg--bot is-typing';
					log.appendChild(el);
					ty = Typer(el);
				}
				ty.push(t);
			};
			var whole = function () {
				/* REST; если закрыт (плагин безопасности, 401/403/404 или не JSON) — запасной канал admin-ajax */
				return call(rest('chat')).then(function (res) {
					if (res.ok || (res.j && res.j.message && (res.status === 429 || res.status === 502))) return res;
					return call(ajax('pga_chat'));
				}, function () { return call(ajax('pga_chat')); })
					.then(function (res) {
						if (!res.ok || !res.j) throw new Error((res.j && res.j.message) || 'error');
						return res.j;
					});
			};
			var streamed = streamCall(rest('chat/stream'), payload, onDelta)
				.catch(function (e) {
					if (el) throw e; // поток оборвался посреди ответа
					if (e && e.message === 'nostream') return streamCall(ajax('pga_chat_stream'), payload, onDelta);
					throw e;
				})
				.catch(function (e) { if (el) throw e; return whole(); });

			streamed.then(function (j) {
				if (j.voice === false) voiceOff(); else if (j.voice === true) voiceOn();
				if (j.error) throw new Error(j.error);
				if (j.queued) { typing.remove(); state.waiting = true; save(); if (!pollT) startPoll(); return; } // разговор у менеджера
				var reply = j.reply || '';
				if (!reply) throw new Error('error');
				if (j.pv) state.pv = j.pv;
				state.messages.push({ role: 'model', text: reply, book: !!j.book });
				save();
				if (!el) onDelta('');
				return new Promise(function (resolve) {
					ty.end(reply, function () {
						el.classList.remove('is-typing');
						if (j.book) { el.appendChild(cta()); stick(); }
						resolve();
					});
				}).then(function () { if (j.escalated) { state.waiting = true; save(); startPoll(); } });
			})
				.catch(function (err) {
					typing.remove();
					if (el) el.remove();
					state.messages.pop();
					save();
					if (opts.el) opts.el.remove();
					bubble('err', (err && err.message && ['error', 'nostream', 'cut', 'Failed to fetch', 'Load failed'].indexOf(err.message) < 0 && err.message.length < 200) ? err.message : (C.i18n.err || 'Ошибка связи.'));
				})
				.then(function () {
					busy = false;
					sendBtn.disabled = false;
				});
		}

		/* ---------- Голосовые сообщения ----------
		 * Запись в WAV 16 кГц прямо в браузере (работает и на iPhone), расшифровка — на сервере через Gemini. */
		var mic = $('.pga-chat__mic', form);
		var rec = null;
		function Recorder() {
			var AC = window.AudioContext || window.webkitAudioContext;
			var ctx = new AC(), chunks = [], stream = null, src = null, proc = null, an = null, t0 = 0;
			return {
				start: function () {
					return navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } }).then(function (st) {
						stream = st;
						if (ctx.state === 'suspended') ctx.resume();
						src = ctx.createMediaStreamSource(st);
						an = ctx.createAnalyser(); an.fftSize = 64; an.smoothingTimeConstant = 0.6;
						proc = ctx.createScriptProcessor(4096, 1, 1);
						proc.onaudioprocess = function (e) { chunks.push(new Float32Array(e.inputBuffer.getChannelData(0))); };
						src.connect(an); src.connect(proc); proc.connect(ctx.destination);
						t0 = Date.now();
					});
				},
				level: function (arr) { if (an) an.getByteFrequencyData(arr); return arr; },
				get seconds() { return t0 ? (Date.now() - t0) / 1000 : 0; },
				stop: function (keep) {
					try { if (proc) { proc.disconnect(); proc.onaudioprocess = null; } if (src) src.disconnect(); } catch (e) {}
					if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
					var rate = ctx.sampleRate;
					try { ctx.close(); } catch (e) {}
					if (!keep) return null;
					var len = chunks.reduce(function (a, c) { return a + c.length; }, 0);
					var all = new Float32Array(len), o = 0;
					chunks.forEach(function (c) { all.set(c, o); o += c.length; });
					var ratio = rate / 16000, n = Math.floor(len / ratio), pcm = new DataView(new ArrayBuffer(44 + n * 2));
					var w = function (p, str) { for (var i = 0; i < str.length; i++) pcm.setUint8(p + i, str.charCodeAt(i)); };
					w(0, 'RIFF'); pcm.setUint32(4, 36 + n * 2, true); w(8, 'WAVE'); w(12, 'fmt '); pcm.setUint32(16, 16, true);
					pcm.setUint16(20, 1, true); pcm.setUint16(22, 1, true); pcm.setUint32(24, 16000, true); pcm.setUint32(28, 32000, true);
					pcm.setUint16(32, 2, true); pcm.setUint16(34, 16, true); w(36, 'data'); pcm.setUint32(40, n * 2, true);
					for (var i = 0; i < n; i++) {
						var a = Math.floor(i * ratio), b = Math.min(len, Math.floor((i + 1) * ratio)), sum = 0;
						for (var k = a; k < b; k++) sum += all[k];
						var v = Math.max(-1, Math.min(1, sum / Math.max(1, b - a)));
						pcm.setInt16(44 + i * 2, v < 0 ? v * 0x8000 : v * 0x7FFF, true);
					}
					var bytes = new Uint8Array(pcm.buffer), bin = '';
					for (var q = 0; q < bytes.length; q += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(q, q + 0x8000));
					return btoa(bin);
				}
			};
		}
		var recBar = null, recRaf = 0;
		function recUI(on) {
			form.classList.toggle('is-rec', on);
			if (on) input.blur();
			if (!recBar) {
				recBar = document.createElement('div');
				recBar.className = 'pga-rec';
				var bars = ''; for (var i = 0; i < 28; i++) bars += '<i></i>';
				recBar.innerHTML = '<button type="button" class="pga-rec__x" aria-label="' + esc(L10.cancel) + '"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>' +
					'<span class="pga-rec__dot"></span><span class="pga-rec__time">0:00</span><span class="pga-rec__wave">' + bars + '</span>' +
					'<button type="button" class="pga-rec__send" aria-label="' + esc(C.i18n.send || 'Send') + '"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg></button>';
				form.appendChild(recBar);
				$('.pga-rec__x', recBar).addEventListener('click', function () { recStop(false); });
				$('.pga-rec__send', recBar).addEventListener('click', function () { recStop(true); });
			}
		}
		function recLoop() {
			var bars = $$('.pga-rec__wave i', recBar), arr = new Uint8Array(32), t = $('.pga-rec__time', recBar);
			(function f() {
				if (!rec) return;
				rec.level(arr);
				bars.forEach(function (b, i) { var v = arr[(i % 14) + 1] / 255; b.style.transform = 'scaleY(' + Math.max(0.12, Math.min(1, v * 1.4)).toFixed(3) + ')'; });
				var sec = rec.seconds;
				t.textContent = Math.floor(sec / 60) + ':' + ('0' + Math.floor(sec % 60)).slice(-2);
				if (sec >= 60) { recStop(true); return; }
				recRaf = requestAnimationFrame(f);
			})();
		}
		function recStart() {
			if (busy || rec) return;
			if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !(window.AudioContext || window.webkitAudioContext)) { bubble('system', L10.micNo); return; }
			rec = Recorder(); // AudioContext создаётся прямо по нажатию (требование iPhone)
			recUI(true);
			rec.start().then(recLoop, function () { rec = null; recUI(false); bubble('system', L10.mic); });
			if (navigator.vibrate) { try { navigator.vibrate(10); } catch (e) {} }
		}
		function recStop(keep) {
			if (!rec) return;
			cancelAnimationFrame(recRaf);
			var r = rec, sec = r.seconds; rec = null;
			var b64 = r.stop(keep && sec >= 0.6);
			recUI(false);
			if (keep && sec < 0.6) { bubble('system', L10.short); return; }
			if (b64) sendVoice(b64);
		}
		function sendVoice(b64) {
			busy = true;
			chips.hidden = true;
			var el = document.createElement('div');
			el.className = 'pga-msg pga-msg--me pga-msg--tr';
			el.innerHTML = '<p>' + esc(L10.tr) + '</p>';
			log.appendChild(el);
			log.scrollTop = log.scrollHeight;
			var body = JSON.stringify({ audio: b64, mime: 'audio/wav', lang: C.lang || 'ru', session: state.session });
			var post = function (url) { return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'omit', body: body }).then(function (r) { return r.text().then(function (t) { var j = null; try { j = JSON.parse(t); } catch (e) {} return { ok: r.ok && !!j, j: j, status: r.status }; }); }); };
			post(rest('chat/voice')).then(function (res) {
				if (res.ok || (res.j && res.j.message && res.status !== 404 && res.status !== 401 && res.status !== 403)) return res;
				return post(ajax('pga_chat_voice'));
			}, function () { return post(ajax('pga_chat_voice')); })
				.then(function (res) {
					if (res.j && (res.j.voice === false || (res.j.data && res.j.data.voice === false))) voiceOff();
					var txt = res.ok && res.j && res.j.text ? String(res.j.text).trim() : '';
					if (!txt) throw new Error((res.j && res.j.message) || L10.short);
					el.classList.remove('pga-msg--tr');
					el.classList.add('is-in');
					el.innerHTML = '<p>' + esc(txt) + '</p>';
					busy = false;
					send(txt, { el: el });
				})
				.catch(function (err) {
					el.remove();
					busy = false;
					bubble('err', err && err.message && err.message.length < 200 && err.message !== 'Failed to fetch' ? err.message : (C.i18n.err || 'Ошибка связи.'));
				});
		}
		if (mic) mic.addEventListener('click', function (e) { e.preventDefault(); recStart(); });
		/* Наплыв посетителей: сервер выключает голосовые — микрофон прячется, остаётся текст */
		function voiceOff() { form.classList.add('no-voice'); sstore.set('pga_novoice', Date.now()); }
		function voiceOn() { form.classList.remove('no-voice'); sstore.set('pga_novoice', 0); }
		if (+(sstore.get('pga_novoice') || 0) > Date.now() - 5 * 60000) voiceOff();
		if (!navigator.mediaDevices || !(window.AudioContext || window.webkitAudioContext)) form.classList.add('no-voice');

		function hasText() { form.classList.toggle('has-text', !!input.value.trim()); }
		function autosize() { input.style.height = 'auto'; input.style.height = Math.min(120, input.scrollHeight) + 'px'; hasText(); }
		function setInput(v) { input.value = v; autosize(); }
		/* нажатие на «Отправить», микрофон или подсказку не уводит курсор из поля — клавиатура не прыгает */
		[sendBtn, mic, chips].forEach(function (x) {
			if (x) x.addEventListener('mousedown', function (e) { if (document.activeElement === input) e.preventDefault(); });
		});
		input.addEventListener('input', autosize);
		input.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(input.value); } });
		form.addEventListener('submit', function (e) { e.preventDefault(); send(input.value); });
		chips.addEventListener('click', function (e) { var c = e.target.closest('.pga-chip'); if (c) send(c.textContent); });
		log.addEventListener('click', function (e) {
			if (e.target.closest('[data-chat-book]')) {
				var p = extract();
				close();
				setTimeout(function () { openBook(p); }, 300);
			}
		});
		$('[data-chat-close]', chat).addEventListener('click', close);
		chat.addEventListener('click', function (e) { if (e.target === chat) close(); });
		$('[data-chat-reset]', chat).addEventListener('click', function () {
			stopPoll();
			state = { session: Math.random().toString(36).slice(2) + Date.now().toString(36), messages: [] };
			save(); render();
		});
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !chat.hidden) close(); });
		/* кнопка «Помощник» в нижнем меню (и любые другие [data-chat]) */
		document.addEventListener('click', function (e) {
			var b = e.target.closest && e.target.closest('[data-chat]');
			if (b) { e.preventDefault(); open(); }
		});

		/* Чат закрывается свайпом по шапке вниз */
		(function () {
			var head = $('.pga-chat__head', chat), panel = $('.pga-chat__panel', chat);
			var y0 = 0, dy = 0, drag = false;
			head.style.touchAction = 'none';
			head.addEventListener('pointerdown', function (e) { if (e.target.closest('button')) return; drag = true; y0 = e.clientY; dy = 0; panel.style.transition = 'none'; head.setPointerCapture(e.pointerId); });
			head.addEventListener('pointermove', function (e) { if (!drag) return; dy = Math.max(0, e.clientY - y0); panel.style.transform = 'translateY(' + dy + 'px)'; });
			var end = function () {
				if (!drag) return;
				drag = false;
				panel.style.transition = 'transform .3s cubic-bezier(.32,.72,0,1)';
				if (dy > 90) {
					panel.style.transform = 'translateY(110%)';
					setTimeout(function () { close(); panel.style.transform = ''; panel.style.transition = ''; }, 260);
				} else { panel.style.transform = ''; setTimeout(function () { panel.style.transition = ''; }, 320); }
			};
			head.addEventListener('pointerup', end);
			head.addEventListener('pointercancel', end);
		})();
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
