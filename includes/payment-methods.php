<?php
/**
 * Metode pembayaran tersimpan — pengelolaan langsung dari dashboard
 * kustom (daftar, hapus, tambah) tanpa keluar dari panel.
 * Penambahan kartu memakai alur resmi WooCommerce: berkas endpoint
 * add-payment-method milik Woo dimuat di dalam panel, dan setelah
 * tersimpan Woo mengalihkan ke daftar metode — dialihkan balik ke
 * panel oleh filter di bawah.
 *
 * @package HaloTG2Core
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_post_halo_tg2_delete_pm', 'halo_tg2_core_delete_pm');
/**
 * Hapus satu metode pembayaran milik pengguna yang sedang masuk.
 */
function halo_tg2_core_delete_pm() {
	$return = isset($_POST['return_url']) ? esc_url_raw(wp_unslash($_POST['return_url'])) : home_url('/'); // phpcs:ignore WordPress.Security.NonceVerification
	$back = function ($status) use ($return) {
		wp_safe_redirect(add_query_arg('bayar', $status, $return));
		exit;
	};
	if (!is_user_logged_in() || !isset($_POST['_htpmnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_htpmnonce'])), 'halo_tg2_delete_pm')) { // phpcs:ignore WordPress.Security.NonceVerification
		$back('gagal');
	}
	if (!class_exists('WooCommerce') || !class_exists('WC_Payment_Tokens')) {
		$back('gagal');
	}
	$token_id = isset($_POST['token_id']) ? absint($_POST['token_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$token = WC_Payment_Tokens::get($token_id);
	if (!$token || (int) $token->get_user_id() !== get_current_user_id()) {
		$back('gagal'); // Bukan milik pengguna ini.
	}
	WC_Payment_Tokens::delete($token_id);
	$back('terhapus');
}

add_filter('woocommerce_get_add_payment_method_url', 'halo_tg2_core_add_pm_panel_url');
/**
 * URL "tambah metode pembayaran" dari dalam panel tetap di panel
 * (mode tersemat), bukan ke endpoint Woo mentah.
 *
 * @param string $url URL bawaan Woo.
 * @return string
 */
function halo_tg2_core_add_pm_panel_url($url) {
	if (isset($_GET['tampilan']) && 'akun' === sanitize_key(wp_unslash($_GET['tampilan']))) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$panel = halo_tg2_core_panel_url();
		if ('' !== $panel) {
			return add_query_arg(array('tampilan' => 'akun', 'tambah_bayar' => '1'), $panel);
		}
	}
	return $url;
}

add_action('template_redirect', 'halo_tg2_core_pm_return_redirect', 1);
/**
 * Setelah metode pembayaran berhasil ditambah dari panel, Woo
 * mengalihkan ke /my-account/payment-methods/ — kembalikan ke panel.
 */
function halo_tg2_core_pm_return_redirect() {
	if (!function_exists('is_account_page') || !is_account_page()) {
		return;
	}
	global $wp;
	if (!isset($wp->query_vars['payment-methods']) || isset($wp->query_vars['add-payment-method'])) {
		return;
	}
	if (!isset($_SERVER['HTTP_REFERER'])) {
		return;
	}
	$referer = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
	if (false === strpos($referer, 'tambah_bayar=1')) {
		return;
	}
	$panel = halo_tg2_core_panel_url();
	if ('' !== $panel) {
		wp_safe_redirect(add_query_arg(array('tampilan' => 'akun', 'bayar' => 'ditambah'), $panel));
		exit;
	}
}
