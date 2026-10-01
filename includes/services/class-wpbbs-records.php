<?php
if (!defined('ABSPATH')) exit;

/**
 * The Hall of Fame: all-time records, the first player to reach each rank, jackpot winners and
 * each month's highest bankroll.
 */
class WPBBS_Records {
    const BIGGEST_SPIN = 'biggest_spin';
    const LONGEST_STREAK = 'longest_streak';

    /**
     * Claims record $key for the player if $value beats the standing record (or there is none).
     * @return bool true if this is a new record
     */
    public static function claim($key, $player, $value, $detail = '') {
        global $wpdb;
        $table = WPBBS_DB::t('records');
        $now = current_time('mysql');
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO $table (record_key, player_id, player_name, record_value, detail, achieved_at) VALUES (%s, %d, %s, %d, %s, %s)",
            $key, $player->id, $player->player_name, $value, $detail, $now
        ));
        if ($inserted) return true;
        return (bool) $wpdb->query($wpdb->prepare(
            "UPDATE $table SET player_id = %d, player_name = %s, record_value = %d, detail = %s, achieved_at = %s
             WHERE record_key = %s AND record_value < %d",
            $player->id, $player->player_name, $value, $detail, $now, $key, $value
        ));
    }

    /** Records the first player to reach each rank at or below $bankroll; returns the titles newly claimed. */
    public static function rank_firsts($player, $bankroll) {
        global $wpdb;
        $claimed = [];
        foreach (WPBBS_Game::RANKS as $threshold => $title) {
            if ($threshold <= 0 || $bankroll < $threshold) continue;
            $done = $wpdb->query($wpdb->prepare(
                'INSERT IGNORE INTO ' . WPBBS_DB::t('records') . ' (record_key, player_id, player_name, record_value, detail, achieved_at) VALUES (%s, %d, %s, %d, %s, %s)',
                'rank_' . $threshold, $player->id, $player->player_name, $bankroll, $title, current_time('mysql')
            ));
            if ($done) $claimed[] = $title;
        }
        return $claimed;
    }

    /** Keeps the player's highest bankroll for the current month. */
    public static function monthly($player_id, $bankroll) {
        global $wpdb;
        $table = WPBBS_DB::t('monthly');
        // reached_at is assigned before peak_bankroll so it compares against the old peak.
        $wpdb->query($wpdb->prepare(
            "INSERT INTO $table (player_id, month, peak_bankroll, reached_at) VALUES (%d, %s, %d, %s)
             ON DUPLICATE KEY UPDATE
               reached_at = IF(VALUES(peak_bankroll) > peak_bankroll, VALUES(reached_at), reached_at),
               peak_bankroll = GREATEST(peak_bankroll, VALUES(peak_bankroll))",
            $player_id, WPBBS_Game::month(), $bankroll, current_time('mysql')
        ));
    }

    public static function get($key) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . WPBBS_DB::t('records') . ' WHERE record_key = %s', $key));
    }

    /** First to reach each rank, lowest rank first. */
    public static function rank_list() {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . WPBBS_DB::t('records') . ' WHERE record_key LIKE %s', $wpdb->esc_like('rank_') . '%'
        ));
        $by_key = [];
        foreach ($rows as $row) $by_key[$row->record_key] = $row;
        $out = [];
        foreach (WPBBS_Game::RANKS as $threshold => $title) {
            if ($threshold <= 0) continue;
            $out[] = ['title' => $title, 'threshold' => $threshold, 'record' => $by_key['rank_' . $threshold] ?? null];
        }
        return $out;
    }

    public static function biggest_jackpot() {
        global $wpdb;
        return $wpdb->get_row('SELECT * FROM ' . WPBBS_DB::t('jackpots') . ' ORDER BY amount DESC, id ASC LIMIT 1');
    }

    public static function jackpots($limit = 10) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . WPBBS_DB::t('jackpots') . ' ORDER BY won_at DESC, id DESC LIMIT %d', max(1, (int) $limit)
        ));
    }

    /** The highest monthly bankroll of all time. */
    public static function best_month() {
        global $wpdb;
        return $wpdb->get_row(
            'SELECT m.month, m.peak_bankroll, m.reached_at, p.player_name FROM ' . WPBBS_DB::t('monthly') . ' m
             JOIN ' . WPBBS_DB::t('players') . ' p ON p.id = m.player_id
             ORDER BY m.peak_bankroll DESC, m.reached_at ASC LIMIT 1'
        );
    }

    /** Each recent month's highest bankroll, newest month first. */
    public static function monthly_leaders($months = 12) {
        global $wpdb;
        $m = WPBBS_DB::t('monthly');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.month, m.peak_bankroll, p.player_name FROM $m m
             JOIN " . WPBBS_DB::t('players') . " p ON p.id = m.player_id
             JOIN (SELECT month, MAX(peak_bankroll) AS best FROM $m GROUP BY month) b
               ON b.month = m.month AND b.best = m.peak_bankroll
             ORDER BY m.month DESC, m.reached_at ASC LIMIT %d",
            max(1, (int) $months) * 3
        ));
        // A tie goes to whoever got there first: keep one row per month.
        $out = [];
        foreach ($rows as $row) {
            if (!isset($out[$row->month])) $out[$row->month] = $row;
        }
        return array_slice(array_values($out), 0, max(1, (int) $months));
    }
}
