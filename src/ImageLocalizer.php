<?php

namespace Vemedia;

use Vemedia\StorageInterface;
use Vemedia\MediaHandler;
use Vemedia\Utils;

class ImageLocalizer
{
    private static $processed = false;

    public static function init()
    {
        add_filter('wp_insert_post_data', [__CLASS__, 'processPostContent'], 10, 2);
        add_filter('content_save_pre', [__CLASS__, 'processContentSave'], 99);
    }

    public static function processContentSave($content)
    {
        if (self::$processed) {
            return $content;
        }
        self::$processed = true;

        if (empty($content)) {
            return $content;
        }

        if (MediaHandler::config('switch') !== 'enable') {
            return $content;
        }

        // 检查图片本地化开关
        if (MediaHandler::config('localize_images') !== 'yes') {
            return $content;
        }

        $content = self::processBase64Images($content);
        $content = self::processExternalImages($content);

        return $content;
    }

    public static function processPostContent($data, $postarr)
    {
        if (!isset($data['post_content']) || empty($data['post_content'])) {
            return $data;
        }

        if (MediaHandler::config('switch') !== 'enable') {
            return $data;
        }

        // 检查图片本地化开关
        if (MediaHandler::config('localize_images') !== 'yes') {
            return $data;
        }

        if (wp_is_post_revision($postarr['ID'] ?? 0)) {
            return $data;
        }

        $data['post_content'] = self::processBase64Images($data['post_content']);
        $data['post_content'] = self::processExternalImages($data['post_content']);

        return $data;
    }

    private static function processBase64Images($content)
    {
        $pattern = '/<img[^>]+src=["\']data:image\/(png|jpeg|jpg|gif|webp|bmp|svg\+xml);base64,([^"\']+)["\'][^>]*>/i';
        
        if (!preg_match($pattern, $content)) {
            return $content;
        }

        Utils::writeLog('发现 base64 编码图片，开始本地化处理');

        $content = preg_replace_callback($pattern, function ($matches) {
            $imageType = strtolower($matches[1]);
            $base64Data = $matches[2];

            $extMap = [
                'png' => 'png',
                'jpeg' => 'jpg',
                'jpg' => 'jpg',
                'gif' => 'gif',
                'webp' => 'webp',
                'bmp' => 'bmp',
                'svg+xml' => 'svg',
            ];

            $ext = $extMap[$imageType] ?? 'png';
            $mimeType = 'image/' . ($imageType === 'svg+xml' ? 'svg+xml' : $imageType);

            $imageData = base64_decode($base64Data);
            if ($imageData === false) {
                Utils::writeLog('base64 解码失败');
                return $matches[0];
            }

            $upload = self::saveToMediaLibrary($imageData, $ext, $mimeType);
            if ($upload) {
                Utils::writeLog('base64 图片本地化成功: ' . $upload['url']);
                return self::replaceImgSrc($matches[0], $upload['url']);
            }

            return $matches[0];
        }, $content);

        return $content;
    }

    private static function processExternalImages($content)
    {
        $siteUrl = site_url();
        $siteHost = parse_url($siteUrl, PHP_URL_HOST);

        $pattern = '/<img[^>]+src=["\'](https?:\/\/([^"\']+))["\'][^>]*>/i';
        
        if (!preg_match($pattern, $content)) {
            return $content;
        }

        Utils::writeLog('发现外链图片，开始本地化处理');

        $content = preg_replace_callback($pattern, function ($matches) use ($siteHost) {
            $fullUrl = $matches[1];
            $urlHost = parse_url($fullUrl, PHP_URL_HOST);

            if ($urlHost === $siteHost) {
                return $matches[0];
            }

            $homeUrl = home_url();
            if (strpos($fullUrl, $homeUrl) === 0) {
                return $matches[0];
            }

            $upload = self::downloadAndSave($fullUrl);
            if ($upload) {
                Utils::writeLog('外链图片本地化成功: ' . $fullUrl . ' -> ' . $upload['url']);
                return self::replaceImgSrc($matches[0], $upload['url']);
            }

            return $matches[0];
        }, $content);

        return $content;
    }

    private static function replaceImgSrc($imgTag, $newUrl)
    {
        return preg_replace('/src=["\'][^"\']+["\']/', 'src="' . esc_url($newUrl) . '"', $imgTag, 1);
    }

    private static function downloadAndSave($url)
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        Utils::writeLog('下载外链图片: ' . $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 VeMedia Plugin');
        
        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($imageData)) {
            Utils::writeLog('下载外链图片失败: HTTP ' . $httpCode);
            return null;
        }

        $ext = self::getExtFromUrlOrMime($url, $contentType);
        $mimeType = self::getMimeTypeFromExt($ext);

        return self::saveToMediaLibrary($imageData, $ext, $mimeType);
    }

    private static function saveToMediaLibrary($imageData, $ext, $mimeType)
    {
        $uploadDir = wp_upload_dir();
        if (!empty($uploadDir['error'])) {
            Utils::writeLog('获取上传目录失败: ' . $uploadDir['error']);
            return null;
        }

        $filename = date('YmdHis') . substr(md5(uniqid(mt_rand(), true)), 0, 8) . '.' . $ext;
        $savePath = $uploadDir['path'] . '/' . $filename;

        $saved = file_put_contents($savePath, $imageData);
        if ($saved === false) {
            Utils::writeLog('保存临时文件失败: ' . $savePath);
            return null;
        }

        $attachment = [
            'guid' => $uploadDir['url'] . '/' . $filename,
            'post_mime_type' => $mimeType,
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit'
        ];

        $attachId = wp_insert_attachment($attachment, $savePath);
        if (is_wp_error($attachId)) {
            Utils::writeLog('创建附件失败: ' . $attachId->get_error_message());
            @unlink($savePath);
            return null;
        }

        if (file_exists(ABSPATH . 'wp-admin/includes/image.php')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachData = wp_generate_attachment_metadata($attachId, $savePath);
        wp_update_attachment_metadata($attachId, $attachData);

        $url = wp_get_attachment_url($attachId);

        return [
            'id' => $attachId,
            'url' => $url,
            'file' => $filename
        ];
    }

    private static function getExtFromUrlOrMime($url, $contentType)
    {
        $path = parse_url($url, PHP_URL_PATH);
        if ($path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $validExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico'];
            if (in_array($ext, $validExts)) {
                return $ext;
            }
        }

        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/svg+xml' => 'svg',
        ];

        if ($contentType && isset($mimeMap[$contentType])) {
            return $mimeMap[$contentType];
        }

        return 'jpg';
    }

    private static function getMimeTypeFromExt($ext)
    {
        $map = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
        ];

        return $map[$ext] ?? 'image/jpeg';
    }
}
