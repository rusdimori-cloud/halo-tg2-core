<?php
/**
 * Formulir Hubungi Kami dari dashboard kustom (Fase 8).
 * Aturan mockup: kontak HANYA lewat email (tanpa LINE). Pesan dikirim
 * ke email kontak resmi (pengaturan plugin) atau email admin situs.
 *
 * @package HaloTG2Core
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_post_halo_tg2_contact', 'halo_tg2_core_contact_handle');
/**
 * Proses kiriman formulir Hubungi Kami lalu kembali ke dashboard.
 */
function halo_tg2_core_contact_handle() {
	$return = isset($_POST['return_url']) ? esc_url_raw(wp_unslash($_POST['return_url'])) : home_url('/'); // phpcs:ignore WordPress.Security.NonceVerification
	$back = function ($status) use ($return) {
		wp_safe_redirect(add_query_arg('hubungi', $status, $return));
		exit;
	};
	if (!is_user_logged_in() || !isset($_POST['_htcnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_htcnonce'])), 'halo_tg2_contact')) { // phpcs:ignore WordPress.Security.NonceVerification
		$back('gagal');
	}
	$kategori = isset($_POST['kategori']) ? sanitize_text_field(wp_unslash($_POST['kategori'])) : 'Lainnya'; // phpcs:ignore WordPress.Security.NonceVerification
	$email    = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$pesan    = isset($_POST['pesan']) ? sanitize_textarea_field(wp_unslash($_POST['pesan'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ('' === $pesan || !is_email($email)) {
		$back('gagal');
	}
	$user   = wp_get_current_user();
	$to     = halo_tg2_core_setting('contact_email');
	if ('' === $to) {
		$to = (string) get_option('admin_email');
	}
	$subject = sprintf('[halo TG2] Hubungi Kami — %s', $kategori);
	$body    = sprintf(
		"Dari: %s (%s)\nAkun: %s\nKategori: %s\n\n%s",
		$user->display_name,
		$email,
		$user->user_login,
		$kategori,
		$pesan
	);
	$sent = wp_mail($to, $subject, $body, array('Reply-To: ' . $email));
	$back($sent ? 'terkirim' : 'gagal');
}
