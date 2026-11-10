<?php
/**
 * Pengaturan profil & kata sandi di panel (Fase 9 — satu dasbor).
 *
 * Nama field, kunci meta, dan validasi meniru persis handler Tutor
 * LMS 4.1.3 (TUTOR\Student::update_profile dan ::tutor_reset_password)
 * agar data yang tersimpan tetap kompatibel dengan Tutor — yang
 * berubah hanya tampilannya, kini di dashboard kustom tema.
 *
 * @package HaloTG2Core
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * URL kembali ke view Pengaturan di panel.
 *
 * @param array $extra Argumen query tambahan (status hasil simpan).
 * @return string
 */
function halo_tg2_core_pengaturan_url($extra = array()) {
	$panel = halo_tg2_core_panel_url();
	$base  = '' !== $panel ? add_query_arg('tampilan', 'pengaturan', $panel) : home_url('/');
	$ret   = isset($_POST['return_url']) ? esc_url_raw(wp_unslash($_POST['return_url'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ('' !== $ret && false !== strpos($ret, home_url('/'))) {
		$base = $ret;
	}
	return !empty($extra) ? add_query_arg($extra, $base) : $base;
}

add_action('admin_post_halo_tg2_save_profile', 'halo_tg2_core_save_profile');
/**
 * Simpan profil — padanan TUTOR\Student::update_profile (Tutor 4.1.3):
 * wp_update_user(first_name, last_name, display_name) + meta
 * phone_number, _tutor_profile_bio, _tutor_profile_job_title,
 * _tutor_timezone, plus action tutor_profile_update_before/after.
 */
function halo_tg2_core_save_profile() {
	if (!is_user_logged_in() || !isset($_POST['_htpnonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htpnonce'])), 'halo_tg2_save_profile')) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('profil' => 'gagal')));
		exit;
	}
	$uid = get_current_user_id();
	do_action('tutor_profile_update_before', $uid);

	$first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
	$last_name  = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
	$display    = isset($_POST['display_name']) ? sanitize_text_field(wp_unslash($_POST['display_name'])) : '';
	$phone      = isset($_POST['phone_number']) ? sanitize_text_field(wp_unslash($_POST['phone_number'])) : '';
	$job        = isset($_POST['tutor_profile_job_title']) ? sanitize_text_field(wp_unslash($_POST['tutor_profile_job_title'])) : '';
	$timezone   = isset($_POST['timezone']) ? sanitize_text_field(wp_unslash($_POST['timezone'])) : '';
	$bio        = isset($_POST['tutor_profile_bio']) ? wp_kses_post(wp_unslash($_POST['tutor_profile_bio'])) : '';

	$userdata = array(
		'ID'         => $uid,
		'first_name' => $first_name,
		'last_name'  => $last_name,
	);
	if ('' !== $display) {
		$userdata['display_name'] = $display;
	}
	$result = wp_update_user($userdata);

	if (!is_wp_error($result)) {
		update_user_meta($uid, 'phone_number', $phone);
		update_user_meta($uid, '_tutor_profile_bio', $bio);
		update_user_meta($uid, '_tutor_profile_job_title', $job);
		update_user_meta($uid, '_tutor_timezone', $timezone);
		do_action('tutor_profile_update_after', $uid);
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('profil' => 'tersimpan')));
		exit;
	}
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('profil' => 'gagal')));
	exit;
}

add_action('admin_post_halo_tg2_change_password', 'halo_tg2_core_change_password');
/**
 * Ganti kata sandi — padanan TUTOR\Student::tutor_reset_password
 * (Tutor 4.1.3): periksa sandi lama dengan wp_check_password,
 * validasi isian, lalu wp_set_password. Sesi saat ini diperbarui
 * agar siswa tidak terlempar keluar setelah sandi berganti.
 */
function halo_tg2_core_change_password() {
	if (!is_user_logged_in() || !isset($_POST['_htsnonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htsnonce'])), 'halo_tg2_change_password')) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('sandi' => 'gagal')));
		exit;
	}
	$user    = wp_get_current_user();
	$prev    = isset($_POST['previous_password']) ? wp_unslash($_POST['previous_password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sandi tidak disanitasi.
	$new     = isset($_POST['new_password']) ? wp_unslash($_POST['new_password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sandi tidak disanitasi.
	$confirm = isset($_POST['confirm_new_password']) ? wp_unslash($_POST['confirm_new_password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sandi tidak disanitasi.

	$error = '';
	if (!wp_check_password($prev, $user->user_pass, $user->ID)) {
		$error = 'lama_salah';
	} elseif ('' === $new) {
		$error = 'baru_kosong';
	} elseif ('' === $confirm) {
		$error = 'konfirmasi_kosong';
	} elseif ($new !== $confirm) {
		$error = 'tidak_cocok';
	}

	if ('' !== $error) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('sandi' => $error)));
		exit;
	}

	wp_set_password($new, $user->ID);
	wp_set_current_user($user->ID);
	wp_set_auth_cookie($user->ID, true);
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('sandi' => 'berubah')));
	exit;
}
