<?php
/**
 * Pengaturan profil & kata sandi di panel (Fase 9, satu dasbor).
 *
 * Nama field, kunci meta, dan validasi meniru persis handler Tutor
 * LMS 4.1.3 (TUTOR\Student::update_profile dan ::tutor_reset_password)
 * agar data yang tersimpan tetap kompatibel dengan Tutor, yang
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

/**
 * Balasan AJAX saat gerbang sesi/nonce gagal: kode 'ht_gate' memberi
 * tahu JS untuk mundur ke kiriman form cara lama (admin-post), yang
 * perilakunya sama seperti sebelum ada AJAX.
 */
function halo_tg2_core_ajax_gate_fail() {
	wp_send_json_error(array('code' => 'ht_gate', 'message' => 'Sesi formulir tidak valid.'));
}

add_action('admin_post_halo_tg2_save_profile', 'halo_tg2_core_save_profile');
add_action('wp_ajax_halo_tg2_save_profile', 'halo_tg2_core_save_profile_ajax');
/**
 * Gerbang sesi + nonce form profil (dipakai jalur klasik dan AJAX).
 */
function halo_tg2_core_save_profile_gate() {
	return is_user_logged_in() && isset($_POST['_htpnonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htpnonce'])), 'halo_tg2_save_profile');
}

/**
 * Simpan profil: padanan TUTOR\Student::update_profile (Tutor 4.1.3):
 * wp_update_user(first_name, last_name, display_name) + meta
 * phone_number, _tutor_profile_bio, _tutor_profile_job_title,
 * _tutor_timezone, plus action tutor_profile_update_before/after.
 *
 * @return string 'tersimpan' atau 'gagal'.
 */
function halo_tg2_core_save_profile_process() {
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

	if (is_wp_error($result)) {
		return 'gagal';
	}
	update_user_meta($uid, 'phone_number', $phone);
	update_user_meta($uid, '_tutor_profile_bio', $bio);
	update_user_meta($uid, '_tutor_profile_job_title', $job);
	update_user_meta($uid, '_tutor_timezone', $timezone);
	do_action('tutor_profile_update_after', $uid);
	return 'tersimpan';
}

/**
 * Jalur klasik (admin-post): simpan lalu kembali dengan status.
 */
function halo_tg2_core_save_profile() {
	if (!halo_tg2_core_save_profile_gate()) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('profil' => 'gagal')));
		exit;
	}
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('profil' => halo_tg2_core_save_profile_process())));
	exit;
}

/**
 * Jalur AJAX: logika simpan yang sama persis, balasan JSON
 * agar panel bisa menampilkan pesan tanpa memuat ulang halaman.
 */
function halo_tg2_core_save_profile_ajax() {
	if (!halo_tg2_core_save_profile_gate()) {
		halo_tg2_core_ajax_gate_fail();
	}
	if ('tersimpan' !== halo_tg2_core_save_profile_process()) {
		wp_send_json_error(array('message' => 'Profil belum tersimpan. Coba lagi sebentar lagi.'));
	}
	$user = wp_get_current_user();
	wp_send_json_success(array(
		'message'      => 'Profil tersimpan.',
		'display_name' => $user->display_name,
		'first_name'   => $user->first_name,
		'initial'      => strtoupper(substr($user->display_name, 0, 1)),
	));
}

add_action('admin_post_halo_tg2_change_password', 'halo_tg2_core_change_password');
/**
 * Ganti kata sandi: padanan TUTOR\Student::tutor_reset_password
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
 * Unggah foto profil & sampul: berkas menjadi lampiran WordPress dan
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
add_action('wp_ajax_halo_tg2_remove_photo', 'halo_tg2_core_remove_photo_ajax');
/**
 * Gerbang sesi + nonce form reset foto.
 */
function halo_tg2_core_remove_photo_gate() {
	return is_user_logged_in() && isset($_POST['_htrnonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htrnonce'])), 'halo_tg2_remove_photo');
}

/**
 * Hapus foto profil atau sampul (meta dikosongkan; berkas lampiran
 * dibiarkan di pustaka media agar tidak menghapus data secara permanen).
 *
 * @return string Jenis foto dari permintaan: 'profil', 'sampul', atau ''.
 */
function halo_tg2_core_remove_photo_process() {
	$jenis = isset($_POST['jenis']) ? sanitize_key(wp_unslash($_POST['jenis'])) : '';
	$uid   = get_current_user_id();
	if ('profil' === $jenis) {
		delete_user_meta($uid, '_tutor_profile_photo');
	} elseif ('sampul' === $jenis) {
		delete_user_meta($uid, '_tutor_cover_photo');
	}
	return $jenis;
}

/**
 * Jalur klasik (admin-post).
 */
function halo_tg2_core_remove_photo() {
	if (!halo_tg2_core_remove_photo_gate()) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('foto' => 'gagal')));
		exit;
	}
	halo_tg2_core_remove_photo_process();
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('foto' => 'dihapus')));
	exit;
}

/**
 * Jalur AJAX: balasan membawa keadaan foto terbaru agar panel bisa
 * memperbarui avatar dan sampul di tempat.
 */
function halo_tg2_core_remove_photo_ajax() {
	if (!halo_tg2_core_remove_photo_gate()) {
		halo_tg2_core_ajax_gate_fail();
	}
	$jenis = halo_tg2_core_remove_photo_process();
	$uid   = get_current_user_id();
	$pid   = (int) get_user_meta($uid, '_tutor_profile_photo', true);
	$cid   = (int) get_user_meta($uid, '_tutor_cover_photo', true);
	$user  = wp_get_current_user();
	wp_send_json_success(array(
		'message'   => 'Foto dihapus.',
		'jenis'     => $jenis,
		'photo_url' => $pid ? (string) wp_get_attachment_image_url($pid, 'thumbnail') : '',
		'cover_url' => $cid ? (string) wp_get_attachment_image_url($cid, 'large') : '',
		'initial'   => strtoupper(substr($user->display_name, 0, 1)),
	));
}

add_action('admin_post_halo_tg2_save_social', 'halo_tg2_core_save_social');
add_action('wp_ajax_halo_tg2_save_social', 'halo_tg2_core_save_social_ajax');
/**
 * Gerbang sesi + nonce form akun sosial.
 */
function halo_tg2_core_save_social_gate() {
	return is_user_logged_in() && isset($_POST['_htsononce']) && wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htsononce'])), 'halo_tg2_save_social');
}

/**
 * Simpan akun sosial: padanan TUTOR\Student::tutor_social_profile
 * (Tutor 4.1.3): setiap kunci dari tutor_user_social_icons() disimpan
 * sebagai user meta; nilai kosong menghapus meta-nya.
 *
 * @return string Selalu 'tersimpan' setelah gerbang lolos.
 */
function halo_tg2_core_save_social_process() {
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
	return 'tersimpan';
}

/**
 * Jalur klasik (admin-post).
 */
function halo_tg2_core_save_social() {
	if (!halo_tg2_core_save_social_gate()) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('sosial' => 'gagal')));
		exit;
	}
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('sosial' => halo_tg2_core_save_social_process())));
	exit;
}

/**
 * Jalur AJAX: logika simpan yang sama, balasan JSON.
 */
function halo_tg2_core_save_social_ajax() {
	if (!halo_tg2_core_save_social_gate()) {
		halo_tg2_core_ajax_gate_fail();
	}
	halo_tg2_core_save_social_process();
	wp_send_json_success(array('message' => 'Akun sosial tersimpan.'));
}

add_action('admin_post_halo_tg2_save_preferences', 'halo_tg2_core_save_preferences');
add_action('wp_ajax_halo_tg2_save_preferences', 'halo_tg2_core_save_preferences_ajax');
/**
 * Gerbang sesi + nonce form preferensi.
 */
function halo_tg2_core_save_preferences_gate() {
	return is_user_logged_in() && isset($_POST['_htpnonce2']) && wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htpnonce2'])), 'halo_tg2_save_preferences');
}

/**
 * Simpan preferensi belajar: nilai divalidasi ke daftar pilihan
 * Tutor 4.1.3 (TUTOR\UserPreference), lalu disimpan lewat metode
 * resmi Tutor bila kelasnya ada agar validasi & kaitannya utuh;
 * tersimpan sebagai satu meta 'tutor_user_preferences'.
 *
 * @return string Selalu 'tersimpan' setelah gerbang lolos.
 */
function halo_tg2_core_save_preferences_process() {
	$pick = function ($field, $allowed, $fallback) {
		$value = isset($_POST[$field]) ? sanitize_key(wp_unslash($_POST[$field])) : '';
		return in_array($value, $allowed, true) ? $value : $fallback;
	};
	$scale = isset($_POST['font_scale']) ? absint(wp_unslash($_POST['font_scale'])) : 100;
	if (!in_array($scale, array(60, 80, 100, 120, 140, 160, 180, 200), true)) {
		$scale = 100;
	}
	// Tema terang/gelap TIDAK lagi diatur dari panel (tidak sinkron
	// dengan Mode Gelap situs), nilai tersimpan lama dipertahankan
	// apa adanya agar perilaku Tutor di halaman lesson tidak berubah.
	$ht_saved_prefs = get_user_meta(get_current_user_id(), 'tutor_user_preferences', true);
	$ht_theme_keep  = (is_array($ht_saved_prefs) && isset($ht_saved_prefs['theme']) && in_array($ht_saved_prefs['theme'], array('light', 'dark', 'system'), true)) ? $ht_saved_prefs['theme'] : 'system';
	$prefs = array(
		'auto_play_next' => isset($_POST['auto_play_next']),
		'theme'          => $ht_theme_keep,
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
	return 'tersimpan';
}

/**
 * Jalur klasik (admin-post).
 */
function halo_tg2_core_save_preferences() {
	if (!halo_tg2_core_save_preferences_gate()) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('preferensi' => 'gagal')));
		exit;
	}
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('preferensi' => halo_tg2_core_save_preferences_process())));
	exit;
}

/**
 * Jalur AJAX: logika simpan yang sama, balasan JSON.
 */
function halo_tg2_core_save_preferences_ajax() {
	if (!halo_tg2_core_save_preferences_gate()) {
		halo_tg2_core_ajax_gate_fail();
	}
	halo_tg2_core_save_preferences_process();
	wp_send_json_success(array('message' => 'Preferensi tersimpan.'));
}

add_action('admin_post_halo_tg2_reset_preferences', 'halo_tg2_core_reset_preferences');
add_action('wp_ajax_halo_tg2_reset_preferences', 'halo_tg2_core_reset_preferences_ajax');
/**
 * Gerbang sesi + nonce form atur ulang preferensi.
 */
function halo_tg2_core_reset_preferences_gate() {
	return is_user_logged_in() && isset($_POST['_htrnonce2']) && wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htrnonce2'])), 'halo_tg2_reset_preferences');
}

/**
 * Atur ulang preferensi ke bawaan: padanan reset Tutor: meta
 * 'tutor_user_preferences' dihapus sehingga nilai bawaan berlaku lagi.
 *
 * @return string Selalu 'diatur-ulang' setelah gerbang lolos.
 */
function halo_tg2_core_reset_preferences_process() {
	delete_user_meta(get_current_user_id(), 'tutor_user_preferences');
	return 'diatur-ulang';
}

/**
 * Nilai bawaan preferensi (samad dengan bawaan Tutor di panel).
 *
 * @return array
 */
function halo_tg2_core_preferences_defaults() {
	return array(
		'auto_play_next' => false,
		'theme'          => 'system',
		'vision'         => 'normal',
		'contrast'       => '',
		'motion_effects' => 'auto',
		'font_scale'     => 100,
		'learning_mood'  => 'modern',
	);
}

/**
 * Jalur klasik (admin-post).
 */
function halo_tg2_core_reset_preferences() {
	if (!halo_tg2_core_reset_preferences_gate()) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('preferensi' => 'gagal')));
		exit;
	}
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('preferensi' => halo_tg2_core_reset_preferences_process())));
	exit;
}

/**
 * Jalur AJAX: balasan membawa nilai bawaan agar panel bisa
 * mengembalikan semua kontrol form ke keadaan awal di tempat.
 */
function halo_tg2_core_reset_preferences_ajax() {
	if (!halo_tg2_core_reset_preferences_gate()) {
		halo_tg2_core_ajax_gate_fail();
	}
	halo_tg2_core_reset_preferences_process();
	wp_send_json_success(array(
		'message' => 'Preferensi diatur ulang ke bawaan.',
		'prefs'   => halo_tg2_core_preferences_defaults(),
	));
}

add_action('admin_post_halo_tg2_save_exam', 'halo_tg2_core_save_exam');
add_action('wp_ajax_halo_tg2_save_exam', 'halo_tg2_core_save_exam_ajax');
/**
 * Gerbang sesi + nonce form pengingat ujian.
 */
function halo_tg2_core_save_exam_gate() {
	return is_user_logged_in() && isset($_POST['_htexnonce']) && wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htexnonce'])), 'halo_tg2_save_exam');
}

/**
 * Simpan pengingat ujian: meta halo_tg2_exam_date (Y-m-d) dan
 * halo_tg2_exam_name milik pengguna sendiri. Tanggal kosong berarti
 * pengingat dihapus. Banner hitung mundur di Dashboard membaca meta ini.
 *
 * @return string 'tersimpan', 'dihapus', atau 'gagal'.
 */
function halo_tg2_core_save_exam_process() {
	$uid  = get_current_user_id();
	$name = isset($_POST['exam_name']) ? sanitize_text_field(wp_unslash($_POST['exam_name'])) : '';
	$date = isset($_POST['exam_date']) ? sanitize_text_field(wp_unslash($_POST['exam_date'])) : '';
	if ('' === $date) {
		delete_user_meta($uid, 'halo_tg2_exam_date');
		delete_user_meta($uid, 'halo_tg2_exam_name');
		return 'dihapus';
	}
	$dt = DateTime::createFromFormat('Y-m-d', $date);
	if (!$dt || $dt->format('Y-m-d') !== $date) {
		return 'gagal';
	}
	update_user_meta($uid, 'halo_tg2_exam_date', $date);
	update_user_meta($uid, 'halo_tg2_exam_name', $name);
	return 'tersimpan';
}

/**
 * Jalur klasik (admin-post).
 */
function halo_tg2_core_save_exam() {
	if (!halo_tg2_core_save_exam_gate()) {
		wp_safe_redirect(halo_tg2_core_pengaturan_url(array('ujian' => 'gagal')));
		exit;
	}
	wp_safe_redirect(halo_tg2_core_pengaturan_url(array('ujian' => halo_tg2_core_save_exam_process())));
	exit;
}

/**
 * Jalur AJAX: logika simpan yang sama, balasan JSON.
 */
function halo_tg2_core_save_exam_ajax() {
	if (!halo_tg2_core_save_exam_gate()) {
		halo_tg2_core_ajax_gate_fail();
	}
	$status = halo_tg2_core_save_exam_process();
	if ('gagal' === $status) {
		wp_send_json_error(array('message' => 'Pengingat ujian belum tersimpan. Periksa tanggalnya lalu coba lagi.'));
	}
	wp_send_json_success(array('message' => 'dihapus' === $status ? 'Pengingat ujian dihapus.' : 'Pengingat ujian tersimpan. Hitung mundurnya tampil di Dashboard.'));
}
