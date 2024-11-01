<?php

class Zewm_Backup_Action_Remove extends Zewm_Migration_Action
{
    public static $action_key = 'backup_remove';

    public function do_action()
    {
        $info = new Zewm_Backup_Info();
        $backup_db_file_path = $info->get_backup_db_file_path();
        $backup_file_path = $info->get_backup_file_path();
        $backup_dir_path = $info->get_backup_dir_path();

        if ( ! is_null( $backup_db_file_path ) ) {
            @fclose( $backup_db_file_path );
        }
        if ( ! is_null( $backup_file_path ) ) {
            @fclose( $backup_file_path );
        }
        // delete current dir
        if ( ! is_null( $backup_dir_path ) ) {
            $remove_status = Zewm_File_Utils::delete_dir( $backup_dir_path );
            if ( ! $remove_status ) {
                $info->set_force_stop(true);
                $info->update();
                Zewm_Migration_Response::create_error_response( 'fail to remove backup dir.', 400 );
            }
        }
        // delete previous dir
        $upload_dir = wp_upload_dir();
        foreach (Zewm_File_Utils::list_dir($upload_dir['basedir'] . 'bk_*') as $previous_dir) {
            Zewm_File_Utils::delete_dir( $previous_dir );
        }

        $info->delete();

        return array(
            'status' => ZEWM_MIGRATION_STATUS_NO_DATA
        );
    }
}
