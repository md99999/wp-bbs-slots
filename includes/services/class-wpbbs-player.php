<?php
if (!defined('ABSPATH')) exit;

/**
 * Players: one per WordPress user, with a player name chosen once and kept for good,
 * a bankroll that carries over, and a day's allowance of spins.
 */
class WPBBS_Player {
    const NAME_MIN = 3;
    const NAME_MAX = 20;

    private static $current = false;

    /** The signed-in user's player, or null. Applies the new-day spins and top-up. */
    public static function current() {
        if (self::$current === false) {
            self::$current = is_user_logged_in() ? self::by_user(get_current_user_id()) : null;
            if (self::$current) {
                global $wpdb;
                $wpdb->update(WPBBS_DB::t('players'), ['last_seen' => current_time('mysql')], ['id' => self::$current->id]);
            }
        }
        return self::$current;
    }

    public static function by_user($user_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . WPBBS_DB::t('players') . ' WHERE user_id = %d', $user_id));
        return $row ? self::new_day($row) : null;
    }

    public static function get($id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . WPBBS_DB::t('players') . ' WHERE id = %d', $id));
        return $row ? self::new_day($row) : null;
    }

    /** Reads the player again from the database, without re-applying the new day. */
    public static function fresh($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . WPBBS_DB::t('players') . ' WHERE id = %d', $id));
    }

    /**
     * The first visit of a day hands out the day's spins and the daily top-up.
     *
     * Spins: turns_per_day for each day since the player last received spins, up to
     * max_catchup_days, so a player who misses a few days can catch up (10 a day for up to
     * 7 days is 70 spins). Spins left over from a day the player did visit do not carry over.
     *
     * Top-up: a bankroll under the daily floor (10,000 by default) is raised to it. The daily
     * cron job does the same for everyone at midnight; whichever comes first wins, once a day.
     */
    private static function new_day($p) {
        global $wpdb;
        $today = WPBBS_Game::today();
        if ($p->spins_date !== $today) {
            $days = 1;
            if ($p->spins_date) {
                $from = date_create_immutable($p->spins_date, wp_timezone());
                $to = date_create_immutable($today, wp_timezone());
                if ($from && $to) $days = max(1, (int) $from->diff($to)->days);
            }
            $days = min($days, WPBBS_Settings::get('max_catchup_days'));
            $spins = WPBBS_Settings::get('turns_per_day') * $days;
            $wpdb->query($wpdb->prepare(
                'UPDATE ' . WPBBS_DB::t('players') . ' SET spins_left = %d, spins_date = %s, today_spins = 0, today_won = 0
                 WHERE id = %d AND (spins_date IS NULL OR spins_date <> %s)',
                $spins, $today, $p->id, $today
            ));
        }
        if ($p->topup_date !== $today) {
            self::topup($p->id);
        }
        return ($p->spins_date !== $today || $p->topup_date !== $today) ? self::fresh($p->id) : $p;
    }

    /**
     * Raises bankrolls under the daily floor to the floor, once a day. With $player_id 0 it
     * covers every player (the cron job). Returns how many players were topped up.
     */
    public static function topup($player_id = 0) {
        global $wpdb;
        $today = WPBBS_Game::today();
        $floor = WPBBS_Settings::get('daily_floor');
        $where = $player_id ? $wpdb->prepare(' AND id = %d', $player_id) : '';
        $names = $wpdb->get_results($wpdb->prepare(
            'SELECT id, player_name, bankroll FROM ' . WPBBS_DB::t('players')
            . ' WHERE bankroll < %d AND (topup_date IS NULL OR topup_date <> %s)' . $where,
            $floor, $today
        ));
        $count = 0;
        foreach ($names as $row) {
            $done = $wpdb->query($wpdb->prepare(
                'UPDATE ' . WPBBS_DB::t('players') . ' SET bankroll = %d, topup_date = %s
                 WHERE id = %d AND bankroll < %d AND (topup_date IS NULL OR topup_date <> %s)',
                $floor, $today, $row->id, $floor, $today
            ));
            if ($done) $count++;
        }
        // Everyone else has had today's top-up too: they did not need it.
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . WPBBS_DB::t('players') . ' SET topup_date = %s WHERE (topup_date IS NULL OR topup_date <> %s)' . $where,
            $today, $today
        ));
        return $count;
    }

    /** Checks a proposed player name; returns it cleaned up, or throws. */
    public static function clean_name($name) {
        $name = trim(preg_replace('/\s+/', ' ', sanitize_text_field((string) $name)));
        $len = mb_strlen($name);
        if ($len < self::NAME_MIN || $len > self::NAME_MAX) {
            throw new WPBBS_Exception(sprintf('Your player name must be %d to %d characters.', self::NAME_MIN, self::NAME_MAX));
        }
        if (!preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9 ._\-]*[A-Za-z0-9])?$/', $name)) {
            throw new WPBBS_Exception('Use letters, numbers, spaces, dots, dashes and underscores only, starting and ending with a letter or number.');
        }
        return $name;
    }

    public static function create($user_id, $name) {
        global $wpdb;
        if (!$user_id) throw new WPBBS_Exception('Sign in to play.');
        if (!WPBBS_Settings::get('allow_new_players')) throw new WPBBS_Exception('New players are not being accepted at the moment.');
        if (self::by_user($user_id)) throw new WPBBS_Exception('You already have a player name, and it is yours for good.');
        $name = self::clean_name($name);
        $taken = $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . WPBBS_DB::t('players') . ' WHERE LOWER(player_name) = LOWER(%s)', $name
        ));
        if ($taken) throw new WPBBS_Exception('That player name is taken. Please choose another.');

        $s = WPBBS_Settings::all();
        $now = current_time('mysql');
        $today = WPBBS_Game::today();
        $ok = $wpdb->insert(WPBBS_DB::t('players'), [
            'user_id' => (int) $user_id, 'player_name' => $name,
            'bankroll' => (int) $s['starting_bankroll'], 'peak_bankroll' => (int) $s['starting_bankroll'],
            'spins_left' => (int) $s['turns_per_day'], 'spins_date' => $today, 'topup_date' => $today,
            'last_bet' => WPBBS_Game::min_bet(), 'created_at' => $now, 'last_seen' => $now,
        ]);
        // The unique keys catch a name or account claimed a moment ago by another request.
        if (!$ok) throw new WPBBS_Exception('That player name is taken. Please choose another.');
        $id = (int) $wpdb->insert_id;
        WPBBS_Log::news('new_player', sprintf('%s pulled up a stool at the machine with %s credits.', $name, WPBBS_Game::fmt($s['starting_bankroll'])), $id);
        self::$current = false;
        return $id;
    }

    /**
     * Removes a player at their request: their row and monthly history go, their news is
     * deleted, and any Hall of Fame entries they held stay on the board with the name removed.
     */
    public static function delete($id) {
        global $wpdb;
        $p = self::fresh($id);
        if (!$p) throw new WPBBS_Exception('Player not found.');
        $wpdb->delete(WPBBS_DB::t('monthly'), ['player_id' => $p->id]);
        $wpdb->delete(WPBBS_DB::t('news'), ['player_id' => $p->id]);
        $wpdb->update(WPBBS_DB::t('records'), ['player_id' => 0, 'player_name' => 'A former player'], ['player_id' => $p->id]);
        $wpdb->update(WPBBS_DB::t('jackpots'), ['player_id' => 0, 'player_name' => 'A former player'], ['player_id' => $p->id]);
        $wpdb->delete(WPBBS_DB::t('players'), ['id' => $p->id]);
        return $p;
    }

    /** The highest wager this player can cover, or 0 if they cannot cover the smallest. */
    public static function max_affordable_bet($bankroll) {
        $best = 0;
        foreach (WPBBS_Game::BETS as $bet) {
            if ($bet <= $bankroll) $best = $bet;
        }
        return $best;
    }

    /** The wager to preselect: the last one used, lowered if the bankroll no longer covers it. */
    public static function default_bet($p) {
        $last = (int) $p->last_bet;
        if (!in_array($last, WPBBS_Game::BETS, true)) $last = WPBBS_Game::min_bet();
        if ($last <= (int) $p->bankroll) return $last;
        return self::max_affordable_bet((int) $p->bankroll) ?: WPBBS_Game::min_bet();
    }

    /** Top players by bankroll. */
    public static function top($limit = 20) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT id, player_name, bankroll, biggest_win, best_streak, jackpots_won, total_spins, last_played FROM '
            . WPBBS_DB::t('players') . ' ORDER BY bankroll DESC, id ASC LIMIT %d', max(1, (int) $limit)
        ));
    }

    /** Players who have spun today, most recent first. */
    public static function played_today($limit = 50) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT id, player_name, bankroll, today_spins, today_won, last_played FROM ' . WPBBS_DB::t('players')
            . ' WHERE last_played >= %s ORDER BY last_played DESC LIMIT %d',
            WPBBS_Game::today() . ' 00:00:00', max(1, (int) $limit)
        ));
    }
}
