<?php
if (!defined('ABSPATH')) exit;

/**
 * Players: one per WordPress user, with a player name chosen once and kept for good,
 * a bankroll that carries over, and a day's allowance of spins.
 */
class WPBBS_Player {
    const NAME_MIN = 3;
    const NAME_MAX = 20;

    /** Player names nobody may take (compared without spaces or punctuation, ignoring case). */
    const RESERVED = ['admin', 'administrator', 'sysop', 'moderator', 'webmaster', 'root', 'system', 'staff',
                      'support', 'house', 'thehouse', 'dealer', 'wpbbsslots', 'aformerplayer', 'nobody', 'anonymous'];

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
     * A new day hands out the day's spins and the daily top-up. The daily cron job does both for
     * every player at the site's midnight; a player's first visit of the day does the same for them,
     * in case cron is late. Whichever comes first wins, once a day.
     */
    private static function new_day($p) {
        $today = WPBBS_Game::today();
        if ($p->spins_date !== $today) {
            self::grant_spins($p->id);
        }
        if ($p->topup_date !== $today) {
            self::topup($p->id);
        }
        return ($p->spins_date !== $today || $p->topup_date !== $today) ? self::fresh($p->id) : $p;
    }

    /**
     * Hands out the day's spins, once a day (site timezone). With $player_id 0 it covers every
     * player (the cron job). Returns how many players received spins.
     *
     * A player who came by on the day they last received spins starts afresh with turns_per_day:
     * unused spins do not carry over. A player who missed whole days keeps what they had and gains
     * turns_per_day for each missed day, up to max_catchup_days' worth (10 a day for 7 days is 70).
     * "Came by" means any visit to a game page that day, whether or not they spun.
     */
    public static function grant_spins($player_id = 0) {
        global $wpdb;
        $today = WPBBS_Game::today();
        $turns = WPBBS_Settings::get('turns_per_day');
        $cap = $turns * WPBBS_Settings::get('max_catchup_days');
        $where = $player_id ? $wpdb->prepare(' AND id = %d', $player_id) : '';
        // Days since the last grant; a player who never had one counts as one day.
        $days = 'GREATEST(COALESCE(DATEDIFF(%s, spins_date), 1), 1)';
        return (int) $wpdb->query($wpdb->prepare(
            'UPDATE ' . WPBBS_DB::t('players') . " SET
                spins_left = IF(spins_date IS NOT NULL AND last_seen IS NOT NULL AND DATE(last_seen) >= spins_date,
                                LEAST(%d * $days, %d),
                                LEAST(spins_left + %d * $days, %d)),
                spins_date = %s, today_spins = 0, today_won = 0
             WHERE (spins_date IS NULL OR spins_date <> %s)" . $where,
            $turns, $today, $cap, $turns, $today, $cap, $today, $today
        ));
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
        // Names that could pass for the site's staff or the game itself.
        $bare = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
        $staff = ['admin', 'sysop', 'moderator', 'webmaster'];
        foreach (self::RESERVED as $word) {
            $starts = in_array($word, $staff, true) && strpos($bare, $word) === 0;
            if ($bare === $word || $starts) {
                throw new WPBBS_Exception('That player name is reserved. Please choose another.');
            }
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
