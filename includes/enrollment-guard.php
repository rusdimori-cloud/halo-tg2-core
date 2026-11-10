<?php
/**
 * Jaring pengaman enrollment (Fase 6, Bagian 8 dokumen arsitektur).
 * Jalur resmi (order lunas → Tutor auto-enroll) tidak diubah. File ini
 * hanya MEMERIKSA: bila order berbayar memuat produk yang tertaut ke
 * sebuah course tetapi pembelinya ternyata belum ter-enroll, guard
 * mencoba enroll lewat API Tutor dan mencatat hasilnya di catatan order
 * + error log — tidak pernah diam-diam menganggap beres.
 *
 * @package HaloTG2Core
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('woocommerce_order_status_changed', 'halo_tg2_core_enrollment_guard', 20, 3);
/**
 * Periksa enrollment setiap kali status order berubah ke status berbayar.
 *
 * @param int    $order_id ID order.
 * @param string $old      Status lama (tanpa awalan wc-).
 * @param string $new      Status baru (tanpa awalan wc-).
 */
function halo_tg2_core_enrollment_guard($order_id, $old, $new) {
	if (!in_array($new, array('processing', 'completed'), true)) {
		return;
	}
	if (!function_exists('tutor_utils') || !class_exists('WooCommerce')) {
		return;
	}
	$order = wc_get_order($order_id);
	if (!$order) {
		return;
	}
	$user_id = $order->get_user_id();
	if (!$user_id) {
		return;
	}
	foreach ($order->get_items() as $item) {
		$product_id = $item->get_product_id();
		if (!$product_id) {
			continue;
		}
		$course_ids = halo_tg2_core_courses_for_product($product_id);
		foreach ($course_ids as $course_id) {
			if (method_exists(tutor_utils(), 'is_enrolled') && tutor_utils()->is_enrolled($course_id, $user_id)) {
				continue; // Jalur resmi bekerja — tidak ada yang perlu dijaga.
			}
			$done = false;
			if (method_exists(tutor_utils(), 'do_enroll')) {
				$done = (bool) tutor_utils()->do_enroll($course_id, $order_id, $user_id);
			}
			$course_title = get_the_title($course_id);
			if ($done) {
				$order->add_order_note(sprintf('halo-tg2-core: enrollment otomatis untuk course "%s" tidak ditemukan; guard berhasil meng-enroll pembeli.', $course_title));
			} else {
				$order->add_order_note(sprintf('halo-tg2-core: PERINGATAN — pembeli belum ter-enroll di course "%s" dan guard tidak berhasil meng-enroll. Periksa manual.', $course_title));
			}
			error_log(sprintf('[halo-tg2-core] enrollment-guard order #%d course #%d user #%d hasil: %s', $order_id, $course_id, $user_id, $done ? 'enrolled' : 'GAGAL'));
		}
	}
}

/**
 * Cari course Tutor yang tertaut ke sebuah produk Woo.
 * Meta tautan dicoba pada dua kunci yang dipakai antarversi Tutor.
 *
 * @param int $product_id ID produk Woo.
 * @return int[] Daftar ID course.
 */
function halo_tg2_core_courses_for_product($product_id) {
	$found = array();
	foreach (array('_tutor_course_product_id', 'tutor_course_product_id') as $key) {
		$ids = get_posts(array(
			'post_type'      => 'courses',
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
			'meta_key'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'     => (string) $product_id, // phpcs:ignore WordPress.DB.SlowDBQuery
		));
		$found = array_merge($found, $ids);
	}
	return array_unique(array_map('intval', $found));
}
