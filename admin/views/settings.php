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
