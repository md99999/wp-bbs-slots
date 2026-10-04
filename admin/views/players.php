<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$players = $wpdb->get_results('SELECT * FROM ' . WPBBS_DB::t('players') . ' ORDER BY bankroll DESC, player_name ASC');
?>
<p>To remove a player who has left the game, or who asks for their data to be deleted, use <strong>Delete</strong>. Their player
    row, monthly history and news are deleted; Hall of Fame records and jackpot wins they held stay on the board as
    "A former player". Their WordPress account is not touched.</p>
<?php if (!$players) : ?>
    <p>No players have joined yet.</p>
<?php else : ?>
<table class="widefat striped">
    <thead><tr><th>Player</th><th>WP user</th><th>Spins (total / wins)</th><th>Best win</th><th>Last played</th><th>Score / spins left today / days played</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($players as $pl) :
        $user = get_userdata($pl->user_id); ?>
        <tr>
            <td><strong><?php echo esc_html($pl->player_name); ?></strong><br>
                <span class="description">#<?php echo (int) $pl->id; ?> &middot; <?php echo esc_html(WPBBS_Game::player_rank($pl)); ?></span></td>
            <td><?php echo $user ? esc_html($user->user_login) : '<em>deleted</em>'; ?></td>
            <td><?php echo esc_html(WPBBS_Game::fmt($pl->total_spins)); ?> / <?php echo esc_html(WPBBS_Game::fmt($pl->total_wins)); ?></td>
            <td><?php echo esc_html(WPBBS_Game::fmt($pl->biggest_win)); ?></td>
            <td><?php echo esc_html($pl->last_played ?: 'never'); ?></td>
            <td>
                <?php echo WPBBS_Admin::form_open('player_update'); ?>
                    <input type="hidden" name="player_id" value="<?php echo (int) $pl->id; ?>">
                    <input type="number" name="bankroll" value="<?php echo (int) $pl->bankroll; ?>" class="small-text" min="0" title="Score (bankroll)" aria-label="Score">
                    <input type="number" name="spins_left" value="<?php echo (int) $pl->spins_left; ?>" class="small-text" min="0" max="1000" title="Spins left today" aria-label="Spins left">
                    <input type="number" name="days_played" value="<?php echo (int) $pl->days_played; ?>" class="small-text" min="0" title="Days played (counts toward rank)" aria-label="Days played">
                    <button class="button button-small">Save</button>
                </form>
            </td>
            <td>
                <?php echo WPBBS_Admin::form_open('player_delete', 'onsubmit="return confirm(\'Delete this player permanently?\')"'); ?>
                    <input type="hidden" name="player_id" value="<?php echo (int) $pl->id; ?>">
                    <label><input type="checkbox" name="confirm_delete" value="1" required> confirm</label>
                    <button class="button button-small button-link-delete">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
