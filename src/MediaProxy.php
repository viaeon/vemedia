<?php

namespace Vemedia;

use Vemedia\MediaHandler;
use Vemedia\Utils;

class MediaProxy
{
    private static $proxySlug = 'vemedia-proxy';

    public static function init()
    {
        add_filter('query_vars', [__CLASS__, 'registerQueryVar']);
        add_action('parse_request', [__CLASS__, 'handleProxyRequest'], 1);
        add_action('init', [__CLASS__, 'registerRewriteRules']);
        add_action('admin_init', [__CLASS__, 'ensureRewriteRules']);
        add_action('admin_init', [__CLASS__, 'ensureHtaccess']);

        add_action('wp_ajax_vemedia_proxy', [__CLASS__, 'ajaxProxy']);
        add_action('wp_ajax_nopriv_vemedia_proxy', [__CLASS__, 'ajaxProxy']);
    }

    public static function registerQueryVar($vars)
    {
        $vars[] = 'vemedia_proxy';
        return $vars;
    }

    public static function registerRewriteRules()
    {
        add_rewrite_rule(
            '^' . self::$proxySlug . '/(.+)$',
            'index.php?vemedia_proxy=$matches[1]',
            'top'
        );
    }

    public static function ensureRewriteRules()
    {
        $rules = get_option('rewrite_rules');
        $ruleKey = '^' . self::$proxySlug . '/(.+)$';

        if (!isset($rules[$ruleKey])) {
            flush_rewrite_rules();
            Utils::writeLog('已刷新 rewrite rules，添加 vemedia-proxy 端点');
        }
    }

    public static function handleProxyRequest($wp)
    {
        $proxyPath = isset($wp->query_vars['vemedia_proxy']) ? $wp->query_vars['vemedia_proxy'] : '';

        if (empty($proxyPath)) {
            if (isset($_GET['vemedia_proxy']) && !empty($_GET['vemedia_proxy'])) {
                $proxyPath = sanitize_text_field($_GET['vemedia_proxy']);
            }
        }

        if (empty($proxyPath)) {
            return;
        }

        $proxyPath = urldecode($proxyPath);

        if (preg_match('/\.(php|phtml|php\d)$/i', $proxyPath)) {
            status_header(403);
            echo 'Forbidden';
            exit;
        }

        if (!self::isConfigured()) {
            status_header(503);
            echo 'Storage not configured';
            exit;
        }

        self::doProxy($proxyPath);
    }

    public static function getProxyUrl($relativePath)
    {
        return admin_url('admin-ajax.php?action=vemedia_proxy&file=' . urlencode($relativePath));
    }

    public static function isCloudAttachment($post_id)
    {
        return (bool) get_post_meta($post_id, '_vemedia_uploaded', true);
    }

    public static function ajaxProxy()
    {
        $proxyPath = isset($_GET['file']) ? sanitize_text_field($_GET['file']) : '';

        if (empty($proxyPath)) {
            status_header(400);
            echo 'Missing file parameter';
            exit;
        }

        $proxyPath = urldecode($proxyPath);

        if (preg_match('/\.(php|phtml|php\d)$/i', $proxyPath)) {
            status_header(403);
            echo 'Forbidden';
            exit;
        }

        if (!self::isConfigured()) {
            status_header(503);
            echo 'Storage not configured';
            exit;
        }

        self::doProxy($proxyPath);
    }

    private static function doProxy($relativePath)
    {
        Utils::writeLog('代理请求: ' . $relativePath);

        $storageClass = self::getStorageClass();
        if (!$storageClass) {
            Utils::writeLog('无法获取存储类');
            status_header(500);
            echo 'Storage not available';
            exit;
        }

        $result = $storageClass::download($relativePath);

        if (empty($result['status'])) {
            Utils::writeLog('代理下载失败: ' . ($result['message'] ?? '未知错误') . ', key=' . $relativePath);
            status_header(404);
            echo 'File not found';
            exit;
        }

        $mimeType = self::guessMimeType($relativePath);
        $cloudHeaders = $result['headers'] ?? [];
        $httpCode = $result['http_code'] ?? 200;
        $data = $result['data'];

        $cacheTime = 86400 * 30;

        if ($httpCode === 206) {
            header('HTTP/1.1 206 Partial Content');
        } else {
            header('HTTP/1.1 200 OK');
        }

        if ($mimeType || isset($cloudHeaders['content-type'])) {
            header('Content-Type: ' . ($mimeType ?: $cloudHeaders['content-type']));
        }
        if (isset($cloudHeaders['content-length'])) {
            header('Content-Length: ' . $cloudHeaders['content-length']);
        } else {
            header('Content-Length: ' . strlen($data));
        }
        if (isset($cloudHeaders['content-range'])) {
            header('Content-Range: ' . $cloudHeaders['content-range']);
        }
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=' . $cacheTime);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
        if (isset($cloudHeaders['etag'])) {
            header('ETag: ' . $cloudHeaders['etag']);
        }
        if (isset($cloudHeaders['last-modified'])) {
            header('Last-Modified: ' . $cloudHeaders['last-modified']);
        }

        echo $data;
        exit;
    }

    private static function isConfigured()
    {
        $storageType = MediaHandler::config('storage_type');
        if ($storageType === 's3') {
            return !empty(MediaHandler::config('s3_endpoint'))
                && !empty(MediaHandler::config('s3_access_key'))
                && !empty(MediaHandler::config('s3_secret_key'))
                && !empty(MediaHandler::config('s3_bucket'));
        } elseif ($storageType === 'webdav') {
            return !empty(MediaHandler::config('webdav_endpoint'));
        } elseif ($storageType === 'ftp') {
            return !empty(MediaHandler::config('ftp_host'));
        }
        return false;
    }

    private static function getStorageClass()
    {
        $storageType = MediaHandler::config('storage_type');
        $map = [
            's3' => S3Storage::class,
            'webdav' => WebDAVStorage::class,
            'ftp' => FTPStorage::class,
        ];
        return $map[$storageType] ?? null;
    }

    private static function guessMimeType($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'bmp'  => 'image/bmp',
            'ico'  => 'image/x-icon',
            'pdf'  => 'application/pdf',
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'ogg'  => 'audio/ogg',
            'mp4'  => 'video/mp4',
            'avi'  => 'video/x-msvideo',
            'mov'  => 'video/quicktime',
            'wmv'  => 'video/x-ms-wmv',
            'flv'  => 'video/x-flv',
            'mkv'  => 'video/x-matroska',
            'webm' => 'video/webm',
            'txt'  => 'text/plain',
            'html' => 'text/html',
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'json' => 'application/json',
            'xml'  => 'application/xml',
            'zip'  => 'application/zip',
            'rar'  => 'application/x-rar-compressed',
            '7z'   => 'application/x-7z-compressed',
            'tar'  => 'application/x-tar',
            'gz'   => 'application/gzip',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'exe'  => 'application/x-msdownload',
            'apk'  => 'application/vnd.android.package-archive',
        ];

        return $types[$ext] ?? null;
    }

    public static function ensureHtaccess()
    {
        if (MediaHandler::config('switch') !== 'enable') {
            return;
        }

        $uploadDir = wp_upload_dir();
        $basedir = $uploadDir['basedir'];

        if (empty($basedir) || !is_dir($basedir)) {
            return;
        }

        $htaccessPath = $basedir . '/.htaccess';
        $marker = '# VEMEDIA_PROXY_START';
        $markerEnd = '# VEMEDIA_PROXY_END';

        $siteUrl = site_url('/', 'relative');
        $siteUrl = rtrim($siteUrl, '/') . '/';

        $rules = <<<APACHE
$marker
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ {$siteUrl}index.php?vemedia_proxy=1 [L,QSA]
</IfModule>
$markerEnd
APACHE;

        if (file_exists($htaccessPath)) {
            $content = file_get_contents($htaccessPath);
            if (strpos($content, $marker) !== false) {
                return;
            }
            $content .= "\n" . $rules;
            @file_put_contents($htaccessPath, $content);
        } else {
            @file_put_contents($htaccessPath, $rules);
        }
    }

    public static function removeHtaccess()
    {
        $uploadDir = wp_upload_dir();
        $basedir = $uploadDir['basedir'];

        if (empty($basedir)) {
            return;
        }

        $htaccessPath = $basedir . '/.htaccess';
        if (!file_exists($htaccessPath)) {
            return;
        }

        $content = file_get_contents($htaccessPath);
        $marker = '# VEMEDIA_PROXY_START';
        $markerEnd = '# VEMEDIA_PROXY_END';

        if (strpos($content, $marker) === false) {
            return;
        }

        $pattern = '/' . preg_quote($marker, '/') . '.*?' . preg_quote($markerEnd, '/') . '/s';
        $content = preg_replace($pattern, '', $content);
        $content = trim($content);

        if (empty($content)) {
            @unlink($htaccessPath);
        } else {
            @file_put_contents($htaccessPath, $content);
        }
    }
}
