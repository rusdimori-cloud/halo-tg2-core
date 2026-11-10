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

add_action('admin_post_halo_tg2_save_photo', 'halo_tg2_core_save_photo');
/**
 * Unggah foto profil & sampul — berkas menjadi lampiran WordPress dan
 * ID-nya disimpan di meta yang sama dengan Tutor 4.1.3
 * (_tutor_profile_photo / _tutor_cover_photo), sehingga Tutor dan
 * seluruh situs membacanya sebagai foto resmi pengguna.
 */
function halo_tg2_core_save_photo() {
	if (!is_user_logged_in() || !isset($_POST['_htfnonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htfnonce'])), 'halo_tg2_save_photo')) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('foto' => 'gagal')));
		exit;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$uid       = get_current_user_id();
	$fields    = array(
		'profile_photo' => '_tutor_profile_photo',
		'cover_photo'   => '_tutor_cover_photo',
	);
	$attempted = 0;
	$saved     = 0;
	foreach ($fields as $field => $meta) {
		if (isset($_FILES[$field]) && isset($_FILES[$field]['name']) && '' !== $_FILES[$field]['name']) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- diperiksa media_handle_upload.
			$attempted++;
			$attachment_id = media_handle_upload($field, 0);
			if (!is_wp_error($attachment_id) && $attachment_id) {
				update_user_meta($uid, $meta, (int) $attachment_id);
				$saved++;
			}
		}
	}
	$status = ($attempted > 0 && $saved === $attempted) ? 'tersimpan' : 'gagal';
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('foto' => $status)));
	exit;
}

add_action('admin_post_halo_tg2_remove_photo', 'halo_tg2_core_remove_photo');
/**
 * Hapus foto profil atau sampul (meta dikosongkan; berkas lampiran
 * dibiarkan di pustaka media agar tidak menghapus data secara permanen).
 */
function halo_tg2_core_remove_photo() {
	if (!is_user_logged_in() || !isset($_POST['_htrnonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htrnonce'])), 'halo_tg2_remove_photo')) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('foto' => 'gagal')));
		exit;
	}
	$jenis = isset($_POST['jenis']) ? sanitize_key(wp_unslash($_POST['jenis'])) : '';
	$uid   = get_current_user_id();
	if ('profil' === $jenis) {
		delete_user_meta($uid, '_tutor_profile_photo');
	} elseif ('sampul' === $jenis) {
		delete_user_meta($uid, '_tutor_cover_photo');
	}
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('foto' => 'dihapus')));
	exit;
}

add_action('admin_post_halo_tg2_save_social', 'halo_tg2_core_save_social');
/**
 * Simpan akun sosial — padanan TUTOR\Student::tutor_social_profile
 * (Tutor 4.1.3): setiap kunci dari tutor_user_social_icons() disimpan
 * sebagai user meta; nilai kosong menghapus meta-nya.
 */
function halo_tg2_core_save_social() {
	if (!is_user_logged_in() || !isset($_POST['_htsononce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htsononce'])), 'halo_tg2_save_social')) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('sosial' => 'gagal')));
		exit;
	}
	$keys = array('_tutor_profile_facebook', '_tutor_profile_twitter', '_tutor_profile_linkedin', '_tutor_profile_github', '_tutor_profile_website');
	if (function_exists('tutor_utils') && method_exists(tutor_utils(), 'tutor_user_social_icons')) {
		$icons = tutor_utils()->tutor_user_social_icons();
		if (is_array($icons) && !empty($icons)) {
			$keys = array_keys($icons);
		}
	}
	$uid = get_current_user_id();
	foreach ($keys as $key) {
		$value = isset($_POST[$key]) ? sanitize_text_field(wp_unslash($_POST[$key])) : '';
		if ('' !== $value) {
			update_user_meta($uid, $key, $value);
		} else {
			delete_user_meta($uid, $key);
		}
	}
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('sosial' => 'tersimpan')));
	exit;
}

add_action('admin_post_halo_tg2_save_preferences', 'halo_tg2_core_save_preferences');
/**
 * Simpan preferensi belajar — nilai divalidasi ke daftar pilihan
 * Tutor 4.1.3 (TUTOR\UserPreference), lalu disimpan lewat metode
 * resmi Tutor bila kelasnya ada agar validasi & kaitannya utuh;
 * tersimpan sebagai satu meta 'tutor_user_preferences'.
 */
function halo_tg2_core_save_preferences() {
	if (!is_user_logged_in() || !isset($_POST['_htpnonce2']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htpnonce2'])), 'halo_tg2_save_preferences')) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('preferensi' => 'gagal')));
		exit;
	}
	$pick = function ($field, $allowed, $fallback) {
		$value = isset($_POST[$field]) ? sanitize_key(wp_unslash($_POST[$field])) : '';
		return in_array($value, $allowed, true) ? $value : $fallback;
	};
	$scale = isset($_POST['font_scale']) ? absint(wp_unslash($_POST['font_scale'])) : 100;
	if (!in_array($scale, array(60, 80, 100, 120, 140, 160, 180, 200), true)) {
		$scale = 100;
	}
	$prefs = array(
		'auto_play_next' => isset($_POST['auto_play_next']),
		'theme'          => $pick('theme', array('light', 'dark', 'system'), 'system'),
		'vision'         => $pick('vision', array('normal', 'protanopia', 'deuteranopia', 'deuteranomaly'), 'normal'),
		'contrast'       => isset($_POST['contrast']) ? 'high' : '',
		'motion_effects' => $pick('motion_effects', array('auto', 'reduce', 'standard'), 'auto'),
		'font_scale'     => $scale,
		'learning_mood'  => $pick('learning_mood', array('modern', 'kids'), 'modern'),
	);
	$uid = get_current_user_id();
	if (class_exists('\TUTOR\UserPreference')) {
		$engine = new \TUTOR\UserPreference();
		$engine->save_preferences($prefs, $uid);
	} else {
		$current = get_user_meta($uid, 'tutor_user_preferences', true);
		if (!is_array($current)) {
			$current = array();
		}
		update_user_meta($uid, 'tutor_user_preferences', array_merge($current, $prefs));
	}
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('preferensi' => 'tersimpan')));
	exit;
}

add_action('admin_post_halo_tg2_reset_preferences', 'halo_tg2_core_reset_preferences');
/**
 * Atur ulang preferensi ke bawaan — padanan reset Tutor: meta
 * 'tutor_user_preferences' dihapus sehingga nilai bawaan berlaku lagi.
 */
function halo_tg2_core_reset_preferences() {
	if (!is_user_logged_in() || !isset($_POST['_htrnonce2']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htrnonce2'])), 'halo_tg2_reset_preferences')) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('preferensi' => 'gagal')));
		exit;
	}
	delete_user_meta(get_current_user_id(), 'tutor_user_preferences');
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('preferensi' => 'diatur-ulang')));
	exit;
}

add_action('admin_post_halo_tg2_save_exam', 'halo_tg2_core_save_exam');
/**
 * Simpan pengingat ujian — meta halo_tg2_exam_date (Y-m-d) dan
 * halo_tg2_exam_name milik pengguna sendiri. Tanggal kosong berarti
 * pengingat dihapus. Banner hitung mundur di Dashboard membaca meta ini.
 */
function halo_tg2_core_save_exam() {
	if (!is_user_logged_in() || !isset($_POST['_htexnonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htexnonce'])), 'halo_tg2_save_exam')) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('ujian' => 'gagal')));
		exit;
	}
	$uid  = get_current_user_id();
	$name = isset($_POST['exam_name']) ? sanitize_text_field(wp_unslash($_POST['exam_name'])) : '';
	$date = isset($_POST['exam_date']) ? sanitize_text_field(wp_unslash($_POST['exam_date'])) : '';
	if ('' === $date) {
		delete_user_meta($uid, 'halo_tg2_exam_date');
		delete_user_meta($uid, 'halo_tg2_exam_name');
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('ujian' => 'dihapus')));
		exit;
	}
	$dt = DateTime::createFromFormat('Y-m-d', $date);
	if (!$dt || $dt->format('Y-m-d') !== $date) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('ujian' => 'gagal')));
		exit;
	}
	update_user_meta($uid, 'halo_tg2_exam_date', $date);
	update_user_meta($uid, 'halo_tg2_exam_name', $name);
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('ujian' => 'tersimpan')));
	exit;
}
