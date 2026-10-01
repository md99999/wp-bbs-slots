<?php
if (!defined('ABSPATH')) exit;

class WPBBS_Installer {

    public static function activate() {
        self::install_schema();
        if (get_option(WPBBS_Settings::OPTION) === false) {
            update_option(WPBBS_Settings::OPTION, WPBBS_Settings::defaults());
        }
        self::seed_state();
        WPBBS_Maintenance::schedule();
    }

    public static function deactivate() {
        WPBBS_Maintenance::unschedule();
    }

    public static function maybe_upgrade() {
        if (get_option('wpbbs_db_version') !== WPBBS_DB_VERSION) {
            self::install_schema();
            self::seed_state();
        }
    }

    /** Runs sql/install.sql through dbDelta, substituting the table prefix. */
    public static function install_schema() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = file_get_contents(WPBBS_PATH . 'sql/install.sql');
        $sql = str_replace(
            ['{prefix}', '{charset_collate}'],
            [$wpdb->prefix, $wpdb->get_charset_collate()],
            $sql
        );
        dbDelta($sql);
        update_option('wpbbs_db_version', WPBBS_DB_VERSION);
    }

    /** Creates the progressive jackpot at its starting value, if it does not exist yet. */
    public static function seed_state() {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . WPBBS_DB::t('state') . ' (state_key, state_value, updated_at) VALUES (%s, %d, %s)',
            'jackpot', WPBBS_Settings::get('jackpot_seed'), current_time('mysql')
        ));
    }

    public static function drop_schema() {
        global $wpdb;
        foreach (WPBBS_DB::TABLES as $table) {
            $wpdb->query('DROP TABLE IF EXISTS ' . WPBBS_DB::t($table));
        }
    }
}
