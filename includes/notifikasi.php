<?php
/**
 * Notifikasi panel dari email WooCommerce.
 *
 * Setiap email WooCommerce yang berhasil terkirim ke seorang
 * pengguna (hook resmi 'woocommerce_email_sent', WC >= 5.6) disimpan
 * sebagai notifikasi milik pengguna itu di tabel sendiri
 * {prefix}halo_tg2_notifications. Isi yang disimpan adalah badan
 * email persis seperti terkirim (ditangkap lewat filter
 * 'woocommerce_mail_content'), diubah ke teks polos agar tampil
 * bersih di panel: judul + kutipan di dropdown lonceng, daftar di
 * halaman Notifikasi, dan baca penuh dengan animasi slide.
 *
 * @package HaloTG2Core
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Nama tabel notifikasi.
 *
 * @return string
 */
function halo_tg2_core_notif_table() {
	global $wpdb;
	return $wpdb->prefix . 'halo_tg2_notifications';
}

add_action('init', 'halo_tg2_core_notif_maybe_create_table', 5);
/**
 * Buat tabel notifikasi sekali saja (dicek keberadaannya dulu).
 */
function halo_tg2_core_notif_maybe_create_table() {
	if (get_option('halo_tg2_core_notif_db_v1')) {
		return;
	}
	global $wpdb;
	$table = halo_tg2_core_notif_table();
	if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE $table (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id BIGINT UNSIGNED NOT NULL,
				email_id VARCHAR(100) NOT NULL DEFAULT '',
				title VARCHAR(255) NOT NULL DEFAULT '',
				body LONGTEXT NULL,
				is_read TINYINT(1) NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				KEY user_read (user_id, is_read),
				KEY user_created (user_id, created_at)
			) $charset;"
		);
	}
	if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		update_option('halo_tg2_core_notif_db_v1', 1);
	}
}

add_filter('woocommerce_mail_content', 'halo_tg2_core_notif_capture_content', 9999);
/**
 * Tangkap badan email Woo persis seperti yang akan terkirim.
 * Pengiriman berjalan berurutan dalam satu request, jadi satu
 * slot buffer cukup: filter ini selalu tepat mendahului
 * 'woocommerce_email_sent' milik email yang sama.
 *
 * @param string $message Badan email (HTML bergaya inline).
 * @return string
 */
function halo_tg2_core_notif_capture_content($message) {
	$GLOBALS['halo_tg2_notif_mail_body'] = $message;
	return $message;
}

add_action('woocommerce_email_sent', 'halo_tg2_core_notif_store', 10, 3);
/**
 * Simpan notifikasi untuk setiap penerima email Woo yang
 * merupakan pengguna terdaftar.
 *
 * @param bool     $sent     Hasil wp_mail.
 * @param string   $email_id ID email Woo (cth. customer_processing_order).
 * @param WC_Email $email    Objek email.
 */
function halo_tg2_core_notif_store($sent, $email_id, $email) {
	$body_html = isset($GLOBALS['halo_tg2_notif_mail_body']) ? (string) $GLOBALS['halo_tg2_notif_mail_body'] : '';
	unset($GLOBALS['halo_tg2_notif_mail_body']);
	if (!$sent || !is_object($email)) {
		return;
	}
	if (!method_exists($email, 'get_recipient') || !method_exists($email, 'get_subject')) {
		return;
	}
	$subject = trim((string) $email->get_subject());
	if ('' === $subject) {
		$subject = '(Tanpa subjek)';
	}
	$body  = halo_tg2_core_notif_html_to_text($body_html);
	$addrs = array_filter(array_map('trim', explode(',', (string) $email->get_recipient())));
	global $wpdb;
	$table = halo_tg2_core_notif_table();
	foreach ($addrs as $addr) {
		$user = get_user_by('email', $addr);
		if (!$user) {
			continue;
		}
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'user_id'    => (int) $user->ID,
				'email_id'   => sanitize_key((string) $email_id),
				'title'      => $subject,
				'body'       => $body,
				'is_read'    => 0,
				'created_at' => current_time('mysql'),
			),
			array('%d', '%s', '%s', '%s', '%d', '%s')
		);
	}
}

/**
 * Ubah badan email HTML menjadi teks polos yang enak dibaca:
 * elemen blok menjadi baris baru, tag dibuang, entitas didekode,
 * baris kosong berlebih dipadatkan.
 *
 * @param string $html Badan email.
 * @return string
 */
function halo_tg2_core_notif_html_to_text($html) {
	if ('' === trim((string) $html)) {
		return '';
	}
	$text = preg_replace('/<(br|\/p|\/div|\/tr|\/table|\/h[1-6]|\/li)[^>]*>/i', "\n", (string) $html);
	$text = wp_strip_all_tags((string) $text);
	$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
	$lines = array();
	foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
		$line = trim(preg_replace('/[ \t]+/', ' ', $line));
		if ('' === $line && (empty($lines) || '' === end($lines))) {
			if (!empty($lines)) {
				continue;
			}
		}
		$lines[] = $line;
	}
	return trim(implode("\n", $lines));
}

/**
 * Daftar notifikasi terbaru seorang pengguna.
 *
 * @param int $user_id ID pengguna.
 * @param int $limit   Jumlah maksimum.
 * @return array Baris objek (id, email_id, title, body, is_read, created_at).
 */
function halo_tg2_core_notif_list($user_id, $limit = 30) {
	global $wpdb;
	$table = halo_tg2_core_notif_table();
	$rows  = $wpdb->get_results($wpdb->prepare("SELECT id, email_id, title, body, is_read, created_at FROM $table WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d", (int) $user_id, (int) $limit)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return is_array($rows) ? $rows : array();
}

/**
 * Jumlah notifikasi belum dibaca.
 *
 * @param int $user_id ID pengguna.
 * @return int
 */
function halo_tg2_core_notif_unread($user_id) {
	global $wpdb;
	$table = halo_tg2_core_notif_table();
	return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE user_id = %d AND is_read = 0", (int) $user_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/**
 * Satu notifikasi milik pengguna.
 *
 * @param int $id      ID notifikasi.
 * @param int $user_id ID pemilik.
 * @return object|null
 */
function halo_tg2_core_notif_get($id, $user_id) {
	global $wpdb;
	$table = halo_tg2_core_notif_table();
	$row   = $wpdb->get_row($wpdb->prepare("SELECT id, email_id, title, body, is_read, created_at FROM $table WHERE id = %d AND user_id = %d", (int) $id, (int) $user_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return is_object($row) ? $row : null;
}

/**
 * Tandai satu notifikasi sebagai dibaca.
 *
 * @param int $id      ID notifikasi.
 * @param int $user_id ID pemilik.
 */
function halo_tg2_core_notif_mark_read($id, $user_id) {
	global $wpdb;
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		halo_tg2_core_notif_table(),
		array('is_read' => 1),
		array('id' => (int) $id, 'user_id' => (int) $user_id),
		array('%d'),
		array('%d', '%d')
	);
}

/**
 * Tandai semua notifikasi pengguna sebagai dibaca.
 *
 * @param int $user_id ID pengguna.
 */
function halo_tg2_core_notif_mark_all_read($user_id) {
	global $wpdb;
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		halo_tg2_core_notif_table(),
		array('is_read' => 1),
		array('user_id' => (int) $user_id, 'is_read' => 0),
		array('%d'),
		array('%d', '%d')
	);
}

/**
 * Label waktu notifikasi: relatif bila < 24 jam, tanggal bila lebih.
 *
 * @param string $mysql Waktu MySQL (waktu situs).
 * @return string
 */
function halo_tg2_core_notif_time_label($mysql) {
	$ts   = strtotime((string) $mysql);
	$diff = time() - $ts;
	if ($ts && $diff >= 0 && $diff < DAY_IN_SECONDS) {
		return human_time_diff($ts, time()) . ' lalu';
	}
	return date_i18n('j M Y, H:i', $ts ? $ts : time());
}

/**
 * Kutipan isi notifikasi untuk daftar & dropdown (dipotong ...).
 *
 * @param string $body Isi teks.
 * @param int    $len  Panjang maksimum.
 * @return string
 */
function halo_tg2_core_notif_excerpt($body, $len = 150) {
	$text = trim(preg_replace('/\s+/', ' ', (string) $body));
	if (function_exists('mb_strimwidth')) {
		return mb_strimwidth($text, 0, (int) $len, '...');
	}
	return strlen($text) > $len ? substr($text, 0, $len) . '...' : $text;
}

add_action('admin_post_halo_tg2_notif_read_all', 'halo_tg2_core_notif_read_all');
/**
 * Handler "Tandai semua dibaca" dari halaman Notifikasi.
 */
function halo_tg2_core_notif_read_all() {
	$back = home_url('/');
	$ret  = isset($_POST['return_url']) ? esc_url_raw(wp_unslash($_POST['return_url'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ('' !== $ret && false !== strpos($ret, home_url('/'))) {
		$back = $ret;
	}
	if (!is_user_logged_in() || !isset($_POST['_htnnonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['_htnnonce'])), 'halo_tg2_notif_read_all')) {
		wp_safe_redirect($back);
		exit;
	}
	halo_tg2_core_notif_mark_all_read(get_current_user_id());
	wp_safe_redirect($back);
	exit;
}

/**
 * Render halaman Notifikasi di panel (daftar atau baca penuh).
 * Dipanggil tema dari page-templates/panel.php.
 */
function halo_tg2_core_render_notifications() {
	$uid      = get_current_user_id();
	$panel    = halo_tg2_core_panel_url();
	$list_url = '' !== $panel ? add_query_arg('tampilan', 'notifikasi', $panel) : home_url('/');
	$baca_id  = isset($_GET['baca']) ? absint(wp_unslash($_GET['baca'])) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	if ($baca_id > 0) {
		$row = halo_tg2_core_notif_get($baca_id, $uid);
		if ($row) {
			if (0 === (int) $row->is_read) {
				halo_tg2_core_notif_mark_read($baca_id, $uid);
			}
			?>
			<a class="ht-panel__notif-back" href="<?php echo esc_url($list_url); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 5l-7 7 7 7"/></svg>Kembali ke Notifikasi</a>
			<article class="ht-panel__notif-detail">
				<p class="ht-panel__notif-meta"><?php echo esc_html(halo_tg2_core_notif_time_label($row->created_at)); ?> · Terkirim juga ke email kamu</p>
				<h2><?php echo esc_html($row->title); ?></h2>
				<div class="ht-panel__notif-body"><?php echo nl2br(esc_html((string) $row->body)); ?></div>
			</article>
			<?php
			return;
		}
	}

	$rows   = halo_tg2_core_notif_list($uid, 30);
	$unread = halo_tg2_core_notif_unread($uid);
	?>
	<div class="ht-panel__notif-head">
		<p class="ht-panel__notif-count">
			<?php
			if ($unread > 0) {
				echo esc_html($unread . ' notifikasi belum dibaca');
			} elseif (!empty($rows)) {
				echo 'Semua notifikasi sudah dibaca';
			}
			?>
		</p>
		<?php if ($unread > 0) : ?>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<input type="hidden" name="action" value="halo_tg2_notif_read_all">
				<?php wp_nonce_field('halo_tg2_notif_read_all', '_htnnonce'); ?>
				<input type="hidden" name="return_url" value="<?php echo esc_url($list_url); ?>">
				<button type="submit" class="halo-tg2__btn halo-tg2__btn--ghost">Tandai Semua Dibaca</button>
			</form>
		<?php endif; ?>
	</div>
	<?php if (empty($rows)) : ?>
		<div class="halo-tg2__empty">
			<div class="halo-tg2__empty-icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 9a6 6 0 1 0-12 0c0 6-2.5 7-2.5 7h17S18 15 18 9ZM10 20a2.2 2.2 0 0 0 4 0"/></svg>
			</div>
			<h3>Belum ada notifikasi</h3>
			<p>Email dari toko (pesanan, pembayaran, akun) akan muncul di sini begitu terkirim ke email kamu.</p>
		</div>
	<?php else : ?>
		<div class="ht-panel__notif-list">
			<?php foreach ($rows as $i => $row) : ?>
				<a class="ht-panel__notif-item<?php echo 0 === (int) $row->is_read ? ' is-unread' : ''; ?>" style="--d:<?php echo esc_attr(min($i * 60, 480)); ?>ms" href="<?php echo esc_url(add_query_arg('baca', (int) $row->id, $list_url)); ?>">
					<?php if (0 === (int) $row->is_read) : ?>
						<span class="ht-panel__notif-dot" aria-hidden="true"></span>
					<?php endif; ?>
					<span class="ht-panel__notif-main">
						<strong><?php echo esc_html($row->title); ?></strong>
						<span><?php echo esc_html(halo_tg2_core_notif_excerpt($row->body, 140)); ?></span>
					</span>
					<time><?php echo esc_html(halo_tg2_core_notif_time_label($row->created_at)); ?></time>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
}
