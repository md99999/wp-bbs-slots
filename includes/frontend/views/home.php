<?php
/**
 * The home page: what the game is, its objectives and how to play. Public.
 *
 * @var object|null $p the player, when one is signed in
 */
if (!defined('ABSPATH')) exit;
$s = WPBBS_Settings::all();
?>
<?php if (is_user_logged_in() && !$p) include WPBBS_PATH . 'includes/frontend/views/_name-form.php'; ?>

<div class="wpbbs-panel wpbbs-hero">
    <p class="wpbbs-jackpot-label">Progressive Jackpot</p>
    <p class="wpbbs-jackpot-small" data-wpbbs="jackpot"><?php echo esc_html(WPBBS_Game::fmt(WPBBS_Game::jackpot())); ?></p>
    <h2>Welcome to <?php echo esc_html(WPBBS_GAME_NAME); ?></h2>
    <p>A three-reel slot machine in the spirit of the BBS door games: a handful of spins a day, a bankroll that
        carries over from one day to the next, and a progressive jackpot that grows with every pull of the handle
        until somebody lines up three Jackpots.</p>
    <?php if ($p) : ?>
        <p><a class="wpbbs-btn wpbbs-btn-big" href="<?php echo esc_url(WPBBS_UI::url('play')); ?>">Play now</a></p>
    <?php elseif (!is_user_logged_in()) : ?>
        <?php echo WPBBS_UI::sign_in_buttons(); ?>
    <?php endif; ?>
</div>

<div class="wpbbs-grid">
    <div class="wpbbs-panel">
        <h3>Objectives</h3>
        <ul>
            <li><strong>Build your score.</strong> Your bankroll of credits is your score, and it carries over for good.
                The Hall of Fame keeps the top scores.</li>
            <li><strong>Hit the progressive.</strong> It starts at <?php echo esc_html(WPBBS_Game::fmt($s['jackpot_seed'])); ?> credits and grows by
                <?php echo esc_html(WPBBS_Game::fmt($s['jackpot_increment'])); ?> with every spin anyone makes. Three Jackpots win the lot.</li>
            <li><strong>Make the Hall of Fame</strong> with the biggest jackpot, the highest single spin, the most wins in a row,
                the highest bankroll of the month, or by being the first to reach a rank.</li>
            <li><strong>Climb the ranks.</strong> You rise by your best-ever score <em>or</em> by the number of days you play,
                whichever gets you there first, and a rank once earned is yours for good: a losing streak never takes it away.</li>
        </ul>
        <div class="wpbbs-table-wrap">
        <table class="wpbbs-table">
            <thead><tr><th>Rank</th><th>Best score</th><th>or days played</th></tr></thead>
            <tbody>
            <?php foreach (WPBBS_Game::RANKS as $i => $r) : ?>
                <tr<?php echo ($p && (int) ($p->rank_level ?? 0) === $i) ? ' class="wpbbs-current"' : ''; ?>>
                    <td><?php echo esc_html($r['title']); ?></td>
                    <td><?php echo $i ? esc_html(WPBBS_Game::fmt($r['score'])) : 'where everyone starts'; ?></td>
                    <td><?php echo $i ? (int) $r['days'] : ''; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <div class="wpbbs-panel">
        <h3>How to play</h3>
        <ol>
            <li>Sign in, open <a href="<?php echo esc_url(WPBBS_UI::url('play')); ?>">Play</a> and choose a player name. It stays with you for good.</li>
            <li>New players start with <?php echo esc_html(WPBBS_Game::fmt($s['starting_bankroll'])); ?> credits.</li>
            <li>Choose a wager: <?php echo esc_html(implode(', ', array_map(['WPBBS_Game', 'fmt'], WPBBS_Game::BETS))); ?> credits.
                It stays set from spin to spin until you change it, or until your bankroll no longer covers it.</li>
            <li>Pull the handle: click <strong>Pull</strong>, or press <kbd>Enter</kbd> or the space bar.</li>
            <li>The three reels spin and stop one at a time, and any win is added to your score.</li>
        </ol>
    </div>
</div>

<div class="wpbbs-grid">
    <div class="wpbbs-panel">
        <h3>Daily play</h3>
        <ul>
            <li>You get <strong><?php echo (int) $s['turns_per_day']; ?> spins a day</strong>. Unused spins do not carry over to the next day,
                but if you miss whole days you can catch up: you receive each missed day's spins, up to
                <?php echo (int) $s['max_catchup_days']; ?> days' worth (<?php echo (int) ($s['turns_per_day'] * $s['max_catchup_days']); ?> spins).</li>
            <li>Every win earns a bonus spin, and a Cherry gives you a free spin.</li>
            <li>Your bankroll carries over permanently. Each new day, anyone with less than
                <?php echo esc_html(WPBBS_Game::fmt($s['daily_floor'])); ?> credits is topped up to <?php echo esc_html(WPBBS_Game::fmt($s['daily_floor'])); ?>.</li>
            <?php if ($s['bailout_amount'] > 0) : ?>
            <li>If your bankroll drops below <?php echo esc_html(WPBBS_Game::fmt($s['bailout_threshold'])); ?> while you still have spins,
                the house gives you a one-time bailout of <?php echo esc_html(WPBBS_Game::fmt($s['bailout_amount'])); ?> credits to finish the day.
                Only one bailout a day: if you spend that too, you are done until tomorrow.</li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="wpbbs-panel">
        <h3>Tips from the regulars</h3>
        <p class="wpbbs-small">Every spin is random, but successful players often:</p>
        <ul>
            <li>Build their bankroll with smaller wagers.</li>
            <li>Raise their bets when credits are plentiful.</li>
            <li>Save the big bets for jackpot runs.</li>
            <li>Log in daily to make the most of their spins.</li>
            <li>Climb the leaderboards through steady play rather than risking everything on a few spins.</li>
        </ul>
    </div>
</div>

<div class="wpbbs-panel">
    <h3>The reels and the pay table</h3>
    <p>Cherries and Lemons come up often, Bells moderately, BARs less often, Diamonds rarely,
        Sevens very rarely and Jackpots hardly ever.</p>
    <?php include WPBBS_PATH . 'includes/frontend/views/_paytable.php'; ?>
</div>

<div class="wpbbs-panel">
    <h3>The news</h3>
    <p>The <a href="<?php echo esc_url(WPBBS_UI::url('gazette')); ?>">Gazette</a> reports who played today, the top scores, big wins and
        where the progressive stands, and the <a href="<?php echo esc_url(WPBBS_UI::url('hof')); ?>">Hall of Fame</a> keeps the records.</p>
    <p class="wpbbs-small wpbbs-dim">This is a game of chance played for a score. Credits have no cash value: nothing can be bought, won,
        sold or redeemed for money or prizes.</p>
</div>
