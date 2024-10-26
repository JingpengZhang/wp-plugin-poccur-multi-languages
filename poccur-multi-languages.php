<?php

/**
 * Plugin Name: Poccur Multi Languages
 * Description: 多语言插件（基于URL）.
 * Version: 1.0
 * Author: jingpeng_zhang@foxmail.com
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 添加重写规则，允许 WordPress 将 /en/page-url 和 /zh/page-url 映射到相应的页面内容。
 */
function custom_language_rewrite_rules()
{
    // 首页重写规则
    add_rewrite_rule('^(en|zh|ja)?/?$', 'index.php', 'top');

    // 其他页面重写规则
    add_rewrite_rule('^(en|zh|ja)/([^/]+)?', 'index.php?pagename=$matches[2]', 'top');
}
add_action('init', 'custom_language_rewrite_rules');

/**
 * 如果 URL 包含语言前缀则不重定向
 */
function prevent_redirect_on_language_prefixed_urls($redirect_url, $requested_url)
{
    if (preg_match('/\/(en|zh|ja)\//', $requested_url)) {
        return false;
    }
    return $redirect_url;
}
add_filter('redirect_canonical', 'prevent_redirect_on_language_prefixed_urls', 10, 2);

/**
 * 如果 url 中包含语言前缀，切换 wordpress 语言
 */
function load_custom_language()
{
    // 检查 URL 中的语言前缀
    $uri = trim($_SERVER['REQUEST_URI'], '/');
    $lang = substr($uri, 0, 2); // 获取 /en 或 /zh 等前缀
    $supported_languages = ['en', 'zh', 'ja']; // 支持的语言列表

    if (in_array($lang, $supported_languages)) {
        if (in_array($lang, ['ja'])) {
            $locale = $lang;
        } else {
            $locale = $lang === 'en' ? 'en_US' : 'zh_CN';
        }
    } else {
        $locale = 'en_US'; // 默认语言
    }

    switch_to_locale($locale);
    load_theme_textdomain('zhongming', get_template_directory() . '/languages');
}
add_action('template_redirect', 'load_custom_language');

/**
 * 根据当前语言为超链接添加语言前缀
 */
function get_translated_link($url)
{
    // 解析传入的完整 URL
    $url_parts = parse_url($url);

    // 分割路径
    $lang = get_locale(); // 获取当前语言前缀

    if ($lang == 'en_US') {
        $lang = 'en';
    } else if ($lang == 'zh_CN') {
        $lang = 'zh';
    }

    // 构建新的 URL
    $new_url = '/' . $lang . '/' . trim($url_parts['path'], '/');

    // 返回完整的目标 URL
    return home_url($new_url);
}

// 刷新重写规则
function mlu_flush_rewrite_rules()
{
    custom_language_rewrite_rules(); // 重新注册重写规则
    flush_rewrite_rules(); // 刷新规则
}
register_activation_hook(__FILE__, 'mlu_flush_rewrite_rules');
// 禁用插件时刷新规则
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');
