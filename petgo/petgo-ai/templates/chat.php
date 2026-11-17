<?php
/** Окно чата. Переменные: $s (настройки ИИ). Выводится внутри .pg-layer сайта PetGo. Тексты — на языке сайта. */
defined( 'ABSPATH' ) || exit;
$pga_name = pga_name();
$pga_x    = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
$pga_r    = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 1 0-2.3 5.7M20 5v6h-6"/></svg>';
?>
	<!-- ИИ‑помощник PetGo (плагин «PetGo ИИ»). Без ключа — вежливый ответ и контакты -->
	<div class="pga-chat" id="pga-chat" hidden role="dialog" aria-modal="true" aria-labelledby="pga-title">
		<div class="pga-chat__panel">
			<div class="pga-chat__head">
				<span class="pga-chat__avatar" aria-hidden="true"><svg viewBox="-24 -24 48 54" width="24" height="26"><path fill="#FBF9E8" d="M0 6c-9 0-16 7-16 13 0 5 4 7 9 6 3-1 5-2 7-2s4 1 7 2c5 1 9-1 9-6 0-6-7-13-16-13z"/><g fill="#FBF9E8"><ellipse cx="-16" cy="-3" rx="5" ry="7" transform="rotate(-20 -16 -3)"/><ellipse cx="-6" cy="-13" rx="5" ry="7.5" transform="rotate(-6 -6 -13)"/><ellipse cx="6" cy="-13" rx="5" ry="7.5" transform="rotate(6 6 -13)"/><ellipse cx="16" cy="-3" rx="5" ry="7" transform="rotate(20 16 -3)"/></g></svg></span>
				<div class="pga-chat__who"><strong id="pga-title"><?php echo esc_html( $pga_name ); ?></strong><small><span class="pga-online"></span><?php echo esc_html( pga_l( 'PetGo · Алматы' ) ); ?></small></div>
				<button type="button" class="pga-round" data-chat-reset aria-label="<?php echo esc_attr( pga_l( 'Начать заново' ) ); ?>" title="<?php echo esc_attr( pga_l( 'Начать заново' ) ); ?>"><?php echo $pga_r; // phpcs:ignore ?></button>
				<button type="button" class="pga-round" data-chat-close aria-label="<?php echo esc_attr( pga_l( 'Закрыть' ) ); ?>"><?php echo $pga_x; // phpcs:ignore ?></button>
			</div>
			<div class="pga-chat__log" id="pga-log" aria-live="polite"></div>
			<div class="pga-chat__chips" id="pga-chips"></div>
			<form class="pga-chat__form" id="pga-form" autocomplete="off">
				<textarea id="pga-in" rows="1" maxlength="1000" placeholder="<?php echo esc_attr( pga_l( 'Спросите о ценах и услугах' ) ); ?>" aria-label="<?php echo esc_attr( pga_l( 'Ваш вопрос' ) ); ?>" data-kb-send enterkeyhint="send"></textarea>
				<button type="button" class="pga-chat__mic" aria-label="<?php echo esc_attr( pga_l( 'Голосовое сообщение' ) ); ?>"><svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21"/></svg></button>
				<button type="submit" class="pga-chat__send" aria-label="<?php echo esc_attr( pga_l( 'Отправить' ) ); ?>"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg></button>
			</form>
			<p class="pga-chat__disc"><?php echo esc_html( pga_l( 'ИИ может ошибаться. Точные детали уточняйте у нас' ) ); ?><?php echo function_exists( 'petgo_site_phone_fmt' ) ? ': ' . esc_html( petgo_site_phone_fmt() ) : ''; ?></p>
		</div>
	</div>
