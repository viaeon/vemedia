<?php

/**
 * Plugin Name: VeMedia
 * Plugin URI: https://github.com/yourname/vemedia
 * Description: 将 WordPress 媒体上传至云端存储（支持S3/WebDAV/FTP）
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 */

if (!defined('ABSPATH')) exit;

define('VEMEDIA_VERSION', '1.0.0');
define('VEMEDIA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VEMEDIA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once plugin_dir_path(__FILE__) . '/autoload.php';

use Vemedia\MediaHandler;
use Vemedia\MediaProxy;
use Vemedia\Utils;

register_activation_hook(__FILE__, 'vemedia_activate');
function vemedia_activate() {
    $log_dir = VEMEDIA_PLUGIN_DIR . 'logs/';
    if (!is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
        file_put_contents($log_dir . '.htaccess', 'Deny from all');
    }

    if (!vemedia_check_requirements()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            'VeMedia 插件需要以下 PHP 扩展：<ul>' . vemedia_get_requirements_list() . '</ul>请联系服务器管理员启用这些扩展后重新激活插件。',
            '插件激活失败',
            ['back_link' => true]
        );
    }
}

register_deactivation_hook(__FILE__, 'vemedia_deactivate');
function vemedia_deactivate() {
    MediaProxy::removeHtaccess();
}

function vemedia_check_requirements() {
    $requirements = [
        'curl' => extension_loaded('curl'),
        'json' => extension_loaded('json'),
    ];

    return !in_array(false, $requirements, true);
}

function vemedia_get_requirements_list() {
    $list = '';
    $requirements = [
        'curl' => ['name' => 'cURL', 'required' => true, 'loaded' => extension_loaded('curl')],
        'json' => ['name' => 'JSON', 'required' => true, 'loaded' => extension_loaded('json')],
        'fileinfo' => ['name' => 'Fileinfo', 'required' => false, 'loaded' => extension_loaded('fileinfo')],
    ];

    foreach ($requirements as $key => $req) {
        $status = $req['loaded'] ? '✓' : '✗';
        $required = $req['required'] ? '（必需）' : '（可选，推荐）';
        $class = $req['loaded'] ? 'success' : ($req['required'] ? 'error' : 'warning');
        $list .= "<li class='$class'>$status {$req['name']} $required</li>";
    }

    return $list;
}

add_action('admin_notices', 'vemedia_admin_notices');
function vemedia_admin_notices() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $screen = get_current_screen();
    if ($screen->id === 'settings_page_vemedia_settings' || $screen->id === 'plugins') {
        $requirements = [
            'curl' => ['name' => 'cURL', 'loaded' => extension_loaded('curl'), 'required' => true],
            'json' => ['name' => 'JSON', 'loaded' => extension_loaded('json'), 'required' => true],
            'fileinfo' => ['name' => 'Fileinfo', 'loaded' => extension_loaded('fileinfo'), 'required' => false],
        ];

        $missing_required = [];
        $missing_optional = [];

        foreach ($requirements as $key => $req) {
            if (!$req['loaded']) {
                if ($req['required']) {
                    $missing_required[] = $req['name'];
                } else {
                    $missing_optional[] = $req['name'];
                }
            }
        }

        if (!empty($missing_required)) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>VeMedia 警告：</strong>缺少必需的 PHP 扩展：<strong>' . implode(', ', $missing_required) . '</strong>。';
            echo '插件可能无法正常工作。请联系服务器管理员启用这些扩展。';
            echo '</p></div>';
        }

        if (!empty($missing_optional)) {
            echo '<div class="notice notice-warning"><p>';
            echo '<strong>VeMedia 提示：</strong>可选扩展 <strong>' . implode(', ', $missing_optional) . '</strong> 未加载。';
            echo '插件将使用备用方案识别文件类型，但建议启用该扩展以获得更好的性能和准确性。';
            echo '</p></div>';
        }
    }
}

if (is_admin()) {
    add_filter('plugin_action_links_vemedia/Vemedia.php', array(MediaHandler::class, 'plugin_settings_link'));
    add_action('admin_menu', array(MediaHandler::class, 'menu'));
}

require 'src/display.php';
require 'src/hooks.php';

add_action('init', function() {
    if (MediaHandler::config('switch') == 'enable') {
        add_filter('wp_calculate_image_srcset', [MediaHandler::class, 'filterImageSrcset'], 10, 5);
        add_filter('wp_get_attachment_link', [MediaHandler::class, 'filterAttachmentLink'], 10, 6);
    }
}, 100);

add_action('wp_ajax_vemedia_debug_upload', 'vemedia_debug_upload');
function vemedia_debug_upload() {
    if (!current_user_can('upload_files')) {
        wp_send_json_error(['message' => '权限不足']);
    }

    $test_file = VEMEDIA_PLUGIN_DIR . 'test_upload.txt';
    file_put_contents($test_file, 'test content ' . date('Y-m-d H:i:s'));

    $storageClass = MediaHandler::getStorageClass();
    if (!$storageClass) {
        wp_send_json_error(['message' => '未配置存储类型']);
    }

    $cloudKey = 'vemedia-test/test_' . date('YmdHis') . '.txt';
    $result = $storageClass::upload($test_file, $cloudKey);

    wp_send_json_success([
        'result' => $result,
        'log_file' => VEMEDIA_PLUGIN_DIR . 'logs/app.log',
        'log_exists' => file_exists(VEMEDIA_PLUGIN_DIR . 'logs/app.log'),
        'log_content' => file_exists(VEMEDIA_PLUGIN_DIR . 'logs/app.log') ? file_get_contents(VEMEDIA_PLUGIN_DIR . 'logs/app.log') : 'No log file'
    ]);
}

add_action('wp_ajax_vemedia_clear_log', 'vemedia_clear_log');
function vemedia_clear_log() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => '权限不足']);
    }

    $log_file = VEMEDIA_PLUGIN_DIR . 'logs/app.log';
    if (file_exists($log_file)) {
        @unlink($log_file);
    }

    wp_send_json_success(['message' => '日志已清除']);
}
