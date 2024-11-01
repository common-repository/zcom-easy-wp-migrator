<?php

class Zewm_Mysql_Query_Backup
{

    private $logger;
    private $dump_file;
    public $file_name;
    public $file_path;
    private $zewm_info;
    private $replace_data_target;

    public function __construct( $dir_path, $logger, $zewm_info )
    {
        $this->logger = $logger;
        $this->file_name = uniqid() . '.sql';
        $this->file_path = $dir_path . DIRECTORY_SEPARATOR . $this->file_name;
        $this->zewm_info = $zewm_info;

        $this->replace_data_target = array(
            array("table" => "options", "column" => "option_name", "prefix_empty_target_value" => array("user_roles")),
            array("table" => "usermeta", "column" => "meta_key", "prefix_empty_target_value" => array("capabilities", "user_level")),
        );
    }

    public function __destruct()
    {
        if (isset( $this->dump_file )) {
            @fclose( $this->dump_file );
        }
    }

    public function file_delete()
    {
        @unlink( $this->file_path );
    }

    private function write_line( $string )
    {
        fwrite( $this->dump_file, $string . "\n" );
    }

    private function get_select_column( $column_name, $column_structures )
    {
        $column_type = $column_structures['Type'];
        if ( strpos( $column_type, 'blob') !== false ) {
            return "HEX(`{$column_name}`) as `{$column_name}`";
        }
        return "`{$column_name}`";
    }

    private function replace_value( $wpdb, $table_name, $column_name, $value, $prefix ) {
        foreach ( $this->replace_data_target as $target ) {
            if ( $table_name === "{$wpdb->prefix}{$target["table"]}" && $column_name === $target["column"] ) {
                if ( empty( $wpdb->prefix ) && !in_array($value, $target["prefix_empty_target_value"] ) ) continue;
                return preg_replace("/^{$wpdb->prefix}/", $prefix, $value );
            }
        }
        return $value;
    }

    private function get_insert_value( $wpdb, $table_name, $column_name, $value, $table_structures, $prefix )
    {
        if ( $value === null ) {
            return "null";
        }
        $value = $this->replace_value($wpdb, $table_name, $column_name, $value, $prefix);
        $structure = $table_structures[$table_name][$column_name]['Type'];
        if ( strpos( $structure, 'int' ) !== false
            || strpos( $structure, 'float' ) !== false
            || strpos( $structure, 'decimal' ) !== false
            || strpos( $structure, 'double' ) !== false
            || strpos( $structure, 'bool' ) !== false ) {
            return $value;
        } elseif ( strpos( $structure, 'blob' ) !== false ) {
            return 'UNHEX("' . $value . '")';
        }

        $esc_value = esc_sql( $value );
        if ( method_exists( $wpdb, 'remove_placeholder_escape' ) ) {
            $esc_value = $wpdb->remove_placeholder_escape( $esc_value );
        }
        return '"' . $esc_value . '"';
    }

    private function get_table_name( $wpdb, $prefix, $table_name )
    {
        if (empty( $prefix )) {
            return $table_name;
        }
        return preg_replace("/^{$wpdb->prefix}/", $prefix, $table_name );
    }

    private function change_create_table_name( $wpdb, $prefix, $create_query )
    {
        if (empty( $prefix )) {
            return $create_query;
        }
        return preg_replace("/CREATE TABLE `{$wpdb->prefix}/", "CREATE TABLE `{$prefix}", $create_query );
    }

    private function create_insert_table($wpdb, $table, $prefix, $table_structures)
    {
        $row_count = $wpdb->get_row("SELECT COUNT(1) FROM {$table}", ARRAY_N);
        if ( $row_count[0] == 0) {
            return;
        }
        $query_table_name = $this->get_table_name( $wpdb, $prefix, $table );

        $columns = array();
        foreach ( $table_structures[$table] as $column_name => $column_structure ) {
            array_push( $columns, $this->get_select_column( $column_name, $column_structure));
        }

        $this->write_line("/*!40000 ALTER TABLE `{$query_table_name}` DISABLE KEYS */;");

        $roop_continue = true;
        $offset = 0;
        while ( $roop_continue ) {
            $roop_continue = $this->create_insert(
                $table,
                $query_table_name,
                $columns,
                $table_structures,
                ZEWM_BACKUP_BULK_INSERT_LIMIT,
                $offset,
                $prefix
            );
            $offset += ZEWM_BACKUP_BULK_INSERT_LIMIT;
        }

        $this->write_line("/*!40000 ALTER TABLE `{$query_table_name}` ENABLE KEYS */;");
    }

    public function create_dump( $prefix )
    {
        $this->dump_file = fopen( $this->file_path, 'c' );

        try {
            global $wpdb;

            if ( empty( $prefix ) === false ) {
                $this->logger->info("change prefix from {$wpdb->prefix} to {$prefix}");
            }

            $this->write_line("/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;");
            $this->write_line("/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;");
            $this->write_line("/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;");
            $this->write_line("/*!40101 SET NAMES utf8 */;");
            $this->write_line("/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;");
            $this->write_line("/*!40103 SET TIME_ZONE='+00:00' */;");
            $this->write_line("/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;");
            $this->write_line("/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;");
            $this->write_line("/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;");
            $this->write_line("/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;");

            $tables = array();
            $table_structures = array();
            $this->logger->info('***** tables *****');
            $table_list_query = "SELECT table_name, table_rows, table_type FROM INFORMATION_SCHEMA.TABLES WHERE table_type = 'BASE TABLE'";
            foreach ( $wpdb->get_results( $table_list_query, ARRAY_A ) as $table ) {
                $table_name = $table['table_name'];
                $table_rows = $table['table_rows'];
                $this->logger->info( "{$table_name}({$table_rows} rows)" );

                if ( $wpdb->prefix != "wp_"
                    && preg_match("/^wp_/", $table_name)
                    && !preg_match("/^{$wpdb->prefix}/", $table_name)) {
                    // 「wp_」プレックス以外の時に「wp_」プレックスのテーブルが存在する場合は移行から除外
                    $this->logger->info( "skip:{$table_name}" );
                    continue;
                }

                $query_table_name = $this->get_table_name( $wpdb, $prefix, $table_name );
                array_push( $tables, $table_name );
                $this->write_line("DROP TABLE IF EXISTS `{$query_table_name}`;");
            }

            // create table
            foreach ( $tables as $table )
            {
                $create_table = $wpdb->get_row("SHOW CREATE TABLE {$table}", ARRAY_A);
                if (empty( $create_table)) {
                    continue;
                }
                $create_query = $create_table["Create Table"];
                $this->write_line( $this->change_create_table_name( $wpdb, $prefix, $create_query) . ";" );

                $structures = $wpdb->get_results("SHOW COLUMNS IN {$table}", ARRAY_A);
                foreach ( $structures as $structure) {
                    $table_structures[$table][$structure['Field']] = $structure;
                }
            }

            $priority_tables = array();
            $normal_tables = array();
            // classify table
            foreach ( $tables as $table )
            {
                if (strpos( $table, 'options') !== false
                    || strpos( $table, 'users') !== false
                    || strpos( $table, 'usermeta') !== false) {
                    array_push( $priority_tables, $table );
                } else {
                    array_push( $normal_tables, $table );
                }
            }

            // insert
            $this->logger->info( '---------- priority table insert----------' );
            foreach ( $priority_tables as $priority_table )
            {
                $this->logger->info( 'table data create: ' . $priority_table );
                $this->create_insert_table( $wpdb, $priority_table, $prefix, $table_structures );
            }
            $this->logger->info( '---------- normal table insert----------' );
            foreach ( $normal_tables as $normal_table )
            {
                $this->logger->info( 'table data create: ' . $normal_table );
                $this->create_insert_table( $wpdb, $normal_table, $prefix, $table_structures );
            }

            $this->write_line("/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;");
            $this->write_line("/*!40014 SET FOREIGN_KEY_CHECKS=IF(@OLD_FOREIGN_KEY_CHECKS IS NULL, 1, @OLD_FOREIGN_KEY_CHECKS) */;");
            $this->write_line("/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;");
        } catch (Exception $e) {
            $this->logger->exception("create_dump error", $e);
        }
        @fclose( $this->dump_file );
    }

    private function create_insert( $table, $query_table_name, $columns, $table_structures, $limit, $offset, $prefix )
    {
        global $wpdb;

        $insert_query = "INSERT INTO `{$query_table_name}` VALUES \n";
        $insert_row = array();
        $select_query = "SELECT " . implode(',', $columns ) . " FROM {$table} LIMIT ${offset},${limit}";
        $results = $wpdb->get_results( $select_query , ARRAY_A );
        if ( count( $results ) === 0 ) {
            return false;
        }
        foreach ( $results  as $table_row ) {
            $insert_values = array();
            foreach ( $table_row as $column_name => $value) {
                array_push( $insert_values, $this->get_insert_value( $wpdb, $table, $column_name, $value, $table_structures, $prefix ) );
            }
            array_push( $insert_row, '('. implode(',', array_values( $insert_values)) . ')');

            if ( $this->zewm_info->check_force_stop()) {
                $this->logger->warning('database dump force stop');
                throw new Exception('force stop');
            }
        }
        $insert_query .= implode(",\n", $insert_row) . ';';
        $this->write_line( $insert_query );
        return true;
    }
}


class Zewm_Mysql_Query_Restore
{
    private $logger;

    public function __construct( $logger )
    {
        $this->logger = $logger;
    }

    private function execute_query( $sql )
    {
        global $wpdb;
        $result = $wpdb->query( $sql );
        if ( $result === false) {
            $this->logger->warning('execute query failed. : ' . substr( $sql, 0, 200) . '...' );
        }
    }

    private function check_first_query( $buffer )
    {
        return strncmp($buffer, '/*!', strlen('/*!')) === 0
            || strncmp($buffer, 'DROP TABLE', strlen('DROP TABLE')) === 0
            || strncmp($buffer, 'CREATE TABLE', strlen('CREATE TABLE')) === 0
            || strncmp($buffer, 'INSERT INTO', strlen('INSERT INTO')) === 0;
    }

    public function restore( $sql_file_path )
    {
        $handle = @fopen( $sql_file_path, "r" );
        try {
            $sql = '';
            while (( $buffer = fgets( $handle, 4096)) !== false ) {
                $buf = preg_replace('/\n|\r|\r\n/', '', $buffer );
                if ( $this->check_first_query($buf) === false ) {
                    $sql .= $buf;
                    continue;
                }
                $this->execute_query( $sql );
                $sql = $buf;
            }
            $this->execute_query( $sql );
            if ( !feof( $handle ) ) {
                throw new Exception('unexpected fgets');
            }
        } catch (Exception $e) {
            $this->logger->exception("restore error", $e);
        }
        @fclose( $handle );
    }

    public function update_site_url($site_url)
    {
        if ( empty($site_url) ) {
            $this->logger->info('no change site url');
            return;
        }
        $this->logger->info('change to ' . $site_url);
        global $wpdb;
        $sql = $wpdb->prepare(
            "UPDATE $wpdb->options SET option_value = %s WHERE option_name = 'siteurl' OR option_name = 'home'",
            $site_url
        );
        $wpdb->query($sql);
    }
}