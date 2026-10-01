<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * The scheduled job is always removed. The game's tables and options are removed only if
 * "Delete all data" is ticked under WP BBS Slots -> Settings; otherwise they are kept so the
 * game can be reinstalled with its players, scores and Hall of Fame intact.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

wp_clear_scheduled_hook('wpbbs_daily_maintenance');

$settings = get_option('wpbbs_settings', []);
if (!is_array($settings) || empty($settings['delete_data_on_uninstall'])) return;

global $wpdb;
foreach (['players', 'state', 'jackpots', 'records', 'monthly', 'news', 'admin_log'] as $table) {
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wpbbs_' . $table);
}
foreach (['wpbbs_settings', 'wpbbs_db_version', 'wpbbs_page_ids', 'wpbbs_nav_post_id', 'wpbbs_last_daily'] as $option) {
    delete_option($option);
}
