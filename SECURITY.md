# Security Policy

## Reporting a vulnerability

Please report security problems privately, by email, rather than opening a public issue:

**sysop@maddogproductions.online**

Please do not post the details publicly until a fix is available, so that sites running the game
are not exposed in the meantime.

### What to include

- what the problem is, and what an attacker could do with it
- the steps to reproduce it, ideally with the exact request, URL or form involved
- the plugin version (shown on the Plugins screen and in the game's footer)
- WordPress and PHP versions, and anything unusual about the site
- whether the attacker needs to be signed in, and with what role
- any proof-of-concept code, patch or suggested fix you have

### What to expect

This is a hobby project maintained by one person, so please be patient. The aim is to acknowledge
a report within a week, agree what the problem is and how serious it is, fix it and release a new
version, and credit you in the release notes if you would like that. No bounty is offered.

## How input is handled

For anyone auditing the plugin, this is the approach it takes. It is not a claim that the code is
flawless: it is what to check, and where a mistake would most likely be.

- **A player can send only two things:** a player name and a wager.
  - The **player name** goes through `sanitize_text_field()`, is limited to 3 to 20 characters, and
    must match a whitelist of letters, numbers, spaces, dots, dashes and underscores. Names that could
    pass for staff or the game (admin, sysop, moderator and the like) are refused. It is stored once
    and never changed by the player.
  - The **wager** must be plain digits (so `5000 OR 1=1` or `1e3` is refused rather than cast) and
    one of the fixed values in `WPBBS_Game::BETS`; anything else is refused.
- **Every game action carries a WordPress nonce**, checked before anything happens, and requires a
  signed-in user. The spin is a POST to `admin-ajax.php` (`wp_ajax_` only, so never for visitors who
  are not signed in), with a plain form POST as the fallback when JavaScript is off.
- **The server decides every spin** with `random_int()`. The browser only animates the result it is
  sent, so nothing a player sends can choose the symbols or the payout.
- **Posted fields are read through one helper** that discards anything which is not a scalar.
- **Every SQL statement with a variable in it uses `$wpdb->prepare()`** with placeholders. Values
  are never concatenated into SQL; table names come from a fixed list.
- **Output is escaped at the point of printing** with `esc_html()`, `esc_attr()` or `esc_url()`. The
  JavaScript builds the reels with `textContent`, never `innerHTML`.
- **No file paths come from user input.** The only dynamic `include` statements use keys from fixed
  lists of pages and admin screens, so directory traversal and file inclusion have nothing to act on.
- **Every folder has an empty `index.php`** and every PHP file exits when loaded directly, so the
  plugin's files cannot be listed or run on their own over the web.
- **No shell, `eval()`, `unserialize()` or dynamic code execution** anywhere in the plugin.
- **Admin functions require `manage_options`** plus their own nonce, and are logged.
- **Redirects are validated** with `wp_validate_redirect()` and `wp_safe_redirect()`.
- **Bankroll, spin and jackpot changes are atomic** single UPDATE statements with the guard in the
  WHERE clause, so a double-submitted spin cannot spend the same spin or credits twice, two players
  cannot both collect the same jackpot, and nobody receives two bailouts or top-ups in a day.

## In scope

- a game action that works without being signed in, or for the wrong player
- one player affecting another's bankroll, spins or name
- SQL injection, cross-site scripting, cross-site request forgery, path traversal, file inclusion,
  or a missing capability check
- an administrator-only function reachable by someone who is not an administrator
- a way to gain spins or credits, or to choose a spin's result, that the game's rules do not permit

## Out of scope

- vulnerabilities in WordPress core, other plugins, themes, PHP, MySQL or the web server
- anything that requires an administrator account to exploit
- missing hardening on the site around the plugin, such as weak passwords or no HTTPS
- denial of service through sheer volume of requests

## Supported versions

Only the latest release is supported. Please update before reporting a problem.

## No warranty

As set out in the [README](README.md) and the [GNU General Public License v2](LICENSE), this
software is provided **as is, without warranty of any kind**. You install and run it at your own
risk, and the author accepts no responsibility or liability for any loss, damage or compromise
arising from its use.
