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

function poccur_ml_load_custom_theme_textdomain()
{
    load_theme_textdomain('poccur_ml', plugins_url() . '/languages');
}
add_action('after_setup_theme', 'poccur_ml_load_custom_theme_textdomain');


/**
 * 添加重写规则，允许 WordPress 将 /en/page-url 和 /zh/page-url 映射到相应的页面内容。
 */
// function custom_language_rewrite_rules()
// {
//     // 首页重写规则
//     add_rewrite_rule('^(en|zh|ja)?/?$', 'index.php', 'top');

//     // 其他页面重写规则
//     add_rewrite_rule('^(en|zh|ja)/([^/]+)?', 'index.php?pagename=$matches[2]', 'top');
// }
// add_action('init', 'custom_language_rewrite_rules');

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


// 创建插件设置菜单
function poccur_ml_create_settings_page()
{
    add_menu_page(
        '多语言', // 页面标题
        '多语言-Poccur', // 菜单标题
        'manage_options', // 权限
        'multi-language-urls', // 菜单slug
        'poccur_ml_settings_page', // 显示的函数
        'dashicons-translation', // 菜单图标
        90 // 菜单位置
    );
}
add_action('admin_menu', 'poccur_ml_create_settings_page');

// 设置页面内容
function poccur_ml_settings_page()
{
?>
    <div class="wrap">
        <h1>多语言（Poccur）设置</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('mlu_options_group');
            do_settings_sections('multi-language-urls');
            submit_button();
            ?>
        </form>
    </div>
<?php
}

// 注册设置
function poccur_ml_register_settings()
{
    register_setting('mlu_options_group', 'mlu_supported_languages', [
        'sanitize_callback' => 'mlu_sanitize_languages', // 设置数据的清理回调
        'default'           => [] // 默认值
    ]);

    add_settings_section(
        'mlu_settings_section',
        '基础设置',
        'poccur_ml_settings_section_callback',
        'multi-language-urls'
    );

    add_settings_field(
        'mlu_supported_languages',
        '语言列表',
        'mlu_supported_languages_callback',
        'multi-language-urls',
        'mlu_settings_section'
    );
}
add_action('admin_init', 'poccur_ml_register_settings');

// 设置部分描述
function poccur_ml_settings_section_callback()
{
    echo '请选择支持的语言:';
}

// 语言选项回调
function mlu_supported_languages_callback()
{
    $options = get_option('mlu_supported_languages', []);
    $languages = ['en' => '英语', 'zh' => '简体中文', 'ja' => '日语'];

    foreach ($languages as $value => $label) {
        $checked = in_array($value, $options) ? 'checked' : '';
        echo '<label><input type="checkbox" name="mlu_supported_languages[]" value="' . esc_attr($value) . '" ' . $checked . '> ' . esc_html($label) . '</label><br>';
    }
}

// 语言选项清理回调
function mlu_sanitize_languages($input)
{
    // 只保留有效的语言选项
    $valid_languages = ['en', 'zh', 'ja'];
    return array_intersect($input, $valid_languages);
}

// 重写规则
function poccur_ml_add_rewrite_rules()
{
    $options = get_option('mlu_supported_languages', []);
    if (empty($options)) {
        return; // 如果没有选择任何语言，则不添加重写规则
    }

    // 首页重写规则
    add_rewrite_rule('^(' . implode('|', $options) . ')?/?$', 'index.php', 'top');

    // 其他页面重写规则
    add_rewrite_rule('^(' . implode('|', $options) . ')/([^/]+)/?', 'index.php?pagename=$matches[2]', 'top');
}
add_action('init', 'poccur_ml_add_rewrite_rules');

// 在设置保存后刷新重写规则
function mlu_flush_rewrite_rules_on_save($option_name)
{
    if ($option_name === 'mlu_supported_languages') {
        error_log('Flushing rewrite rules on save for: ' . $option_name);
        // 刷新重写规则
        flush_rewrite_rules();
    }
}
add_action('updated_option', 'mlu_flush_rewrite_rules_on_save');

// 刷新重写规则
function mlu_flush_rewrite_rules()
{
    poccur_ml_add_rewrite_rules(); // 重新注册重写规则
    flush_rewrite_rules(); // 刷新规则
}
register_activation_hook(__FILE__, 'mlu_flush_rewrite_rules');
// 禁用插件时刷新规则
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');


// 返回用户勾选的可用语言的函数
function poccur_ml_get_selected_languages()
{
    // 获取插件设置中支持的语言
    $available_languages = get_option('mlu_supported_languages', []);

    // 检查是否为空，返回默认值或其他处理
    if (empty($available_languages)) {
        return [get_locale()]; // 返回默认语言，例如 'en'
    }

    return $available_languages;
}

function _poccur_ml_e($text)
{
    return _e($text, "poccur_ml");
}

function _poccur_ml__($text)
{
    return __($text, "poccur_ml");
}
