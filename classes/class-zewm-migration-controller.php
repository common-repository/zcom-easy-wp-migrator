<?php

class Zewm_Migration_Controller
{
    private $actions = array();

    public static function instance()
    {
        static $self = false;
        if ( ! $self ) {
            $self = new Zewm_Migration_Controller();
            array_push( $self->actions, Zewm_Migration_Action_Wp_Info::get_action_info() );
            array_push( $self->actions, Zewm_Backup_Action_Info::get_action_info() );
            array_push( $self->actions, Zewm_Backup_Action_Backup::get_action_info() );
            array_push( $self->actions, Zewm_Backup_Action_Remove::get_action_info() );
            array_push( $self->actions, Zewm_Backup_Action_Log::get_action_info() );
            array_push( $self->actions, Zewm_Restore_Action_Restore::get_action_info() );
            array_push( $self->actions, Zewm_Restore_Action_Remove::get_action_info() );
            array_push( $self->actions, Zewm_Restore_Action_Info::get_action_info() );
            array_push( $self->actions, Zewm_Restore_Action_Log::get_action_info() );
        }
        add_action( 'wp_ajax_zewm_migration', array( $self, 'execute' ) );
        return $self;
    }

    public function execute()
    {
        if ( ! current_user_can( 'administrator' ) ) {
            return;
        }
        if ( ! isset( $_GET['zewm_migration_actions'] ) ) {
            return;
        }
        $execute_actions = $_GET['zewm_migration_actions'];
        if ( empty( $execute_actions ) ) {
            Zewm_Migration_Response::create_error_response( 'unauthorized', 401 );
        }

        if ( ! is_admin() ) {
            Zewm_Migration_Response::create_error_response( 'unauthorized', 401 );
        }
        foreach ( $this->actions as $action ) {
            if ( $action['action_key'] === $execute_actions ) {
                ini_set('memory_limit', '1024M');
                @set_time_limit(0);
                $action_cls = new $action['class_name'];
                $action_response = $action_cls->do_action();
                Zewm_Migration_Response::create_response( $action_response );
            }
        }
    }
}
