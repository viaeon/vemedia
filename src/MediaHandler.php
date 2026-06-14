<?php

namespace Vemedia;

use Vemedia\Plugin;
use Vemedia\Utils;
use Vemedia\S3Storage;
use Vemedia\WebDAVStorage;
use Vemedia\FTPStorage;

class MediaHandler extends Plugin
{
    private static $configInstance = null;

    private static function getConfigInstance()
    {
        if (self::$configInstance === null) {
            self::$configInstance = new self();
        }
        return self::$configInstance;
    }

    public static function config($key)
    {
        $instance = self::getConfigInstance();
        switch ($key) {
            case 'switch':
                return $instance->storage_switch;
            case 'storage_type':
                return $instance->storage_type;
            case 's3_endpoint':
                return $instance->s3_endpoint;
            case 's3_access_key':
                return $instance->s3_access_key;
            case 's3_secret_key':
                return $instance->s3_secret_key;
            case 's3_bucket':
                return $instance->s3_bucket;
            case 's3_region':
                return $instance->s3_region;
            case 's3_path_style':
                return $instance->s3_path_style;
            case 's3_custom_url':
                return $instance->s3_custom_url;
            case 'webdav_endpoint':
                return $instance->webdav_endpoint;
            case 'webdav_username':
                return $instance->webdav_username;
            case 'webdav_password':
                return $instance->webdav_password;
            case 'webdav_path':
                return $instance->webdav_path;
            case 'webdav_custom_url':
                return $instance->webdav_custom_url;
            case 'ftp_host':
                return $instance->ftp_host;
            case 'ftp_port':
                return $instance->ftp_port;
            case 'ftp_username':
                return $instance->ftp_username;
            case 'ftp_password':
                return $instance->ftp_password;
            case 'ftp_path':
                return $instance->ftp_path;
            case 'ftp_passive':
                return $instance->ftp_passive;
            case 'ftp_ssl':
                return $instance->ftp_ssl;
            case 'ftp_custom_url':
                return $instance->ftp_custom_url;
            // 功能开关
            case 'localize_images':
                return $instance->localize_images;
            case 'image_compress':
                return $instance->image_compress;
            case 'image_compress_quality':
                return $instance->image_compress_quality;
            case 'image_watermark':
                return $instance->image_watermark;
            case 'watermark_type':
                return $instance->watermark_type;
            case 'watermark_text':
                return $instance->watermark_text;
            case 'watermark_position':
                return $instance->watermark_position;
            case 'watermark_opacity':
                return $instance->watermark_opacity;
            case 'watermark_image':
                return $instance->watermark_image;
            case 'keep_original':
                return $instance->keep_original;
            // 视频处理
            case 'video_compress':
                return $instance->video_compress;
            case 'video_compress_quality':
                return $instance->video_compress_quality;
            case 'video_max_resolution':
                return $instance->video_max_resolution;
            case 'video_watermark':
                return $instance->video_watermark;
            default:
                return null;
        }
    }

    private static function isConfigured()
    {
        $storageType = self::config('storage_type');

        if ($storageType === 's3') {
            return !empty(self::config('s3_endpoint'))
                && !empty(self::config('s3_access_key'))
                && !empty(self::config('s3_secret_key'))
                && !empty(self::config('s3_bucket'));
        } elseif ($storageType === 'webdav') {
            return !empty(self::config('webdav_endpoint'));
        } elseif ($storageType === 'ftp') {
            return !empty(self::config('ftp_host'));
        }

        return false;
    }

    public static function getStorageClass()
    {
        $storageType = self::config('storage_type');
        $map = [
            's3' => S3Storage::class,
            'webdav' => WebDAVStorage::class,
            'ftp' => FTPStorage::class,
        ];
        return $map[$storageType] ?? null;
    }

    private static function buildCloudKey($dir, $filename)
    {
        if ($dir === '.' || $dir === '') {
            return $filename;
        }
        return $dir . '/' . $filename;
    }

    private static function buildThumbFilepath($basedir, $dir, $filename)
    {
        if ($dir === '.' || $dir === '') {
            return $basedir . '/' . $filename;
        }
        return $basedir . '/' . $dir . '/' . $filename;
    }

    public static function menu()
    {
        return add_menu_page(
            "VeMedia 设置",
            "VeMedia 设置",
            'manage_options',
            "vemedia_settings",
            'vemedia_display',
            'dashicons-cloud-upload',
            100
        );
    }

    public static function plugin_settings_link($links)
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=vemedia_settings') . '">设置</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public static function add_attachment($post_id)
    {
        Utils::writeLog('add_attachment 被调用, post_id=' . $post_id);

        if (!self::isConfigured()) {
            Utils::writeLog('存储配置不完整，跳过');
            return;
        }

        update_post_meta($post_id, '_vemedia_uploaded', '1');
        Utils::writeLog('已标记为待上传，等待 generate_attachment_metadata 处理');
    }

    public static function generate_attachment_metadata($meta, $post_id, $context)
    {
        Utils::writeLog('generate_attachment_metadata 被调用, context=' . $context);

        if ($context === 'update') {
            return $meta;
        }

        if (!get_post_meta($post_id, '_vemedia_uploaded', true)) {
            return $meta;
        }

        $uploadDir = wp_upload_dir();

        if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
            $dir = dirname($meta['file']);
            $storageClass = self::getStorageClass();
            if (!$storageClass) {
                return $meta;
            }

            foreach ($meta['sizes'] as $size => $value) {
                if (empty($value['file'])) {
                    continue;
                }

                $filepath = self::buildThumbFilepath($uploadDir['basedir'], $dir, $value['file']);
                if (!file_exists($filepath)) {
                    Utils::writeLog('缩略图文件不存在: ' . $filepath);
                    continue;
                }

                $cloudKey = self::buildCloudKey($dir, $value['file']);
                Utils::writeLog('缩略图上传到云, cloudKey=' . $cloudKey);

                try {
                    $result = $storageClass::upload($filepath, $cloudKey);
                    if (!empty($result['status'])) {
                        Utils::writeLog('缩略图上传成功: ' . $cloudKey);
                    } else {
                        Utils::writeLog('缩略图上传失败: ' . json_encode($result));
                    }
                } catch (\Exception $e) {
                    Utils::writeLog('缩略图上传异常: ' . $e->getMessage());
                }
            }
        }

        Utils::writeLog('检查主文件, meta[file]=' . ($meta['file'] ?? 'null'));

        // 判断是否需要检查保留原文件（仅在图片或视频水印/压缩启用时）
        $imageCompressEnabled = self::config('image_compress') === 'yes';
        $imageWatermarkEnabled = self::config('image_watermark') === 'yes';
        $videoCompressEnabled = self::config('video_compress') === 'yes';
        $videoWatermarkEnabled = self::config('video_watermark') === 'yes';
        $needCheckKeepOriginal = $imageCompressEnabled || $imageWatermarkEnabled || $videoCompressEnabled || $videoWatermarkEnabled;
        $keepOriginal = !$needCheckKeepOriginal || self::config('keep_original') !== 'no';

        if (!empty($meta['file'])) {
            $mainFile = $uploadDir['basedir'] . '/' . $meta['file'];
            Utils::writeLog('主文件路径: ' . $mainFile . ', 存在=' . (file_exists($mainFile) ? 'yes' : 'no') . ', keepOriginal=' . ($keepOriginal ? 'yes' : 'no'));

            if (file_exists($mainFile)) {
                if ($keepOriginal) {
                    // 上传原图
                    $mainCloudKey = $meta['file'];
                    Utils::writeLog('上传主文件(元数据中的file): ' . $mainCloudKey);

                    $storageClass = self::getStorageClass();
                    if ($storageClass) {
                        try {
                            $result = $storageClass::upload($mainFile, $mainCloudKey);
                            if (!empty($result['status'])) {
                                Utils::writeLog('主文件上传成功: ' . $mainCloudKey);
                                update_post_meta($post_id, '_vemedia_cloud_key', $mainCloudKey);
                            } else {
                                Utils::writeLog('主文件上传失败: ' . json_encode($result));
                            }
                        } catch (\Exception $e) {
                            Utils::writeLog('主文件上传异常: ' . $e->getMessage());
                        }
                    }
                } else {
                    // 不保留原图，使用最大尺寸的缩略图作为主图
                    Utils::writeLog('不保留原图模式，跳过主文件上传');
                    // 找到最大的缩略图作为 cloud_key
                    $maxSize = 0;
                    $maxSizeKey = '';
                    if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
                        foreach ($meta['sizes'] as $size => $value) {
                            $area = ($value['width'] ?? 0) * ($value['height'] ?? 0);
                            if ($area > $maxSize) {
                                $maxSize = $area;
                                $maxSizeKey = $value['file'] ?? '';
                            }
                        }
                    }
                    if ($maxSizeKey) {
                        $dir = dirname($meta['file']);
                        $cloudKey = self::buildCloudKey($dir, $maxSizeKey);
                        update_post_meta($post_id, '_vemedia_cloud_key', $cloudKey);
                        Utils::writeLog('使用最大缩略图作为主图: ' . $cloudKey);
                    }
                }
            } else {
                Utils::writeLog('主文件不存在，跳过上传');
            }
        } else {
            Utils::writeLog('meta[file] 为空，跳过主文件上传');
        }

        self::deleteLocalFiles($post_id, $meta);

        return $meta;
    }

    private static function deleteLocalFiles($post_id, $meta)
    {
        $uploadDir = wp_upload_dir();

        $originalFile = get_attached_file($post_id);
        if ($originalFile && file_exists($originalFile)) {
            @unlink($originalFile);
            Utils::writeLog('已删除本地原始文件: ' . $originalFile);
        }

        if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
            $dir = dirname($meta['file']);
            foreach ($meta['sizes'] as $size => $value) {
                if (empty($value['file'])) {
                    continue;
                }
                $filepath = self::buildThumbFilepath($uploadDir['basedir'], $dir, $value['file']);
                if (file_exists($filepath)) {
                    @unlink($filepath);
                    Utils::writeLog('已删除本地缩略图: ' . $filepath);
                }
            }
        }
    }

    public static function media_del_handle($post_id, $post)
    {
        $meta = wp_get_attachment_metadata($post_id);
        if (!$meta) {
            return;
        }

        $storageClass = self::getStorageClass();
        if (!$storageClass) {
            return;
        }

        $oldKey = $meta['key'] ?? '';
        if (!empty($oldKey)) {
            $storageClass::delete($oldKey);
            Utils::writeLog('已删除云端文件(旧格式): ' . $oldKey);
        }

        if (!empty($meta['file'])) {
            $cloudKey = $meta['file'];
            $storageClass::delete($cloudKey);
            Utils::writeLog('已删除云端原始文件: ' . $cloudKey);
        }

        if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
            $dir = dirname($meta['file']);
            foreach ($meta['sizes'] as $size => $value) {
                $sizeKey = $value['key'] ?? '';
                if (!empty($sizeKey)) {
                    $storageClass::delete($sizeKey);
                    Utils::writeLog('已删除云端缩略图(旧格式): ' . $sizeKey);
                }

                if (!empty($value['file'])) {
                    $cloudKey = self::buildCloudKey($dir, $value['file']);
                    $storageClass::delete($cloudKey);
                    Utils::writeLog('已删除云端缩略图: ' . $cloudKey);
                }
            }
        }

        delete_post_meta($post_id, '_vemedia_uploaded');
    }

    public static function attachment_editor($form_fields, $post)
    {
        $post_id = $post->ID;
        $uploaded = get_post_meta($post_id, '_vemedia_uploaded', true);
        $label = $uploaded ? esc_html__("已存储至云端", "vemedia") : esc_html__("一键替换", "vemedia");
        $disabled = $uploaded ? 'disabled' : '';
        $form_fields["upload-to-vemedia"] = [
            "label" => esc_html__("云端存储", "vemedia"),
            "input" => "html",
            "html" => '<script>var vemedia_js_flag="page";var vemedia_ajax_url="' . admin_url('admin-ajax.php') . '";var post_id="' . $post_id . '";</script>' . "<a class='button-secondary' id='vemedia-upload-one' href=\"javascript:;\" $disabled>" . $label . "</a>" . '<script src="' . plugins_url('../static/post.js', __FILE__) . '"></script>',
            "helps" => esc_html__("将媒体文件上传到云端存储并删除本地副本。", "vemedia")
        ];
        return $form_fields;
    }

    public static function update_to_cloud($post_id)
    {
        $meta = wp_get_attachment_metadata($post_id);
        if (!$meta) {
            Utils::writeLog('update_to_cloud: 无法获取附件元数据');
            return;
        }

        if (get_post_meta($post_id, '_vemedia_uploaded', true)) {
            Utils::writeLog('update_to_cloud: 文件已上传至云端');
            return;
        }

        $storageClass = self::getStorageClass();
        if (!$storageClass) {
            return;
        }

        $uploadDir = wp_upload_dir();

        $originalFile = get_attached_file($post_id);
        if ($originalFile && file_exists($originalFile)) {
            $cloudKey = StorageInterface::getCloudKey($originalFile);
            Utils::writeLog('一键替换: 上传原始文件, cloudKey=' . $cloudKey);
            $result = $storageClass::upload($originalFile, $cloudKey);
            if (!empty($result['status'])) {
                Utils::writeLog('一键替换: 原始文件上传成功');
                update_post_meta($post_id, '_vemedia_cloud_key', $cloudKey);
            } else {
                Utils::writeLog('一键替换: 原始文件上传失败: ' . json_encode($result));
                return;
            }
        }

        if (!empty($meta['sizes']) && is_array($meta['sizes'])) {
            $dir = dirname($meta['file']);
            foreach ($meta['sizes'] as $size => $value) {
                if (empty($value['file'])) {
                    continue;
                }
                $filepath = self::buildThumbFilepath($uploadDir['basedir'], $dir, $value['file']);
                if (file_exists($filepath)) {
                    $cloudKey = self::buildCloudKey($dir, $value['file']);
                    Utils::writeLog('一键替换: 上传缩略图, cloudKey=' . $cloudKey);
                    $storageClass::upload($filepath, $cloudKey);
                }
            }
        }

        update_post_meta($post_id, '_vemedia_uploaded', '1');
        self::deleteLocalFiles($post_id, $meta);
        Utils::writeLog('一键替换: 完成');
    }

    public static function replaced_one()
    {
        $post_id = intval($_POST['post_id']);
        self::update_to_cloud($post_id);
        wp_send_json_success(['message' => '上传完成']);
    }

    public static function test_storage_connection()
    {
        $storage_type = sanitize_text_field($_POST['storage_type']);
        $result = ['status' => false, 'message' => '未知存储类型'];

        $temp_setting = unserialize(get_option('vemedia_setting'));

        if ($storage_type === 's3') {
            $temp_setting['s3_endpoint'] = sanitize_text_field($_POST['s3_endpoint']);
            $temp_setting['s3_access_key'] = sanitize_text_field($_POST['s3_access_key']);
            $temp_setting['s3_secret_key'] = sanitize_text_field($_POST['s3_secret_key']);
            $temp_setting['s3_bucket'] = sanitize_text_field($_POST['s3_bucket']);
            $temp_setting['s3_region'] = sanitize_text_field($_POST['s3_region']);
            $temp_setting['s3_path_style'] = sanitize_text_field($_POST['s3_path_style']);
            update_option('vemedia_setting', serialize($temp_setting));
            self::$configInstance = null;
            $result = S3Storage::testConnection();
        } elseif ($storage_type === 'webdav') {
            $temp_setting['webdav_endpoint'] = sanitize_text_field($_POST['webdav_endpoint']);
            $temp_setting['webdav_username'] = sanitize_text_field($_POST['webdav_username']);
            $temp_setting['webdav_password'] = sanitize_text_field($_POST['webdav_password']);
            $temp_setting['webdav_path'] = sanitize_text_field($_POST['webdav_path']);
            update_option('vemedia_setting', serialize($temp_setting));
            self::$configInstance = null;
            $result = WebDAVStorage::testConnection();
        } elseif ($storage_type === 'ftp') {
            $temp_setting['ftp_host'] = sanitize_text_field($_POST['ftp_host']);
            $temp_setting['ftp_port'] = sanitize_text_field($_POST['ftp_port']);
            $temp_setting['ftp_username'] = sanitize_text_field($_POST['ftp_username']);
            $temp_setting['ftp_password'] = sanitize_text_field($_POST['ftp_password']);
            $temp_setting['ftp_path'] = sanitize_text_field($_POST['ftp_path']);
            $temp_setting['ftp_passive'] = sanitize_text_field($_POST['ftp_passive']);
            $temp_setting['ftp_ssl'] = sanitize_text_field($_POST['ftp_ssl']);
            update_option('vemedia_setting', serialize($temp_setting));
            self::$configInstance = null;
            $result = FTPStorage::testConnection();
        }

        if (ob_get_length()) ob_clean();
        echo json_encode($result);
        wp_die();
    }

    public static function filterAttachmentUrl($url, $post_id)
    {
        if (!MediaProxy::isCloudAttachment($post_id)) {
            return $url;
        }

        $cloudKey = get_post_meta($post_id, '_vemedia_cloud_key', true);
        if (empty($cloudKey)) {
            $filepath = get_attached_file($post_id);
            if ($filepath && file_exists($filepath)) {
                $cloudKey = StorageInterface::getCloudKey($filepath);
            } else {
                $meta = wp_get_attachment_metadata($post_id);
                $cloudKey = $meta['file'] ?? '';
            }
        }

        if (empty($cloudKey)) {
            return $url;
        }

        return MediaProxy::getProxyUrl($cloudKey);
    }

    public static function filterAttachmentImageSrc($image, $attachment_id, $size, $icon)
    {
        if (!$image) {
            return $image;
        }

        $isCloud = MediaProxy::isCloudAttachment($attachment_id);
        Utils::writeLog("filterAttachmentImageSrc called: post_id=$attachment_id, isCloud=$isCloud, size=" . (is_string($size) ? $size : 'unknown'));

        if (!$isCloud) {
            return $image;
        }

        $meta = wp_get_attachment_metadata($attachment_id);
        Utils::writeLog("filterAttachmentImageSrc: meta=" . json_encode($meta));

        if (!$meta || empty($meta['file'])) {
            return $image;
        }

        $dir = dirname($meta['file']);
        $cloudKey = null;

        if ($size === 'full') {
            $cloudKey = $meta['file'];
        } elseif (is_string($size) && !empty($meta['sizes'][$size])) {
            $cloudKey = self::buildCloudKey($dir, $meta['sizes'][$size]['file']);
        } else {
            $cloudKey = $meta['file'];
        }

        Utils::writeLog("filterAttachmentImageSrc: cloudKey=$cloudKey, dir=$dir");

        if ($cloudKey) {
            $image[0] = MediaProxy::getProxyUrl($cloudKey);
            Utils::writeLog("filterAttachmentImageSrc: replaced with " . $image[0]);
        }

        return $image;
    }

    public static function filterAttachmentMetadata($data, $post_id)
    {
        if (!$data || !MediaProxy::isCloudAttachment($post_id)) {
            return $data;
        }

        return $data;
    }

    public static function filterAttachmentLink($link, $post, $size)
    {
        if (!MediaProxy::isCloudAttachment($post->ID)) {
            return $link;
        }

        $meta = wp_get_attachment_metadata($post->ID);
        if (!$meta || empty($meta['file'])) {
            return $link;
        }

        $cloudKey = $meta['file'];
        $proxyUrl = MediaProxy::getProxyUrl($cloudKey);
        return $proxyUrl;
    }

    public static function filterImageSrcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
    {
        if (!MediaProxy::isCloudAttachment($attachment_id)) {
            return $sources;
        }

        if (!$sources || empty($image_meta['sizes'])) {
            return $sources;
        }

        $dir = dirname($image_meta['file']);
        $cloudKey = $image_meta['file'];

        foreach ($sources as $width => $source) {
            if (isset($image_meta['sizes'])) {
                foreach ($image_meta['sizes'] as $size_name => $size_data) {
                    if (isset($size_data['width']) && $size_data['width'] == $width) {
                        $thumbKey = self::buildCloudKey($dir, $size_data['file']);
                        $sources[$width]['url'] = MediaProxy::getProxyUrl($thumbKey);
                        break;
                    }
                }
            }
        }

        return $sources;
    }

    public static function filterAttachmentsUrl($url, $attachment_id) {
        if (!MediaProxy::isCloudAttachment($attachment_id)) {
            return $url;
        }
        $meta = wp_get_attachmen_metadata($attachment_id);
        if (!$meta || empty($meta['file'])) {
            return $url;
        }
        return MediaProxy::getProxyUrl($meta['file']);
    }

    public static function filterMediaSendToEditor($html, $attachment_id, $data) {
        if (!MediaProxy::isCloudAttachment($attachment_id)) {
            return $html;
        }
        $meta = wp_get_attachment_metadata($attachment_id);
        if (!$meta || empty($meta['file'])) {
            return $html;
        }
        $proxyUrl = MediaProxy::getProxyUrl($meta['file']);
        $html = preg_replace('/src="[^"]+"/', 'src="' . esc_url($proxyUrl) . '"', $html);
        $html = preg_replace('/href="[^"]+"/', 'href="' . esc_url($proxyUrl) . '"', $html);
        return $html;
    }

    /**
     * 处理 REST API 返回的附件数据 (媒体库网格视图)
     */
    public static function filterRestAttachment($response, $post) {
        $attachment_id = $post->ID;

        if (!MediaProxy::isCloudAttachment($attachment_id)) {
            return $response;
        }

        $meta = wp_get_attachment_metadata($attachment_id);
        if (!$meta || empty($meta['file'])) {
            return $response;
        }

        Utils::writeLog("filterRestAttachment: 处理附件 ID=$attachment_id");

        $dir = dirname($meta['file']);

        // 处理 source_url (原始图片URL)
        if (isset($response->data['source_url'])) {
            $response->data['source_url'] = MediaProxy::getProxyUrl($meta['file']);
        }

        // 处理 media_details 中的 sizes (缩略图URL)
        if (isset($response->data['media_details']['sizes']) && is_array($response->data['media_details']['sizes'])) {
            foreach ($response->data['media_details']['sizes'] as $size_name => &$size_data) {
                if (!empty($size_data['source_url'])) {
                    $cloudKey = self::buildCloudKey($dir, $size_data['file'] ?? $meta['file']);
                    $size_data['source_url'] = MediaProxy::getProxyUrl($cloudKey);
                }
            }
        }

        // 处理标题图片 URL (媒体库缩略图)
        if (isset($response->data['title']['rendered'])) {
            // 保持标题不变
        }

        // 处理媒体库列表中显示的图标/缩略图
        if (isset($response->data['link'])) {
            $response->data['link'] = MediaProxy::getProxyUrl($meta['file']);
        }

        return $response;
    }

    /**
     * 处理媒体库网格视图 AJAX 请求返回的附件数据
     * 这是处理媒体库缩略图显示的关键方法
     */
    public static function filterPrepareAttachmentForJs($attachment, $post) {
        $attachment_id = $post->ID;

        if (!MediaProxy::isCloudAttachment($attachment_id)) {
            return $attachment;
        }

        $meta = wp_get_attachment_metadata($attachment_id);
        if (!$meta || empty($meta['file'])) {
            return $attachment;
        }

        Utils::writeLog("filterPrepareAttachmentForJs: 处理附件 ID=$attachment_id, file=" . $meta['file']);

        $dir = dirname($meta['file']);
        $uploadDir = wp_upload_dir();
        $baseUrl = $uploadDir['baseurl'];

        // 处理主文件 URL
        if (!empty($attachment['url'])) {
            $attachment['url'] = MediaProxy::getProxyUrl($meta['file']);
        }

        // 处理缩略图尺寸
        if (!empty($attachment['sizes']) && is_array($attachment['sizes'])) {
            foreach ($attachment['sizes'] as $size_name => &$size_data) {
                if (!empty($size_data['url'])) {
                    // 从 sizes 中获取正确的文件名
                    $sizeFile = $size_data['file'] ?? ($meta['sizes'][$size_name]['file'] ?? '');
                    if ($sizeFile) {
                        $cloudKey = self::buildCloudKey($dir, $sizeFile);
                        $size_data['url'] = MediaProxy::getProxyUrl($cloudKey);
                        Utils::writeLog("filterPrepareAttachmentForJs: 尺寸 $size_name URL -> " . $size_data['url']);
                    }
                }
            }
        }

        // 处理图标 (非图片文件的缩略图)
        if (!empty($attachment['icon']) && strpos($attachment['icon'], $baseUrl) !== false) {
            // 如果是默认图标，保持不变
        }

        return $attachment;
    }

    /**
     * 获取缩略图的代理URL
     */
    public static function getThumbProxyUrl($attachment_id, $size = 'thumbnail') {
        if (!MediaProxy::isCloudAttachment($attachment_id)) {
            return false;
        }

        $meta = wp_get_attachment_metadata($attachment_id);
        if (!$meta || empty($meta['file'])) {
            return false;
        }

        $dir = dirname($meta['file']);

        if ($size === 'full') {
            return MediaProxy::getProxyUrl($meta['file']);
        }

        if (!empty($meta['sizes'][$size]['file'])) {
            $cloudKey = self::buildCloudKey($dir, $meta['sizes'][$size]['file']);
            return MediaProxy::getProxyUrl($cloudKey);
        }

        // 如果没有指定尺寸，返回原图
        return MediaProxy::getProxyUrl($meta['file']);
    }

    /**
     * AJAX 获取附件URL
     */
    public static function getAttachmentUrlAjax()
    {
        $attachment_id = intval($_POST['attachment_id']);
        if (!$attachment_id) {
            wp_send_json_error(['message' => '缺少附件ID']);
        }

        $url = wp_get_attachment_url($attachment_id);
        if (!$url) {
            wp_send_json_error(['message' => '无法获取附件URL']);
        }

        wp_send_json_success(['url' => $url]);
    }
}
