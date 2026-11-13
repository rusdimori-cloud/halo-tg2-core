<?php
/**
 * Plugin Name:       Halo TG2 Core
 * Plugin URI:        https://halo-tg2.com
 * Description:       Fungsi pendamping tema halo-tg2 (tema = tampilan, plugin = fungsi): satu pintu login, pengalihan akun Woo ke dashboard gabungan, tab Woo di dashboard Tutor, dan jaring pengaman enrollment.
 * Version:           1.5.3
 * Author:            halo TG2
 * Text Domain:       halo-tg2-core
 *
 * @package HaloTG2Core
 */

if (!defined('ABSPATH')) {
	exit;
}

define('HALO_TG2_CORE_VERSION', '1.5.3');

require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/redirects.php';
require_once plugin_dir_path(__FILE__) . 'includes/dashboard-merge.php';
require_once plugin_dir_path(__FILE__) . 'includes/enrollment-guard.php';
require_once plugin_dir_path(__FILE__) . 'includes/emails.php';
require_once plugin_dir_path(__FILE__) . 'includes/contact.php';
require_once plugin_dir_path(__FILE__) . 'includes/pengaturan.php';
require_once plugin_dir_path(__FILE__) . 'includes/profil.php';
require_once plugin_dir_path(__FILE__) . 'includes/notifikasi.php';
require_once plugin_dir_path(__FILE__) . 'includes/payment-methods.php';
require_once plugin_dir_path(__FILE__) . 'includes/updater.php';

register_activation_hook(__FILE__, 'halo_tg2_core_activate');
/**
 * Aktivasi: segarkan aturan rewrite agar sub-halaman dashboard kustom
 * (pesanan-saya, akun-pembayaran) tidak 404.
 */
function halo_tg2_core_activate() {
	flush_rewrite_rules();
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'halo_tg2_core_action_links');
/**
 * Tautan cepat "Pengaturan" pada baris plugin di daftar Plugins.
 *
 * @param string[] $links Tautan bawaan.
 * @return string[]
 */
function halo_tg2_core_action_links($links) {
	$links[] = '<a href="' . esc_url(admin_url('options-general.php?page=halo-tg2-core')) . '">Pengaturan</a>';
	return $links;
}
