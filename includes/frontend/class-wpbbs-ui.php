<?php
if (!defined('ABSPATH')) exit;

/**
 * Front-end helpers: page URLs, navigation, notices, forms, symbols and the footer.
 */
class WPBBS_UI {
    /**
     * key => [page title, slug, shortcode, nav label]
     * Order is the in-game navigation order. The home page is the only one meant for a site menu.
     */
    const PAGES = [
        'home'    => ['WP BBS Slots', 'wp-bbs-slots', 'wpbs-home', 'Home'],
        'play'    => ['WP BBS Slots - Play', 'wp-bbs-slots-play', 'wpbs-play', 'Play'],
        'gazette' => ['WP BBS Slots - Gazette', 'wp-bbs-slots-gazette', 'wpbs-gazette', 'Gazette'],
        'hof'     => ['WP BBS Slots - Hall of Fame', 'wp-bbs-slots-hall-of-fame', 'wpbs-hof', 'Hall of Fame'],
    ];

    /** Other spellings each shortcode answers to: shortcode => page key. */
    const ALIASES = [
        'wpb-hof'      => 'hof',
        'wpbs_hof'     => 'hof',
        'wpbs_gazette' => 'gazette',
        'wpbs_home'    => 'home',
        'wpbs_play'    => 'play',
    ];

    /** Every shortcode name the plugin registers, mapped to its page key. */
    public static function shortcodes() {
        $out = [];
        foreach (self::PAGES as $key => $def) $out[$def[2]] = $key;
        return $out + self::ALIASES;
    }

    public static function url($key, $args = []) {
        static $cache = [];
        if (!isset($cache[$key])) {
            $ids = get_option('wpbbs_page_ids', []);
            $url = '';
            if (!empty($ids[$key]) && get_post_status($ids[$key]) === 'publish') $url = get_permalink($ids[$key]);
            if (!$url && isset(self::PAGES[$key])) {
                $page = get_page_by_path(self::PAGES[$key][1]);
                $url = $page ? get_permalink($page) : home_url('/' . self::PAGES[$key][1] . '/');
            }
            $cache[$key] = $url;
        }
        return $args ? add_query_arg($args, $cache[$key]) : $cache[$key];
    }

    public static function enqueue_assets() {
        wp_register_style('wp-bbs-slots', WPBBS_URL . 'assets/css/wp-bbs-slots.css', [], WPBBS_VERSION);
        wp_register_script('wp-bbs-slots', WPBBS_URL . 'assets/js/wp-bbs-slots.js', [], WPBBS_VERSION, true);
        $symbols = [];
        foreach (WPBBS_Game::SYMBOLS as $key => $s) {
            $symbols[$key] = ['label' => $s['label'], 'icon' => $s['icon']];
        }
        wp_localize_script('wp-bbs-slots', 'WPBBS', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => is_user_logged_in() ? wp_create_nonce('wpbbs_spin') : '',
            'symbols' => $symbols,
            'bets'    => WPBBS_Game::BETS,
        ]);
        global $post;
        if (!is_singular() || !$post) return;
        foreach (array_keys(self::shortcodes()) as $tag) {
            if (has_shortcode($post->post_content, $tag)) {
                wp_enqueue_style('wp-bbs-slots');
                wp_enqueue_script('wp-bbs-slots');
                return;
            }
        }
    }

    public static function flash($type, $message) {
        $key = 'wpbbs_flash_' . get_current_user_id();
        $list = get_transient($key);
        if (!is_array($list)) $list = [];
        $list[] = [$type, $message];
        set_transient($key, $list, 5 * MINUTE_IN_SECONDS);
    }

    public static function render_flashes() {
        if (!is_user_logged_in()) return '';
        $key = 'wpbbs_flash_' . get_current_user_id();
        $list = get_transient($key);
        if (!$list) return '';
        delete_transient($key);
        $out = '';
        foreach ($list as $f) {
            $out .= '<div class="wpbbs-flash wpbbs-flash-' . esc_attr($f[0]) . '">' . nl2br(esc_html($f[1])) . '</div>';
        }
        return $out;
    }

    /**
     * Opens a POST form for a game action; close it with </form>.
     * The form carries the page it was submitted from, so the player comes back to it.
     */
    public static function form_open($action, $class = '', $attrs = '') {
        return '<form method="post" class="wpbbs-form ' . esc_attr($class) . '" ' . $attrs . '>'
            . '<input type="hidden" name="wpbbs_action" value="' . esc_attr($action) . '">'
            . '<input type="hidden" name="wpbbs_return" value="' . esc_url(self::current_url()) . '">'
            . wp_nonce_field('wpbbs_action', 'wpbbs_nonce', true, false);
    }

    /** The URL of the page being viewed, including its query string. */
    public static function current_url() {
        $url = home_url(add_query_arg([]));
        return remove_query_arg(['wpbbs_action', 'wpbbs_nonce', '_wp_http_referer'], $url);
    }

    /** A reel symbol as HTML: emoji for the fruit, styled text for BAR, 7 and Jackpot. */
    public static function symbol($key) {
        if (!isset(WPBBS_Game::SYMBOLS[$key])) return '';
        $s = WPBBS_Game::SYMBOLS[$key];
        return '<span class="wpbbs-sym wpbbs-sym-' . esc_attr($key) . '" role="img" aria-label="' . esc_attr($s['label']) . '">'
            . '<span class="wpbbs-sym-icon" aria-hidden="true">' . esc_html($s['icon']) . '</span>'
            . '<span class="wpbbs-sym-label" aria-hidden="true">' . esc_html($s['label']) . '</span></span>';
    }

    public static function status_bar($p) {
        $items = [
            'Player' => esc_html($p->player_name),
            'Rank'   => esc_html(WPBBS_Game::rank_title((int) $p->bankroll)),
            'Score'  => '<span data-wpbbs="bankroll">' . WPBBS_Game::fmt($p->bankroll) . '</span>',
            'Spins left' => '<span data-wpbbs="spins">' . (int) $p->spins_left . '</span>',
        ];
        $html = '<div class="wpbbs-status">';
        foreach ($items as $label => $value) {
            $html .= '<span class="wpbbs-stat"><span class="wpbbs-label">' . esc_html($label) . '</span> ' . $value . '</span>';
        }
        return $html . '</div>';
    }

    public static function nav($current) {
        $html = '<nav class="wpbbs-nav" aria-label="' . esc_attr(WPBBS_GAME_NAME) . '">';
        foreach (self::PAGES as $key => $def) {
            $html .= '<a href="' . esc_url(self::url($key)) . '"' . ($key === $current ? ' class="wpbbs-active" aria-current="page"' : '') . '>'
                . esc_html($def[3]) . '</a>';
        }
        return $html . '</nav>';
    }

    /** The footer: the no-cash-value notice, the version and the site credit. */
    public static function footer() {
        return '<div class="wpbbs-footer">'
            . '<span class="wpbbs-footer-note">Credits have no cash value and depict a game score only.</span>'
            . '<span class="wpbbs-stat">' . esc_html(WPBBS_GAME_NAME) . ' v' . esc_html(WPBBS_VERSION)
            . ' &middot; <a href="' . esc_url(WPBBS_SOURCE_URL) . '" target="_blank" rel="noopener">' . esc_html(WPBBS_SITE_NAME) . '</a></span>'
            . '</div>';
    }

    /** Sign-in buttons for visitors who are not signed in. */
    public static function sign_in_buttons($return = '') {
        $return = $return ?: self::url('play');
        $html = '<p class="wpbbs-buttons"><a class="wpbbs-btn" href="' . esc_url(wp_login_url($return)) . '">Sign in to play</a>';
        if (get_option('users_can_register')) {
            $html .= ' <a class="wpbbs-btn wpbbs-btn-alt" href="' . esc_url(wp_registration_url()) . '">Create an account</a>';
        }
        return $html . '</p>';
    }

    public static function time_ago($mysql) {
        $ts = strtotime((string) $mysql);
        return $ts ? human_time_diff($ts, current_time('timestamp')) . ' ago' : '';
    }
}
