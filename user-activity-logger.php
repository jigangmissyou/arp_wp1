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
require_once __DIR__ .'/UidProcessor.php';
// 创建日志记录器
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;


// 设置日志
$log = new Logger('user_activity');
$handler = new StreamHandler(__DIR__ . '/user-activity.log', Logger::INFO);
$handler->setFormatter(new JsonFormatter());

$log->pushHandler($handler);
$log->pushProcessor(new UidProcessor());

// $log->pushProcessor(new UidProcessor());

// 记录用户点击链接的事件

function log_user_activity_on_click() {
    global $log;

    if ( ! isset( $_GET['action'] ) ) {
        return;
    }

    $action = sanitize_text_field( $_GET['action'] );
    $user_ip = $_SERVER['REMOTE_ADDR'];

    // 处理不同的动作
    switch ( $action ) {
        case 'page_scrolled_to_bottom':
            log_scroll_event( '用户滚动到页面底部', $user_ip );
            break;
        
        case 'page_scrolled_to_50_percent':
            log_scroll_event( '用户滚动到页面 50% 位置', $user_ip );
            break;
        
        case 'page_stay_duration':
            log_page_stay_duration( $user_ip );
            break;
        
        case 'accordion_toggle':
            log_accordion_toggle( $user_ip );
            break;
        
        default:
            log_click_event( $user_ip );
            break;
    }
}

// 记录滚动事件
function log_scroll_event( $message, $user_ip ) {
    global $log;
    $log->info( $message, [ 'user_ip' => $user_ip ] );
}

// 记录页面停留时间
function log_page_stay_duration( $user_ip ) {
    global $log;
    $duration = isset( $_GET['duration'] ) ? intval( $_GET['duration'] ) : 0;
    $log->info( '用户页面停留时间', [ 'duration' => $duration . ' 秒', 'user_ip' => $user_ip ] );
}

// 记录折叠面板交互
function log_accordion_toggle( $user_ip ) {
    global $log;
    $accordion_id = isset( $_GET['accordion_id'] ) ? sanitize_text_field( $_GET['accordion_id'] ) : 'unknown';
    $title = isset( $_GET['title'] ) ? sanitize_text_field( $_GET['title'] ) : 'unknown';
    $state = isset( $_GET['state'] ) ? sanitize_text_field( $_GET['state'] ) : 'unknown';
    
    $log->info( '用户折叠面板交互', [
        'accordion_id' => $accordion_id,
        'title' => $title,
        'state' => $state,
        'user_ip' => $user_ip
    ] );
}

// 记录点击事件
function log_click_event( $user_ip ) {
    global $log;
    $log->info( '用户触发了点击事件', [
        'url' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
        'user_ip' => $user_ip
    ] );
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

            document.addEventListener('DOMContentLoaded', function () {
                let startTime = Date.now(); // 记录进入页面的时间
                // 监听用户离开页面
                window.addEventListener('beforeunload', function () {
                    let endTime = Date.now(); // 记录离开页面的时间
                    let duration = Math.round((endTime - startTime) / 1000); // 计算停留秒数
                    // 发送日志到服务器
                    navigator.sendBeacon('<?php echo esc_url( home_url() ); ?>/?action=page_stay_duration&duration=' + duration);
                });

                // 获取按钮元素
                const button = document.querySelector('.kb-button');
                // 如果按钮存在
                if (button) {
                    // 监听按钮点击事件
                    button.addEventListener('click', function () {
                        fetch('<?php echo esc_url( home_url() ); ?>/?action=clicked_link', {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/json',
                            }
                        }).then(response => {
                            console.log('点击记录已发送');
                        }).catch(error => console.log('请求失败', error));
                    });
                }

                const submit = document.querySelector('.kb-forms-submit');
                if (submit) {
                    // 监听按钮点击事件
                    submit.addEventListener('click', function () {
                        fetch('<?php echo esc_url( home_url() ); ?>/?action=submitted_form', {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/json',
                            }
                        }).then(response => {
                            console.log('form submit记录已发送');
                        }).catch(error => console.log('请求失败', error));
                    });
                }

                const accordionButtons = document.querySelectorAll(".kt-blocks-accordion-header");

                accordionButtons.forEach(button => {
                    button.addEventListener("click", function () {
                        // 获取按钮的 ID
                        const accordionId = this.getAttribute("id");
                        // 获取折叠状态
                        const isExpanded = this.getAttribute("aria-expanded") === "true";
                        // 获取标题内容
                        const title = this.querySelector(".kt-blocks-accordion-title")?.innerText || "Unknown Title";
                        const state = isExpanded ? "Opened" : "Closed";

                        // 发送日志请求到服务器
                        fetch('<?php echo esc_url( home_url() ); ?>/?action=accordion_toggle&accordion_id=' + encodeURIComponent(accordionId) + '&title=' + encodeURIComponent(title) + '&state=' + encodeURIComponent(state), {
                            method: 'GET',
                            headers: {
                                'Content-Type': 'application/json',
                            }
                        }).then(response => {
                            console.log('Accordion event logged:', { accordionId, title, state });
                        }).catch(error => console.log('请求失败', error));
                    });
                });

                window.addEventListener('scroll', function() {
                    // 用户滚动到页面的底部时触发
                    if (window.scrollY + window.innerHeight >= document.documentElement.scrollHeight) {
                        fetch('?action=page_scrolled_to_bottom');
                    }
                });

                // 记录用户滚动到页面的 50% 位置
                window.addEventListener('scroll', function() {
                    var scrollPercentage = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
                    if (scrollPercentage >= 50 && !window.scrolled50) {
                        window.scrolled50 = true; // 防止多次记录
                        fetch('?action=page_scrolled_to_50_percent');
                    }
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


