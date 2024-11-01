<?php

class Zewm_File_Utils
{
    public static function delete_dir( $target_dir )
    {
        if ( file_exists($target_dir) === false ) {
            return true;
        }
        foreach(glob("{$target_dir}/*", GLOB_MARK) as $file){
            if (is_dir($file)) {
                $remove_status = self::delete_dir($file);
            } else {
                $remove_status = unlink($file);
            }
            if ( ! $remove_status ) {
                return false;
            }
        }
        return rmdir($target_dir);
    }

    public static function list_dir( $base_dir )
    {
        $dirs = array();
        foreach(glob($base_dir, GLOB_ONLYDIR) as $dir){
            array_push($dirs, $dir);
        }
        return $dirs;
    }

    public static function file_download( $file_path, $data_url )
    {

        $headers = array(
            'Accept'          => '*/*',
            'Accept-Encoding' => '*',
            'Accept-Charset'  => '*',
            'Accept-Language' => '*',
            'User-Agent'      => 'Mozilla/5.0',
        );

        $curlopt_headers = array();
        foreach ( $headers as $key => $value ) {
            $curlopt_headers[] = "{$key}: {$value}";
        }

        $ch = curl_init();
        $file = fopen($file_path, 'w');
        curl_setopt($ch, CURLOPT_URL, $data_url);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FILE, $file);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlopt_headers);
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        $response = @curl_exec($ch);
        $errorno = curl_errno($ch);
        curl_close($ch);
        fclose($file);

        if ($response === false) {
            return false;
        }

        if ($errorno == 18) {
            return self::file_download_for_wget( $file_path, $data_url);

        }

        return true;
    }

    public static function file_download_for_wget( $file_path, $data_url)
    {
        $cmd = "wget -O {$file_path} {$data_url} > /dev/null 2>&1";
        passthru($cmd, $ret);
        if ($ret == 1) {
            return false;
        }
        return true;
    }
}
