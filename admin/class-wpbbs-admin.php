<?php
if (!defined('ABSPATH')) exit;

/**
 * wp-admin screens: dashboard (pages and menu), settings, players and maintenance.
 * Every action posts to admin-post.php with a nonce and a capability check, and is logged.
 */
class WPBBS_Admin {
    const CAP = 'manage_options';

    const SCREENS = [
        'wpbbs_dashboard'   => ['Dashboard', 'dashboard'],
        'wpbbs_settings'    => ['Settings', 'settings'],
        'wpbbs_players'     => ['Players', 'players'],
        'wpbbs_maintenance' => ['Maintenance', 'maintenance'],
    ];

    const POST_ACTIONS = ['setup_pages', 'save_settings', 'player_update', 'player_delete', 'run_maintenance', 'reset_jackpot', 'reset_scores', 'reset_all'];

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_notices', ['WPBBS_Health', 'notice']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        foreach (self::POST_ACTIONS as $action) {
            add_action('admin_post_wpbbs_' . $action, function () use ($action) {
                WPBBS_Admin::handle($action);
            });
        }
    }

    public static function menu() {
        add_menu_page(WPBBS_GAME_NAME, WPBBS_GAME_NAME, self::CAP, 'wpbbs_dashboard', [__CLASS__, 'render'], 'dashicons-games', 58);
        foreach (self::SCREENS as $slug => $def) {
            add_submenu_page('wpbbs_dashboard', WPBBS_GAME_NAME . ' ' . $def[0], $def[0], self::CAP, $slug, [__CLASS__, 'render']);
        }
    }

    public static function assets($hook) {
        if (strpos($hook, 'wpbbs_') === false) return;
        wp_add_inline_style('common', '
            .wpbbs-admin .wpbbs-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin:16px 0}
            .wpbbs-admin .wpbbs-card{background:#fff;border:1px solid #c3c4c7;padding:12px 16px}
            .wpbbs-admin .wpbbs-card strong{display:block;font-size:22px;line-height:1.3}
            .wpbbs-admin .wpbbs-danger{border-left:4px solid #d63638;background:#fff;padding:12px 16px;margin:16px 0;max-width:860px}
            .wpbbs-admin .wpbbs-box{background:#fff;border:1px solid #c3c4c7;padding:12px 16px;margin:16px 0;max-width:860px}
            .wpbbs-admin input.small-text{width:110px}
            .wpbbs-admin .wpbbs-danger-zone{border:2px solid #d63638;border-left-width:6px}
            .wpbbs-admin .wpbbs-danger-zone>h2{color:#d63638;letter-spacing:.05em}
        ');
    }

    public static function render() {
        if (!current_user_can(self::CAP)) wp_die('Not allowed.');
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : 'wpbbs_dashboard';
        if (!isset(self::SCREENS[$page])) $page = 'wpbbs_dashboard';
        echo '<div class="wrap wpbbs-admin"><h1>' . esc_html(WPBBS_GAME_NAME) . ' &mdash; ' . esc_html(self::SCREENS[$page][0]) . '</h1>';
        self::render_notices();
        // The view file comes from the fixed SCREENS list, never from the request.
        include WPBBS_PATH . 'admin/views/' . self::SCREENS[$page][1] . '.php';
        self::disclaimer();
        echo '</div>';
    }

    /** The at-your-own-risk notice shown at the foot of every admin screen. */
    public static function disclaimer() {
        ?>
        <div class="wpbbs-danger">
            <h2>Disclaimer</h2>
            <p>You run this plugin at your own risk. The author accepts no responsibility or liability for any loss, damage or
                compromise arising from its use. Every effort has been made to write it safely, but new vulnerabilities appear in
                software of every kind every day and no website can be guaranteed secure. It is provided as is, without warranty of
                any kind, under the <a href="https://www.gnu.org/licenses/gpl-2.0.html" target="_blank" rel="noopener">GNU General Public License v2</a>.</p>
            <p>Test on a staging site first, keep backups of your database and files, keep WordPress, PHP, your theme and plugins up
                to date, and serve the site over HTTPS. Full details are in the plugin's README.md. Found a security problem? Please
                report it privately to <a href="mailto:sysop@maddogproductions.online">sysop@maddogproductions.online</a>.</p>
            <p>Credits in this game have no cash value and depict a game score only. <?php echo esc_html(WPBBS_GAME_NAME); ?> is not
                affiliated with any casino, sports or gaming company, or any other slot machine game or code.</p>
        </div>
        <?php
    }

    /** Opens an admin-post form for $action with its nonce. */
    public static function form_open($action, $attrs = '') {
        return '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" ' . $attrs . '>'
            . '<input type="hidden" name="action" value="wpbbs_' . esc_attr($action) . '">'
            . wp_nonce_field('wpbbs_admin_' . $action, '_wpbbsnonce', true, false);
    }

    public static function notice($type, $message) {
        $key = 'wpbbs_admin_notice_' . get_current_user_id();
        $list = get_transient($key) ?: [];
        $list[] = [$type, $message];
        set_transient($key, $list, 5 * MINUTE_IN_SECONDS);
    }

    private static function render_notices() {
        $key = 'wpbbs_admin_notice_' . get_current_user_id();
        $list = get_transient($key);
        if (!$list) return;
        delete_transient($key);
        foreach ($list as $n) {
            echo '<div class="notice notice-' . esc_attr($n[0]) . ' is-dismissible"><p>' . esc_html($n[1]) . '</p></div>';
        }
    }

    public static function handle($action) {
        if (!current_user_can(self::CAP)) wp_die('Not allowed.', 403);
        check_admin_referer('wpbbs_admin_' . $action, '_wpbbsnonce');
        $back = wp_get_referer() ?: admin_url('admin.php?page=wpbbs_dashboard');
        try {
            $method = 'do_' . $action;
            self::$method();
        } catch (WPBBS_Exception $e) {
            self::notice('error', $e->getMessage());
        }
        wp_safe_redirect($back);
        exit;
    }

    /** A posted field as a string; non-scalars are discarded so sanitisers get what they expect. */
    private static function post($name, $default = '') {
        if (!isset($_POST[$name]) || !is_scalar($_POST[$name])) return $default;
        return wp_unslash((string) $_POST[$name]);
    }

    /** A posted array of settings, keys and values reduced to strings. */
    private static function post_array($name) {
        if (empty($_POST[$name]) || !is_array($_POST[$name])) return [];
        $out = [];
        foreach (wp_unslash($_POST[$name]) as $key => $value) {
            if (is_scalar($value)) $out[sanitize_key($key)] = (string) $value;
        }
        return $out;
    }

    // ----- Handlers -----

    private static function do_setup_pages() {
        $ids = get_option('wpbbs_page_ids', []);
        $created = 0;
        $renamed = 0;
        foreach (WPBBS_UI::PAGES as $key => $def) {
            list($title, $slug, $shortcode) = $def;
            $existing = get_page_by_path($slug);
            if ($existing && $existing->post_status !== 'trash') {
                $ids[$key] = $existing->ID;
                if ($existing->post_title !== $title) {
                    wp_update_post(['ID' => $existing->ID, 'post_title' => $title]);
                    $renamed++;
                }
                continue;
            }
            $ids[$key] = wp_insert_post([
                'post_title' => $title, 'post_name' => $slug, 'post_content' => '[' . $shortcode . ']',
                'post_status' => 'publish', 'post_type' => 'page', 'comment_status' => 'closed', 'ping_status' => 'closed',
            ]);
            $created++;
        }
        update_option('wpbbs_page_ids', $ids);
        $msg = sprintf('%d page(s) created, %d renamed.', $created, $renamed);

        // Adding the home page to a menu is optional: nowhere (the default), a block theme's
        // header, or one of the theme's classic menu locations.
        $location = sanitize_key(self::post('menu_location'));
        if ($location) {
            $menu = wp_get_nav_menu_object(WPBBS_GAME_NAME);
            $menu_id = $menu ? $menu->term_id : wp_create_nav_menu(WPBBS_GAME_NAME);
            if (is_wp_error($menu_id)) throw new WPBBS_Exception('Could not create the menu: ' . $menu_id->get_error_message());
            $home_item = 0;
            foreach ((array) wp_get_nav_menu_items($menu_id) as $item) {
                if ($item && $item->object === 'page' && (int) $item->object_id === (int) $ids['home']) $home_item = (int) $item->db_id;
            }
            $item_id = wp_update_nav_menu_item($menu_id, $home_item, [
                'menu-item-title' => WPBBS_GAME_NAME, 'menu-item-object' => 'page',
                'menu-item-object-id' => $ids['home'], 'menu-item-type' => 'post_type',
                'menu-item-status' => 'publish', 'menu-item-parent-id' => 0, 'menu-item-position' => 1,
            ]);
            if (is_wp_error($item_id)) throw new WPBBS_Exception('Could not add the menu item: ' . $item_id->get_error_message());

            if ($location === 'block_header') {
                self::build_block_navigation($ids);
                $msg .= ' A block navigation menu with a single "' . WPBBS_GAME_NAME . '" link was created for your theme\'s header;'
                    . ' if the header still shows another menu, choose it in the Navigation block in the Site Editor.';
            } elseif (array_key_exists($location, get_registered_nav_menus())) {
                $locations = get_theme_mod('nav_menu_locations', []);
                $locations[$location] = $menu_id;
                set_theme_mod('nav_menu_locations', $locations);
                $msg .= ' The "' . WPBBS_GAME_NAME . '" menu, with a single link to the home page, is assigned to the "' . $location . '" location.';
            }
        } else {
            $msg .= ' No menu was changed; add the "' . WPBBS_GAME_NAME . '" page to a menu under Appearance whenever you like.';
        }
        WPBBS_Log::admin('setup', $msg);
        self::notice('success', $msg);
    }

    /**
     * Block themes ignore classic menus: their header Navigation block shows a wp_navigation
     * post. This creates (or refreshes) one holding a single link to the home page.
     */
    private static function build_block_navigation(array $ids) {
        $content = get_comment_delimited_block_content('core/navigation-link', [
            'label' => WPBBS_GAME_NAME, 'type' => 'page', 'id' => (int) $ids['home'],
            'url' => get_permalink($ids['home']), 'kind' => 'post-type',
        ], '');
        $post_id = (int) get_option('wpbbs_nav_post_id');
        $post = [
            'post_type' => 'wp_navigation', 'post_status' => 'publish',
            'post_title' => WPBBS_GAME_NAME, 'post_content' => wp_slash($content),
        ];
        if ($post_id && get_post_type($post_id) === 'wp_navigation') {
            $post['ID'] = $post_id;
            $result = wp_update_post($post, true);
        } else {
            $result = wp_insert_post($post, true);
        }
        if (is_wp_error($result)) throw new WPBBS_Exception('Could not create the block navigation menu: ' . $result->get_error_message());
        update_option('wpbbs_nav_post_id', (int) $result, false);
    }

    private static function do_save_settings() {
        $values = self::post_array('wpbbs');
        // An unticked checkbox sends nothing.
        $values['delete_data_on_uninstall'] = !empty($values['delete_data_on_uninstall']) ? 1 : 0;
        $values['allow_new_players'] = !empty($values['allow_new_players']) ? 1 : 0;
        WPBBS_Settings::update($values);
        WPBBS_Log::admin('settings', 'Settings updated.');
        self::notice('success', 'Settings saved.');
    }

    private static function do_player_update() {
        global $wpdb;
        $id = (int) self::post('player_id');
        $p = WPBBS_Player::fresh($id);
        if (!$p) throw new WPBBS_Exception('Player not found.');
        $wpdb->update(WPBBS_DB::t('players'), [
            'bankroll'   => max(0, (int) self::post('bankroll')),
            'spins_left' => max(0, min(1000, (int) self::post('spins_left'))),
        ], ['id' => $id]);
        WPBBS_Log::admin('player', sprintf('Edited player #%d (%s).', $id, $p->player_name));
        self::notice('success', sprintf('%s updated.', $p->player_name));
    }

    private static function do_player_delete() {
        if (!self::post('confirm_delete')) throw new WPBBS_Exception('Tick the box to confirm the deletion.');
        $p = WPBBS_Player::delete((int) self::post('player_id'));
        WPBBS_Log::admin('player', sprintf('Deleted player #%d.', $p->id));
        self::notice('success', sprintf('Deleted %s. Their news is gone and any Hall of Fame entries no longer show their name.', $p->player_name));
    }

    private static function do_run_maintenance() {
        $u = wp_get_current_user();
        $result = WPBBS_Maintenance::daily(true, 'Run now by ' . $u->user_login);
        WPBBS_Log::admin('maintenance', 'Manual run: ' . $result);
        self::notice('success', $result);
    }

    /** The resets in the DANGER SECTION need the warning box ticked and $word typed exactly. */
    private static function confirm($word, $what) {
        if (!self::post('confirm_warning') || trim(self::post('confirm_text')) !== $word) {
            throw new WPBBS_Exception(sprintf('%s cancelled: tick the warning box and type %s to confirm.', $what, $word));
        }
    }

    /** A new season: every player back to the starting bankroll and spins, stats cleared. The Hall of Fame is kept. */
    private static function do_reset_scores() {
        global $wpdb;
        self::confirm('SCORES', 'Score reset');
        $s = WPBBS_Settings::all();
        $today = WPBBS_Game::today();
        $count = (int) $wpdb->query($wpdb->prepare(
            'UPDATE ' . WPBBS_DB::t('players') . ' SET bankroll = %d, peak_bankroll = %d, spins_left = %d, spins_date = %s, topup_date = %s,
                bailout_date = NULL, last_bet = %d, today_spins = 0, today_won = 0, total_spins = 0, total_wins = 0, total_won = 0,
                biggest_win = 0, current_streak = 0, best_streak = 0, jackpots_won = 0',
            $s['starting_bankroll'], $s['starting_bankroll'], $s['turns_per_day'], $today, $today, WPBBS_Game::min_bet()
        ));
        WPBBS_Log::news('season', sprintf('A new season begins! Every player starts again with %s credits. The Hall of Fame remembers the last one.',
            WPBBS_Game::fmt($s['starting_bankroll'])));
        WPBBS_Log::admin('reset', sprintf('All scores reset: %d players back to %d credits.', $count, $s['starting_bankroll']));
        self::notice('success', sprintf('All scores reset: %d players are back to %s credits and %d spins.', $count,
            WPBBS_Game::fmt($s['starting_bankroll']), $s['turns_per_day']));
    }

    /** Back to new: all game data deleted and the progressive reseeded. Settings and pages are kept. */
    private static function do_reset_all() {
        global $wpdb;
        self::confirm('NEW GAME', 'Game reset');
        foreach (['players', 'jackpots', 'records', 'monthly', 'news', 'admin_log'] as $table) {
            $wpdb->query('DELETE FROM ' . WPBBS_DB::t($table));
        }
        WPBBS_Installer::seed_state();
        $wpdb->update(WPBBS_DB::t('state'), ['state_value' => WPBBS_Settings::get('jackpot_seed'), 'updated_at' => current_time('mysql')], ['state_key' => 'jackpot']);
        delete_option('wpbbs_last_daily');
        WPBBS_Log::admin('reset', 'Game reset to new: all players, scores, records and news deleted.');
        self::notice('success', 'The game has been reset to new. Every player will choose a player name again.');
    }

    private static function do_reset_jackpot() {
        global $wpdb;
        self::confirm('RESET', 'Jackpot reset');
        $seed = WPBBS_Settings::get('jackpot_seed');
        WPBBS_Installer::seed_state();
        $wpdb->update(WPBBS_DB::t('state'), ['state_value' => $seed, 'updated_at' => current_time('mysql')], ['state_key' => 'jackpot']);
        WPBBS_Log::admin('jackpot', 'Progressive reset to ' . $seed . '.');
        self::notice('success', 'The progressive jackpot has been reset to ' . WPBBS_Game::fmt($seed) . '.');
    }
}
