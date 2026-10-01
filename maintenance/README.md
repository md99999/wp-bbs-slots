# Maintenance script

`daily_maintenance.php` runs the game's daily job from a real (system) cron job: every player is
given the day's spins, every bankroll under the daily floor (10,000 by default) is topped up to it,
and old Gazette news is purged. The day follows the timezone set in Settings → General.

It finds and loads WordPress by itself, so it can be run from any directory, once a day at your
site's midnight:

```
php /path/to/wordpress/wp-content/plugins/wp-bbs-slots/maintenance/daily_maintenance.php
```

Running it alongside WP-Cron is safe: the job takes a database lock before it does anything and
refuses to run twice on the same day, and nobody is ever topped up twice in a day. Players are also
topped up and given their spins the first time they visit on a new day, so the game works even if
cron is late.

**WP BBS Slots → Maintenance** in wp-admin shows this command with your site's real paths, and the
recommended alternative of triggering `wp-cron.php` every 5 minutes. See
**[Scheduled maintenance (cron)](../README.md#scheduled-maintenance-cron)** in the main README.
