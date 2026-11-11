CREATE DATABASE IF NOT EXISTS stockland_wp_upgrade
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON stockland_wp_upgrade.* TO 'stockland_wp'@'%';
FLUSH PRIVILEGES;
