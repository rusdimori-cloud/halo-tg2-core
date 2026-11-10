<?php
/**
 * Profil publik Tutor — halo-tg2.com/profile/<username>/.
 *
 * Tutor LMS hanya melayani halaman profil bila layout profil pada
 * opsinya bukan "private" (gerbang di templates/public-profile.php).
 * Bawaan situs ini belum pernah disimpan, jadi profil terkunci.
 * Rutin sekali-jalan ini menyetel layout siswa + instruktur ke
 * "pp-circle" (nilai persisnya bebas — tema mengganti seluruh
 * tampilannya lewat override tutor/public-profile.php), lalu
 * menyegarkan aturan rewrite sekali.
 *
 * @package HaloTG2Core
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_init', 'halo_tg2_core_enable_public_profiles');
/**
 * Aktifkan profil publik Tutor sekali saja (bendera opsi sendiri).
 */
function halo_tg2_core_enable_public_profiles() {
	if (get_option('halo_tg2_core_profile_v1')) {
		return;
	}
	if (!function_exists('tutor_utils')) {
		return;
	}
	$opts = get_option('tutor_option');
	if (!is_array($opts)) {
		$opts = array();
	}
	foreach (array('student_public_profile_layout', 'public_profile_layout') as $key) {
		if (empty($opts[$key]) || 'private' === $opts[$key]) {
			$opts[$key] = 'pp-circle';
		}
	}
	update_option('tutor_option', $opts);
	flush_rewrite_rules(false);
	update_option('halo_tg2_core_profile_v1', 1);
}
