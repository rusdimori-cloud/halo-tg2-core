<?php
/**
 * Pemeriksa update mandiri halo-tg2-core.
 * Memakai pustaka Plugin Update Checker (YahnisElsts, MIT) mode GitHub:
 * WordPress memeriksa repo github.com/rusdimori-cloud/halo-tg2-core
 * (branch main) dan menawarkan update seperti plugin biasa
 * (bisa auto-update). Versi dibaca dari header plugin di repo.
 *
 * @package HaloTG2Core
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('init', 'halo_tg2_core_init_updater');
/**
 * Nyalakan pemeriksa update (dijaga: tanpa pustaka, plugin tetap aman).
 */
function halo_tg2_core_init_updater() {
	$puc = plugin_dir_path(__DIR__) . 'vendor/plugin-update-checker/plugin-update-checker.php';
	if (!file_exists($puc)) {
		return;
	}
	require_once $puc;
	if (!class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
		return;
	}
	$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/rusdimori-cloud/halo-tg2-core/',
		plugin_dir_path(__DIR__) . 'halo-tg2-core.php',
		'halo-tg2-core'
	);
	$checker->setBranch('main');
}
