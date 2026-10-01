<?php
/*
Plugin Name: WP BBS Slots
Plugin URI: https://github.com/md99999/wp-bbs-slots
Author: Bill Mantz
Author URI: https://maddogproductions.online/
Description: WP BBS Slots: a turn-based progressive slot machine game for WordPress, in the spirit of the old BBS door games. A few spins a day, a bankroll that carries over, a progressive jackpot, a Gazette and a Hall of Fame. Credits have no cash value and are a game score only.
Version: 1.0.1
Requires PHP: 8.0
Requires at least: 7.0
Text Domain: wp-bbs-slots
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Copyright (C) 2026 Bill Mantz

WP BBS Slots is free software: you can redistribute it and/or modify it under the terms of the
GNU General Public License as published by the Free Software Foundation, either version 2 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
General Public License for more details. A copy is included in LICENSE.

DISCLAIMER: you install and run this plugin at your own risk, and the author accepts no
responsibility or liability for any loss, damage or compromise arising from its use. Every effort
has been made to write it safely, but new vulnerabilities appear in software of every kind every
day and no website can be guaranteed secure. Credits in this game have no cash value and depict a
game score only; it is not gambling and nothing can be bought, sold or redeemed. See README.md.
Report security problems privately to sysop@maddogproductions.online; see SECURITY.md.
*/
if (!defined('ABSPATH')) exit;

define('WPBBS_GAME_NAME', 'WP BBS Slots');
define('WPBBS_GAZETTE_NAME', 'The WP BBS Slots Gazette');
define('WPBBS_SOURCE_URL', 'https://github.com/md99999/wp-bbs-slots');
define('WPBBS_SITE_NAME', 'maddogproductions.online');
define('WPBBS_VERSION', '1.0.1');
define('WPBBS_DB_VERSION', '1');
define('WPBBS_FILE', __FILE__);
define('WPBBS_PATH', plugin_dir_path(__FILE__));
define('WPBBS_URL', plugin_dir_url(__FILE__));

require_once WPBBS_PATH . 'includes/class-wpbbs-core.php';
require_once WPBBS_PATH . 'includes/class-wpbbs-installer.php';
require_once WPBBS_PATH . 'includes/services/class-wpbbs-player.php';
require_once WPBBS_PATH . 'includes/services/class-wpbbs-records.php';
require_once WPBBS_PATH . 'includes/services/class-wpbbs-slots.php';
require_once WPBBS_PATH . 'includes/services/class-wpbbs-maintenance.php';
require_once WPBBS_PATH . 'includes/frontend/class-wpbbs-ui.php';
require_once WPBBS_PATH . 'includes/frontend/class-wpbbs-actions.php';
require_once WPBBS_PATH . 'includes/frontend/class-wpbbs-shortcodes.php';

register_activation_hook(__FILE__, ['WPBBS_Installer', 'activate']);
register_deactivation_hook(__FILE__, ['WPBBS_Installer', 'deactivate']);

add_action('plugins_loaded', ['WPBBS_Installer', 'maybe_upgrade']);
add_action('init', ['WPBBS_Shortcodes', 'register']);
add_action('template_redirect', ['WPBBS_Actions', 'handle']);
add_action('wp_ajax_wpbbs_spin', ['WPBBS_Actions', 'ajax_spin']);
add_action('wp_ajax_wpbbs_jackpot', ['WPBBS_Actions', 'ajax_jackpot']);
add_action('wp_ajax_nopriv_wpbbs_jackpot', ['WPBBS_Actions', 'ajax_jackpot']);
add_action('wp_enqueue_scripts', ['WPBBS_UI', 'enqueue_assets']);
add_action(WPBBS_Maintenance::DAILY_HOOK, ['WPBBS_Maintenance', 'daily']);

if (is_admin()) {
    require_once WPBBS_PATH . 'admin/class-wpbbs-admin.php';
    WPBBS_Admin::init();
}
