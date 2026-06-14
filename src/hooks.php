<?php

namespace Vemedia;
use Vemedia\MediaHandler;
use Vemedia\MediaProxy;
use Vemedia\ImageLocalizer;
use Vemedia\ImageProcessor;
use Vemedia\VideoProcessor;

add_action('wp_ajax_test_storage_connection', [MediaHandler::class, 'test_storage_connection']);

// 获取附件URL的AJAX处理
add_action('wp_ajax_vemedia_get_attachment_url', [MediaHandler::class, 'getAttachmentUrlAjax']);

MediaProxy::init();

if (MediaHandler::config('switch') == 'enable') {
    add_action('wp_ajax_vemedia_upload_one', [MediaHandler::class, 'replaced_one']);
    add_filter('attachment_fields_to_edit', [MediaHandler::class, 'attachment_editor'], 10, 2);
    add_action('delete_attachment', [MediaHandler::class, 'media_del_handle'], 10, 2);
    add_action('add_attachment', [MediaHandler::class, 'add_attachment']);
    add_filter('wp_generate_attachment_metadata', [MediaHandler::class, 'generate_attachment_metadata'], 10, 3);
    add_filter('wp_get_attachment_url', [MediaHandler::class, 'filterAttachmentUrl'], 10, 2);
    add_filter('wp_get_attachment_image_src', [MediaHandler::class, 'filterAttachmentImageSrc'], 10, 4);
    add_filter('wp_calculate_image_srcset', [MediaHandler::class, 'filterImageSrcset'], 10, 5);
    add_filter('wp_get_attachment_link', [MediaHandler::class, 'filterAttachmentLink'], 10, 6);
    add_filter('wp_attachments_s3_url', [MediaHandler::class, 'filterAttachmentsUrl'], 10, 2);

    add_filter('media_send_to_editor', [MediaHandler::class, 'filterMediaSendToEditor'], 10, 3);

    // 处理媒体库网格视图 (REST API)
    add_filter('rest_prepare_attachment', [MediaHandler::class, 'filterRestAttachment'], 10, 2);

    // 处理媒体库网格视图 AJAX (最关键的过滤器)
    add_filter('wp_prepare_attachment_for_js', [MediaHandler::class, 'filterPrepareAttachmentForJs'], 10, 2);

    // 图片压缩和水印处理（在生成缩略图之前）
    add_filter('wp_handle_upload_prefilter', [ImageProcessor::class, 'handleUploadPrefilter'], 10, 1);

    // 视频压缩和水印处理
    add_filter('wp_handle_upload_prefilter', [VideoProcessor::class, 'handleUploadPrefilter'], 10, 1);

    // 图片本地化（根据开关决定是否启用）
    if (MediaHandler::config('localize_images') === 'yes') {
        ImageLocalizer::init();
    }
}