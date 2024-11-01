<?php

class Zewm_Tar
{
    protected $tar;
    protected $zewm_info;
    protected $logger;

    public function __construct( $tar_file, $zewm_info, $logger )
    {
        $this->tar_file = $tar_file;
        $this->open( $tar_file );
        $this->zewm_info = $zewm_info;
        $this->logger = $logger;
    }

    public function open( $tar_file )
    {
        try {
            $this->tar = new PharData( $tar_file, Phar::CURRENT_AS_FILEINFO | Phar::KEY_AS_FILENAME);
        } catch (UnexpectedValueException $e) {
            throw new Exception( 'fail to open tar file.' );
        }
    }

    public function close()
    {
        $this->tar = null;
    }
}


class Zewm_Tar_Compress extends Zewm_Tar
{
    public function __construct( $tar_file, $zewm_info, $logger )
    {
        parent::__construct( $tar_file, $zewm_info, $logger );
    }

    public function add_file( $file_path, $file_name )
    {
        $result = $this->tar->addFile( $file_path, $file_name );
        $this->logger->info('add file ' . $file_name);
        if ( $result === false ) {
            $this->logger->warning('fail to add file ' . $file_name);
        }
    }

    public function add_from_string( $file_name, $content )
    {
        $this->tar->addFromString( $file_name, $content );
    }

    public function add_wp_content_dir()
    {
        $file_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(WP_CONTENT_DIR),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $file_count = 0;
        $files = array();
        foreach ( $file_iterator as $file_path => $file_info )
        {
            if ( $file_info->isDir() ) {
                continue;
            } elseif ( strlen( $file_info->getFilename() ) > 100 ) {
                $this->logger->info('skip compression file : file name is 100 characters or more.');
                $this->logger->info($file_info->getFilename());
                continue;
            } elseif (strpos( $file_path, '/mu-plugins') !== false ) {
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
            } elseif (strpos( $file_path, '/wp-content/cache/') !== false ) {
                // exclude cache directory
                continue;
            }

            if ( $this->zewm_info->check_force_stop() ) {
                $this->logger->warning('tar compress force stop');
                return;
            }
            $tar_file_path = 'wp-content' . DIRECTORY_SEPARATOR . str_replace(WP_CONTENT_DIR . DIRECTORY_SEPARATOR, '', $file_path);
            // $this->tar->addFile( $file_path, $tar_file_path );
            $files[$tar_file_path] = $file_path;

            $file_count++;

            if ( $file_count % 100 === 0 ) {
                $this->tar->buildFromIterator(new ArrayIterator($files));
                $files = array();
            }

        }

        if ( count( $files ) !== 0 ) {
            $this->tar->buildFromIterator(new ArrayIterator($files));
        }

        $this->logger->info( 'additional files ' . $file_count );
    }
}


class Zewm_Tar_Extract extends Zewm_Tar
{
    public function __construct( $tar_file, $zewm_info, $logger )
    {
        parent::__construct( $tar_file, $zewm_info, $logger, false );
    }

    private function extract_to_wp_content( $entries = null )
    {
        $destination =  preg_replace( "/[\/|\\\\]wp-content/", '', WP_CONTENT_DIR );
        $result = $this->tar->extractTo( $destination, $entries, True );
        if ( $result === false) {
            $this->logger->warning('fail to extract tar file.');
        }
    }

    public function extract_to( $destination, $entries )
    {
        if ( is_array( $entries) === false ) {
            $entries = array( $entries );
        }
        $this->tar->extractTo( $destination, $entries );
    }

    public function extract_and_get_content( $destination, $entry )
    {
        $this->extract_to( $destination, array( $entry ));
        return file_get_contents( $destination . DIRECTORY_SEPARATOR . $entry );
    }

    public function extract_wp_content_dir( $sql_file )
    {
        $this->tar->delete( $sql_file );
        $this->tar->delete( 'backup.json' );
        $this->extract_to_wp_content();
    }
}