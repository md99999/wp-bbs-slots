<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$players = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . WPBBS_DB::t('players'));
$today = count(WPBBS_Player::played_today(1000));
$jackpots = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . WPBBS_DB::t('jackpots'));
$ids = get_option('wpbbs_page_ids', []);
$log = $wpdb->get_results('SELECT * FROM ' . WPBBS_DB::t('admin_log') . ' ORDER BY id DESC LIMIT 15');
$locations = get_registered_nav_menus();
?>
<div class="wpbbs-cards">
    <div class="wpbbs-card">Players<strong><?php echo esc_html(WPBBS_Game::fmt($players)); ?></strong></div>
    <div class="wpbbs-card">Played today<strong><?php echo esc_html(WPBBS_Game::fmt($today)); ?></strong></div>
    <div class="wpbbs-card">Progressive jackpot<strong><?php echo esc_html(WPBBS_Game::fmt(WPBBS_Game::jackpot())); ?></strong></div>
    <div class="wpbbs-card">Jackpots won<strong><?php echo esc_html(WPBBS_Game::fmt($jackpots)); ?></strong></div>
</div>

<div class="wpbbs-box">
    <h2>Game pages</h2>
    <table class="widefat striped">
        <thead><tr><th>Page</th><th>Shortcode</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach (WPBBS_UI::PAGES as $key => $def) :
            $id = !empty($ids[$key]) ? (int) $ids[$key] : 0;
            if (!$id) {
                $found = get_page_by_path($def[1]);
                $id = $found ? (int) $found->ID : 0;
            }
            $status = $id ? get_post_status($id) : false; ?>
            <tr>
                <td><?php echo esc_html($def[0]); ?></td>
                <td><code>[<?php echo esc_html($def[2]); ?>]</code></td>
                <td><?php if ($status === 'publish') : ?>
                        <a href="<?php echo esc_url(get_permalink($id)); ?>" target="_blank" rel="noopener">View</a>
                    <?php else : ?>
                        <em>missing</em>
                    <?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php echo WPBBS_Admin::form_open('setup_pages'); ?>
        <p>Creates any missing pages, each holding its shortcode. Pages that already exist with these slugs are kept and renamed
            to the "WP BBS Slots - ..." titles.</p>
        <p><label for="wpbbs-menu-location">Add the "<?php echo esc_html(WPBBS_GAME_NAME); ?>" home page to a menu:</label>
            <select id="wpbbs-menu-location" name="menu_location">
                <option value="">Don't add it to a menu</option>
                <option value="block_header">Theme header (block themes such as Twenty Twenty-Five)</option>
                <?php foreach ($locations as $slug => $label) : ?>
                    <option value="<?php echo esc_attr($slug); ?>"><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select></p>
        <p class="description">Only the home page goes in the menu. Players reach Play, the Gazette and the Hall of Fame from the
            game's own navigation bar.</p>
        <?php submit_button('Create pages', 'primary', 'submit', false); ?>
    </form>
</div>

<div class="wpbbs-box">
    <h2>Widgets</h2>
    <p>Add a <strong>Shortcode</strong> block to a sidebar or footer (Appearance &rarr; Widgets, or the Site Editor on block themes) and paste:</p>
    <p><code>[wpbs-gazette limit=10 compact=1]</code> the progressive and the top 10 scores<br>
        <code>[wpbs-hof limit=10 compact=1]</code> the Hall of Fame records and the top 10 scores (<code>[wpb-hof]</code> works too)</p>
</div>

<div class="wpbbs-box">
    <h2>Recent admin activity</h2>
    <?php if (!$log) : ?>
        <p>Nothing yet.</p>
    <?php else : ?>
        <table class="widefat striped">
            <thead><tr><th>When</th><th>Who</th><th>What</th></tr></thead>
            <tbody>
            <?php foreach ($log as $row) :
                $u = get_userdata($row->user_id); ?>
                <tr>
                    <td><?php echo esc_html($row->created_at); ?></td>
                    <td><?php echo $u ? esc_html($u->user_login) : '<em>system</em>'; ?></td>
                    <td><?php echo esc_html($row->message); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
