<?php

namespace Vemedia;

use Vemedia\StorageInterface;
use Vemedia\MediaHandler;
use Vemedia\Utils;

class S3Storage extends StorageInterface
{
    public static function getConfig()
    {
        return [
            'endpoint' => MediaHandler::config('s3_endpoint'),
            'access_key' => MediaHandler::config('s3_access_key'),
            'secret_key' => MediaHandler::config('s3_secret_key'),
            'bucket' => MediaHandler::config('s3_bucket'),
            'region' => MediaHandler::config('s3_region') ?: 'us-east-1',
            'path_style' => MediaHandler::config('s3_path_style') === 'yes',
            'custom_url' => MediaHandler::config('s3_custom_url'),
        ];
    }

    public static function upload($filepath, $cloudKey = null)
    {
        $config = self::getConfig();

        if (!file_exists($filepath)) {
            return ['status' => false, 'message' => '文件不存在'];
        }

        if ($cloudKey === null) {
            $cloudKey = self::getCloudKey($filepath);
        }

        $mimetype = self::getMimeType($filepath);
        $filedata = file_get_contents($filepath);

        Utils::writeLog('S3上传配置: ' . json_encode([
            'endpoint' => $config['endpoint'],
            'bucket' => $config['bucket'],
            'region' => $config['region'],
            'path_style' => $config['path_style'],
            'cloudKey' => $cloudKey,
            'mimetype' => $mimetype,
            'filesize' => strlen($filedata)
        ]));

        $date = gmdate('Ymd\THis\Z');
        $shortDate = gmdate('Ymd');

        $url = self::buildUrl($config, $cloudKey);
        $host = parse_url($url, PHP_URL_HOST);
        $path = self::buildCanonicalPath($config, $cloudKey);

        $canonicalRequest = self::createCanonicalRequest('PUT', $path, $host, $filedata);
        $stringToSign = self::createStringToSign($date, $shortDate, $config['region'], $canonicalRequest);
        $signature = self::calculateSignature($config['secret_key'], $shortDate, $config['region'], $stringToSign);

        $authorization = 'AWS4-HMAC-SHA256 ' .
            'Credential=' . $config['access_key'] . '/' . $shortDate . '/' . $config['region'] . '/s3/aws4_request, ' .
            'SignedHeaders=host;x-amz-content-sha256, ' .
            'Signature=' . $signature;

        $headers = [
            'Host: ' . $host,
            'Date: ' . $date,
            'Content-Type: ' . $mimetype,
            'Authorization: ' . $authorization,
            'x-amz-content-sha256: ' . hash('sha256', $filedata)
        ];

        Utils::writeLog('S3请求URL: ' . $url);
        Utils::writeLog('S3签名路径: ' . $path);
        Utils::writeLog('S3请求头: ' . json_encode($headers));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $filedata);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        Utils::writeLog('S3响应码: ' . $httpCode);
        Utils::writeLog('S3响应: ' . $response);

        if ($httpCode >= 200 && $httpCode < 300) {
            $publicUrl = self::getPublicUrl($config, $cloudKey);
            return [
                'status' => true,
                'data' => [
                    'url' => $publicUrl,
                    'key' => $cloudKey,
                    'name' => basename($cloudKey),
                    'mimetype' => $mimetype,
                    'pathname' => $cloudKey
                ]
            ];
        }

        return [
            'status' => false,
            'message' => '上传失败: ' . ($error ?: "HTTP $httpCode"),
            'response' => $response
        ];
    }

    public static function delete($key)
    {
        $config = self::getConfig();

        $date = gmdate('Ymd\THis\Z');
        $shortDate = gmdate('Ymd');

        $url = self::buildUrl($config, $key);
        $host = parse_url($url, PHP_URL_HOST);
        $path = self::buildCanonicalPath($config, $key);

        $canonicalRequest = self::createCanonicalRequest('DELETE', $path, $host, '');
        $stringToSign = self::createStringToSign($date, $shortDate, $config['region'], $canonicalRequest);
        $signature = self::calculateSignature($config['secret_key'], $shortDate, $config['region'], $stringToSign);

        $authorization = 'AWS4-HMAC-SHA256 ' .
            'Credential=' . $config['access_key'] . '/' . $shortDate . '/' . $config['region'] . '/s3/aws4_request, ' .
            'SignedHeaders=host;x-amz-content-sha256, ' .
            'Signature=' . $signature;

        $headers = [
            'Host: ' . $host,
            'Date: ' . $date,
            'Authorization: ' . $authorization,
            'x-amz-content-sha256: ' . hash('sha256', '')
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    public static function testConnection()
    {
        $config = self::getConfig();

        if (empty($config['endpoint']) || empty($config['access_key']) ||
            empty($config['secret_key']) || empty($config['bucket'])) {
            return ['status' => false, 'message' => '请填写完整的S3配置信息'];
        }

        $date = gmdate('Ymd\THis\Z');
        $shortDate = gmdate('Ymd');

        $url = self::buildUrl($config, '');
        $host = parse_url($url, PHP_URL_HOST);
        $path = self::buildCanonicalPath($config, '');

        $canonicalRequest = self::createCanonicalRequest('GET', $path, $host, '');
        $stringToSign = self::createStringToSign($date, $shortDate, $config['region'], $canonicalRequest);
        $signature = self::calculateSignature($config['secret_key'], $shortDate, $config['region'], $stringToSign);

        $authorization = 'AWS4-HMAC-SHA256 ' .
            'Credential=' . $config['access_key'] . '/' . $shortDate . '/' . $config['region'] . '/s3/aws4_request, ' .
            'SignedHeaders=host;x-amz-content-sha256, ' .
            'Signature=' . $signature;

        $headers = [
            'Host: ' . $host,
            'Date: ' . $date,
            'Authorization: ' . $authorization,
            'x-amz-content-sha256: ' . hash('sha256', '')
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['status' => true, 'message' => 'S3连接成功'];
        }

        return ['status' => false, 'message' => 'S3连接失败: ' . ($error ?: "HTTP $httpCode")];
    }

    public static function getCloudUrl($key)
    {
        $config = self::getConfig();
        return self::getPublicUrl($config, $key);
    }

    public static function download($key)
    {
        $config = self::getConfig();

        $date = gmdate('Ymd\THis\Z');
        $shortDate = gmdate('Ymd');

        $url = self::buildUrl($config, $key);
        $host = parse_url($url, PHP_URL_HOST);
        $path = self::buildCanonicalPath($config, $key);

        $rangeHeader = '';
        if (isset($_SERVER['HTTP_RANGE'])) {
            $rangeHeader = $_SERVER['HTTP_RANGE'];
        }

        $canonicalRequest = self::createCanonicalRequest('GET', $path, $host, '', $rangeHeader);
        $stringToSign = self::createStringToSign($date, $shortDate, $config['region'], $canonicalRequest);
        $signature = self::calculateSignature($config['secret_key'], $shortDate, $config['region'], $stringToSign);

        $signedHeaders = 'host;x-amz-content-sha256';
        if ($rangeHeader) {
            $signedHeaders = 'host;range;x-amz-content-sha256';
        }

        $authorization = 'AWS4-HMAC-SHA256 ' .
            'Credential=' . $config['access_key'] . '/' . $shortDate . '/' . $config['region'] . '/s3/aws4_request, ' .
            'SignedHeaders=' . $signedHeaders . ', ' .
            'Signature=' . $signature;

        $headers = [
            'Host: ' . $host,
            'Date: ' . $date,
            'Authorization: ' . $authorization,
            'x-amz-content-sha256: ' . hash('sha256', '')
        ];

        if ($rangeHeader) {
            $headers[] = 'Range: ' . $rangeHeader;
        }

        $responseHeaders = [];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $len = strlen($header);
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $key = strtolower(trim($parts[0]));
                $value = trim($parts[1]);
                if (in_array($key, ['content-type', 'content-length', 'content-range', 'cache-control', 'etag', 'last-modified', 'accept-ranges'])) {
                    $responseHeaders[$key] = $value;
                }
            }
            return $len;
        });

        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'status' => true,
                'data' => $data,
                'http_code' => $httpCode,
                'headers' => $responseHeaders
            ];
        }

        return [
            'status' => false,
            'http_code' => $httpCode,
            'message' => $error ?: "HTTP $httpCode"
        ];
    }

    private static function createCanonicalRequest($method, $path, $host, $body, $rangeHeader = '')
    {
        $hashedPayload = hash('sha256', $body);

        $canonicalHeaders = "host:$host\n";
        $signedHeaderList = ['host'];

        if ($rangeHeader) {
            $canonicalHeaders .= "range:$rangeHeader\n";
            $signedHeaderList[] = 'range';
        }

        $canonicalHeaders .= "x-amz-content-sha256:$hashedPayload\n";
        $signedHeaderList[] = 'x-amz-content-sha256';

        $signedHeaders = implode(';', $signedHeaderList);

        $canonicalRequest = $method . "\n" .
               $path . "\n" .
               "\n" .
               $canonicalHeaders . "\n" .
               $signedHeaders . "\n" .
               $hashedPayload;

        Utils::writeLog('Canonical Request: ' . str_replace("\n", "\\n", $canonicalRequest));

        return $canonicalRequest;
    }

    private static function createStringToSign($date, $shortDate, $region, $canonicalRequest)
    {
        $credentialScope = $shortDate . '/' . $region . '/s3/aws4_request';
        $hashedCanonicalRequest = hash('sha256', $canonicalRequest);

        $stringToSign = "AWS4-HMAC-SHA256\n" .
               $date . "\n" .
               $credentialScope . "\n" .
               $hashedCanonicalRequest;

        Utils::writeLog('String to Sign: ' . str_replace("\n", "\\n", $stringToSign));

        return $stringToSign;
    }

    private static function calculateSignature($secretKey, $shortDate, $region, $stringToSign)
    {
        $kSecret = 'AWS4' . $secretKey;
        $kDate = hash_hmac('sha256', $shortDate, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        Utils::writeLog('Signature: ' . $signature);

        return $signature;
    }

    public static function buildPublicUrl($config, $key)
    {
        return self::buildUrl($config, $key);
    }

    private static function buildUrl($config, $key)
    {
        $endpoint = rtrim($config['endpoint'], '/');

        if ($config['path_style']) {
            return $endpoint . '/' . $config['bucket'] . '/' . $key;
        } else {
            $parsed = parse_url($endpoint);
            $host = $parsed['host'];
            $scheme = $parsed['scheme'] ?? 'https';
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            return $scheme . '://' . $config['bucket'] . '.' . $host . $port . '/' . $key;
        }
    }

    private static function buildCanonicalPath($config, $key)
    {
        if ($config['path_style']) {
            return '/' . $config['bucket'] . '/' . $key;
        } else {
            return '/' . $key;
        }
    }

    private static function getPublicUrl($config, $key)
    {
        if (!empty($config['custom_url'])) {
            return rtrim($config['custom_url'], '/') . '/' . $key;
        }
        return self::buildUrl($config, $key);
    }
}
