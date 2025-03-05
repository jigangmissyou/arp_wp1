<?php
/**
 * Plugin Name: User Activity Logger
 * Description: 记录用户在首页页面上的行为，如点击链接和观看视频
 * Version: 1.0
 * Author: Your Name
 */

// 防止直接访问文件
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 引入 Composer 自动加载文件
require_once __DIR__ . '/vendor/autoload.php';

// 创建日志记录器
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// 设置日志
$log = new Logger('user_activity');
$log->pushHandler(new StreamHandler(__DIR__ . '/user-activity.log', Logger::INFO));

// 记录用户点击链接的事件
function log_user_activity_on_click() {
    global $log;

    if ( isset( $_GET['action'] ) && $_GET['action'] === 'clicked_link' ) {
        $log->info('用户点击了链接', ['url' => $_SERVER['HTTP_REFERER'], 'user_ip' => $_SERVER['REMOTE_ADDR']]);
    }
}
add_action('init', 'log_user_activity_on_click');

// 记录用户观看视频的事件
function log_user_video_watching() {
    global $log;

    if ( isset( $_GET['action'] ) && $_GET['action'] === 'watched_video' ) {
        $log->info('用户观看了视频', ['video_id' => $_GET['video_id'], 'user_ip' => $_SERVER['REMOTE_ADDR']]);
    }
}
add_action('init', 'log_user_video_watching');

// 添加前端脚本，用于触发日志记录
function add_user_activity_scripts() {
    // 只在首页启用
    if ( is_front_page() ) {
        ?>
        <script type="text/javascript">
            // 点击链接时，记录日志
            document.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    const url = e.target.href;
                    fetch(url + '?action=clicked_link');
                });
            });

            // 用户观看视频时，记录日志
            document.querySelectorAll('.video-player').forEach(function(player) {
                player.addEventListener('play', function() {
                    const videoId = player.getAttribute('data-video-id');
                    fetch('?action=watched_video&video_id=' + videoId);
                });
            });
        </script>
        <?php
    }
}
add_action('wp_footer', 'add_user_activity_scripts');
