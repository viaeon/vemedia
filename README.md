# VeMedia - WordPress 云端媒体存储插件

将 WordPress 媒体文件上传至云端存储，支持多种存储后端。

## 环境要求

### 必需扩展
- **PHP >= 7.4**
- **cURL** - 用于与云存储服务通信
- **JSON** - 用于处理 JSON 数据

### 可选扩展（推荐）
- **Fileinfo** - 用于检测文件 MIME 类型
  - 如果未加载，插件将使用备用方案（文件扩展名映射）
  - 建议启用以获得更好的性能和准确性

### 特定存储类型要求
- **FTP 存储**：需要 PHP 的 FTP 扩展

## 支持的存储类型

- **S3 兼容存储** - AWS S3、阿里云 OSS、腾讯云 COS、MinIO 等
- **WebDAV** - 支持 WebDAV 协议的网盘/存储服务
- **FTP** - 传统 FTP/FTPS 服务器

## 功能特点

### 核心功能
- 支持所有类型的媒体文件上传（图片、视频、文档、压缩包等）
- 自动上传 WordPress 生成的图片缩略图
- 支持一键替换已有媒体到云端
- 删除媒体时自动删除云端文件
- 支持自定义 CDN 加速域名
- 系统环境自动检测和提示
- 详细的日志记录和调试工具

### 图片处理功能
- **图片本地化**：自动将文章中的 Base64 编码图片和外部图片下载到本地并上传至云端
- **图片压缩**：支持 JPEG、PNG、WebP 格式的图片压缩，可自定义压缩质量
- **水印功能**：
  - 支持文字水印和图片水印
  - 可自定义水印位置（9个位置可选）
  - 可调整水印透明度
  - 支持自定义水印文字字体大小和颜色

### 视频处理功能
- **视频压缩**：使用 FFmpeg 压缩视频，可自定义压缩质量
- **分辨率限制**：支持限制视频最大分辨率
- **视频水印**：支持为视频添加水印
- **自动检测**：自动检测服务器 FFmpeg 环境

### 媒体代理功能
- 提供媒体文件代理访问端点
- 支持 AJAX 方式代理访问
- 自动处理 .htaccess 配置

## 安装方式

1. 下载最新 release 的 zip 包
2. 解压到 WordPress 的 `wp-content/plugins/` 目录
3. 确保插件目录名为 `vemedia`
4. 在 WordPress 后台启用插件

**注意**：激活时插件会自动检测必需的 PHP 扩展，如果缺少必需扩展会显示错误提示。

## 使用方式

1. 进入 WordPress 后台 -> 设置 -> VeMedia 设置
2. 查看系统状态检查，确保必需扩展已加载
3. 选择存储类型（S3/WebDAV/FTP）
4. 填写对应的配置信息
5. 点击"测试连接"验证配置
6. 启用插件并保存设置

## 配置示例

### MinIO 配置

MinIO 是高性能的对象存储服务，完全兼容 AWS S3 API。

**配置步骤：**
1. 存储类型：选择 `S3 兼容存储`
2. Endpoint：`http://your-minio-server:9000` 或 `https://your-minio-server`
3. Access Key：MinIO 的 Access Key（在 MinIO 控制台创建）
4. Secret Key：MinIO 的 Secret Key
5. Bucket：存储桶名称
6. Region：填写 `us-east-1` 或留空（MinIO 通常不严格要求 region）
7. 路径样式：**必须选择"路径样式"**（重要！）

**为什么必须使用路径样式？**
- MinIO 默认不支持虚拟主机样式（bucket.endpoint）
- 必须使用路径样式（endpoint/bucket）
- 否则会出现 404 或签名错误

**示例配置：**
```
Endpoint: http://192.168.1.100:9000
Access Key: minioadmin
Secret Key: minioadmin
Bucket: mybucket
Region: us-east-1
路径样式: 路径样式 (endpoint/bucket)
```

### AWS S3 配置

1. Endpoint: `https://s3.amazonaws.com`
2. Access Key: AWS IAM 用户的 Access Key ID
3. Secret Key: AWS IAM 用户的 Secret Access Key
4. Bucket: S3 存储桶名称
5. Region: 存储桶所在区域（如 `us-east-1`, `ap-northeast-1`）
6. 路径样式: 虚拟主机样式（推荐）

### 阿里云 OSS 配置

阿里云 OSS 兼容 S3 API，需要使用 S3 兼容接口：

1. Endpoint: `https://oss-cn-hangzhou.aliyuncs.com`（根据实际区域）
2. Access Key: 阿里云 AccessKey ID
3. Secret Key: 阿里云 AccessKey Secret
4. Bucket: OSS Bucket 名称
5. Region: OSS 区域（如 `oss-cn-hangzhou`）
6. 路径样式: 虚拟主机样式

### 腾讯云 COS 配置

腾讯云 COS 也支持 S3 API：

1. Endpoint: `https://cos.ap-guangzhou.myqcloud.com`（根据实际区域）
2. Access Key: 腾讯云 SecretId
3. Secret Key: 腾讯云 SecretKey
4. Bucket: COS Bucket 名称（格式：bucket-appid）
5. Region: COS 区域（如 `ap-guangzhou`）
6. 路径样式: 虚拟主机样式

## 扩展安装指南

### 如何启用 Fileinfo 扩展

**Linux/Unix:**
```bash
# 编辑 php.ini
sudo nano /etc/php/版本号/apache2/php.ini

# 取消注释或添加
extension=fileinfo

# 重启 Web 服务器
sudo service apache2 restart
# 或
sudo service nginx restart
```

**Windows:**
```ini
; 编辑 php.ini
extension=php_fileinfo.dll
```

**宝塔面板:**
1. 进入 软件商店 -> PHP -> 设置
2. 点击"安装扩展"
3. 找到 fileinfo 并点击安装

## 故障排除

### 插件激活失败
- 检查 PHP 版本是否 >= 7.4
- 检查必需扩展（cURL、JSON）是否已加载
- 查看 WordPress 调试日志：`wp-content/debug.log`

### 上传失败
1. 进入设置页面查看系统状态检查
2. 使用"测试上传功能"按钮进行诊断
3. 查看日志内容了解详细错误信息
4. 检查存储配置是否正确
5. 确认服务器可以访问云存储服务

### MIME 类型识别问题
- 如果 Fileinfo 扩展未加载，插件使用文件扩展名映射
- 支持 40+ 种常见文件类型
- 如需更准确的类型识别，建议启用 Fileinfo 扩展

## 文件结构

```
vemedia/
├── Vemedia.php           # 主插件文件
├── autoload.php          # 自动加载器
├── uninstall.php         # 卸载清理
├── README.md              # 说明文档
├── src/
│   ├── Plugin.php        # 插件基类
│   ├── MediaHandler.php  # 媒体处理核心类
│   ├── StorageInterface.php  # 存储接口
│   ├── S3Storage.php     # S3 存储实现
│   ├── WebDAVStorage.php # WebDAV 存储实现
│   ├── FTPStorage.php    # FTP 存储实现
│   ├── ImageLocalizer.php # 图片本地化处理
│   ├── ImageProcessor.php # 图片处理（压缩、水印）
│   ├── VideoProcessor.php # 视频处理（压缩、水印）
│   ├── MediaProxy.php    # 媒体代理
│   ├── Utils.php         # 工具类
│   ├── Update.php        # 更新检查
│   ├── display.php       # 设置页面
│   └── hooks.php         # WordPress 钩子
├── static/
│   └── post.js           # 前端脚本
└── logs/
    └── app.log           # 日志文件
```

## 更新日志

### v1.0.0

- 重构插件命名为 VeMedia
- 支持多种存储后端（S3/WebDAV/FTP）
- 支持所有类型媒体文件上传
- 添加系统环境检测和提示功能
- 添加 MIME 类型备用识别方案
- 优化代码结构和命名规范
- 添加详细的日志记录和调试工具

### 新增功能

#### 图片处理
- 图片本地化功能：自动下载并上传 Base64 和外部图片
- 图片压缩功能：支持 JPEG、PNG、WebP 格式压缩
- 水印功能：支持文字和图片水印，可自定义位置和透明度

#### 视频处理
- 视频压缩功能：使用 FFmpeg 进行视频压缩
- 视频水印功能：支持为视频添加水印
- 分辨率限制：支持限制视频最大分辨率

#### 媒体代理
- 添加媒体代理端点，支持代理访问云端媒体文件
- 自动处理 .htaccess 配置

## 环境要求补充

### 视频处理要求
- **FFmpeg**（可选）：用于视频压缩和水印功能
  - Linux: 通常位于 `/usr/bin/ffmpeg` 或 `/usr/local/bin/ffmpeg`
  - Windows: 需要手动安装并配置环境变量
  - 如果未安装，视频处理功能将被禁用，但不影响其他功能

### 图片处理要求
- **GD 库** 或 **ImageMagick**（推荐）：用于图片压缩和水印功能
  - PHP 通常默认启用 GD 库
  - ImageMagick 提供更好的图片处理质量

## 技术支持

如遇问题，请提供以下信息：
1. PHP 版本
2. 已加载的 PHP 扩展列表
3. WordPress 版本
4. 错误日志内容（设置页面底部）
5. 具体的错误描述和复现步骤