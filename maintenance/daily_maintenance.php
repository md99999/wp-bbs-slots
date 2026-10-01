<?php
/**
 * Optional: run from a system cron instead of relying on WP-Cron, once a day at the site's midnight.
 *   0 0 * * * php /path/to/wp-content/plugins/wp-bbs-slots/maintenance/daily_maintenance.php
 */
if (PHP_SAPI !== 'cli') exit("CLI only.\n");
require_once __DIR__ . '/bootstrap.php';
echo WPBBS_Maintenance::daily() . "\n";
