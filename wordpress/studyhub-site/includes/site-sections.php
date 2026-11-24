<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
   БЛОКИ СТРАНИЦ. На главной порядок задаёт ключ (фильтр studyhub_site_home_sections):
   10 первый экран, 20 услуги, 25 вузы, 30 «как это работает», 35 ИИ, 40 отзывы, 50 «получить доступ».
   ============================================================ */

function studyhub_site_home_sections_default() {
	$s = array(
		10 => 'studyhub_site_section_hero',
		20 => function () {
			return studyhub_site_section_services( true );
		},
		25 => 'studyhub_site_section_unis',
		30 => 'studyhub_site_section_how',
		40 => function () {
			return studyhub_site_section_reviews( true );
		},
		50 => 'studyhub_site_section_cta',
	);
	if ( apply_filters( 'studyhub_ai_active', false ) ) {
		$s[35] = 'studyhub_site_section_ai';
	}
	return $s;
}

function studyhub_site_hero_art() {
	$img = StudyHub_Settings::get( 'hero_image' );
	if ( $img ) {
		return '<img src="' . esc_url( $img ) . '" alt="" fetchpriority="high">';
	}
	$back = '';
	$front = '';
	for ( $r = 0; $r < 8; $r++ ) {
		for ( $c = 0; $c < 3; $c++ ) {
			$back .= '<rect x="' . ( 162 + $c * 18 ) . '" y="' . ( 50 + $r * 22 ) . '" width="11" height="14" rx="1.5" fill="url(#hg)" opacity=".85"/>';
		}
	}
	for ( $r = 0; $r < 4; $r++ ) {
		for ( $c = 0; $c < 4; $c++ ) {
			$front .= '<rect x="' . ( 78 + $c * 27 ) . '" y="' . ( 112 + $r * 29 ) . '" width="21" height="21" rx="2" fill="url(#hg)"/>';
		}
		$front .= '<rect x="70" y="' . ( 133 + $r * 29 ) . '" width="120" height="3" fill="#D8D2CB"/>';
		$front .= '<polygon points="190,' . ( 106 + $r * 29 ) . ' 230,' . ( 117 + $r * 29 ) . ' 230,' . ( 120 + $r * 29 ) . ' 190,' . ( 109 + $r * 29 ) . '" fill="#B9B2AA"/>';
	}
	return '<svg viewBox="0 0 260 230" preserveAspectRatio="xMaxYMax meet" aria-hidden="true"><defs><linearGradient id="hg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#C9DAEA"/><stop offset=".6" stop-color="#8FA7C0"/><stop offset="1" stop-color="#6F87A2"/></linearGradient></defs>'
		. '<circle cx="118" cy="52" r="24" fill="#FFD7B5" opacity=".75"/>'
		. '<rect x="150" y="38" width="66" height="192" fill="#DCD7D1"/><polygon points="216,38 252,50 252,230 216,230" fill="#C3BCB4"/>' . $back
		. '<rect x="70" y="98" width="120" height="132" fill="#F1EDE8"/><polygon points="190,98 230,110 230,230 190,230" fill="#CFC8C0"/><rect x="66" y="94" width="128" height="6" rx="1" fill="#E4DED8"/>' . $front
		. '<ellipse cx="40" cy="214" rx="26" ry="22" fill="#9DB88F"/><ellipse cx="22" cy="222" rx="18" ry="15" fill="#86A57A"/><ellipse cx="246" cy="222" rx="20" ry="14" fill="#93B087"/><rect x="0" y="226" width="260" height="4" fill="#CFC8C0"/></svg>';
}

function studyhub_site_section_hero() {
	$s = StudyHub_Settings::all();
	$l = studyhub_lang();
	return '<section class="hero s-hero">'
		. '<div class="hero-art">' . studyhub_site_hero_art() . '</div>'
		. '<span class="anon-badge">' . studyhub_icon( 'lock', 'xs' ) . esc_html( studyhub_st( 'anon' ) ) . '</span>'
		. '<div class="hero-body"><h1>' . esc_html( 'kk' === $l ? $s['hero_title_kk'] : $s['hero_title_ru'] ) . '</h1>'
		. '<p>' . esc_html( 'kk' === $l ? $s['hero_text_kk'] : $s['hero_text_ru'] ) . '</p>'
		. '<div class="s-cta-row"><a class="btn btn-dark" href="' . esc_url( studyhub_site_app_url( 'services' ) ) . '">' . esc_html( studyhub_st( 'order' ) ) . studyhub_icon( 'arrowRight', 'sm' ) . '</a>'
		. '<a class="btn btn-ghost" href="' . esc_url( studyhub_site_url( 'services' ) ) . '">' . esc_html( studyhub_st( 'all_services' ) ) . '</a></div></div></section>'
		. '<div class="trust s-trust"><span>' . studyhub_icon( 'shield' ) . esc_html( studyhub_st( 'trust1' ) ) . '</span><span>' . studyhub_icon( 'mask' ) . esc_html( studyhub_st( 'trust2' ) ) . '</span><span>' . studyhub_icon( 'lock' ) . esc_html( studyhub_st( 'trust3' ) ) . '</span></div>';
}

function studyhub_site_section_services( $short ) {
	$cards = '';
	foreach ( studyhub_public_services() as $svc ) {
		$price  = '' !== $svc['price'] ? '<span class="price">' . esc_html( $svc['price'] ) . '</span>' : '<span class="price na">' . esc_html( studyhub_st( 'price_chat' ) ) . '</span>';
		$cards .= '<article class="svc glass"><div class="svc-ico tone-' . esc_attr( $svc['color'] ? $svc['color'] : 'peach' ) . ' tone-bg">' . studyhub_icon( $svc['icon'], 'lg' ) . '</div>'
			. '<div><h3>' . esc_html( studyhub_loc( $svc['title'] ) ) . '</h3><p>' . esc_html( studyhub_loc( $svc['desc'] ) ) . '</p></div>'
			. '<div class="svc-foot">' . $price . '<a class="btn btn-primary btn-sm" href="' . esc_url( studyhub_site_app_url( 'services' ) ) . '">' . esc_html( studyhub_st( 'order' ) ) . studyhub_icon( 'arrowRight', 'xs' ) . '</a></div></article>';
	}
	$more = $short ? '<a class="link-more" href="' . esc_url( studyhub_site_url( 'services' ) ) . '">' . esc_html( studyhub_st( 'more' ) ) . studyhub_icon( 'arrowRight', 'xs' ) . '</a>' : '';
	$tag  = $short ? 'h2' : 'h1';
	return '<section class="section s-section" id="services"><div class="section-head"><' . $tag . ' class="' . ( $short ? '' : 'page-title' ) . '">' . esc_html( studyhub_st( $short ? 'services_t' : 'nav_services' ) ) . '</' . $tag . '>' . $more . '</div>'
		. ( $short ? '' : '<p class="page-sub">' . esc_html( studyhub_st( 'services_s' ) ) . '</p>' )
		. '<div class="svc-list s-grid">' . $cards . '</div></section>';
}

function studyhub_site_section_unis() {
	$unis = studyhub_public_universities();
	if ( ! $unis ) {
		return '';
	}
	$out = '';
	foreach ( $unis as $u ) {
		$out .= '<div class="s-uni glass"><span class="uni-chip">' . ( $u['logo'] ? '<img src="' . esc_url( $u['logo'] ) . '" alt="">' : esc_html( $u['short'] ) ) . '</span><span><b>' . esc_html( $u['short'] ) . '</b><small>' . esc_html( studyhub_loc( $u['name'] ) ) . '</small></span></div>';
	}
	return '<section class="section s-section"><div class="section-head"><h2>' . esc_html( studyhub_st( 'unis_t' ) ) . '</h2></div><div class="s-unis">' . $out . '</div></section>';
}

function studyhub_site_section_how() {
	$steps = '';
	foreach ( array( 1, 2, 3 ) as $n ) {
		$steps .= '<div class="step"><span class="step-n">' . $n . '</span><div><b>' . esc_html( studyhub_st( 'step' . $n . '_t' ) ) . '</b><span>' . esc_html( studyhub_st( 'step' . $n . '_s' ) ) . '</span></div></div>';
	}
	$priv = '';
	foreach ( array( 'priv1', 'priv2', 'priv3' ) as $k ) {
		$priv .= '<div class="privacy-row">' . studyhub_icon( 'check', 'sm' ) . '<span>' . esc_html( studyhub_st( $k ) ) . '</span></div>';
	}
	return '<section class="section s-section" id="how"><div class="s-two"><div><div class="section-head"><h2>' . esc_html( studyhub_st( 'how_t' ) ) . '</h2></div><div class="steps glass" style="margin-top:0">' . $steps . '</div></div>'
		. '<div><div class="section-head"><h2>' . esc_html( studyhub_st( 'priv_t' ) ) . '</h2></div><div class="privacy glass" style="margin-top:0">' . $priv . '</div></div></div></section>';
}

function studyhub_site_section_ai() {
	return '<section class="section s-section"><a class="ai-banner glass" href="' . esc_url( studyhub_site_app_url( 'ai' ) ) . '">'
		. '<svg class="ai-star" viewBox="0 0 100 100" aria-hidden="true"><defs><radialGradient id="sai" cx="36%" cy="30%" r="80%"><stop offset="0" stop-color="#FFE1C4"/><stop offset=".42" stop-color="#FF9B52"/><stop offset="1" stop-color="#E2541C"/></radialGradient></defs><path d="M50 3C54 31 69 46 97 50 69 54 54 69 50 97 46 69 31 54 3 50 31 46 46 31 50 3Z" fill="url(#sai)"/></svg>'
		. '<div class="grow"><h3>' . esc_html( studyhub_st( 'ai_t' ) ) . '</h3><p>' . esc_html( studyhub_st( 'ai_s' ) ) . '</p></div><span class="round-arrow">' . studyhub_icon( 'arrowRight', 'sm' ) . '</span></a></section>';
}

function studyhub_site_review_card( $r ) {
	$who   = $r['university'] ? sprintf( studyhub_st( 'student_of' ), $r['university'] ) : studyhub_st( 'student' );
	$stars = '<span class="stars" aria-label="' . (int) $r['rating'] . '/5">';
	for ( $i = 1; $i <= 5; $i++ ) {
		$stars .= '<span class="' . ( $i <= $r['rating'] ? '' : 'off' ) . '">' . studyhub_icon( 'star' ) . '</span>';
	}
	$stars .= '</span>';
	$tones = array( 'peach', 'lilac', 'mint', 'sky', 'sand', 'rose' );
	return '<article class="review glass"><div class="review-head"><div class="anon-ava tone-' . $tones[ $r['id'] % 6 ] . ' tone-bg">' . studyhub_icon( 'mask' ) . '</div>'
		. '<div><div class="review-who">' . esc_html( $who ) . '</div>' . $stars . '</div></div><p>“' . esc_html( $r['text'] ) . '”</p></article>';
}

function studyhub_site_section_reviews( $short ) {
	$reviews = studyhub_public_reviews( $short ? 12 : 60 );
	$stats   = StudyHub_DB::review_stats();
	$sum     = $stats['count'] ? '<span class="rating-sum">' . studyhub_icon( 'star', 'xs' ) . '<b>' . esc_html( $stats['average'] ) . '</b> · ' . (int) $stats['count'] . '</span>' : '';
	if ( ! $reviews ) {
		$body = '<div class="empty glass">' . studyhub_icon( 'star' ) . esc_html( studyhub_st( 'no_reviews' ) ) . '</div>';
	} else {
		$cards = '';
		foreach ( $reviews as $r ) {
			$cards .= studyhub_site_review_card( $r );
		}
		$body = $short ? '<div class="h-scroll s-reviews-row">' . $cards . '</div>' : '<div class="s-reviews-grid">' . $cards . '</div>';
	}
	$tag = $short ? 'h2' : 'h1';
	return '<section class="section s-section" id="reviews"><div class="section-head"><' . $tag . ' class="' . ( $short ? '' : 'page-title' ) . '">' . esc_html( studyhub_st( 'reviews_t' ) ) . '</' . $tag . '>' . $sum . '</div>'
		. ( $short ? '' : '<p class="page-sub">' . esc_html( studyhub_st( 'reviews_s' ) ) . '</p>' ) . $body
		. ( $short && $reviews ? '<p style="margin:6px 2px 0"><a class="link-more" href="' . esc_url( studyhub_site_url( 'reviews' ) ) . '">' . esc_html( studyhub_st( 'more' ) ) . studyhub_icon( 'arrowRight', 'xs' ) . '</a></p>' : '' ) . '</section>';
}

function studyhub_site_section_cta() {
	$contact = StudyHub_Settings::get( 'contact_url' );
	$label   = StudyHub_Settings::get( 'contact_label' );
	return '<section class="section s-section"><div class="promo s-cta"><span class="pill">' . studyhub_icon( 'shield', 'xs' ) . esc_html( studyhub_st( 'anon' ) ) . '</span>'
		. '<h3>' . esc_html( studyhub_st( 'cta_t' ) ) . '</h3><p>' . esc_html( studyhub_st( $contact ? 'cta_s' : 'cta_s_nocontact' ) ) . '</p>'
		. '<div class="s-cta-row"><a class="btn btn-dark" href="' . esc_url( studyhub_site_app_url() ) . '">' . studyhub_icon( 'user', 'xs' ) . esc_html( studyhub_st( 'nav_login' ) ) . '</a>'
		. ( $contact ? '<a class="btn" href="' . esc_url( $contact ) . '" target="_blank" rel="noopener nofollow">' . studyhub_icon( 'chat', 'xs' ) . esc_html( $label ? $label : studyhub_st( 'get_access' ) ) . '</a>' : '' )
		. '</div></div></section>';
}
