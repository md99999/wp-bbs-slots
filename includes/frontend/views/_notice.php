<?php
/**
 * The no-monetary-value notice at the foot of the home and play pages.
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wpbbs-panel wpbbs-notice" role="note">
    <p>This game has no monetary value, Players play against other players for high score points to get on the
        <a href="<?php echo esc_url(WPBBS_UI::url('hof')); ?>">Hall of Fame</a> page.</p>
</div>
