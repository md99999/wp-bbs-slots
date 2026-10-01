<?php
if (!defined('ABSPATH')) exit;

/**
 * Handles the game's two forms (choosing a player name, and pulling the handle when JavaScript
 * is off) as POST -> redirect -> GET, plus the AJAX spin and jackpot ticker.
 *
 * The only things a player can send are a player name and a wager. The name goes through
 * sanitize_text_field() and a strict character whitelist; the wager must be one of the fixed
 * WPBBS_Game::BETS values. Every request carries a nonce and requires a signed-in user.
 */
class WPBBS_Actions {

    public static function handle() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || empty($_POST['wpbbs_action']) || !is_user_logged_in()) return;

        $return = self::field('wpbbs_return');
        $redirect = $return ? wp_validate_redirect($return, WPBBS_UI::url('play')) : WPBBS_UI::url('play');
        $nonce = self::field('wpbbs_nonce');
        if (!$nonce || !wp_verify_nonce(sanitize_text_field($nonce), 'wpbbs_action')) {
            WPBBS_UI::flash('error', 'Your session expired. Please try again.');
            wp_safe_redirect($redirect);
            exit;
        }

        $action = sanitize_key(self::field('wpbbs_action'));
        try {
            switch ($action) {
                case 'register':
                    WPBBS_Player::create(get_current_user_id(), self::field('player_name'));
                    WPBBS_UI::flash('success', 'Welcome to the machine! Your player name is yours for good. Pick a wager and pull the handle.');
                    $redirect = WPBBS_UI::url('play');
                    break;
                case 'spin':
                    $p = WPBBS_Player::current();
                    if (!$p) throw new WPBBS_Exception('Choose a player name first.');
                    $r = WPBBS_Slots::spin($p, self::bet());
                    $faces = implode(' | ', array_map(function ($k) { return WPBBS_Game::SYMBOLS[$k]['label']; }, $r['reels']));
                    WPBBS_UI::flash($r['win'] > 0 ? 'success' : 'info', '[ ' . $faces . " ]\n" . $r['message']);
                    foreach ($r['news'] as $line) WPBBS_UI::flash('success', $line);
                    if ($r['bailout']) WPBBS_UI::flash('warning', $r['bailout']);
                    break;
                default:
                    throw new WPBBS_Exception('Unknown command.');
            }
        } catch (WPBBS_Exception $e) {
            WPBBS_UI::flash($e->type, $e->getMessage());
        }
        wp_safe_redirect($redirect);
        exit;
    }

    /** One pull of the handle, for the animated reels. */
    public static function ajax_spin() {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Sign in to play.'], 403);
        if (!check_ajax_referer('wpbbs_spin', 'nonce', false)) {
            wp_send_json_error(['message' => 'Your session expired. Reload the page and try again.'], 403);
        }
        try {
            $p = WPBBS_Player::current();
            if (!$p) throw new WPBBS_Exception('Choose a player name first.');
            wp_send_json_success(WPBBS_Slots::spin($p, self::bet()));
        } catch (WPBBS_Exception $e) {
            $p = WPBBS_Player::current() ? WPBBS_Player::fresh(WPBBS_Player::current()->id) : null;
            wp_send_json_error([
                'message'    => $e->getMessage(),
                'bankroll'   => $p ? (int) $p->bankroll : 0,
                'spins_left' => $p ? (int) $p->spins_left : 0,
                'next_bet'   => $p ? WPBBS_Player::default_bet($p) : WPBBS_Game::min_bet(),
            ]);
        }
    }

    /** The current progressive, for the ticker on the play page. Public and read-only. */
    public static function ajax_jackpot() {
        wp_send_json_success(['jackpot' => WPBBS_Game::jackpot()]);
    }

    /**
     * The posted wager. Only plain digits are accepted, so "5000 OR 1=1" or "1e3" is refused rather
     * than cast to a number; WPBBS_Slots::spin() then checks it against the fixed list of wagers.
     */
    private static function bet() {
        $bet = trim(self::field('bet'));
        return (ctype_digit($bet) && strlen($bet) <= 6) ? (int) $bet : 0;
    }

    /**
     * A posted field as a string. Anything that is not a scalar (an array posted where a string
     * is expected, say) is discarded, so the sanitisers always get the type they expect.
     */
    private static function field($name, $default = '') {
        if (!isset($_POST[$name]) || !is_scalar($_POST[$name])) return $default;
        return wp_unslash((string) $_POST[$name]);
    }
}
