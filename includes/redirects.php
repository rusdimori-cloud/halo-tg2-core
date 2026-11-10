<?php
/**
 * Satu pintu masuk (Fase 5) — semua pintu lama tiba di halaman yang sama:
 *  - URL login/daftar/lupa sandi WordPress diarahkan ke halaman auth tema.
 *  - /my-account/ WooCommerce diarahkan ke dashboard gabungan (Tutor).
 *  - Setelah login, pengguna non-admin tiba di dashboard.
 *
 * @package HaloTG2Core
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * URL halaman auth tema (template page-templates/auth.php), bila ada.
 *
 * @return string '' bila halaman belum dibuat.
 */
function halo_tg2_core_auth_url() {
	static $url = null;
	if (null !== $url) {
		return $url;
	}
	$url = '';
	$pages = get_pages(array(
		'meta_key'   => '_wp_page_template',
		'meta_value' => 'page-templates/auth.php',
		'number'     => 1,
	));
	if (!empty($pages)) {
		$url = (string) get_permalink($pages[0]->ID);
	}
	return $url;
}

/**
 * URL dashboard gabungan (Tutor), aman lintas versi.
 *
 * @return string
 */
/**
 * URL halaman dashboard kustom tema (template panel.php), bila ada.
 *
 * @return string '' bila belum dibuat.
 */
function halo_tg2_core_panel_url() {
	static $url = null;
	if (null !== $url) {
		return $url;
	}
	$url = '';
	$pages = get_pages(array(
		'meta_key'   => '_wp_page_template',
		'meta_value' => 'page-templates/panel.php',
		'number'     => 1,
	));
	if (!empty($pages)) {
		$url = (string) get_permalink($pages[0]->ID);
	}
	return $url;
}

/**
 * Beranda pengguna: dashboard kustom bila ada, jika tidak dashboard Tutor.
 *
 * @return string
 */
function halo_tg2_core_home_url() {
	$panel = halo_tg2_core_panel_url();
	return '' !== $panel ? $panel : halo_tg2_core_dashboard_url();
}

function halo_tg2_core_dashboard_url() {
	if (function_exists('tutor_utils') && method_exists(tutor_utils(), 'get_tutor_dashboard_page_permalink')) {
		$url = tutor_utils()->get_tutor_dashboard_page_permalink();
		if ($url) {
			return $url;
		}
	}
	return home_url('/dashboard/');
}

add_filter('login_url', 'halo_tg2_core_login_url', 10, 2);
/**
 * Arahkan URL login bawaan ke halaman auth (kecuali untuk reauth paksa).
 *
 * @param string $login_url URL bawaan.
 * @param string $redirect  Tujuan setelah login.
 * @return string
 */
function halo_tg2_core_login_url($login_url, $redirect) {
	$auth = halo_tg2_core_auth_url();
	if ('' === $auth) {
		return $login_url;
	}
	if ($redirect) {
		$auth = add_query_arg('redirect_to', $redirect, $auth);
	}
	return $auth;
}

add_filter('register_url', 'halo_tg2_core_register_url');
/**
 * Arahkan URL daftar bawaan ke mode daftar di halaman auth.
 *
 * @param string $register_url URL bawaan.
 * @return string
 */
function halo_tg2_core_register_url($register_url) {
	$auth = halo_tg2_core_auth_url();
	return '' === $auth ? $register_url : add_query_arg('mode', 'register', $auth);
}

add_filter('lostpassword_url', 'halo_tg2_core_lostpassword_url');
/**
 * Arahkan URL lupa sandi bawaan ke mode lupa sandi di halaman auth.
 *
 * @param string $lostpassword_url URL bawaan.
 * @return string
 */
function halo_tg2_core_lostpassword_url($lostpassword_url) {
	$auth = halo_tg2_core_auth_url();
	return '' === $auth ? $lostpassword_url : add_query_arg('mode', 'lostpassword', $auth);
}

add_filter('login_redirect', 'halo_tg2_core_login_redirect', 10, 3);
/**
 * Setelah login: non-admin ke dashboard gabungan (admin tetap ke tujuannya,
 * mis. wp-admin, agar pengelolaan situs tidak terganggu).
 *
 * @param string  $redirect_to Tujuan bawaan.
 * @param string  $requested   Tujuan yang diminta.
 * @param WP_User $user        Pengguna yang masuk.
 * @return string
 */
function halo_tg2_core_login_redirect($redirect_to, $requested, $user) {
	if ($user instanceof WP_User && !user_can($user, 'manage_options') && '' === (string) $requested) {
		return halo_tg2_core_home_url();
	}
	return $redirect_to;
}

add_action('template_redirect', 'halo_tg2_core_myaccount_redirect');
/**
 * /my-account/ Woo → dashboard gabungan (Bagian 7 dokumen arsitektur).
 * Endpoint di bawah akun (orders, downloads, edit-account, dst.) TIDAK
 * dialihkan dari sini — mereka dibuka dari tab dashboard pada Fase 6 dan
 * harus tetap bisa dijangkau.
 */
function halo_tg2_core_myaccount_redirect() {
	if (!function_exists('is_account_page') || !is_account_page()) {
		return;
	}
	global $wp;
	$endpoint = isset($wp->query_vars) && is_array($wp->query_vars)
		? array_diff(array_keys($wp->query_vars), array('page', 'pagename', 'name'))
		: array();
	// Halaman akun akar (tanpa endpoint) → dashboard.
	$known = array('orders', 'downloads', 'edit-address', 'payment-methods', 'edit-account', 'customer-logout', 'view-order', 'add-payment-method', 'delete-payment-method', 'set-default-payment-method');
	if (!array_intersect($known, $endpoint)) {
		wp_safe_redirect(halo_tg2_core_home_url());
		exit;
	}
}

add_action('template_redirect', 'halo_tg2_core_tutor_dashboard_redirect', 20);
/**
 * Satu dasbor (Fase 9): halaman dasbor Tutor untuk siswa dialihkan ke
 * view panel padanannya, agar siswa tidak pernah melihat dua dasbor.
 * Admin tidak dialihkan; pengunjung belum login dibiarkan (Tutor
 * menampilkan gerbang masuknya sendiri).
 */
function halo_tg2_core_tutor_dashboard_redirect() {
	if (!is_user_logged_in() || current_user_can('manage_options') || !is_page()) {
		return;
	}
	global $post, $wp_query;
	if (!$post) {
		return;
	}
	$is_dash = false;
	if (function_exists('tutor_utils') && method_exists(tutor_utils(), 'get_option')) {
		$dash_id = (int) tutor_utils()->get_option('tutor_dashboard_page_id');
		$is_dash = $dash_id > 0 && (int) $post->ID === $dash_id;
	}
	if (!$is_dash) {
		$is_dash = has_shortcode((string) $post->post_content, 'tutor_dashboard');
	}
	if (!$is_dash) {
		return;
	}
	$panel = halo_tg2_core_panel_url();
	if ('' === $panel) {
		return;
	}
	$page = '';
	if (isset($wp_query->query_vars['tutor_dashboard_page'])) {
		$page = (string) $wp_query->query_vars['tutor_dashboard_page'];
	}
	$map  = array(
		'my-courses'       => 'kelas',
		'enrolled-courses' => 'kelas',
		'active-courses'   => 'kelas',
		'completed-courses' => 'kelas',
		'wishlist'         => 'wishlist',
		'purchase-history' => 'pesanan',
		'settings'         => 'pengaturan',
		'quiz-attempts'    => 'statistik',
	);
	$view   = isset($map[$page]) ? $map[$page] : 'beranda';
	$target = 'beranda' === $view ? $panel : add_query_arg('tampilan', $view, $panel);
	wp_safe_redirect($target);
	exit;
}
