<?php
if (!defined('ABSPATH')) exit;
if (!wp_next_scheduled(WPBBS_Maintenance::DAILY_HOOK)) WPBBS_Maintenance::schedule();
$next = wp_next_scheduled(WPBBS_Maintenance::DAILY_HOOK);
$next = $next ? wp_date('Y-m-d H:i:s', $next) : 'not scheduled';
$cron_url = home_url('/wp-cron.php?doing_wp_cron');
$php_bin = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
$daily_php = WPBBS_PATH . 'maintenance/daily_maintenance.php';
$wp_cron_off = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
$floor = WPBBS_Game::fmt(WPBBS_Settings::get('daily_floor'));

/** A read-only field that selects itself when clicked, so the command is easy to copy. */
$cmd = function ($command) {
    echo '<p><input type="text" class="large-text code" readonly onfocus="this.select()" value="' . esc_attr($command) . '"></p>';
};
?>
<table class="widefat striped" style="max-width:860px">
    <thead><tr><th>Job</th><th>What it does</th><th>Last run</th><th>Next run</th><th></th></tr></thead>
    <tbody>
        <tr>
            <td><strong>Daily</strong></td>
            <td>Tops every bankroll under <?php echo esc_html($floor); ?> up to <?php echo esc_html($floor); ?>, and purges old news.</td>
            <td><?php echo esc_html(get_option('wpbbs_last_daily', 'never')); ?></td>
            <td><?php echo esc_html($next); ?></td>
            <td><?php echo WPBBS_Admin::form_open('run_maintenance'); ?><button class="button">Run now</button></form></td>
        </tr>
    </tbody>
</table>
<p class="description" style="max-width:860px">Each player is also topped up and given the day's spins the first time they visit on a
    new day, so play works even if cron is late. Running the job twice in a day does no harm: nobody is topped up twice.</p>

<div class="wpbbs-box">
    <h2>Set up a real cron job</h2>
    <p>WordPress's own scheduler (WP-Cron) only runs when someone visits the site. A real cron job, added in your hosting control
        panel, makes the daily top-up happen at midnight even on a quiet site.</p>
    <p><strong>Running both is safe.</strong> The job takes a database lock before it does anything and refuses to run twice on
        the same day. <em>WP-Cron is currently <?php echo $wp_cron_off ? 'disabled on this site (DISABLE_WP_CRON is set), so a real cron job is required' : 'enabled on this site'; ?>.</em></p>

    <h3>Recommended: trigger WordPress's scheduler every 5 minutes</h3>
    <p>In cPanel open <strong>Advanced &rarr; Cron Jobs</strong>, choose <strong>Once Per Five Minutes</strong> and paste this into
        <strong>Command</strong> (click the box to select it):</p>
    <?php $cmd('curl -s ' . $cron_url . ' > /dev/null 2>&1'); ?>
    <p class="description">If the host has no <code>curl</code>, use <code>wget</code>:</p>
    <?php $cmd('wget -q -O - ' . $cron_url . ' > /dev/null 2>&1'); ?>
    <p class="description">Editing a crontab by hand? Paste the whole line:</p>
    <?php $cmd('*/5 * * * * curl -s ' . $cron_url . ' > /dev/null 2>&1'); ?>

    <h3>Alternative: run the game's daily job directly</h3>
    <p>Run this once a day at your site's midnight (cPanel: <em>Once Per Day</em>, then set the hour):</p>
    <?php $cmd($php_bin . ' ' . $daily_php); ?>
    <p class="description">Cron follows the <em>server's</em> clock, often UTC, while the game uses the timezone in Settings &rarr; General
        (currently <code><?php echo esc_html(wp_timezone_string()); ?></code>, where it is now <?php echo esc_html(current_time('H:i')); ?>).
        Choose the hour that matches your local midnight.</p>
</div>

<div class="wpbbs-danger">
    <h2>Reset the progressive jackpot</h2>
    <p>Sets the progressive back to its starting value of <?php echo esc_html(WPBBS_Game::fmt(WPBBS_Settings::get('jackpot_seed'))); ?>
        without anyone winning it. This cannot be undone.</p>
    <?php echo WPBBS_Admin::form_open('reset_jackpot'); ?>
        <p><label><input type="checkbox" name="confirm_warning" value="1"> I understand the current jackpot will be lost.</label></p>
        <p><label>Type <code>RESET</code> to confirm: <input type="text" name="confirm_text" class="small-text"></label></p>
        <?php submit_button('Reset the jackpot', 'delete', 'submit', false); ?>
    </form>
</div>
