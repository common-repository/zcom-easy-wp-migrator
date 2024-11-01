<?php
/*
Plugin Name: Z.com Easy WP Migrator
Plugin URI: https://wordpress.org/plugins/zcom-easy-wp-migrator/
Description: A plugin for migrations by Z.com for WordPress.
Version: 1.1.5
Author: GMO Internet Group, Inc.
Author URI: https://z.com/
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Text Domain: zcom-easy-wp-migrator
*/

// constant
define( 'ZEWM_MIGRATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

define( 'ZEWM_MIGRATION_STATUS_NO_DATA', 'no_data' );

define( 'ZEWM_MIGRATION_STATUS_BACKUP_START', 'start_backup' );
define( 'ZEWM_MIGRATION_STATUS_BACKUP_DUMP_DB', 'dump_db' );
define( 'ZEWM_MIGRATION_STATUS_BACKUP_COMPRESS_ZIP', 'compress_zip' );
define( 'ZEWM_MIGRATION_STATUS_BACKUP_COMPRESS_TAR', 'compress_tar' );
define( 'ZEWM_MIGRATION_STATUS_BACKUP_COMPLETE', 'complete_backup' );

define( 'ZEWM_MIGRATION_STATUS_RESTORE_START', 'start_restore' );
define( 'ZEWM_MIGRATION_STATUS_RESTORE_DOWNLOAD_FILE', 'download_file' );
define( 'ZEWM_MIGRATION_STATUS_RESTORE_EXTRACT_ZIP', 'extract_zip' );
define( 'ZEWM_MIGRATION_STATUS_RESTORE_EXTRACT_TAR', 'extract_tar' );
define( 'ZEWM_MIGRATION_STATUS_RESTORE_DB', 'restore_db' );
define( 'ZEWM_MIGRATION_STATUS_RESTORE_COMPLETE', 'complete_restore' );

define( 'ZEWM_BACKUP_INFO_WP_OPTION_KEY', 'zewm_backup_info' );
define( 'ZEWM_BACKUP_BULK_INSERT_LIMIT', 500 );

define( 'ZEWM_RESTORE_INFO_WP_OPTION_KEY', 'zewm_restore_info' );

// import
$class_dir = ZEWM_MIGRATION_PLUGIN_DIR . 'classes' . DIRECTORY_SEPARATOR;
require_once $class_dir . 'class-zewm-migration-logger.php';
require_once $class_dir . 'class-zewm-migration-response.php';
require_once $class_dir . 'class-zewm-migration-info.php';

require_once $class_dir . 'mysql' . DIRECTORY_SEPARATOR . 'class-zewm-migration-mysql-query.php';
require_once $class_dir . 'utils' . DIRECTORY_SEPARATOR . 'class-zewm-migration-zip.php';
require_once $class_dir . 'utils' . DIRECTORY_SEPARATOR . 'class-zewm-migration-tar.php';
require_once $class_dir . 'utils' . DIRECTORY_SEPARATOR . 'class-zewm-migration-file-utils.php';
require_once $class_dir . 'utils' . DIRECTORY_SEPARATOR . 'class-zewm-migration-server-info.php';

$action_dir = $class_dir . 'actions' . DIRECTORY_SEPARATOR;
require_once $action_dir . 'class-zewm-migration-action-base.php';
require_once $action_dir . 'class-zewm-migration-action-wp-info.php';
require_once $action_dir . 'class-zewm-backup-action-info.php';
require_once $action_dir . 'class-zewm-backup-action-backup.php';
require_once $action_dir . 'class-zewm-backup-action-remove.php';
require_once $action_dir . 'class-zewm-backup-action-log.php';
require_once $action_dir . 'class-zewm-restore-action-restore.php';
require_once $action_dir . 'class-zewm-restore-action-remove.php';
require_once $action_dir . 'class-zewm-restore-action-info.php';
require_once $action_dir . 'class-zewm-restore-action-log.php';

require_once $class_dir . 'class-zewm-migration-controller.php';

$controller = Zewm_Migration_Controller::instance();
