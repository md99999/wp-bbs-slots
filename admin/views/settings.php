<?php
if (!defined('ABSPATH')) exit;
$s = WPBBS_Settings::all();
$limits = WPBBS_Settings::limits();
$fields = [
    'Daily play' => [
        'turns_per_day'    => ['Spins per day', 'Between 1 and 50. Unused spins do not carry over.'],
        'max_catchup_days' => ['Catch-up days', 'A player who misses whole days receives each missed day\'s spins, up to this many days (1 to 7).'],
    ],
    'Bankroll' => [
        'starting_bankroll' => ['Starting bankroll', 'Credits a new player begins with.'],
        'daily_floor'       => ['Daily top-up to', 'At the start of each day, anyone below this is raised to it.'],
        'bailout_threshold' => ['Bailout when below', 'A player whose bankroll falls below this with spins left gets the bailout, once a day.'],
        'bailout_amount'    => ['Bailout amount', 'Credits added by the daily bailout. 0 turns the bailout off.'],
    ],
    'Progressive jackpot' => [
        'jackpot_seed'      => ['Starting jackpot', 'The progressive starts here and resets here after it is won.'],
        'jackpot_increment' => ['Added per spin', 'How much every spin by any player adds to the progressive.'],
    ],
    'Gazette' => [
        'big_win_news'        => ['Report wins of at least', 'Single spins worth this much or more make the news. 0 reports none.'],
        'news_retention_days' => ['Keep news for (days)', ''],
    ],
];
?>
<?php echo WPBBS_Admin::form_open('save_settings'); ?>
    <?php foreach ($fields as $section => $rows) : ?>
        <h2><?php echo esc_html($section); ?></h2>
        <table class="form-table" role="presentation">
            <?php foreach ($rows as $key => $row) : ?>
                <tr>
                    <th><label for="wpbbs-<?php echo esc_attr($key); ?>"><?php echo esc_html($row[0]); ?></label></th>
                    <td>
                        <input id="wpbbs-<?php echo esc_attr($key); ?>" name="wpbbs[<?php echo esc_attr($key); ?>]" type="number"
                               min="<?php echo (int) $limits[$key][0]; ?>" max="<?php echo (int) $limits[$key][1]; ?>"
                               value="<?php echo esc_attr($s[$key]); ?>" class="small-text">
                        <?php if ($row[1]) : ?><p class="description"><?php echo esc_html($row[1]); ?></p><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endforeach; ?>

    <h2>Players</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th>New players</th>
            <td><label><input type="checkbox" name="wpbbs[allow_new_players]" value="1" <?php checked($s['allow_new_players'], 1); ?>>
                Let signed-in users choose a player name and join the game</label></td>
        </tr>
    </table>

    <h2>Uninstall</h2>
    <table class="form-table" role="presentation">
        <tr>
            <th>Delete all data</th>
            <td><label><input type="checkbox" name="wpbbs[delete_data_on_uninstall]" value="1" <?php checked($s['delete_data_on_uninstall'], 1); ?>>
                When the plugin is deleted from the Plugins screen, remove every game table, setting, player, score, record and news item</label>
                <p class="description">Left unticked, deleting the plugin keeps the game data so it can be reinstalled later. Deactivating the
                    plugin never removes anything. Ticked, deletion is permanent and cannot be undone, so back up your database first.
                    The game pages themselves are ordinary WordPress pages and are left for you to remove.</p></td>
        </tr>
    </table>
    <?php submit_button('Save settings'); ?>
</form>

<div class="wpbbs-danger wpbbs-danger-zone" id="wpbbs-danger">
    <h2>DANGER SECTION</h2>
    <p><strong>Everything here resets game data for every player at once, and cannot be undone.</strong> Take a database backup first.
        Each reset needs the box ticked and the word typed exactly, and is recorded in the admin log. Settings and the game pages are never touched.</p>

    <h3>Reset the progressive jackpot</h3>
    <p>Sets the progressive back to <?php echo esc_html(WPBBS_Game::fmt($s['jackpot_seed'])); ?> without anyone winning it.
        Players, scores and the Hall of Fame are kept.</p>
    <?php echo WPBBS_Admin::form_open('reset_jackpot'); ?>
        <p><label><input type="checkbox" name="confirm_warning" value="1" required> I understand the current jackpot will be lost.</label></p>
        <p><label>Type <code>RESET</code> to confirm: <input type="text" name="confirm_text" class="small-text" required autocomplete="off"></label></p>
        <?php submit_button('Reset the jackpot', 'delete', 'submit', false); ?>
    </form>

    <h3>Reset all scores (a new season)</h3>
    <p>Every player keeps their player name but goes back to <?php echo esc_html(WPBBS_Game::fmt($s['starting_bankroll'])); ?> credits and
        <?php echo (int) $s['turns_per_day']; ?> spins, with their wins, streaks and other stats cleared. The Hall of Fame, jackpot winners,
        monthly bests and the progressive are kept, so past seasons stay on record. The Gazette announces the new season.</p>
    <?php echo WPBBS_Admin::form_open('reset_scores'); ?>
        <p><label><input type="checkbox" name="confirm_warning" value="1" required> I understand every player's score will be lost.</label></p>
        <p><label>Type <code>SCORES</code> to confirm: <input type="text" name="confirm_text" class="small-text" required autocomplete="off"></label></p>
        <?php submit_button('Reset all scores', 'delete', 'submit', false); ?>
    </form>

    <h3>Reset the game to new</h3>
    <p>Deletes every player, score, Hall of Fame record, jackpot win, monthly best, Gazette item and the admin log, and sets the progressive
        back to <?php echo esc_html(WPBBS_Game::fmt($s['jackpot_seed'])); ?>: the game as it was the day it was installed. Players' WordPress
        accounts are kept, and each will choose a player name again.</p>
    <?php echo WPBBS_Admin::form_open('reset_all'); ?>
        <p><label><input type="checkbox" name="confirm_warning" value="1" required> I understand all game data will be permanently deleted.</label></p>
        <p><label>Type <code>NEW GAME</code> to confirm: <input type="text" name="confirm_text" class="regular-text" required autocomplete="off"></label></p>
        <?php submit_button('Reset the game to new', 'delete', 'submit', false); ?>
    </form>
</div>
