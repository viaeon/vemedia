<?php

namespace Vemedia;

use Vemedia\Plugin;

class Utils extends Plugin
{

    public static function writeLog($message, $logFile_name = 'app.log')
    {
        try {
            $logDir = self::getLogDir();
            
            if (!is_dir($logDir)) {
                if (!wp_mkdir_p($logDir)) {
                    error_log("VeMedia: 无法创建日志目录: $logDir");
                    return;
                }
            }
            
            $logFile = $logDir . $logFile_name;
            
            if (!is_writable($logDir)) {
                @chmod($logDir, 0755);
            }
            
            date_default_timezone_set('Asia/Shanghai');
            $timestamp = date('Y-m-d H:i:s');
            
            if (is_array($message) || is_object($message)) {
                $message = print_r($message, true);
            }
            
            $logMessage = "[$timestamp] $message" . PHP_EOL;
            
            $result = @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
            
            if ($result === false) {
                error_log("VeMedia: 无法写入日志文件: $logFile");
            }
        } catch (\Exception $e) {
            error_log("VeMedia: 日志记录异常: " . $e->getMessage());
        }
    }

    public static function curl_get($url, $header = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $output = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => $error];
        }
        
        curl_close($ch);
        return json_decode($output, true);
    }

    public static function curl_post($url, $data, $header = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $output = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => $error];
        }
        
        curl_close($ch);
        return json_decode($output, true);
    }

    public static function curl_delete($url, $header = array(), $data = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $output = curl_exec($ch);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => $error];
        }
        
        curl_close($ch);
        return json_decode($output, true);
    }
}
