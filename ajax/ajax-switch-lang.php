<?php
if (!defined('ABSPATH')) {
    exit;
}

// 处理 AJAX 请求
function handle_switch_language()
{
    error_log('到了' . $_POST['current_path']);
    // 检查请求的参数
    if (isset($_POST['target_lang'])) {
        $current_path = sanitize_text_field($_POST['current_path']);
        $current_lang = sanitize_text_field($_POST['current_lang']);
        $target_lang = sanitize_text_field($_POST['target_lang']);

        // 调用相应的 PHP 函数来处理语言切换
        switch_to_language($current_path, $current_lang, $target_lang);

        // 返回成功响应
        wp_send_json_success(['message' => 'Language switched to ' . $target_lang]);
    } else {
        wp_send_json_error(['message' => 'No language parameter provided']);
    }
}

add_action('wp_ajax_switch_language', 'handle_switch_language'); // 处理已登录用户的 AJAX 请求
add_action('wp_ajax_nopriv_switch_language', 'handle_switch_language'); // 处理未登录用户的 AJAX 请求

function switch_to_language($current_path, $current_lang, $target_lang)
{

    if (in_array($target_lang, get_option('mlu_supported_languages', []))) {

        $path = $current_path;

        // 使用正则表达式提取语言前缀
        if (preg_match('/^\/(en_US|zh_CN|ja|fr_FR)(\/.*)?$/', $current_path, $matches)) {
            $path = $matches[2];
        }

        error_log(get_locale() . $target_lang);

        if ($current_lang != $target_lang) {
            switch_to_locale($target_lang);

            error_log('目标语言' . $target_lang);
            // 返回 JSON 响应
            wp_send_json_success(['redirect_url' => '/' . $target_lang . $path]);
        }
        // 结束 AJAX 请求
        wp_die();
    }
}
