<?php

class Zewm_Restore_Action_Log extends Zewm_Migration_Action
{
    public static $action_key = 'restore_log';

    public function do_action()
    {
        $info = new Zewm_Restore_Info();
        $log_data = null;
        if ( $info->get_restore_dir_path() !== null ) {
            $log_data = file_get_contents( $info->get_restore_dir_path() . DIRECTORY_SEPARATOR . 'restore.log' );
            if ( ! $log_data ) {
                $log_data = null;
            }
        }
        return array(
            'data' => $log_data
        );
    }
}
