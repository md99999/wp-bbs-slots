<?php
/**
 * The machine. The form works on its own (a POST that reloads the page with the result); with
 * JavaScript, assets/js/wp-bbs-slots.js takes it over, spins the reels and asks the server for
 * the result without leaving the page.
 *
 * @var object $p the player
 */
if (!defined('ABSPATH')) exit;

// A player who ran low before the bailout rules applied (or whose bankroll an admin changed) gets it now.
if (WPBBS_Slots::maybe_bailout($p)) {
    $p = WPBBS_Player::fresh($p->id);
    WPBBS_UI::flash('warning', 'Your bankroll was low, so the house has given you today\'s one-time bailout.');
    echo WPBBS_UI::render_flashes();
}

$bankroll = (int) $p->bankroll;
$spins = (int) $p->spins_left;
$bet = WPBBS_Player::default_bet($p);
$can_spin = $spins > 0 && $bankroll >= WPBBS_Game::min_bet();
$start = ['seven', 'jackpot', 'seven'];
if (!$can_spin) {
    $status = $spins <= 0
        ? 'No spins left today. Your spins come back tomorrow (site time).'
        : 'You are out of credits until tomorrow\'s top-up.';
} else {
    $status = 'Choose your wager and pull the handle.';
}
?>
<div class="wpbbs-panel wpbbs-machine" data-wpbbs-machine>
    <p class="wpbbs-jackpot-label">Progressive Jackpot</p>
    <h1 class="wpbbs-jackpot" data-wpbbs="jackpot"><?php echo esc_html(WPBBS_Game::fmt(WPBBS_Game::jackpot())); ?></h1>

    <p class="wpbbs-score">
        <span class="wpbbs-label">Your score</span>
        <strong data-wpbbs="bankroll"><?php echo esc_html(WPBBS_Game::fmt($bankroll)); ?></strong>
        <span class="wpbbs-dim">credits</span>
        <span class="wpbbs-sep">&middot;</span>
        <span class="wpbbs-label">Spins left</span> <strong data-wpbbs="spins"><?php echo (int) $spins; ?></strong>
    </p>

    <div class="wpbbs-reels" aria-label="Reels">
        <?php foreach ($start as $i => $key) : ?>
            <div class="wpbbs-reel" data-wpbbs-reel="<?php echo (int) $i; ?>"><?php echo WPBBS_UI::symbol($key); ?></div>
        <?php endforeach; ?>
    </div>

    <?php echo WPBBS_UI::form_open('spin', 'wpbbs-controls', 'data-wpbbs="form"'); ?>
        <label for="wpbbs-bet">Wager</label>
        <select id="wpbbs-bet" name="bet" data-wpbbs="bet">
            <?php foreach (WPBBS_Game::BETS as $amount) : ?>
                <option value="<?php echo (int) $amount; ?>" <?php selected($amount, $bet); ?> <?php disabled($amount > $bankroll); ?>>
                    <?php echo esc_html(WPBBS_Game::fmt($amount)); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="wpbbs-btn wpbbs-pull" data-wpbbs="pull" <?php disabled(!$can_spin); ?>>Pull</button>
    </form>
    <p class="wpbbs-small wpbbs-dim wpbbs-keys">Press <kbd>Enter</kbd> or the space bar to pull. Your wager stays set until you change it.</p>

    <div class="wpbbs-result" data-wpbbs="status" role="status" aria-live="polite"><?php echo esc_html($status); ?></div>
    <div class="wpbbs-extra" data-wpbbs="extra" aria-live="polite"></div>
</div>

<details class="wpbbs-panel wpbbs-help">
    <summary>Pay table</summary>
    <?php include WPBBS_PATH . 'includes/frontend/views/_paytable.php'; ?>
</details>
