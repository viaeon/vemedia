<?php

use Vemedia\MediaHandler;
use Vemedia\Utils;
use Vemedia\VideoProcessor;

function vemedia_display()
{
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action == 'save') {
        $datas['storage_type'] = sanitize_text_field(trim($_POST['storage_type']));
        $datas['switch'] = sanitize_text_field(trim($_POST['switch']));

        $datas['s3_endpoint'] = sanitize_text_field(trim($_POST['s3_endpoint']));
        $datas['s3_access_key'] = sanitize_text_field(trim($_POST['s3_access_key']));
        $datas['s3_secret_key'] = sanitize_text_field(trim($_POST['s3_secret_key']));
        $datas['s3_bucket'] = sanitize_text_field(trim($_POST['s3_bucket']));
        $datas['s3_region'] = sanitize_text_field(trim($_POST['s3_region']));
        $datas['s3_path_style'] = sanitize_text_field(trim($_POST['s3_path_style']));
        $datas['s3_custom_url'] = sanitize_text_field(trim($_POST['s3_custom_url']));

        $datas['webdav_endpoint'] = sanitize_text_field(trim($_POST['webdav_endpoint']));
        $datas['webdav_username'] = sanitize_text_field(trim($_POST['webdav_username']));
        $datas['webdav_password'] = sanitize_text_field(trim($_POST['webdav_password']));
        $datas['webdav_path'] = sanitize_text_field(trim($_POST['webdav_path']));
        $datas['webdav_custom_url'] = sanitize_text_field(trim($_POST['webdav_custom_url']));

        $datas['ftp_host'] = sanitize_text_field(trim($_POST['ftp_host']));
        $datas['ftp_port'] = sanitize_text_field(trim($_POST['ftp_port']));
        $datas['ftp_username'] = sanitize_text_field(trim($_POST['ftp_username']));
        $datas['ftp_password'] = sanitize_text_field(trim($_POST['ftp_password']));
        $datas['ftp_path'] = sanitize_text_field(trim($_POST['ftp_path']));
        $datas['ftp_passive'] = sanitize_text_field(trim($_POST['ftp_passive']));
        $datas['ftp_ssl'] = sanitize_text_field(trim($_POST['ftp_ssl']));
        $datas['ftp_custom_url'] = sanitize_text_field(trim($_POST['ftp_custom_url']));

        // 功能开关
        $datas['localize_images'] = sanitize_text_field(trim($_POST['localize_images']));
        $datas['image_compress'] = sanitize_text_field(trim($_POST['image_compress']));
        $datas['image_compress_quality'] = intval($_POST['image_compress_quality']);
        $datas['image_watermark'] = sanitize_text_field(trim($_POST['image_watermark']));
        $datas['watermark_type'] = sanitize_text_field(trim($_POST['watermark_type']));
        $datas['watermark_text'] = sanitize_text_field(trim($_POST['watermark_text']));
        $datas['watermark_position'] = sanitize_text_field(trim($_POST['watermark_position']));
        $datas['watermark_opacity'] = intval($_POST['watermark_opacity']);
        $datas['watermark_image'] = intval($_POST['watermark_image']);
        $datas['keep_original'] = sanitize_text_field(trim($_POST['keep_original']));

        // 视频处理
        $datas['video_compress'] = sanitize_text_field(trim($_POST['video_compress']));
        $datas['video_compress_quality'] = sanitize_text_field(trim($_POST['video_compress_quality']));
        $datas['video_max_resolution'] = sanitize_text_field(trim($_POST['video_max_resolution']));
        $datas['video_watermark'] = sanitize_text_field(trim($_POST['video_watermark']));

        $datas = serialize($datas);
        update_option('vemedia_setting', $datas);

        echo '<div id="message" class="updated fade">设置已保存！</div>';
    }

    $storage_type = MediaHandler::config('storage_type') ?: 's3';
    $log_file = VEMEDIA_PLUGIN_DIR . 'logs/app.log';
    $log_content = file_exists($log_file) ? file_get_contents($log_file) : '暂无日志';
    
    $requirements = [
        'curl' => ['name' => 'cURL', 'loaded' => extension_loaded('curl'), 'required' => true, 'desc' => '用于与云存储服务通信'],
        'json' => ['name' => 'JSON', 'loaded' => extension_loaded('json'), 'required' => true, 'desc' => '用于处理 JSON 数据'],
        'fileinfo' => ['name' => 'Fileinfo', 'loaded' => extension_loaded('fileinfo'), 'required' => false, 'desc' => '用于检测文件 MIME 类型（可选，插件有备用方案）'],
    ];
?>
<style>
#message {
    margin: 1em 0;
    padding: .5em;
}
#vemedia-setting {
    margin-bottom: 20px;
    padding: 10px;
    background-color: #ffffff;
    border: 1px solid #e6e6e6;
    box-shadow: 0 0 3px #e3e3e3;
}
.storage-config {
    display: none;
}
.storage-config.active {
    display: table-row;
}
.test-result {
    margin-left: 10px;
    font-weight: bold;
}
.test-result.success {
    color: green;
}
.test-result.error {
    color: red;
}
#debug-result {
    margin-top: 10px;
    padding: 10px;
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
    max-height: 300px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 12px;
    white-space: pre-wrap;
    display: none;
}
.system-status {
    margin-bottom: 20px;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
.system-status h3 {
    margin-top: 0;
    margin-bottom: 10px;
}
.status-item {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}
.status-item:last-child {
    border-bottom: none;
}
.status-icon {
    font-size: 18px;
    margin-right: 10px;
    width: 20px;
}
.status-icon.success {
    color: #46b450;
}
.status-icon.warning {
    color: #ffb900;
}
.status-icon.error {
    color: #dc3232;
}
.status-label {
    font-weight: 600;
    min-width: 120px;
}
.status-desc {
    color: #666;
    font-size: 13px;
}
.status-required {
    margin-left: auto;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
}
.status-required.required {
    background: #dc3232;
    color: #fff;
}
.status-required.optional {
    background: #ffb900;
    color: #fff;
}
</style>
<div class="wrap">
    <h2>VeMedia 设置</h2>
    
    <div class="system-status">
        <h3>系统状态检查</h3>
        <?php foreach ($requirements as $key => $req): ?>
        <div class="status-item">
            <span class="status-icon <?php echo $req['loaded'] ? 'success' : ($req['required'] ? 'error' : 'warning'); ?>">
                <?php echo $req['loaded'] ? '✓' : '✗'; ?>
            </span>
            <span class="status-label"><?php echo $req['name']; ?></span>
            <span class="status-desc"><?php echo $req['desc']; ?></span>
            <span class="status-required <?php echo $req['required'] ? 'required' : 'optional'; ?>">
                <?php echo $req['required'] ? '必需' : '可选'; ?>
            </span>
        </div>
        <?php endforeach; ?>
        
        <?php 
        $missing_required = array_filter($requirements, function($req) {
            return $req['required'] && !$req['loaded'];
        });
        
        if (!empty($missing_required)): 
        ?>
        <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
            <strong>警告：</strong>缺少必需的 PHP 扩展，插件可能无法正常工作。请联系服务器管理员启用这些扩展。
        </div>
        <?php endif; ?>
        
        <?php 
        $missing_optional = array_filter($requirements, function($req) {
            return !$req['required'] && !$req['loaded'];
        });
        
        if (!empty($missing_optional)): 
        ?>
        <div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 4px solid #0073aa;">
            <strong>提示：</strong>可选扩展未加载，插件将使用备用方案工作，但建议启用以获得更好的性能。
        </div>
        <?php endif; ?>
    </div>
    
    <div id="vemedia-setting">
        <form method="post" action="">
            <input type="hidden" name="action" id="form_action" value="save" />
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><label for="storage_type">存储类型</label></th>
                        <td>
                            <select name="storage_type" id="storage_type">
                                <option value="s3" <?php selected($storage_type, 's3'); ?>>S3 兼容存储</option>
                                <option value="webdav" <?php selected($storage_type, 'webdav'); ?>>WebDAV</option>
                                <option value="ftp" <?php selected($storage_type, 'ftp'); ?>>FTP</option>
                            </select>
                            <button type="button" class="button" id="test_connection">测试连接</button>
                            <span class="test-result" id="test_result"></span>
                        </td>
                    </tr>

                    <!-- S3 配置 -->
                    <tr class="s3-config storage-config <?php echo $storage_type === 's3' ? 'active' : ''; ?>">
                        <th><label for="s3_endpoint">Endpoint</label></th>
                        <td><input size="40" type="text" name="s3_endpoint" value="<?php echo esc_attr(MediaHandler::config('s3_endpoint')); ?>" placeholder="https://s3.amazonaws.com" /></td>
                    </tr>
                    <tr class="s3-config storage-config <?php echo $storage_type === 's3' ? 'active' : ''; ?>">
                        <th><label for="s3_access_key">Access Key</label></th>
                        <td><input size="40" type="text" name="s3_access_key" value="<?php echo esc_attr(MediaHandler::config('s3_access_key')); ?>" /></td>
                    </tr>
                    <tr class="s3-config storage-config <?php echo $storage_type === 's3' ? 'active' : ''; ?>">
                        <th><label for="s3_secret_key">Secret Key</label></th>
                        <td><input size="40" type="password" name="s3_secret_key" value="<?php echo esc_attr(MediaHandler::config('s3_secret_key')); ?>" /></td>
                    </tr>
                    <tr class="s3-config storage-config <?php echo $storage_type === 's3' ? 'active' : ''; ?>">
                        <th><label for="s3_bucket">Bucket</label></th>
                        <td><input size="40" type="text" name="s3_bucket" value="<?php echo esc_attr(MediaHandler::config('s3_bucket')); ?>" /></td>
                    </tr>
                    <tr class="s3-config storage-config <?php echo $storage_type === 's3' ? 'active' : ''; ?>">
                        <th><label for="s3_region">Region</label></th>
                        <td>
                            <input size="40" type="text" name="s3_region" value="<?php echo esc_attr(MediaHandler::config('s3_region')); ?>" placeholder="us-east-1" />
                            <p class="description" style="margin-top: 5px;">MinIO 可填写 us-east-1 或留空</p>
                        </td>
                    </tr>
                    <tr class="s3-config storage-config <?php echo $storage_type === 's3' ? 'active' : ''; ?>">
                        <th><label for="s3_path_style">路径样式</label></th>
                        <td>
                            <select name="s3_path_style" id="s3_path_style">
                                <option value="no" <?php selected(MediaHandler::config('s3_path_style'), 'no'); ?>>虚拟主机样式 (bucket.endpoint)</option>
                                <option value="yes" <?php selected(MediaHandler::config('s3_path_style'), 'yes'); ?>>路径样式 (endpoint/bucket)</option>
                            </select>
                            <p class="description" style="margin-top: 5px;">
                                <strong>MinIO 用户请注意：</strong>必须选择"路径样式"，否则会出现 404 或签名错误。<br>
                                AWS S3、阿里云 OSS、腾讯云 COS 推荐使用"虚拟主机样式"。
                            </p>
                        </td>
                    </tr>
                    <tr class="s3-config storage-config <?php echo $storage_type === 's3' ? 'active' : ''; ?>">
                        <th><label for="s3_custom_url">自定义访问URL</label></th>
                        <td><input size="40" type="text" name="s3_custom_url" value="<?php echo esc_attr(MediaHandler::config('s3_custom_url')); ?>" placeholder="可选，用于CDN加速" /></td>
                    </tr>

                    <!-- WebDAV 配置 -->
                    <tr class="webdav-config storage-config <?php echo $storage_type === 'webdav' ? 'active' : ''; ?>">
                        <th><label for="webdav_endpoint">Endpoint</label></th>
                        <td><input size="40" type="text" name="webdav_endpoint" value="<?php echo esc_attr(MediaHandler::config('webdav_endpoint')); ?>" placeholder="https://dav.example.com" /></td>
                    </tr>
                    <tr class="webdav-config storage-config <?php echo $storage_type === 'webdav' ? 'active' : ''; ?>">
                        <th><label for="webdav_username">用户名</label></th>
                        <td><input size="40" type="text" name="webdav_username" value="<?php echo esc_attr(MediaHandler::config('webdav_username')); ?>" /></td>
                    </tr>
                    <tr class="webdav-config storage-config <?php echo $storage_type === 'webdav' ? 'active' : ''; ?>">
                        <th><label for="webdav_password">密码</label></th>
                        <td><input size="40" type="password" name="webdav_password" value="<?php echo esc_attr(MediaHandler::config('webdav_password')); ?>" /></td>
                    </tr>
                    <tr class="webdav-config storage-config <?php echo $storage_type === 'webdav' ? 'active' : ''; ?>">
                        <th><label for="webdav_path">存储路径</label></th>
                        <td><input size="40" type="text" name="webdav_path" value="<?php echo esc_attr(MediaHandler::config('webdav_path')); ?>" placeholder="/images" /></td>
                    </tr>
                    <tr class="webdav-config storage-config <?php echo $storage_type === 'webdav' ? 'active' : ''; ?>">
                        <th><label for="webdav_custom_url">自定义访问URL</label></th>
                        <td><input size="40" type="text" name="webdav_custom_url" value="<?php echo esc_attr(MediaHandler::config('webdav_custom_url')); ?>" placeholder="可选，用于公开访问" /></td>
                    </tr>

                    <!-- FTP 配置 -->
                    <tr class="ftp-config storage-config <?php echo $storage_type === 'ftp' ? 'active' : ''; ?>">
                        <th><label for="ftp_host">主机地址</label></th>
                        <td><input size="40" type="text" name="ftp_host" value="<?php echo esc_attr(MediaHandler::config('ftp_host')); ?>" placeholder="ftp.example.com" /></td>
                    </tr>
                    <tr class="ftp-config storage-config <?php echo $storage_type === 'ftp' ? 'active' : ''; ?>">
                        <th><label for="ftp_port">端口</label></th>
                        <td><input size="10" type="text" name="ftp_port" value="<?php echo esc_attr(MediaHandler::config('ftp_port')); ?>" /></td>
                    </tr>
                    <tr class="ftp-config storage-config <?php echo $storage_type === 'ftp' ? 'active' : ''; ?>">
                        <th><label for="ftp_username">用户名</label></th>
                        <td><input size="40" type="text" name="ftp_username" value="<?php echo esc_attr(MediaHandler::config('ftp_username')); ?>" /></td>
                    </tr>
                    <tr class="ftp-config storage-config <?php echo $storage_type === 'ftp' ? 'active' : ''; ?>">
                        <th><label for="ftp_password">密码</label></th>
                        <td><input size="40" type="password" name="ftp_password" value="<?php echo esc_attr(MediaHandler::config('ftp_password')); ?>" /></td>
                    </tr>
                    <tr class="ftp-config storage-config <?php echo $storage_type === 'ftp' ? 'active' : ''; ?>">
                        <th><label for="ftp_path">存储路径</label></th>
                        <td><input size="40" type="text" name="ftp_path" value="<?php echo esc_attr(MediaHandler::config('ftp_path')); ?>" placeholder="/public_html/images" /></td>
                    </tr>
                    <tr class="ftp-config storage-config <?php echo $storage_type === 'ftp' ? 'active' : ''; ?>">
                        <th><label for="ftp_passive">被动模式</label></th>
                        <td>
                            <select name="ftp_passive" id="ftp_passive">
                                <option value="yes" <?php selected(MediaHandler::config('ftp_passive'), 'yes'); ?>>是</option>
                                <option value="no" <?php selected(MediaHandler::config('ftp_passive'), 'no'); ?>>否</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="ftp-config storage-config <?php echo $storage_type === 'ftp' ? 'active' : ''; ?>">
                        <th><label for="ftp_ssl">SSL/TLS</label></th>
                        <td>
                            <select name="ftp_ssl" id="ftp_ssl">
                                <option value="no" <?php selected(MediaHandler::config('ftp_ssl'), 'no'); ?>>否</option>
                                <option value="yes" <?php selected(MediaHandler::config('ftp_ssl'), 'yes'); ?>>是</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="ftp-config storage-config <?php echo $storage_type === 'ftp' ? 'active' : ''; ?>">
                        <th><label for="ftp_custom_url">自定义访问URL</label></th>
                        <td><input size="40" type="text" name="ftp_custom_url" value="<?php echo esc_attr(MediaHandler::config('ftp_custom_url')); ?>" placeholder="可选，用于公开访问" /></td>
                    </tr>

                </tbody>
            </table>

            <h3 style="margin-top: 30px;">功能设置</h3>
            <table class="form-table">
                <tbody>
                    <!-- 图片本地化 -->
                    <tr>
                        <th><label for="localize_images">图片本地化</label></th>
                        <td>
                            <select name="localize_images" id="localize_images">
                                <option value="yes" <?php selected(MediaHandler::config('localize_images'), 'yes'); ?>>启用</option>
                                <option value="no" <?php selected(MediaHandler::config('localize_images'), 'no'); ?>>禁用</option>
                            </select>
                            <p class="description">自动将文章中的外链图片和 Base64 图片下载到媒体库并上传云端</p>
                        </td>
                    </tr>

                    <!-- 图片压缩 -->
                    <tr>
                        <th><label for="image_compress">图片压缩</label></th>
                        <td>
                            <select name="image_compress" id="image_compress">
                                <option value="no" <?php selected(MediaHandler::config('image_compress'), 'no'); ?>>禁用</option>
                                <option value="yes" <?php selected(MediaHandler::config('image_compress'), 'yes'); ?>>启用</option>
                            </select>
                            <p class="description">上传前自动压缩图片，减少存储空间和带宽消耗</p>
                        </td>
                    </tr>
                    <tr class="compress-config" style="display: <?php echo MediaHandler::config('image_compress') === 'yes' ? 'table-row' : 'none'; ?>;">
                        <th><label for="image_compress_quality">压缩质量</label></th>
                        <td>
                            <input type="range" name="image_compress_quality" id="image_compress_quality" min="10" max="100" value="<?php echo esc_attr(MediaHandler::config('image_compress_quality') ?: 80); ?>" style="width: 200px; vertical-align: middle;">
                            <span id="quality_value"><?php echo esc_attr(MediaHandler::config('image_compress_quality') ?: 80); ?></span>%
                            <p class="description">建议 70-85，数值越小压缩率越高但画质越低</p>
                        </td>
                    </tr>

                    <!-- 水印功能 -->
                    <tr>
                        <th><label for="image_watermark">图片水印</label></th>
                        <td>
                            <select name="image_watermark" id="image_watermark">
                                <option value="no" <?php selected(MediaHandler::config('image_watermark'), 'no'); ?>>禁用</option>
                                <option value="yes" <?php selected(MediaHandler::config('image_watermark'), 'yes'); ?>>启用</option>
                            </select>
                            <p class="description">上传前自动为图片添加水印</p>
                        </td>
                    </tr>
                    <tr class="watermark-config" style="display: <?php echo MediaHandler::config('image_watermark') === 'yes' ? 'table-row' : 'none'; ?>;">
                        <th><label for="watermark_type">水印类型</label></th>
                        <td>
                            <select name="watermark_type" id="watermark_type">
                                <option value="text" <?php selected(MediaHandler::config('watermark_type'), 'text'); ?>>文字水印</option>
                                <option value="image" <?php selected(MediaHandler::config('watermark_type'), 'image'); ?>>图片水印</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="watermark-config watermark-text-config" style="display: <?php echo MediaHandler::config('image_watermark') === 'yes' && MediaHandler::config('watermark_type') === 'text' ? 'table-row' : 'none'; ?>;">
                        <th><label for="watermark_text">水印文字</label></th>
                        <td>
                            <input size="40" type="text" name="watermark_text" value="<?php echo esc_attr(MediaHandler::config('watermark_text')); ?>" placeholder="请输入水印文字">
                        </td>
                    </tr>
                    <tr class="watermark-config watermark-image-config" style="display: <?php echo MediaHandler::config('image_watermark') === 'yes' && MediaHandler::config('watermark_type') === 'image' ? 'table-row' : 'none'; ?>;">
                        <th><label for="watermark_image">水印图片</label></th>
                        <td>
                            <input type="hidden" name="watermark_image" id="watermark_image_id" value="<?php echo esc_attr(MediaHandler::config('watermark_image')); ?>">
                            <button type="button" class="button" id="select_watermark_image">选择图片</button>
                            <span id="watermark_image_preview"></span>
                            <p class="description">建议使用透明背景的 PNG 图片</p>
                        </td>
                    </tr>
                    <tr class="watermark-config" style="display: <?php echo MediaHandler::config('image_watermark') === 'yes' ? 'table-row' : 'none'; ?>;">
                        <th><label for="watermark_position">水印位置</label></th>
                        <td>
                            <select name="watermark_position" id="watermark_position">
                                <option value="top-left" <?php selected(MediaHandler::config('watermark_position'), 'top-left'); ?>>左上角</option>
                                <option value="top-center" <?php selected(MediaHandler::config('watermark_position'), 'top-center'); ?>>顶部居中</option>
                                <option value="top-right" <?php selected(MediaHandler::config('watermark_position'), 'top-right'); ?>>右上角</option>
                                <option value="center-left" <?php selected(MediaHandler::config('watermark_position'), 'center-left'); ?>>左侧居中</option>
                                <option value="center" <?php selected(MediaHandler::config('watermark_position'), 'center'); ?>>居中</option>
                                <option value="center-right" <?php selected(MediaHandler::config('watermark_position'), 'center-right'); ?>>右侧居中</option>
                                <option value="bottom-left" <?php selected(MediaHandler::config('watermark_position'), 'bottom-left'); ?>>左下角</option>
                                <option value="bottom-center" <?php selected(MediaHandler::config('watermark_position'), 'bottom-center'); ?>>底部居中</option>
                                <option value="bottom-right" <?php selected(MediaHandler::config('watermark_position'), 'bottom-right'); ?>>右下角</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="watermark-config" style="display: <?php echo MediaHandler::config('image_watermark') === 'yes' ? 'table-row' : 'none'; ?>;">
                        <th><label for="watermark_opacity">水印透明度</label></th>
                        <td>
                            <input type="range" name="watermark_opacity" id="watermark_opacity" min="10" max="100" value="<?php echo esc_attr(MediaHandler::config('watermark_opacity') ?: 50); ?>" style="width: 200px; vertical-align: middle;">
                            <span id="opacity_value"><?php echo esc_attr(MediaHandler::config('watermark_opacity') ?: 50); ?></span>%
                        </td>
                    </tr>

                    <!-- 保留原图（仅在水印或压缩启用时显示） -->
                    <tr class="keep-original-config" style="display: <?php echo (MediaHandler::config('image_compress') === 'yes' || MediaHandler::config('image_watermark') === 'yes' || MediaHandler::config('video_compress') === 'yes' || MediaHandler::config('video_watermark') === 'yes') ? 'table-row' : 'none'; ?>;">
                        <th><label for="keep_original">保留原文件</label></th>
                        <td>
                            <select name="keep_original" id="keep_original">
                                <option value="yes" <?php selected(MediaHandler::config('keep_original'), 'yes'); ?>>是（原文件+处理后的文件都上传）</option>
                                <option value="no" <?php selected(MediaHandler::config('keep_original'), 'no'); ?>>否（仅上传处理后的文件）</option>
                            </select>
                            <p class="description">开启水印或压缩时，是否同时保留原文件到云端。关闭后仅上传处理后的文件，可节省存储空间</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h3 style="margin-top: 30px;">视频设置</h3>
            <?php
            // 检测 FFmpeg 是否可用
            $ffmpegAvailable = VideoProcessor::checkFFmpeg();
            ?>
            <?php if (!$ffmpegAvailable): ?>
            <div class="notice notice-warning inline" style="margin-bottom: 15px;">
                <p><strong>提示：</strong>未检测到 FFmpeg，视频压缩和水印功能不可用。请联系服务器管理员安装 FFmpeg。</p>
            </div>
            <?php endif; ?>

            <table class="form-table">
                <tbody>
                    <!-- 视频压缩 -->
                    <tr>
                        <th><label for="video_compress">视频压缩</label></th>
                        <td>
                            <select name="video_compress" id="video_compress" <?php echo !$ffmpegAvailable ? 'disabled' : ''; ?>>
                                <option value="no" <?php selected(MediaHandler::config('video_compress'), 'no'); ?>>禁用</option>
                                <option value="yes" <?php selected(MediaHandler::config('video_compress'), 'yes'); ?>>启用</option>
                            </select>
                            <p class="description">压缩视频以减少存储空间和带宽消耗（需要 FFmpeg）</p>
                        </td>
                    </tr>
                    <tr class="video-compress-config" style="display: <?php echo MediaHandler::config('video_compress') === 'yes' ? 'table-row' : 'none'; ?>;">
                        <th><label for="video_compress_quality">压缩质量</label></th>
                        <td>
                            <select name="video_compress_quality" id="video_compress_quality">
                                <option value="low" <?php selected(MediaHandler::config('video_compress_quality'), 'low'); ?>>低（最小体积，画质较低）</option>
                                <option value="medium" <?php selected(MediaHandler::config('video_compress_quality'), 'medium'); ?>>中等（平衡体积和画质）</option>
                                <option value="high" <?php selected(MediaHandler::config('video_compress_quality'), 'high'); ?>>高（较好画质，体积较大）</option>
                            </select>
                        </td>
                    </tr>
                    <tr class="video-compress-config" style="display: <?php echo MediaHandler::config('video_compress') === 'yes' ? 'table-row' : 'none'; ?>;">
                        <th><label for="video_max_resolution">最大分辨率</label></th>
                        <td>
                            <select name="video_max_resolution" id="video_max_resolution">
                                <option value="480p" <?php selected(MediaHandler::config('video_max_resolution'), '480p'); ?>>480p (854×480)</option>
                                <option value="720p" <?php selected(MediaHandler::config('video_max_resolution'), '720p'); ?>>720p (1280×720)</option>
                                <option value="1080p" <?php selected(MediaHandler::config('video_max_resolution'), '1080p'); ?>>1080p (1920×1080)</option>
                                <option value="2160p" <?php selected(MediaHandler::config('video_max_resolution'), '2160p'); ?>>4K (3840×2160)</option>
                                <option value="original" <?php selected(MediaHandler::config('video_max_resolution'), 'original'); ?>>保持原始分辨率</option>
                            </select>
                            <p class="description">超过此分辨率的视频将被缩放</p>
                        </td>
                    </tr>

                    <!-- 视频水印 -->
                    <tr>
                        <th><label for="video_watermark">视频水印</label></th>
                        <td>
                            <select name="video_watermark" id="video_watermark" <?php echo !$ffmpegAvailable ? 'disabled' : ''; ?>>
                                <option value="no" <?php selected(MediaHandler::config('video_watermark'), 'no'); ?>>禁用</option>
                                <option value="yes" <?php selected(MediaHandler::config('video_watermark'), 'yes'); ?>>启用</option>
                            </select>
                            <p class="description">为视频添加水印（使用图片水印设置，需要 FFmpeg）</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table class="form-table">
                <tbody>
                    <tr>
                        <th><label>是否启用：</label></th>
                        <td>
                            <select name="switch" id="switch">
                                <option value="enable" <?php selected(MediaHandler::config('switch'), 'enable'); ?>>启用</option>
                                <option value="disable" <?php selected(MediaHandler::config('switch'), 'disable'); ?>>禁用</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><input class="button-primary" type="submit" value="保存设置" /></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </form>
        
        <hr style="margin: 20px 0;">
        
        <h3>调试工具</h3>
        <p>
            <button type="button" class="button" id="debug_upload">测试上传功能</button>
            <button type="button" class="button" id="clear_log">清除日志</button>
        </p>
        <div id="debug-result"></div>
        
        <h3>日志内容</h3>
        <textarea rows="10" cols="80" readonly style="width: 100%; font-family: monospace; font-size: 12px;"><?php echo esc_textarea($log_content); ?></textarea>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function updateStorageConfig() {
        var storageType = $('#storage_type').val();
        $('.storage-config').removeClass('active');
        $('.' + storageType + '-config').addClass('active');
    }

    $('#storage_type').change(updateStorageConfig);
    updateStorageConfig();

    // 功能开关动态显示
    $('#image_compress').change(function() {
        if ($(this).val() === 'yes') {
            $('.compress-config').show();
        } else {
            $('.compress-config').hide();
        }
        updateKeepOriginal();
    });

    $('#image_compress_quality').on('input', function() {
        $('#quality_value').text($(this).val());
    });

    $('#image_watermark').change(function() {
        if ($(this).val() === 'yes') {
            $('.watermark-config').show();
            updateWatermarkType();
        } else {
            $('.watermark-config').hide();
        }
        updateKeepOriginal();
    });

    $('#watermark_type').change(function() {
        updateWatermarkType();
    });

    function updateWatermarkType() {
        var type = $('#watermark_type').val();
        if (type === 'text') {
            $('.watermark-text-config').show();
            $('.watermark-image-config').hide();
        } else {
            $('.watermark-text-config').hide();
            $('.watermark-image-config').show();
        }
    }

    // 更新保留原图选项显示状态
    function updateKeepOriginal() {
        var imageCompressEnabled = $('#image_compress').val() === 'yes';
        var imageWatermarkEnabled = $('#image_watermark').val() === 'yes';
        var videoCompressEnabled = $('#video_compress').val() === 'yes';
        var videoWatermarkEnabled = $('#video_watermark').val() === 'yes';
        if (imageCompressEnabled || imageWatermarkEnabled || videoCompressEnabled || videoWatermarkEnabled) {
            $('.keep-original-config').show();
        } else {
            $('.keep-original-config').hide();
        }
    }

    // 初始化水印类型显示
    if ($('#image_watermark').val() === 'yes') {
        $('.watermark-config').show();
        updateWatermarkType();
    }

    // 初始化保留原图显示
    updateKeepOriginal();

    // 视频压缩设置
    $('#video_compress').change(function() {
        if ($(this).val() === 'yes') {
            $('.video-compress-config').show();
        } else {
            $('.video-compress-config').hide();
        }
        updateKeepOriginal();
    });

    // 初始化视频压缩显示
    if ($('#video_compress').val() === 'yes') {
        $('.video-compress-config').show();
    }

    // 视频水印变化时更新保留原图
    $('#video_watermark').change(function() {
        updateKeepOriginal();
    });

    $('#watermark_opacity').on('input', function() {
        $('#opacity_value').text($(this).val());
    });

    // 水印图片选择器
    var watermarkFrame;
    $('#select_watermark_image').click(function(e) {
        e.preventDefault();
        if (watermarkFrame) {
            watermarkFrame.open();
            return;
        }
        watermarkFrame = wp.media({
            title: '选择水印图片',
            button: { text: '使用此图片' },
            multiple: false
        });
        watermarkFrame.on('select', function() {
            var attachment = watermarkFrame.state().get('selection').first().toJSON();
            $('#watermark_image_id').val(attachment.id);
            $('#watermark_image_preview').html('<img src="' + attachment.url + '" style="max-height: 50px; margin-left: 10px; vertical-align: middle;">');
        });
        watermarkFrame.open();
    });

    // 显示已选水印图片
    var currentWatermarkId = $('#watermark_image_id').val();
    if (currentWatermarkId) {
        $.post(ajaxurl, {
            action: 'vemedia_get_attachment_url',
            attachment_id: currentWatermarkId
        }, function(response) {
            if (response.success && response.data.url) {
                $('#watermark_image_preview').html('<img src="' + response.data.url + '" style="max-height: 50px; margin-left: 10px; vertical-align: middle;">');
            }
        });
    }

    $('#test_connection').click(function() {
        var storageType = $('#storage_type').val();
        var data = {
            action: 'test_storage_connection',
            storage_type: storageType
        };

        if (storageType === 's3') {
            data.s3_endpoint = $('input[name="s3_endpoint"]').val();
            data.s3_access_key = $('input[name="s3_access_key"]').val();
            data.s3_secret_key = $('input[name="s3_secret_key"]').val();
            data.s3_bucket = $('input[name="s3_bucket"]').val();
            data.s3_region = $('input[name="s3_region"]').val();
            data.s3_path_style = $('#s3_path_style').val();
        } else if (storageType === 'webdav') {
            data.webdav_endpoint = $('input[name="webdav_endpoint"]').val();
            data.webdav_username = $('input[name="webdav_username"]').val();
            data.webdav_password = $('input[name="webdav_password"]').val();
            data.webdav_path = $('input[name="webdav_path"]').val();
        } else if (storageType === 'ftp') {
            data.ftp_host = $('input[name="ftp_host"]').val();
            data.ftp_port = $('input[name="ftp_port"]').val();
            data.ftp_username = $('input[name="ftp_username"]').val();
            data.ftp_password = $('input[name="ftp_password"]').val();
            data.ftp_path = $('input[name="ftp_path"]').val();
            data.ftp_passive = $('#ftp_passive').val();
            data.ftp_ssl = $('#ftp_ssl').val();
        }

        $('#test_result').removeClass('success error').text('测试中...');

        $.post(ajaxurl, data, function(response) {
            var result = JSON.parse(response);
            if (result.status) {
                $('#test_result').addClass('success').text('✓ ' + result.message);
            } else {
                $('#test_result').addClass('error').text('✗ ' + result.message);
            }
        }).fail(function() {
            $('#test_result').addClass('error').text('✗ 请求失败');
        });
    });
    
    $('#debug_upload').click(function() {
        var $btn = $(this);
        var $result = $('#debug-result');
        
        $btn.prop('disabled', true).text('测试中...');
        $result.show().text('正在测试上传功能...');
        
        $.post(ajaxurl, {action: 'vemedia_debug_upload'}, function(response) {
            if (response.success) {
                $result.text('测试结果:\n' + JSON.stringify(response.data, null, 2));
            } else {
                $result.text('测试失败:\n' + JSON.stringify(response, null, 2));
            }
        }).fail(function(xhr, status, error) {
            $result.text('请求失败: ' + error + '\n\n响应内容:\n' + xhr.responseText);
        }).always(function() {
            $btn.prop('disabled', false).text('测试上传功能');
        });
    });
    
    $('#clear_log').click(function() {
        if (confirm('确定要清除日志文件吗？')) {
            $.post(ajaxurl, {action: 'vemedia_clear_log'}, function(response) {
                if (response.success) {
                    alert('日志已清除');
                    location.reload();
                } else {
                    alert('清除失败: ' + (response.data || '未知错误'));
                }
            });
        }
    });
});
</script>
<?php
}
