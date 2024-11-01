<?php

class Zewm_Zip
{
    protected $zip;
    protected $zewm_info;
    protected $logger;

    public function __construct( $zip_file, $zewm_info, $logger, $is_create )
    {
        $this->zip_file = $zip_file;
        $this->open( $zip_file, $is_create);
        $this->zewm_info = $zewm_info;
        $this->logger = $logger;
    }

    public function __destruct()
    {
        if (isset( $this->zip )) {
            $this->zip->close();
        }
    }

    public function open( $zip_file, $is_create )
    {
        $this->zip = new ZipArchive();
        if ( $is_create === true ) {
            $zip_status = $this->zip->open( $zip_file, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE );
        } else {
            $zip_status = $this->zip->open( $zip_file );
        }
        if ( $zip_status !== true ) {
            throw new Exception( 'fail to open zip file.' );
        }
    }

    public function re_open()
    {
        $this->zip = new ZipArchive();
        $zip_status = $this->zip->open( $this->zip_file );
        if ( $zip_status !== true ) {
            throw new Exception( 'fail to open zip file.' );
        }
    }

    public function close()
    {
        if ( isset( $this->zip ) ) {
            $this->zip->close();
        }
        $this->zip = null;
    }
}


class Zewm_Zip_Compress extends Zewm_Zip
{
    public function __construct( $zip_file, $zewm_info, $logger )
    {
        parent::__construct( $zip_file, $zewm_info, $logger, true );
    }

    public function add_file( $file_path, $file_name )
    {
        $result = $this->zip->addFile( $file_path, $file_name );
        $this->logger->info('add file ' . $file_name);
        if ( $result === false ) {
            $this->logger->warning('fail to add file ' . $file_name);
        }
    }

    public function add_from_string( $file_name, $content )
    {
        $this->zip->addFromString( $file_name, $content );
    }

    public function add_wp_content_dir()
    {
        $file_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(WP_CONTENT_DIR),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $file_count = 0;
        foreach ( $file_iterator as $file_path => $file_info )
        {
            if ( $file_info->isDir() ) {
                continue;
            } elseif (strpos( $file_path, '/plugins/mu-plugins') !== false ) {
                // exclude mu-plugins itself
                continue;
            } elseif (strpos( $file_path, '/plugins/zcom-easy-wp-migrator') !== false ) {
                // exclude migration plugin itself
                continue;
            } elseif (strpos( $file_path, '/plugins/jetpack') !== false ) {
                // exclude jetpack plugin
                continue;
            } elseif (strpos( $file_path, '/plugins/all-in-one-wp-migration') !== false ) {
                // exclude All-in-One WP Migration plugin
                continue;
            } elseif (strpos( $file_path, '/uploads/backwpup-') !== false ) {
                // exclude backwpup plugin backup directory
                continue;
            } elseif (strpos( $file_path, '/uploads/bk_') !== false ) {
                // exclude migration backup directory
                continue;
            } elseif (strpos( $file_path, '/wp-content/updraft/') !== false ) {
                // exclude UpdraftPlus plugin backup directory
                continue;
            } elseif (strpos( $file_path, '/wp-content/cache/') !== false ) {
                // exclude cache directory
                continue;
            }

            if ( $this->zewm_info->check_force_stop() ) {
                $this->logger->warning( 'zip compress force stop' );
                return;
            }
	        $encoding_type = mb_detect_encoding($file_path);
	        $this->logger->info( $encoding_type . ' : ' . $encoding_type );
            $zip_file_path = 'wp-content' . DIRECTORY_SEPARATOR . str_replace(WP_CONTENT_DIR . DIRECTORY_SEPARATOR, '', $file_path);
            $this->zip->addFile( $file_path, $zip_file_path );
            $file_count++;
        }
        $this->logger->info( 'additional files ' . $file_count );
    }
}


class Zewm_Zip_Extract extends Zewm_Zip
{
    public function __construct( $zip_file, $zewm_info, $logger )
    {
        parent::__construct( $zip_file, $zewm_info, $logger, false );
    }

    private function extract_to_wp_content( $entries )
    {
        $destination =  preg_replace( "/[\/|\\\\]wp-content/", '', WP_CONTENT_DIR );
        $result = $this->zip->extractTo( $destination, $entries );
        if ( $result === false) {
            $this->logger->warning('fail to extract zip file.');
            foreach ( $entries as $entry) {
                $this->logger->warning( $entry );
            }

        }
    }

    public function extract_to( $destination, $entries )
    {
        if ( is_array( $entries) === false ) {
            $entries = array( $entries );
        }
        $this->zip->extractTo( $destination, $entries );
    }

    public function extract_and_get_content( $destination, $entry )
    {
        $this->extract_to( $destination, array( $entry ));
        return file_get_contents( $destination . DIRECTORY_SEPARATOR . $entry );
    }

    public function extract_wp_content_dir( $sql_file )
    {
        $entries = array();
        $bulk_extract_count = 5;
        for( $i = 0; $i < $this->zip->numFiles; $i++) {
            $file_name = $this->zip->getNameIndex($i);
            if ( $file_name === $sql_file ) {
                // exclude sql dump file
                continue;
            } elseif (strpos( $file_name, 'mu-plugins') !== false ) {
                // exclude mu-plugins itself
                continue;
            } elseif (strpos( $file_name, 'zcom-easy-wp-migrator') !== false ) {
                // exclude migration plugin itself
                continue;
            } elseif ( $file_name === 'backup.json' ) {
                // exclude backup.json
                continue;
            }
            array_push( $entries, $file_name );
            if ( count( $entries ) === $bulk_extract_count ) {
                if ( $this->zewm_info->check_force_stop() ) {
                    $this->logger->warning('zip compress force stop');
                    return;
                }
                $this->extract_to_wp_content( $entries );
                $entries = array();
            }
        }
        if ( count( $entries) !== 0 ) {
            $this->extract_to_wp_content( $entries );
        }
    }
}