<?php

abstract class Zewm_Migration_Action
{
    public static $action_key = null;

    public static function get_action_info() {
        return array(
            'action_key' => static::$action_key,
            'class_name' => get_called_class()
        );
    }
    abstract public function do_action();
}
