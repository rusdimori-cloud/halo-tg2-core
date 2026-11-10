<?php
/**
 * Pengaturan persisten halo-tg2-core + halaman pengaturannya.
 * Disimpan sebagai option WordPress (bukan theme_mod) agar tidak hilang
 * saat tema berganti (Bagian 8 dokumen arsitektur).
 * Halaman: Settings → Halo TG2 Core. Penampilan situs BUKAN di sini —
 * itu wilayah Customizer → Halo TG2 milik tema.
 *
 * @package HaloTG2Core
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_init', 'halo_tg2_core_register_settings');
/**
 * Daftarkan option persisten plugin.
 */
function halo_tg2_core_register_settings() {
	register_setting('halo_tg2_core', 'halo_tg2_core_line_url', array(
		'type'              => 'string',
		'sanitize_callback' => 'esc_url_raw',
		'default'           => '',
	));
	register_setting('halo_tg2_core', 'halo_tg2_core_contact_email', array(
		'type'              => 'string',
		'sanitize_callback' => 'sanitize_email',
		'default'           => '',
	));
	register_setting('halo_tg2_core', 'halo_tg2_core_softnav', array(
		'type'              => 'string',
		'sanitize_callback' => 'halo_tg2_core_sanitize_softnav',
		'default'           => '1',
	));
	add_settings_section(
		'halo_tg2_core_main',
		'Kontak resmi',
		'halo_tg2_core_section_text',
		'halo-tg2-core'
	);
	add_settings_field(
		'halo_tg2_core_line_url',
		'Tautan LINE OA',
		'halo_tg2_core_field_line_url',
		'halo-tg2-core',
		'halo_tg2_core_main'
	);
	add_settings_field(
		'halo_tg2_core_contact_email',
		'Email kontak resmi',
		'halo_tg2_core_field_contact_email',
		'halo-tg2-core',
		'halo_tg2_core_main'
	);
	add_settings_section(
		'halo_tg2_core_nav',
		'Navigasi',
		'halo_tg2_core_nav_section_text',
		'halo-tg2-core'
	);
	add_settings_field(
		'halo_tg2_core_softnav',
		'Navigasi lembut',
		'halo_tg2_core_field_softnav',
		'halo-tg2-core',
		'halo_tg2_core_nav'
	);
}

/**
 * Sanitasi saklar navigasi lembut: hanya '1' atau '0'.
 *
 * @param mixed $value Nilai dari formulir.
 * @return string
 */
function halo_tg2_core_sanitize_softnav($value) {
	return '1' === (string) $value ? '1' : '0';
}

/**
 * Teks pengantar seksi navigasi.
 */
function halo_tg2_core_nav_section_text() {
	echo '<p>Perilaku perpindahan halaman di panel siswa dan halaman lesson.</p>';
}

/**
 * Kolom saklar navigasi lembut (panel & lesson tanpa muat ulang,
 * hanya konten yang bertukar). Matikan bila ada masalah tampilan —
 * situs kembali ke perpindahan halaman biasa seketika.
 */
function halo_tg2_core_field_softnav() {
	$on = '1' === (string) get_option('halo_tg2_core_softnav', '1');
	echo '<label><input type="checkbox" name="halo_tg2_core_softnav" value="1"' . checked($on, true, false) . '> Aktifkan navigasi lembut + skeleton</label>';
	echo '<p class="description">Berlaku untuk dashboard panel dan halaman lesson. Checkout, login, dan konten video/kuis bawaan Tutor selalu memakai muat penuh.</p>';
}

/**
 * Ambil pengaturan persisten plugin.
 *
 * @param string $key 'line_url' | 'contact_email'
 * @return string
 */
function halo_tg2_core_setting($key) {
	return (string) get_option('halo_tg2_core_' . $key, '');
}

/**
 * Teks pengantar seksi pengaturan.
 */
function halo_tg2_core_section_text() {
	echo '<p>Data kontak resmi situs. Dipakai komponen-komponen halo TG2 dan tetap tersimpan walau tema berganti.</p>';
}

/**
 * Kolom tautan LINE OA.
 */
function halo_tg2_core_field_line_url() {
	$value = halo_tg2_core_setting('line_url');
	echo '<input type="url" class="regular-text" name="halo_tg2_core_line_url" value="' . esc_url($value) . '" placeholder="https://line.me/R/ti/p/@contoh">';
	echo '<p class="description">Tautan akun LINE Official Account (kosongkan bila belum ada).</p>';
}

/**
 * Kolom email kontak resmi.
 */
function halo_tg2_core_field_contact_email() {
	$value = halo_tg2_core_setting('contact_email');
	echo '<input type="email" class="regular-text" name="halo_tg2_core_contact_email" value="' . esc_attr($value) . '" placeholder="halo@halo-tg2.com">';
	echo '<p class="description">Email yang ditampilkan untuk dihubungi siswa.</p>';
}

add_action('admin_menu', 'halo_tg2_core_add_settings_page');
/**
 * Daftarkan halaman Settings → Halo TG2 Core.
 */
function halo_tg2_core_add_settings_page() {
	add_options_page(
		'Halo TG2 Core',
		'Halo TG2 Core',
		'manage_options',
		'halo-tg2-core',
		'halo_tg2_core_render_settings_page'
	);
}

/**
 * Render halaman pengaturan + panel status fungsi plugin.
 */
function halo_tg2_core_render_settings_page() {
	if (!current_user_can('manage_options')) {
		return;
	}
	$theme     = wp_get_theme();
	$auth_url  = halo_tg2_core_auth_url();
	$dash_url  = halo_tg2_core_dashboard_url();
	?>
	<div class="wrap">
		<h1>Halo TG2 Core</h1>
		<p>Plugin ini menjalankan fungsi di belakang layar: satu pintu masuk, dashboard gabungan, jaring pengaman enrollment, dan email. Penampilan situs diatur di <strong>Appearance → Customize → Halo TG2</strong>, bukan di sini.</p>
		<form action="options.php" method="post">
			<?php
			settings_fields('halo_tg2_core');
			do_settings_sections('halo-tg2-core');
			submit_button('Simpan Pengaturan');
			?>
		</form>

		<hr>
		<h2>Status fungsi</h2>
		<table class="widefat striped" style="max-width:760px">
			<tbody>
				<tr>
					<td style="width:240px"><strong>Tema aktif</strong></td>
					<td><?php echo esc_html($theme->get('Name') . ' ' . $theme->get('Version')); ?></td>
				</tr>
				<tr>
					<td><strong>Tutor LMS</strong></td>
					<td><?php echo function_exists('tutor_utils') ? 'Aktif' : 'Tidak terdeteksi'; ?></td>
				</tr>
				<tr>
					<td><strong>WooCommerce</strong></td>
					<td><?php echo class_exists('WooCommerce') ? 'Aktif' : 'Tidak terdeteksi'; ?></td>
				</tr>
				<tr>
					<td><strong>Halaman Masuk (satu pintu)</strong></td>
					<td>
						<?php if ('' !== $auth_url) : ?>
							Terdeteksi — <a href="<?php echo esc_url($auth_url); ?>"><?php echo esc_html($auth_url); ?></a>
						<?php else : ?>
							<strong>Belum terdeteksi.</strong> Buat halaman baru dan pilih template &ldquo;Halo TG2 — Login &amp; Daftar (Satu Pintu)&rdquo;, lalu terbitkan.
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong>Dashboard kustom</strong></td>
					<td>
						<?php $panel_url = halo_tg2_core_panel_url(); ?>
						<?php if ('' !== $panel_url) : ?>
							Terdeteksi — <a href="<?php echo esc_url($panel_url); ?>"><?php echo esc_html($panel_url); ?></a>
						<?php else : ?>
							<strong>Belum terdeteksi.</strong> Buat halaman baru dan pilih template &ldquo;Halo TG2 — Dashboard Kustom&rdquo;, lalu terbitkan.
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><strong>Dashboard gabungan</strong></td>
					<td><a href="<?php echo esc_url($dash_url); ?>"><?php echo esc_html($dash_url); ?></a></td>
				</tr>
			</tbody>
		</table>
	</div>
	<?php
}
