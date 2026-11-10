<?php
/**
 * Poles email dasar (Fase 7, Bagian 4 dokumen arsitektur):
 * email Woo memakai logo situs + warna Template (kanvas & primer).
 * Email Tutor diatur dari pengaturan email Tutor sendiri (logo di sana).
 *
 * @package HaloTG2Core
 */

if (!defined('ABSPATH')) {
	exit;
}

add_filter('woocommerce_email_header_image', 'halo_tg2_core_email_logo');
/**
 * Logo email = logo situs dari Customizer (fallback: ikon situs).
 *
 * @param string $image URL gambar bawaan Woo (kosong secara default).
 * @return string
 */
function halo_tg2_core_email_logo($image) {
	$logo_id = (int) get_theme_mod('custom_logo');
	if ($logo_id) {
		$url = wp_get_attachment_image_url($logo_id, 'full');
		if ($url) {
			return $url;
		}
	}
	$icon = get_site_icon_url(512);
	return $icon ? $icon : $image;
}

add_filter('woocommerce_email_background_color', function () {
	return '#F4F6FB';
});
add_filter('woocommerce_email_base_color', function () {
	return '#1463FF';
});
add_filter('woocommerce_email_text_color', function () {
	return '#0A0B0D';
});

add_filter('woocommerce_email_styles', 'halo_tg2_core_email_styles');
/**
 * Sentuhan akhir CSS email Woo: tautan primer + judul tegas.
 *
 * @param string $css CSS bawaan Woo.
 * @return string
 */
function halo_tg2_core_email_styles($css) {
	$css .= "\n#body_content a { color: #1463FF; }";
	$css .= "\n#body_content h1, #body_content h2, #body_content h3 { letter-spacing: -0.01em; }";
	return $css;
}
