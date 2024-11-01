<?php

class Zewm_Restore_Action_Info extends Zewm_Migration_Action
{
    public static $action_key = 'restore_info';

    public function do_action()
    {
        $info = new Zewm_Restore_Info();
        return array(
            'status' => $info->get_status(),
            'restore_dir_name' => $info->get_restore_dir_name(),
            'start_datetime' => $info->get_start_datetime(),
            'finish_datetime' => $info->get_finish_datetime()
        );
    }
}
