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

require_once plugin_dir_path(__FILE__) . 'ajax/ajax-switch-lang.php';

/**
 * 加载多语言文件
 */
add_action('plugins_loaded', function () {
    load_plugin_textdomain('poccur-ml', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

/**
 * 如果 URL 包含语言前缀则不重定向
 */
function prevent_redirect_on_language_prefixed_urls($redirect_url, $requested_url)
{
    if (preg_match('/\/(en_US|zh_CN|ja|fr_FR)\//', $requested_url)) {
        return false;
    }
    return $redirect_url;
}
add_filter('redirect_canonical', 'prevent_redirect_on_language_prefixed_urls', 10, 2);

/**
 * 如果 url 中包含语言前缀，切换 wordpress 语言
 */
function redirect_to_language()
{
    // 获取当前请求的 URI
    $uri = trim($_SERVER['REQUEST_URI'], '/');

    // 使用正则表达式匹配 URL 中的语言前缀
    if (preg_match('/^(en_US|zh_CN|ja|fr_FR)(\/.*)?$/', $uri, $matches)) {
        // 提取语言前缀
        $lang = $matches[1];

        // 切换到对应的语言
        switch_to_locale($lang);
    } else {
        // 默认语言设置
        switch_to_locale('zh_CN'); // 设置默认语言
    }
}
add_action('template_redirect', 'redirect_to_language');

/**
 * 根据当前语言为超链接添加语言前缀
 */
function get_translated_link($url)
{
    if (preg_match('/^\/(en_US|zh_CN|ja|fr_FR)(\/.*)?$/', esc_url($_SERVER['REQUEST_URI']), $matches)) {
        // 解析传入的完整 URL
        $url_parts = parse_url($url);

        // 分割路径
        $lang = get_locale(); // 获取当前语言前缀

        // 构建新的 URL
        $new_url = '/' . $lang . '/' . trim($url_parts['path'], '/');

        // 返回完整的目标 URL
        return home_url($new_url);
    } else {
        return $url;
    }
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
    $languages = ['zh_CN' => '简体中文', 'en_US' => '英文',  'ja' => '日文', 'fr_FR' => "法文"];

    foreach ($languages as $value => $label) {
        $checked = in_array($value, $options) ? 'checked' : '';
        echo '<label><input type="checkbox" name="mlu_supported_languages[]" value="' . esc_attr($value) . '" ' . $checked . '> ' . esc_html($label) . '</label><br>';
    }
}

// 语言选项清理回调
function mlu_sanitize_languages($input)
{
    // 只保留有效的语言选项
    $valid_languages = ['zh_CN', 'en_US', 'ja', 'fr_FR'];
    return array_intersect($input, $valid_languages);
}

// 重写规则
function poccur_ml_add_rewrite_rules()
{
    $options = get_option('mlu_supported_languages', []);
    foreach ($options as $opt) {
        error_log($opt);
    }
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

// 插件激活时刷新重写规则
register_activation_hook(
    __FILE__,
    function () {
        poccur_ml_add_rewrite_rules(); // 重新注册重写规则
        flush_rewrite_rules(); // 刷新规则
    }
);

// 插件禁用时清理规则
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

/**
 * 返回用户勾选的可用语言的函数
 */
function poccur_ml_get_selected_languages()
{
    // 获取插件设置中支持的语言
    $available_languages = get_option('mlu_supported_languages', []);

    // 获取当前语言
    $current_language = get_locale(); // 获取当前语言，例如 'en_US'

    // 检查当前语言是否在可用语言中
    if (in_array($current_language, $available_languages)) {
        // 将当前语言移到数组的开头
        $available_languages = array_diff($available_languages, [$current_language]); // 移除当前语言
        array_unshift($available_languages, $current_language); // 添加到数组开头
    } else {
        // 如果没有可用语言，返回默认语言
        return [$current_language];
    }

    return $available_languages;
}

function _poccur_ml_e($text)
{
    return _e($text, "poccur-ml");
}

function _poccur_ml__($text)
{
    return __($text, "poccur-ml");
}
