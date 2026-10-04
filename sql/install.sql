CREATE TABLE {prefix}wpbbs_players (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  player_name varchar(20) NOT NULL DEFAULT '',
  bankroll bigint(20) NOT NULL DEFAULT 0,
  spins_left int(11) NOT NULL DEFAULT 0,
  spins_date date DEFAULT NULL,
  topup_date date DEFAULT NULL,
  bailout_date date DEFAULT NULL,
  last_bet int(11) NOT NULL DEFAULT 100,
  today_spins int(11) NOT NULL DEFAULT 0,
  today_won bigint(20) NOT NULL DEFAULT 0,
  total_spins int(11) NOT NULL DEFAULT 0,
  total_wins int(11) NOT NULL DEFAULT 0,
  total_won bigint(20) NOT NULL DEFAULT 0,
  biggest_win bigint(20) NOT NULL DEFAULT 0,
  current_streak int(11) NOT NULL DEFAULT 0,
  best_streak int(11) NOT NULL DEFAULT 0,
  jackpots_won int(11) NOT NULL DEFAULT 0,
  peak_bankroll bigint(20) NOT NULL DEFAULT 0,
  rank_level tinyint(3) unsigned NOT NULL DEFAULT 0,
  days_played int(11) NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  last_played datetime DEFAULT NULL,
  last_seen datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY user_id (user_id),
  UNIQUE KEY player_name (player_name),
  KEY bankroll (bankroll),
  KEY last_played (last_played)
) {charset_collate};
CREATE TABLE {prefix}wpbbs_state (
  state_key varchar(40) NOT NULL,
  state_value bigint(20) NOT NULL DEFAULT 0,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY  (state_key)
) {charset_collate};
CREATE TABLE {prefix}wpbbs_jackpots (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  player_id bigint(20) unsigned NOT NULL DEFAULT 0,
  player_name varchar(20) NOT NULL DEFAULT '',
  amount bigint(20) NOT NULL DEFAULT 0,
  bet int(11) NOT NULL DEFAULT 0,
  won_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY amount (amount),
  KEY player_id (player_id)
) {charset_collate};
CREATE TABLE {prefix}wpbbs_records (
  record_key varchar(40) NOT NULL,
  player_id bigint(20) unsigned NOT NULL DEFAULT 0,
  player_name varchar(20) NOT NULL DEFAULT '',
  record_value bigint(20) NOT NULL DEFAULT 0,
  detail varchar(191) NOT NULL DEFAULT '',
  achieved_at datetime DEFAULT NULL,
  PRIMARY KEY  (record_key),
  KEY player_id (player_id)
) {charset_collate};
CREATE TABLE {prefix}wpbbs_monthly (
  player_id bigint(20) unsigned NOT NULL DEFAULT 0,
  month char(7) NOT NULL DEFAULT '',
  peak_bankroll bigint(20) NOT NULL DEFAULT 0,
  reached_at datetime DEFAULT NULL,
  PRIMARY KEY  (player_id,month),
  KEY month_peak (month,peak_bankroll)
) {charset_collate};
CREATE TABLE {prefix}wpbbs_news (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event_type varchar(20) NOT NULL DEFAULT '',
  player_id bigint(20) unsigned NOT NULL DEFAULT 0,
  message varchar(255) NOT NULL DEFAULT '',
  created_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at),
  KEY player_id (player_id)
) {charset_collate};
CREATE TABLE {prefix}wpbbs_admin_log (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  event_type varchar(20) NOT NULL DEFAULT '',
  message varchar(255) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at)
) {charset_collate};
