<?php

class Zewm_Backup_Action_Backup extends Zewm_Migration_Action
{
    public static $action_key = 'backup';

    private $logger;
    private $backup_dir_name;
    private $backup_dir_path;
    private $backup_file_name;
    private $zewm_info;

    public function do_action()
    {
        $this->zewm_info = new Zewm_Backup_Info();
        $restore_info = new Zewm_Restore_Info();
        $restore_info->update();

        $current_status = $this->zewm_info->get_status();
        if ( $current_status !== ZEWM_MIGRATION_STATUS_NO_DATA
            && $current_status !== ZEWM_MIGRATION_STATUS_BACKUP_COMPLETE ) {
            Zewm_Migration_Response::create_error_response( 'backup is already in progress.', 400 );
        }
        if ( $current_status === ZEWM_MIGRATION_STATUS_BACKUP_COMPLETE ) {
            // remove previous data
            Zewm_File_Utils::delete_dir( $this->zewm_info->get_backup_dir_path() );
        }
        $this->zewm_info->delete();

        try {
            $this->backup_start();

            $prefix = "wp_";
            if ( isset( $_GET['prefix'] ) && $_GET['prefix'] !== '' ) {
                $prefix = $_GET['prefix'];
            }
            $mysql_backup = $this->dump_database( $prefix );
            $this->compress_data( $mysql_backup );
            $this->complete_backup();
        } catch (Exception $e) {
            if (isset( $this->logger)) {
                $this->logger->exception('backup error', $e);
            }
        }

        if (isset( $this->logger)) {
            $this->logger->close();
        }

        return array(
            'status' => $this->zewm_info->get_status(),
            'backup_key' => $this->zewm_info->get_backup_key(),
            'site_url' => $this->zewm_info->get_site_url(),
            'backup_url' => $this->zewm_info->get_backup_url(),
            'backup_type' => $this->zewm_info->get_backup_type(),
            'start_datetime' => $this->zewm_info->get_start_datetime(),
            'finish_datetime' => $this->zewm_info->get_finish_datetime()
        );
    }

    private function make_dir( $path)
    {
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $path;
        if ( ! file_exists( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
        }
        return $target_dir;
    }

    private function make_url( $path)
    {
        $upload_dir = wp_upload_dir();
        $target_url = $upload_dir['baseurl'] . DIRECTORY_SEPARATOR . $path;
        return $target_url;
    }


    public function backup_start()
    {
        $this->backup_dir_name = uniqid('bk_' . date('YmdHis') . '_');
        $this->backup_dir_path = $this->make_dir( $this->backup_dir_name);
        $log_file_path = $this->backup_dir_path . DIRECTORY_SEPARATOR . 'backup.log';
        $this->logger = new Zewm_Logger( $log_file_path);
        $this->logger->info('===========  start zewm-backup  ===========');

        Zewm_Server_Info::logging_info( $this->logger);

        $this->zewm_info->set_backup_dir_path( $this->backup_dir_path );
        $this->zewm_info->set_status( ZEWM_MIGRATION_STATUS_BACKUP_START );
        $this->zewm_info->update();
    }

    public function dump_database( $prefix)
    {
        $this->logger->info('===========  start dump database  ===========');

        $mysql_backup = new Zewm_Mysql_Query_Backup( $this->backup_dir_path, $this->logger, $this->zewm_info );

        $this->zewm_info->set_status( ZEWM_MIGRATION_STATUS_BACKUP_DUMP_DB );
        $this->zewm_info->set_backup_key(uniqid());
        $this->zewm_info->set_backup_db_file_name( $mysql_backup->file_name );
        $this->zewm_info->set_start_datetime(date( DATE_ATOM, time() ));
        $this->zewm_info->update();

        $mysql_backup->create_dump( $prefix );

        $this->logger->info('===========  complete dump database  ===========');
        return $mysql_backup;
    }

    public function compress_data( $mysql_backup)
    {
        $this->logger->info('===========  start compress files  ===========');
        $this->zewm_info->set_backup_url( $this->make_url($this->backup_dir_name) );

        if ( !extension_loaded('zip') ) {
            // compress type tar
            $this->logger->info('backup type is tar.');
            $this->zewm_info->set_backup_type('phar');
            $this->backup_file_name = uniqid() . '.tar';
            $this->logger->info($this->backup_file_name);
            $this->zewm_info->set_backup_file_name( $this->backup_file_name );
            $this->zewm_info->set_status( ZEWM_MIGRATION_STATUS_BACKUP_COMPRESS_TAR );
            $this->zewm_info->update();

            $tar = new Zewm_Tar_Compress( $this->zewm_info->get_backup_file_path(), $this->zewm_info, $this->logger );

            $tar->add_file( $mysql_backup->file_path, $mysql_backup->file_name );
            $backup_info = $this->zewm_info->to_array();
            unset( $backup_info['backup_key'] );
            $tar->add_from_string( 'backup.json', json_encode( $backup_info, JSON_FORCE_OBJECT ) );
            $tar->add_wp_content_dir();
            $tar->close();

        } else {
            // compress type zip
            $this->logger->info('backup type is zip.');
            $this->zewm_info->set_backup_type('zip');
            $this->backup_file_name = uniqid() . '.zip';
            $this->logger->info($this->backup_file_name);
            $this->zewm_info->set_backup_file_name( $this->backup_file_name );
            $this->zewm_info->set_status( ZEWM_MIGRATION_STATUS_BACKUP_COMPRESS_ZIP );
            $this->zewm_info->update();
            $zip = new Zewm_Zip_Compress( $this->zewm_info->get_backup_file_path(), $this->zewm_info, $this->logger );

            $zip->add_file( $mysql_backup->file_path, $mysql_backup->file_name );
            $backup_info = $this->zewm_info->to_array();
            unset( $backup_info['backup_key'] );
            $zip->add_from_string( 'backup.json', json_encode( $backup_info, JSON_FORCE_OBJECT ) );
            $zip->add_wp_content_dir();
            $zip->close();

        }

        $mysql_backup->file_delete();

        $dh = opendir($this->backup_dir_path);
        while (false !== ($filename = readdir($dh))) {
            if ( $filename == '.' || $filename == '..'  ) continue;
            $this->logger->info('display backup directory: ' . $filename);
        }
        $this->logger->info('===========  complete compress files  ===========');
    }


    public function complete_backup()
    {
        $this->zewm_info->set_status( ZEWM_MIGRATION_STATUS_BACKUP_COMPLETE );
        $this->zewm_info->set_backup_url( $this->make_url($this->backup_dir_name) . '/' . $this->backup_file_name );
        $this->zewm_info->set_site_url( get_site_url() );
        $this->zewm_info->set_finish_datetime( date( DATE_ATOM, time() ) );
        $this->zewm_info->update();
        $this->logger->info('===========  complete zewm-backup  ===========');
    }
}
