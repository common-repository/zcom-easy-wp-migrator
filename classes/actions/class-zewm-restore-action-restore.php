<?php

class Zewm_Restore_Action_Restore extends Zewm_Migration_Action
{
    public static $action_key = 'restore';

    private $logger;
    private $backup_file_url;
    private $backup_key;
    private $site_url;
    private $restore_dir_name;
    private $restore_dir_path;
    private $restore_file_path;
    private $zewm_info;

    public function do_action()
    {
        $this->zewm_info = new Zewm_Restore_Info();
        $current_status = $this->zewm_info->get_status();
        if ( $current_status !== ZEWM_MIGRATION_STATUS_NO_DATA
            && $current_status !== ZEWM_MIGRATION_STATUS_RESTORE_COMPLETE ) {
            Zewm_Migration_Response::create_error_response( 'restore is already in progress.', 400 );
        }
        if ( $current_status === ZEWM_MIGRATION_STATUS_RESTORE_COMPLETE ) {
            // remove previous data
            Zewm_File_Utils::delete_dir( $this->zewm_info->get_restore_dir_path() );
        }
        $this->zewm_info->delete();

        // get parameter
        if ( ! isset( $_GET['backup_file'] ) || $_GET['backup_file'] === '' ) {
            Zewm_Migration_Response::create_error_response( 'parameter backup_file is missing.', 400 );
        }
        if ( ! isset( $_GET['backup_key'] ) || $_GET['backup_key'] === '' ) {
            Zewm_Migration_Response::create_error_response( 'parameter backup_key is missing.', 400 );
        }
        if ( ! isset( $_GET['site_url'] ) || $_GET['site_url'] === '' ) {
            $this->site_url = null;
        } else {
            $this->site_url = $_GET['site_url'];
        }

        $this->backup_file_url = $_GET['backup_file'];
        $this->backup_key = $_GET['backup_key'];

        try {
            $this->restore_start();
            $this->download_file();

            $ext = substr($this->restore_file_path, strrpos($this->restore_file_path, '.') + 1);

            if ( $ext == 'zip' ) {
                $compress = new Zewm_Zip_Extract( $this->restore_file_path, $this->zewm_info, $this->logger );
            }  else {
                $compress = new Zewm_Tar_Extract($this->restore_file_path, $this->zewm_info, $this->logger);
            }

            $backup_json = $compress->extract_and_get_content( $this->restore_dir_path, 'backup.json' );
            $backup_json = json_decode( $backup_json, true );
            foreach ($backup_json as $backup_data) {
                $this->logger->info('backup data:' . $backup_data);
            }
            $backup_type = $backup_json['backup_type'];
            $sql_file = $backup_json['backup_db_file_name'];

            $compress->extract_to( $this->restore_dir_path, $sql_file );
            $this->extract_data( $compress, $backup_type, $sql_file );
            $this->import_database( $sql_file );
            $compress->close();
            $this->complete_restore();
        } catch (Exception $e) {
            if (isset( $this->logger )) {
                $this->logger->exception( 'restore error', $e );
            }
        }

        if (isset( $this->logger )) {
            $this->logger->close();
        }

        return array(
            'status' => $this->zewm_info->get_status(),
            'restore_dir_name' => $this->zewm_info->get_restore_dir_name(),
            'start_datetime' => $this->zewm_info->get_start_datetime(),
            'finish_datetime' => $this->zewm_info->get_finish_datetime()
        );
    }

    private function make_dir( $path )
    {
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $path;
        if ( ! file_exists( $target_dir ) ) {
            wp_mkdir_p( $target_dir );
        }
        return $target_dir;
    }

    public function restore_start()
    {
        $this->restore_dir_name = uniqid('rs_' . date('YmdHis') . '_');
        $this->restore_dir_path = $this->make_dir( $this->restore_dir_name );
        $log_file_path = $this->restore_dir_path . DIRECTORY_SEPARATOR . 'restore.log';
        $this->logger = new Zewm_Logger( $log_file_path );
        $this->logger->info('===========  start zewm-restore  ===========');

        Zewm_Server_Info::logging_info( $this->logger );

        $this->zewm_info->set_restore_dir_name( $this->restore_dir_name );
        $this->zewm_info->set_restore_dir_path( $this->restore_dir_path );
        $this->zewm_info->set_status(ZEWM_MIGRATION_STATUS_RESTORE_START );
        $this->zewm_info->set_start_datetime( date( DATE_ATOM, time() ) );
        $this->zewm_info->update();
    }

    public function download_file()
    {
        $this->logger->info('===========  start download file  ===========');
        $this->zewm_info->set_status(ZEWM_MIGRATION_STATUS_RESTORE_DOWNLOAD_FILE);
        $this->zewm_info->update();

        $exploded_backup_url = explode('/', $this->backup_file_url);
        $file_name = end( $exploded_backup_url);
        $this->restore_file_path = $this->restore_dir_path . DIRECTORY_SEPARATOR . $file_name;
        $success = Zewm_File_Utils::file_download( $this->restore_file_path, $this->backup_file_url );
        if ( $success === false) {
            $this->logger->info('backup file cannot download');

            Zewm_File_Utils::delete_dir( $this->restore_dir_path );
            $this->zewm_info->delete();

            Zewm_Migration_Response::create_error_response( 'backup file cannot download.', 400 );

        }
        $this->logger->info('filesize : ' . filesize($this->restore_file_path) . ' bytes');
        $this->logger->info('===========  complete download file  ===========');
    }

    public function import_database( $sql_file )
    {
        $this->logger->info('===========  start import database  ===========');
        $this->zewm_info->set_status( ZEWM_MIGRATION_STATUS_RESTORE_DB );
        $this->zewm_info->update();

        $sql_file_path = $this->restore_dir_path . DIRECTORY_SEPARATOR . $sql_file;
        $mysql_restore = new Zewm_Mysql_Query_Restore( $this->logger );
        $mysql_restore->restore( $sql_file_path );
        $mysql_restore->update_site_url( $this->site_url );

        // re-set info
        $new_info = new Zewm_Restore_Info();
        $new_info->set_status($this->zewm_info->get_status());
        $new_info->set_force_stop($this->zewm_info->is_force_stop());
        $new_info->set_backup_url($this->zewm_info->get_backup_url());
        $new_info->set_backup_key($this->zewm_info->get_backup_key());
        $new_info->set_start_datetime($this->zewm_info->get_start_datetime());
        $new_info->set_restore_dir_path($this->zewm_info->get_restore_dir_path());
        $new_info->update();
        $this->zewm_info = $new_info;

        $this->logger->info('===========  complete import database  ===========');
    }

    public function extract_data( $compress, $backup_type, $sql_file )
    {
        $this->logger->info('===========  start extract files  ===========');
        if ( $backup_type == 'zip' ) {
            $this->zewm_info->set_status( ZEWM_MIGRATION_STATUS_RESTORE_EXTRACT_ZIP );
        } else {
            $this->zewm_info->set_status( ZEWM_MIGRATION_STATUS_RESTORE_EXTRACT_TAR );
        }
        $this->zewm_info->update();

        $compress->extract_wp_content_dir( $sql_file );

        $this->logger->info('===========  complete extract files  ===========');
    }

    public function complete_restore()
    {
        $this->zewm_info->set_status( ZEWM_MIGRATION_STATUS_RESTORE_COMPLETE );
        $this->zewm_info->set_finish_datetime( date( DATE_ATOM, time() ) );
        $this->zewm_info->update();
        $this->logger->info('===========  complete zewm-restore  ===========');
    }
}
