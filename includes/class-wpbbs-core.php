<?php
/**
 * Core helpers shared by every part of the game: table names, settings, game-level
 * exceptions, logging, the reel symbols, wagers and ranks.
 */
if (!defined('ABSPATH')) exit;

/**
 * Thrown by services when a player action cannot be completed.
 * The message is shown to the player; $type controls the notice colour.
 */
class WPBBS_Exception extends Exception {
    public $type;
    public function __construct($message, $type = 'error') {
        parent::__construct($message);
        $this->type = $type;
    }
}

class WPBBS_DB {
    const TABLES = ['players', 'state', 'jackpots', 'records', 'monthly', 'news', 'admin_log'];

    public static function t($name) {
        global $wpdb;
        return $wpdb->prefix . 'wpbbs_' . $name;
    }
}

class WPBBS_Settings {
    const OPTION = 'wpbbs_settings';

    public static function defaults() {
        return [
            'turns_per_day'            => 10,
            'max_catchup_days'         => 7,
            'starting_bankroll'        => 10000,
            'daily_floor'              => 10000,
            'bailout_threshold'        => 1000,
            'bailout_amount'           => 10000,
            'jackpot_seed'             => 100000000,
            'jackpot_increment'        => 5000,
            'big_win_news'             => 50000,
            'news_retention_days'      => 30,
            'allow_new_players'        => 1,
            'delete_data_on_uninstall' => 0,
        ];
    }

    /** Lowest and highest value each setting may take. */
    public static function limits() {
        return [
            'turns_per_day'            => [1, 50],
            'max_catchup_days'         => [1, 7],
            'starting_bankroll'        => [100, 1000000000],
            'daily_floor'              => [0, 1000000000],
            'bailout_threshold'        => [0, 1000000000],
            'bailout_amount'           => [0, 1000000000],
            'jackpot_seed'             => [1000, 100000000000],
            'jackpot_increment'        => [0, 10000000],
            'big_win_news'             => [0, 100000000000],
            'news_retention_days'      => [1, 365],
            'allow_new_players'        => [0, 1],
            'delete_data_on_uninstall' => [0, 1],
        ];
    }

    public static function all() {
        $saved = get_option(self::OPTION, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::defaults());
    }

    public static function get($key) {
        $all = self::all();
        return isset($all[$key]) ? (int) $all[$key] : 0;
    }

    /** Saves the settings in $values, each cast to an integer and clamped to its limits. */
    public static function update(array $values) {
        $current = self::all();
        $limits = self::limits();
        foreach (self::defaults() as $key => $default) {
            if (!array_key_exists($key, $values)) continue;
            list($min, $max) = $limits[$key];
            $current[$key] = max($min, min($max, (int) $values[$key]));
        }
        update_option(self::OPTION, $current);
        return $current;
    }
}

class WPBBS_Log {
    /** A public Gazette item. */
    public static function news($type, $message, $player_id = 0) {
        global $wpdb;
        $wpdb->insert(WPBBS_DB::t('news'), [
            'event_type' => substr(sanitize_key($type), 0, 20),
            'player_id'  => (int) $player_id,
            'message'    => mb_substr($message, 0, 255),
            'created_at' => current_time('mysql'),
        ]);
    }

    /** Administrative audit log. */
    public static function admin($type, $message) {
        global $wpdb;
        $wpdb->insert(WPBBS_DB::t('admin_log'), [
            'event_type' => substr(sanitize_key($type), 0, 20),
            'message'    => mb_substr($message, 0, 255),
            'user_id'    => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);
    }
}

class WPBBS_Game {
    /**
     * The reel symbols, most common first.
     *
     * 'weight' is how many of the reel's stops carry the symbol; every reel uses the same strip.
     * 'pays' is the multiple of the wager paid for three in a row (0 for the Jackpot, which pays
     * the progressive). Any pair on the first two reels scores 2x, and a single Cherry anywhere earns
     * a Bonus Spin. With these weights the machine returns roughly 102% of wagers before Bonus Spins
     * and the progressive, and three Jackpots come up about once in 29,000 spins.
     */
    const SYMBOLS = [
        'cherry'  => ['label' => 'Cherry',  'icon' => "\u{1F352}", 'weight' => 20, 'pays' => 25],
        'lemon'   => ['label' => 'Lemon',   'icon' => "\u{1F34B}", 'weight' => 40, 'pays' => 2],
        'bell'    => ['label' => 'Bell',    'icon' => "\u{1F514}", 'weight' => 12, 'pays' => 50],
        'bar'     => ['label' => 'BAR',     'icon' => 'BAR',       'weight' => 8,  'pays' => 100],
        'diamond' => ['label' => 'Diamond', 'icon' => "\u{1F48E}", 'weight' => 5,  'pays' => 250],
        'seven'   => ['label' => 'Lucky Seven', 'icon' => '7',     'weight' => 4,  'pays' => 500],
        'jackpot' => ['label' => 'Jackpot', 'icon' => 'JACKPOT',   'weight' => 3,  'pays' => 0],
    ];

    /** Pair multiple: two matching symbols on reels 1 and 2. */
    const PAIR_PAYS = 2;

    /** The wagers a player may choose. */
    const BETS = [100, 200, 300, 400, 500, 1000, 2500, 5000];

    /**
     * Ranks, lowest first; the index is the rank's level. A player reaches a rank by either road:
     * a best-ever score (their highest bankroll) of at least 'score', or at least 'days' days on
     * which they spun. Ranks are kept for good: a falling score never takes one away, and nor does
     * a new season. The Hall of Fame records who reached each one first.
     */
    const RANKS = [
        ['title' => 'Newcomer',    'score' => 0,          'days' => 0],
        ['title' => 'Regular',     'score' => 25000,      'days' => 3],
        ['title' => 'High Roller', 'score' => 100000,     'days' => 10],
        ['title' => 'Card Shark',  'score' => 500000,     'days' => 25],
        ['title' => 'Millionaire', 'score' => 1000000,    'days' => 50],
        ['title' => 'Tycoon',      'score' => 10000000,   'days' => 100],
        ['title' => 'Mogul',       'score' => 100000000,  'days' => 200],
        ['title' => 'BBS Legend',  'score' => 1000000000, 'days' => 365],
    ];

    /** The title for a rank level. */
    public static function rank_title($level) {
        $level = max(0, min(count(self::RANKS) - 1, (int) $level));
        return self::RANKS[$level]['title'];
    }

    /** The highest rank level that a best-ever score or a count of days played earns. */
    public static function rank_for($best_score, $days_played) {
        $level = 0;
        foreach (self::RANKS as $i => $r) {
            if ($best_score >= $r['score'] || $days_played >= $r['days']) $level = $i;
        }
        return $level;
    }

    /** A player's rank title, from the level they have reached. */
    public static function player_rank($p) {
        return self::rank_title($p->rank_level ?? 0);
    }

    public static function min_bet() {
        return min(self::BETS);
    }

    public static function today() {
        return current_time('Y-m-d');
    }

    public static function month() {
        return current_time('Y-m');
    }

    public static function fmt($n) {
        return number_format_i18n((float) $n);
    }

    /** The current progressive jackpot. */
    public static function jackpot() {
        global $wpdb;
        $amount = $wpdb->get_var($wpdb->prepare(
            'SELECT state_value FROM ' . WPBBS_DB::t('state') . ' WHERE state_key = %s', 'jackpot'
        ));
        if ($amount === null) {
            WPBBS_Installer::seed_state();
            return WPBBS_Settings::get('jackpot_seed');
        }
        return (int) $amount;
    }
}
