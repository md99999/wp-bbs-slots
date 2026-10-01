<?php
if (!defined('ABSPATH')) exit;

/**
 * The machine itself: spinning the reels, paying out, the progressive jackpot and the
 * once-a-day bailout. Everything is decided here on the server; the browser only animates
 * the result it is sent.
 */
class WPBBS_Slots {

    /** One reel stop, weighted by WPBBS_Game::SYMBOLS. */
    public static function roll() {
        $total = 0;
        foreach (WPBBS_Game::SYMBOLS as $s) $total += $s['weight'];
        $pick = random_int(1, $total);
        foreach (WPBBS_Game::SYMBOLS as $key => $s) {
            $pick -= $s['weight'];
            if ($pick <= 0) return $key;
        }
        return 'lemon';
    }

    /**
     * Scores three reels against the pay table.
     * @return array kind (jackpot, triple, pair, free or none), multiple and a description
     */
    public static function evaluate(array $reels) {
        list($a, $b, $c) = $reels;
        $sym = WPBBS_Game::SYMBOLS;
        if ($a === $b && $b === $c) {
            if ($a === 'jackpot') return ['kind' => 'jackpot', 'multiple' => 0, 'label' => 'Jackpot Jackpot Jackpot'];
            return ['kind' => 'triple', 'multiple' => (int) $sym[$a]['pays'], 'label' => 'Three ' . self::plural($a)];
        }
        if ($a === $b) {
            return ['kind' => 'pair', 'multiple' => WPBBS_Game::PAIR_PAYS, 'label' => 'A pair of ' . self::plural($a)];
        }
        if (in_array('cherry', $reels, true)) {
            return ['kind' => 'free', 'multiple' => 0, 'label' => 'A Cherry'];
        }
        return ['kind' => 'none', 'multiple' => 0, 'label' => ''];
    }

    private static function plural($key) {
        if ($key === 'cherry') return 'Cherries';
        if ($key === 'seven') return 'Sevens';
        return WPBBS_Game::SYMBOLS[$key]['label'] . 's';
    }

    /**
     * Spins the reels for $p at $bet and settles the result.
     * @return array the outcome, as sent to the browser
     */
    public static function spin($p, $bet) {
        global $wpdb;
        $t = WPBBS_DB::t('players');
        $bet = (int) $bet;
        if (!in_array($bet, WPBBS_Game::BETS, true)) throw new WPBBS_Exception('Choose a wager from the list.');

        $p = WPBBS_Player::fresh($p->id);
        if (!$p) throw new WPBBS_Exception('Player not found.');
        if ((int) $p->spins_left <= 0) throw new WPBBS_Exception('You have no spins left today. Your spins come back tomorrow.', 'warning');
        if ((int) $p->bankroll < WPBBS_Game::min_bet()) throw new WPBBS_Exception('You are out of credits until tomorrow\'s top-up.', 'warning');
        if ((int) $p->bankroll < $bet) throw new WPBBS_Exception('Your bankroll does not cover that wager. Choose a smaller one.', 'warning');

        // Take the wager and the spin in one guarded statement, so a double click cannot spend either twice.
        $took = $wpdb->query($wpdb->prepare(
            "UPDATE $t SET bankroll = bankroll - %d, spins_left = spins_left - 1 WHERE id = %d AND bankroll >= %d AND spins_left > 0",
            $bet, $p->id, $bet
        ));
        if (!$took) throw new WPBBS_Exception('That spin did not go through. Please try again.');

        // Every spin feeds the progressive.
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . WPBBS_DB::t('state') . ' SET state_value = state_value + %d, updated_at = %s WHERE state_key = %s',
            WPBBS_Settings::get('jackpot_increment'), current_time('mysql'), 'jackpot'
        ));

        $reels = [self::roll(), self::roll(), self::roll()];
        $result = self::evaluate($reels);
        $win = $result['kind'] === 'jackpot' ? self::claim_jackpot() : $bet * $result['multiple'];
        $won = $win > 0;
        $bonus = ($won || $result['kind'] === 'free') ? 1 : 0;
        $now = current_time('mysql');

        // MySQL applies these left to right, so best_streak and peak_bankroll see the new values.
        $wpdb->query($wpdb->prepare(
            "UPDATE $t SET bankroll = bankroll + %d, spins_left = spins_left + %d, last_bet = %d,
                today_spins = today_spins + 1, today_won = today_won + %d,
                total_spins = total_spins + 1, total_wins = total_wins + %d, total_won = total_won + %d,
                biggest_win = GREATEST(biggest_win, %d),
                current_streak = IF(%d = 1, current_streak + 1, 0), best_streak = GREATEST(best_streak, current_streak),
                jackpots_won = jackpots_won + %d, peak_bankroll = GREATEST(peak_bankroll, bankroll), last_played = %s
             WHERE id = %d",
            $win, $bonus, $bet, $win, $won ? 1 : 0, $win, $win, $won ? 1 : 0,
            $result['kind'] === 'jackpot' ? 1 : 0, $now, $p->id
        ));

        $p = WPBBS_Player::fresh($p->id);
        $news = self::record_spin($p, $reels, $result, $bet, $win);

        if ($won) {
            $message = sprintf('%s! You won %s credits.', $result['label'], WPBBS_Game::fmt($win));
            if ($result['kind'] === 'jackpot') {
                $message = sprintf('JACKPOT! You won the progressive jackpot of %s credits!', WPBBS_Game::fmt($win));
            }
            $message .= ' Bonus spin awarded.';
        } elseif ($result['kind'] === 'free') {
            $message = 'A Cherry: free spin! You get that spin back.';
        } else {
            $message = 'No win this time. Play again.';
        }

        $bailout = self::maybe_bailout($p);
        if ($bailout) $p = WPBBS_Player::fresh($p->id);

        return [
            'reels'      => $reels,
            'kind'       => $result['kind'],
            'win'        => $win,
            'bet'        => $bet,
            'bonus'      => $bonus,
            'message'    => $message,
            'bailout'    => $bailout,
            'news'       => $news,
            'bankroll'   => (int) $p->bankroll,
            'spins_left' => (int) $p->spins_left,
            'jackpot'    => WPBBS_Game::jackpot(),
            'next_bet'   => WPBBS_Player::default_bet($p),
            'rank'       => WPBBS_Game::rank_title((int) $p->bankroll),
        ];
    }

    /**
     * Pays out the progressive and resets it to its starting value. Compare-and-swap, so two
     * winners at the same moment cannot both collect the same pot.
     */
    private static function claim_jackpot() {
        global $wpdb;
        $seed = WPBBS_Settings::get('jackpot_seed');
        for ($i = 0; $i < 10; $i++) {
            $amount = WPBBS_Game::jackpot();
            $done = $wpdb->query($wpdb->prepare(
                'UPDATE ' . WPBBS_DB::t('state') . ' SET state_value = %d, updated_at = %s WHERE state_key = %s AND state_value = %d',
                $seed, current_time('mysql'), 'jackpot', $amount
            ));
            if ($done) return $amount;
        }
        return $seed;
    }

    /**
     * The once-a-day bailout: a player whose bankroll falls under the threshold (1,000) with spins
     * still to play is given the bailout amount (10,000) to finish the day. Once only, until tomorrow.
     * @return string a message for the player, or '' if no bailout was given
     */
    public static function maybe_bailout($p) {
        global $wpdb;
        $threshold = WPBBS_Settings::get('bailout_threshold');
        $amount = WPBBS_Settings::get('bailout_amount');
        $today = WPBBS_Game::today();
        if ($amount <= 0 || (int) $p->spins_left <= 0 || (int) $p->bankroll >= $threshold || $p->bailout_date === $today) return '';
        $done = $wpdb->query($wpdb->prepare(
            'UPDATE ' . WPBBS_DB::t('players') . ' SET bankroll = bankroll + %d, bailout_date = %s
             WHERE id = %d AND bankroll < %d AND spins_left > 0 AND (bailout_date IS NULL OR bailout_date <> %s)',
            $amount, $today, $p->id, $threshold, $today
        ));
        if (!$done) return '';
        WPBBS_Log::news('bailout', sprintf('%s ran low and took the daily bailout of %s credits.', $p->player_name, WPBBS_Game::fmt($amount)), $p->id);
        return sprintf('Your bankroll ran low, so the house has given you today\'s one-time bailout of %s credits to finish your spins. There is only one bailout a day.', WPBBS_Game::fmt($amount));
    }

    /** Hall of Fame and Gazette bookkeeping after a spin. Returns lines worth showing the player. */
    private static function record_spin($p, array $reels, array $result, $bet, $win) {
        global $wpdb;
        $lines = [];
        $big = WPBBS_Settings::get('big_win_news');
        $name = $p->player_name;

        if ($result['kind'] === 'jackpot') {
            $wpdb->insert(WPBBS_DB::t('jackpots'), [
                'player_id' => $p->id, 'player_name' => $name, 'amount' => $win, 'bet' => $bet, 'won_at' => current_time('mysql'),
            ]);
            WPBBS_Log::news('jackpot', sprintf('JACKPOT! %s lined up three Jackpots and won the progressive: %s credits! The progressive resets to %s.',
                $name, WPBBS_Game::fmt($win), WPBBS_Game::fmt(WPBBS_Settings::get('jackpot_seed'))), $p->id);
        } elseif ($big > 0 && $win >= $big) {
            WPBBS_Log::news('big_win', sprintf('%s hit %s on a %s wager for %s credits.', $name, $result['label'], WPBBS_Game::fmt($bet), WPBBS_Game::fmt($win)), $p->id);
        }

        if ($win > 0) {
            $detail = $result['label'] . ' on a ' . WPBBS_Game::fmt($bet) . ' wager';
            if (WPBBS_Records::claim(WPBBS_Records::BIGGEST_SPIN, $p, $win, $detail)) {
                $lines[] = 'New Hall of Fame record: the highest single spin win!';
                if ($big > 0 && $win >= $big) WPBBS_Log::news('record', sprintf('%s set a new Hall of Fame record for the highest single spin win: %s credits.', $name, WPBBS_Game::fmt($win)), $p->id);
            }
            $streak = (int) $p->current_streak;
            if ($streak >= 2 && WPBBS_Records::claim(WPBBS_Records::LONGEST_STREAK, $p, $streak, $streak . ' wins in a row')) {
                $lines[] = sprintf('New Hall of Fame record: %d wins in a row!', $streak);
                if ($streak >= 3) WPBBS_Log::news('record', sprintf('%s won %d spins in a row, a new Hall of Fame record.', $name, $streak), $p->id);
            }
        }

        WPBBS_Records::monthly($p->id, (int) $p->bankroll);
        foreach (WPBBS_Records::rank_firsts($p, (int) $p->bankroll) as $title) {
            $lines[] = sprintf('You are the first player to reach the rank of %s!', $title);
            WPBBS_Log::news('rank', sprintf('%s is the first player to reach the rank of %s.', $name, $title), $p->id);
        }
        return $lines;
    }
}
