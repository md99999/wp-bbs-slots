<?php
if (!defined('ABSPATH')) exit;

/**
 * The daily job. Runs from WP-Cron at the site's midnight, from the admin Maintenance page,
 * or from maintenance/daily_maintenance.php for a real system cron.
 */
class WPBBS_Maintenance {
    const DAILY_HOOK = 'wpbbs_daily_maintenance';

    public static function schedule() {
        if (!wp_next_scheduled(self::DAILY_HOOK)) {
            // First run at the next local midnight.
            $midnight = new DateTime('tomorrow', wp_timezone());
            wp_schedule_event($midnight->getTimestamp(), 'daily', self::DAILY_HOOK);
        }
    }

    public static function unschedule() {
        wp_clear_scheduled_hook(self::DAILY_HOOK);
    }

    /**
     * Moves the job to the next midnight in the site's current timezone. Called when the timezone
     * under Settings -> General changes, and after each run so daylight saving never leaves it an
     * hour off.
     */
    public static function reschedule() {
        self::unschedule();
        self::schedule();
    }

    /** When the job is next due to start, as a Unix timestamp, or 0. */
    public static function next_run() {
        return (int) wp_next_scheduled(self::DAILY_HOOK);
    }

    /** True when the next run is not at a midnight in the site's timezone. */
    public static function off_midnight() {
        $next = self::next_run();
        return $next && wp_date('H:i', $next) !== '00:00';
    }

    /*
     * Running WP-Cron and a real cron job side by side is safe. The job takes a database lock
     * before doing anything, so only one run happens at a time whatever started it, and it
     * refuses to run twice on the same day. Players are also topped up and given their spins the
     * first time they visit on a new day, so the game works even if cron is late.
     */

    /** Lock name, scoped to this database and table prefix so sites on shared hosting don't collide. */
    private static function lock_name() {
        global $wpdb;
        return 'wpbbs_daily_' . substr(md5((defined('DB_NAME') ? DB_NAME : '') . $wpdb->prefix), 0, 16);
    }

    /** @return bool true if this process may proceed (also true where the database has no advisory locks) */
    private static function lock() {
        global $wpdb;
        $got = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', self::lock_name()));
        return $got === null ? true : (int) $got === 1;
    }

    private static function unlock() {
        global $wpdb;
        $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', self::lock_name()));
    }

    /**
     * Gives every player the day's spins, tops every bankroll under the daily floor up to it and
     * purges old news.
     * Runs at most once per calendar day (site timezone) unless $force is set.
     */
    public static function daily($force = false) {
        $today = WPBBS_Game::today();
        if (!$force && substr((string) get_option('wpbbs_last_daily'), 0, 10) === $today) {
            return 'Daily maintenance skipped: it already ran today at ' . get_option('wpbbs_last_daily') . '.';
        }
        if (!self::lock()) {
            return 'Daily maintenance is already running elsewhere; this run stood down.';
        }
        try {
            if (!$force && substr((string) get_option('wpbbs_last_daily'), 0, 10) === $today) {
                return 'Daily maintenance skipped: another run just completed it at ' . get_option('wpbbs_last_daily') . '.';
            }
            return self::run_daily();
        } finally {
            self::unlock();
        }
    }

    private static function run_daily() {
        global $wpdb;
        WPBBS_Installer::seed_state();
        $floor = WPBBS_Settings::get('daily_floor');
        $granted = WPBBS_Player::grant_spins();
        $topped = WPBBS_Player::topup();
        if ($topped) {
            WPBBS_Log::news('topup', sprintf('A new day at the machine: %d %s topped up to %s credits.',
                $topped, $topped === 1 ? 'player was' : 'players were', WPBBS_Game::fmt($floor)));
        }
        $cutoff = wp_date('Y-m-d H:i:s', time() - WPBBS_Settings::get('news_retention_days') * DAY_IN_SECONDS);
        $purged = (int) $wpdb->query($wpdb->prepare('DELETE FROM ' . WPBBS_DB::t('news') . ' WHERE created_at < %s', $cutoff));
        update_option('wpbbs_last_daily', current_time('mysql'), false);
        return sprintf('Daily maintenance for %s: %d %s given the day\'s spins, %d topped up to %s credits, %d old news items purged.',
            WPBBS_Game::today(), $granted, $granted === 1 ? 'player' : 'players', $topped, WPBBS_Game::fmt($floor), $purged);
    }

    /** The WP-Cron entry point: the daily job, then back on to local midnight if it has drifted. */
    public static function cron() {
        $result = self::daily();
        if (self::off_midnight()) self::reschedule();
        return $result;
    }
}
