<?php

abstract class Zewm_Info
{
    protected $status;
    protected $backup_url;
    protected $backup_key;
    protected $backup_type;
    protected $start_datetime;
    protected $finish_datetime;
    protected $force_stop = false;

    public function __construct()
    {
        $this->reload();
    }

    abstract public function get_wp_option_key();
    abstract public function to_array();
    abstract protected function set_reload_data( $data);

    public function reload()
    {
        $serialized_data = get_option( $this->get_wp_option_key(), null );
        if ( $serialized_data != null ) {
            $s = unserialize( $serialized_data );
            $this->set_reload_data( $s );
        }
    }

    public function update()
    {
        $s = serialize( $this );
        update_option( $this->get_wp_option_key(), $s, '', 'no' );
    }

    public function delete()
    {
        delete_option( $this->get_wp_option_key() );
        $this->status = null;
        $this->backup_type = null;
        $this->backup_key = null;
        $this->backup_url = null;
        $this->start_datetime = null;
        $this->finish_datetime = null;
        $this->force_stop = false;
    }

    public function check_force_stop()
    {
        if ( date('s') !== '00' ) {
            return false;
        }
        $this->update();
        return $this->force_stop;
    }

    public function get_status()
    {
        if ( ! isset( $this->status) || is_null( $this->status) ) {
            return ZEWM_MIGRATION_STATUS_NO_DATA;
        }
        return $this->status;
    }

    public function set_status( $status )
    {
        $this->status = $status;
        return $this;
    }

    public function get_backup_type()
    {
        return $this->backup_type;
    }

    public function set_backup_type( $backup_type )
    {
        $this->backup_type = $backup_type;
        return $this;
    }

    public function get_backup_key()
    {
        return $this->backup_key;
    }

    public function set_backup_key( $backup_key )
    {
        $this->backup_key = $backup_key;
        return $this;
    }

    public function get_backup_url()
    {
        return $this->backup_url;
    }

    public function set_backup_url( $backup_url )
    {
        $this->backup_url = $backup_url;
        return $this;
    }

    public function get_start_datetime()
    {
        return $this->start_datetime;
    }

    public function set_start_datetime( $start_datetime )
    {
        $this->start_datetime = $start_datetime;
        return $this;
    }

    public function get_finish_datetime()
    {
        return $this->finish_datetime;
    }

    public function set_finish_datetime( $finish_datetime )
    {
        $this->finish_datetime = $finish_datetime;
        return $this;
    }

    public function is_force_stop()
    {
        return $this->force_stop;
    }

    public function set_force_stop( $force_stop )
    {
        $this->force_stop = $force_stop;
        return $this;
    }
}


class Zewm_Backup_Info extends Zewm_Info
{
    private $site_url;
    private $backup_dir_path;
    private $backup_file_name;
    private $backup_db_file_name;

    public function __construct()
    {
        parent::__construct();
    }

    public function get_wp_option_key()
    {
        return ZEWM_BACKUP_INFO_WP_OPTION_KEY;
    }

    protected function set_reload_data( $data)
    {
        if ( $data instanceof Zewm_Backup_Info ) {
            $this->status = $data->get_status();
            $this->backup_key = $data->get_backup_key();
            $this->site_url = $data->get_site_url();
            $this->backup_url = $data->get_backup_url();
            $this->backup_dir_path = $data->get_backup_dir_path();
            $this->backup_type = $data->get_backup_type();
            $this->backup_file_name = $data->get_backup_file_name();
            $this->backup_db_file_name = $data->get_backup_db_file_name();
            $this->start_datetime = $data->get_start_datetime();
            $this->finish_datetime = $data->get_finish_datetime();
            $this->force_stop = $data->is_force_stop();
        }
    }

    public function to_array()
    {
        return array(
            'status' => $this->get_status(),
            'backup_key' => $this->get_backup_key(),
            'site_url' => $this->get_site_url(),
            'backup_url'=> $this->get_backup_url(),
            'backup_dir_path' => $this->get_backup_dir_path(),
            'backup_type' => $this->get_backup_type(),
            'backup_file_name' => $this->get_backup_file_name(),
            'backup_db_file_name' => $this->get_backup_db_file_name(),
            'start_datetime' => $this->get_start_datetime(),
            'finish_datetime' => $this->get_finish_datetime(),
            'force_stop' => $this->is_force_stop()
        );
    }

    public function delete()
    {
        parent::delete();
        $this->site_url = null;
        $this->backup_dir_path = null;
        $this->backup_file_name = null;
        $this->backup_db_file_name = null;
    }

    public function get_backup_file_path()
    {
        return $this->backup_dir_path . DIRECTORY_SEPARATOR . $this->backup_file_name;
    }

    public function get_backup_db_file_path()
    {
        return $this->backup_dir_path . DIRECTORY_SEPARATOR . $this->backup_db_file_name;
    }

    public function get_site_url()
    {
        return $this->site_url;
    }

    public function set_site_url( $site_url )
    {
        $this->site_url = $site_url;
        return $this;
    }

    public function get_backup_dir_path()
    {
        return $this->backup_dir_path;
    }

    public function set_backup_dir_path( $backup_dir_path )
    {
        $this->backup_dir_path = $backup_dir_path;
        return $this;
    }

    public function get_backup_file_name()
    {
        return $this->backup_file_name;
    }

    public function set_backup_file_name( $backup_file_name )
    {
        $this->backup_file_name = $backup_file_name;
        return $this;
    }

    public function get_backup_db_file_name()
    {
        return $this->backup_db_file_name;
    }

    public function set_backup_db_file_name( $backup_db_file_name )
    {
        $this->backup_db_file_name = $backup_db_file_name;
        return $this;
    }
}


class Zewm_Restore_Info extends Zewm_Info
{
    private $restore_dir_name;
    private $restore_dir_path;

    public function __construct()
    {
        parent::__construct();
    }

    protected function set_reload_data( $data )
    {
        if ( $data instanceof Zewm_Restore_Info) {
            $this->status = $data->get_status();
            $this->backup_key = $data->get_backup_key();
            $this->backup_url = $data->get_backup_url();
            $this->start_datetime = $data->get_start_datetime();
            $this->finish_datetime = $data->get_finish_datetime();
            $this->restore_dir_name = $data->get_restore_dir_name();
            $this->restore_dir_path = $data->get_restore_dir_path();
            $this->force_stop = $data->is_force_stop();
        }
    }

    public function to_array()
    {
        return array(
            'status' => $this->get_status(),
            'backup_key' => $this->get_backup_key(),
            'backup_url'=> $this->get_backup_url(),
            'restore_dir_name' => $this->get_restore_dir_name(),
            'restore_dir_path' => $this->get_restore_dir_path(),
            'start_datetime' => $this->get_start_datetime(),
            'finish_datetime' => $this->get_finish_datetime(),
            'force_stop' => $this->is_force_stop()
        );
    }

    public function delete()
    {
        parent::delete();
        $this->restore_dir_path = null;
    }

    public function get_wp_option_key()
    {
        return ZEWM_RESTORE_INFO_WP_OPTION_KEY;
    }


    public function get_restore_dir_name()
    {
        return $this->restore_dir_name;
    }

    public function set_restore_dir_name( $restore_dir_name )
    {
        $this->restore_dir_name = $restore_dir_name;
        return $this;
    }

    public function get_restore_dir_path()
    {
        return $this->restore_dir_path;
    }
    public function set_restore_dir_path( $restore_dir_path )
    {
        $this->restore_dir_path = $restore_dir_path;
        return $this;
    }
}