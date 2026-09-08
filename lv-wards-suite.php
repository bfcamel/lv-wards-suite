<?php
/**
 * Plugin Name: Люди и Верблюды — Подопечные
 * Plugin URI:  https://bfcamel.ru/
 * Description: База подопечных, каталог, карусель на главной и блок «Другие истории» фонда «Люди и Верблюды». Совместим с прежними шорткодами, API, CPT, taxonomy и meta-полями.
 * Version:     1.0.1
 * Author:      БФ «Люди и Верблюды»
 * Text Domain: lv-wards-suite
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LV_WARDS_SUITE_VERSION', '1.0.1');
define('LV_WARDS_SUITE_FILE', __FILE__);
define('LV_WARDS_SUITE_DIR', plugin_dir_path(__FILE__));


/**
 * Возвращает URL каталога подопечных без жёсткой привязки к одному slug.
 *
 * Сначала ищем актуальную опубликованную страницу /wards/, затем сохраняем
 * совместимость со старой структурой /home/wards/. Если ни одна страница
 * не найдена, используем /wards/ как безопасный fallback.
 */
function lv_wards_suite_get_catalog_url() {
    static $catalog_url = null;

    if ($catalog_url !== null) {
        return $catalog_url;
    }

    $candidate_paths = [
        'wards',
        'home/wards',
    ];

    foreach ($candidate_paths as $path) {
        $page = get_page_by_path($path, OBJECT, 'page');

        if (
            $page instanceof WP_Post &&
            $page->post_status === 'publish'
        ) {
            $permalink = get_permalink($page->ID);

            if ($permalink) {
                $catalog_url = $permalink;
                break;
            }
        }
    }

    if ($catalog_url === null) {
        $catalog_url = home_url('/wards/');
    }

    /**
     * Позволяет переопределить URL каталога без изменения кода плагина.
     */
    $catalog_url = apply_filters(
        'lv_wards_suite_catalog_url',
        $catalog_url
    );

    return $catalog_url;
}


/**
 * Возвращает ID опубликованного подопечного, связанного с указанной
 * страницей истории. Нужен, чтобы модуль «Другие истории» не выполнялся
 * на всех страницах сайта.
 */
function lv_wards_suite_get_ward_id_by_history_page($page_id = 0) {
    $page_id = $page_id
        ? absint($page_id)
        : absint(get_queried_object_id());

    if (!$page_id) {
        return 0;
    }

    $ward_ids = get_posts([
        'post_type'              => 'lv_ward',
        'post_status'            => 'publish',
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'meta_key'               => '_lv_ward_history_page_id',
        'meta_value'             => $page_id,
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);

    return !empty($ward_ids)
        ? absint($ward_ids[0])
        : 0;
}

/**
 * Загружает модули максимально поздно на plugins_loaded.
 *
 * Это позволяет безопасно активировать плагин до отключения старых
 * WPCode-сниппетов: если старый модуль уже определил свой публичный API,
 * соответствующий модуль плагина переходит в режим ожидания и не вызывает
 * fatal error из-за повторного объявления функций.
 */
function lv_wards_suite_bootstrap() {
    $legacy_modules = [];

    // 1. База подопечных и публичный API.
    if (
        function_exists('lv_get_ward_data') ||
        function_exists('lv_get_wards') ||
        function_exists('lv_render_ward_metabox')
    ) {
        $legacy_modules[] = 'База подопечных';
    } else {
        require_once LV_WARDS_SUITE_DIR . 'includes/base.php';
    }

    // 2. Каталог / страница всех подопечных.
    if (
        function_exists('lv_render_wards_page') ||
        function_exists('lv_wards_page_url')
    ) {
        $legacy_modules[] = 'Страница подопечных';
    } else {
        require_once LV_WARDS_SUITE_DIR . 'includes/wards-page.php';
    }

    // 3. Карусель на главной.
    if (
        function_exists('lv_wards_home_render') ||
        function_exists('lv_wards_home_enqueue_script')
    ) {
        $legacy_modules[] = 'Подопечные на главной';
    } else {
        require_once LV_WARDS_SUITE_DIR . 'includes/wards-home.php';
    }

    // 4. Блок «Другие истории».
    if (function_exists('lv_other_ward_stories_script')) {
        $legacy_modules[] = 'Другие истории';
    } else {
        require_once LV_WARDS_SUITE_DIR . 'includes/other-stories.php';
    }

    if ($legacy_modules) {
        $GLOBALS['lv_wards_suite_legacy_modules'] = $legacy_modules;
        add_action('admin_notices', 'lv_wards_suite_legacy_notice');
    }
}
add_action('plugins_loaded', 'lv_wards_suite_bootstrap', PHP_INT_MAX);

/**
 * Показывает только администраторам понятное предупреждение о старых
 * активных сниппетах. Плагин при этом не ломает сайт и не перехватывает
 * модуль, который уже работает из legacy-сниппета.
 */
function lv_wards_suite_legacy_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $modules = isset($GLOBALS['lv_wards_suite_legacy_modules'])
        ? (array) $GLOBALS['lv_wards_suite_legacy_modules']
        : [];

    if (!$modules) {
        return;
    }

    $labels = array_map('esc_html', $modules);

    echo '<div class="notice notice-warning"><p>';
    echo '<strong>Люди и Верблюды — Подопечные:</strong> ';
    echo 'обнаружены активные старые сниппеты: ' . implode(', ', $labels) . '. ';
    echo 'Соответствующие модули плагина временно не загружены. ';
    echo 'Отключите старые сниппеты в WPCode — на следующей загрузке плагин автоматически возьмёт их функции на себя без изменения данных, шорткодов и внешнего вида.';
    echo '</p></div>';
}
