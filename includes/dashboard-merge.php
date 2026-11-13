<?php
/**
 * Dashboard gabungan (Fase 6), jantung rencana penggabungan.
 * Menambah dua tab Woo ke dashboard Tutor lewat filter resminya:
 *  - "Pesanan Saya"     : daftar order pengguna (wc_get_orders).
 *  - "Akun & Pembayaran": ringkasan akun + jalan ke endpoint Woo.
 * Konten tab dirender di sini; Tutor memuatnya lewat template tema
 * tutor/dashboard/pesanan-saya.php & tutor/dashboard/akun-pembayaran.php.
 *
 * @package HaloTG2Core
 */

if (!defined('ABSPATH')) {
	exit;
}

add_filter('tutor_dashboard/nav_items', 'halo_tg2_core_dashboard_tabs_items');
/**
 * Daftarkan tab Woo pada menu dashboard Tutor.
 *
 * @param array $items Item menu bawaan Tutor.
 * @return array
 */
function halo_tg2_core_dashboard_tabs_items($items) {
	if (!is_array($items)) {
		return $items;
	}
	$base = halo_tg2_core_dashboard_url();
	$items['halo-tg2-belanja'] = array(
		'title' => 'Belanja',
		'type'  => 'separator',
	);
	$items['pesanan-saya'] = array(
		'title' => 'Pesanan Saya',
		'icon'  => 'tutor-icon-cart-line',
		'url'   => trailingslashit($base) . 'pesanan-saya',
	);
	$items['akun-pembayaran'] = array(
		'title' => 'Akun & Pembayaran',
		'icon'  => 'tutor-icon-user-line',
		'url'   => trailingslashit($base) . 'akun-pembayaran',
	);
	return $items;
}

/**
 * Render isi tab Pesanan Saya (dipanggil template tema).
 */
function halo_tg2_core_render_orders() {
	if (!class_exists('WooCommerce') || !is_user_logged_in()) {
		echo '<p>Fitur pesanan belum aktif.</p>';
		return;
	}
	$orders = wc_get_orders(array(
		'customer_id' => get_current_user_id(),
		'limit'       => 20,
		'orderby'     => 'date',
		'order'       => 'DESC',
		'status'      => array_keys(wc_get_order_statuses()),
	));
	if (empty($orders)) {
		?>
		<div class="halo-tg2__empty">
			<div class="halo-tg2__empty-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 7h12l-1.2 11a2 2 0 0 1-2 1.8H9.2a2 2 0 0 1-2-1.8L6 7Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg>
			</div>
			<h3>Belum ada pesanan</h3>
			<p>Pesanan pertamamu akan muncul di sini setelah kamu membeli kelas.</p>
		</div>
		<?php
		return;
	}
	?>
	<div class="ht-core__orders">
		<?php foreach ($orders as $order) : ?>
			<?php
			$names = array();
			foreach ($order->get_items() as $item) {
				$names[] = $item->get_name();
			}
			$paid = $order->is_paid();
			?>
			<article class="ht-core__order">
				<div class="ht-core__order-main">
					<p class="ht-core__order-name"><?php echo esc_html(implode(', ', $names)); ?></p>
					<p class="ht-core__order-meta">
						#<?php echo esc_html($order->get_order_number()); ?> ·
						<?php echo esc_html($order->get_date_created() ? $order->get_date_created()->date_i18n('j M Y') : ''); ?> ·
						<?php echo wp_kses_post($order->get_formatted_order_total()); ?>
					</p>
				</div>
				<span class="ht-core__chip <?php echo $paid ? 'is-paid' : 'is-pending'; ?>"><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></span>
				<a class="ht-core__order-link" href="<?php echo esc_url($order->get_view_order_url()); ?>">Detail</a>
			</article>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Render isi tab Akun & Pembayaran (dipanggil template tema).
 * Berisi daftar metode pembayaran tersimpan dan penghapusannya,
 * langsung di tempat. Kepala akun dan kartu jalan ke endpoint Woo
 * (Detail Akun, Alamat Penagihan) dihapus atas perintah pemilik
 * 2026-11-13: panel tidak boleh lagi mengirim siswa ke /my-account/,
 * urusan detail akun ditangani halaman Pengaturan panel sendiri.
 * Tombol tambah metode ikut dihapus: situs belum punya gateway kartu
 * yang mendukung penyimpanan, dan alur tambahnya rusak sejak awal
 * (nama berkas template Woo yang dicari salah) sehingga selalu jatuh
 * ke teks cadangan bertautan /my-account/. Kartu tersimpan akan
 * muncul sendiri setelah pembayaran memakai gateway pendukung.
 */
function halo_tg2_core_render_account() {
	if (!class_exists('WooCommerce') || !is_user_logged_in()) {
		echo '<p>Fitur akun belum aktif.</p>';
		return;
	}
	$user_id = get_current_user_id();
	$panel   = halo_tg2_core_panel_url();
	$self    = '' !== $panel
		? add_query_arg('tampilan', 'akun', $panel)
		: home_url('/');
	$notice  = isset($_GET['bayar']) ? sanitize_key(wp_unslash($_GET['bayar'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	?>
	<h3 style="font-size:17px;font-weight:600;margin:0 0 6px">Metode Pembayaran Tersimpan</h3>
	<p style="font-size:13.6px;color:#5B6B8C;margin-bottom:12px">Kartu tersimpan muncul setelah pembayaran pertamamu memakai gateway yang mendukung penyimpanan kartu.</p>
	<?php if ('terhapus' === $notice) : ?>
		<p style="background:rgba(18,138,67,.09);border:1px solid rgba(18,138,67,.35);color:#128A43;border-radius:12px;padding:10px 14px;font-size:13.6px;margin-bottom:12px">Metode pembayaran sudah dihapus.</p>
	<?php elseif ('ditambah' === $notice) : ?>
		<p style="background:rgba(18,138,67,.09);border:1px solid rgba(18,138,67,.35);color:#128A43;border-radius:12px;padding:10px 14px;font-size:13.6px;margin-bottom:12px">Metode pembayaran baru sudah ditambahkan.</p>
	<?php elseif ('gagal' === $notice) : ?>
		<p style="background:rgba(217,45,32,.08);border:1px solid rgba(217,45,32,.35);color:#D92D20;border-radius:12px;padding:10px 14px;font-size:13.6px;margin-bottom:12px">Aksi belum berhasil. Coba lagi ya.</p>
	<?php endif; ?>
	<?php
	$tokens = WC_Payment_Tokens::get_customer_tokens($user_id);
	if (empty($tokens)) :
		?>
		<div class="halo-tg2__empty">
			<h3>Belum ada metode tersimpan</h3>
			<p>Belum ada kartu yang tersimpan di akunmu.</p>
		</div>
	<?php else : ?>
		<div class="ht-core__orders">
			<?php foreach ($tokens as $token) : ?>
				<article class="ht-core__order">
					<div class="ht-core__order-main">
						<p class="ht-core__order-name"><?php echo esc_html($token->get_display_name()); ?></p>
						<?php if (method_exists($token, 'get_expiry_month') && $token->get_expiry_month()) : ?>
							<p class="ht-core__order-meta">Berlaku sampai <?php echo esc_html($token->get_expiry_month() . '/' . $token->get_expiry_year()); ?></p>
						<?php endif; ?>
					</div>
					<?php if ($token->is_default()) : ?>
						<span class="ht-core__chip is-paid">Utama</span>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Hapus metode pembayaran ini?');">
						<input type="hidden" name="action" value="halo_tg2_delete_pm">
						<input type="hidden" name="token_id" value="<?php echo esc_attr($token->get_id()); ?>">
						<input type="hidden" name="return_url" value="<?php echo esc_url($self); ?>">
						<?php wp_nonce_field('halo_tg2_delete_pm', '_htpmnonce'); ?>
						<button type="submit" style="background:none;border:0;color:#D92D20;font-weight:600;font-size:14px;cursor:pointer;font-family:inherit">Hapus</button>
					</form>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
}

add_action('wp_enqueue_scripts', 'halo_tg2_core_dashboard_assets');
/**
 * Muat CSS komponen tab hanya di halaman dashboard.
 */
function halo_tg2_core_dashboard_assets() {
	if (!is_page()) {
		return;
	}
	$post = get_queried_object();
	if ($post && has_shortcode((string) $post->post_content, 'tutor_dashboard')) {
		wp_enqueue_style(
			'halo-tg2-core-tabs',
			plugin_dir_url(__DIR__) . 'assets/css/dashboard-tabs.css',
			array(),
			HALO_TG2_CORE_VERSION
		);
	}
}

add_action('admin_post_halo_tg2_wishlist_remove', 'halo_tg2_core_wishlist_remove');
/**
 * Hapus satu kelas dari wishlist Tutor milik pengguna
 * (baris usermeta '_tutor_course_wishlist', mekanisme yang sama
 * dengan penghapusan bawaan Tutor), lalu kembali ke halaman
 * Wishlist panel.
 */
function halo_tg2_core_wishlist_remove() {
	$back = home_url('/');
	$ret  = isset($_POST['return_url']) ? esc_url_raw(wp_unslash($_POST['return_url'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ('' !== $ret && false !== strpos($ret, home_url('/'))) {
		$back = $ret;
	}
	if (!is_user_logged_in() || !isset($_POST['_htwnonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htwnonce'])), 'halo_tg2_wishlist_remove')) {
		wp_safe_redirect($back);
		exit;
	}
	$course_id = isset($_POST['course_id']) ? absint(wp_unslash($_POST['course_id'])) : 0;
	if ($course_id > 0) {
		delete_user_meta(get_current_user_id(), '_tutor_course_wishlist', $course_id);
		$back = add_query_arg('status', 'terhapus', $back);
	}
	wp_safe_redirect($back);
	exit;
}
