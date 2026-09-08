<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * Фонд «Люди и Верблюды»
 * Страница всех подопечных
 * ============================================================
 *
 * Шорткод:
 *
 * [lv_wards_page]
 *
 * Требует активный сниппет "База подопечных",
 * создающий тип записи lv_ward и функцию lv_get_ward_data().
 *
 * Возможности:
 * - данные автоматически берутся из базы подопечных;
 * - 9 карточек на страницу;
 * - серверная пагинация;
 * - адаптив 3 / 2 / 1 карточка;
 * - изображения через WordPress Media Library;
 * - автоматические srcset/sizes;
 * - центральное кадрирование фотографий через object-fit: cover;
 * - единый фон страницы от шапки до подвала;
 * - чёрно-белые фото ушедших подопечных;
 * - без JavaScript;
 * - без дублирования данных.
 *
 * ============================================================
 */


/* ============================================================
 * 1. РЕГИСТРАЦИЯ ШОРТКОДА
 * ============================================================ */

add_shortcode('lv_wards_page', 'lv_render_wards_page');


/* ============================================================
 * 1.1. КЛАСС СТРАНИЦЫ
 * ============================================================ */

add_filter('body_class', 'lv_wards_page_body_class');


/**
 * Определяет страницу, на которой размещён каталог подопечных.
 *
 * Проверяем обычное содержимое, данные Elementor и, как надёжный
 * резервный вариант, рабочий slug страницы /wards/.
 */
function lv_is_wards_page_request() {

    if (
        is_admin() ||
        !is_singular()
    ) {
        return false;
    }


    $page_id = get_queried_object_id();

    if (!$page_id) {
        return false;
    }


    if (is_page('wards')) {
        return true;
    }


    $post_content = (string) get_post_field(
        'post_content',
        $page_id
    );

    if (
        $post_content &&
        has_shortcode(
            $post_content,
            'lv_wards_page'
        )
    ) {
        return true;
    }


    /*
     * Elementor хранит содержимое виджетов в post meta,
     * поэтому одного has_shortcode() бывает недостаточно.
     */
    $elementor_data = get_post_meta(
        $page_id,
        '_elementor_data',
        true
    );

    return (
        is_string($elementor_data) &&
        strpos(
            $elementor_data,
            'lv_wards_page'
        ) !== false
    );
}


/**
 * Класс нужен, чтобы фон страницы продолжался от шапки до подвала,
 * включая внешние отступы темы и Elementor.
 */
function lv_wards_page_body_class($classes) {

    if (lv_is_wards_page_request()) {
        $classes[] = 'lv-wards-page';
    }

    return array_values(
        array_unique($classes)
    );
}


/* ============================================================
 * 2. ОСНОВНОЙ РЕНДЕР
 * ============================================================ */

function lv_render_wards_page($atts = []) {

    /*
     * Проверяем, что база подопечных подключена.
     */
    if (
        !post_type_exists('lv_ward') ||
        !function_exists('lv_get_ward_data')
    ) {

        if (current_user_can('manage_options')) {

            return '
                <div style="
                    max-width:900px;
                    margin:40px auto;
                    padding:20px;
                    border:1px solid #d63638;
                    background:#fff;
                    color:#1d2327;
                ">
                    <strong>База подопечных не найдена.</strong><br>
                    Убедитесь, что сниппет с типом записи
                    <code>lv_ward</code> активирован.
                </div>
            ';
        }

        return '';
    }


    /* ========================================================
     * НАСТРОЙКИ
     * ======================================================== */

    $per_page = 9;


    /* ========================================================
     * ТЕКУЩАЯ СТРАНИЦА
     * ======================================================== */

    $current_page = 1;

    if (isset($_GET['wards_page'])) {

        $current_page = max(
            1,
            absint(
                wp_unslash($_GET['wards_page'])
            )
        );
    }


    /* ========================================================
     * ОБЩЕЕ КОЛИЧЕСТВО
     * ======================================================== */

    $counts = wp_count_posts('lv_ward');

    $total_wards = isset($counts->publish)
        ? (int) $counts->publish
        : 0;

    $total_pages = max(
        1,
        (int) ceil($total_wards / $per_page)
    );


    /*
     * Если пользователь запросил страницу,
     * которой уже не существует.
     */
    if ($current_page > $total_pages) {
        $current_page = $total_pages;
    }


    /* ========================================================
     * ЗАПРОС
     * ======================================================== */

    $query = new WP_Query([
        'post_type'              => 'lv_ward',
        'post_status'            => 'publish',

        'posts_per_page'         => $per_page,
        'paged'                  => $current_page,

        /*
         * Сначала используется поле "Порядок"
         * из редактора подопечного.
         *
         * Если у нескольких записей одинаковый порядок,
         * они сортируются по имени.
         */
        'orderby' => [
            'menu_order' => 'ASC',
            'title'      => 'ASC',
        ],

        'order'                  => 'ASC',

        'ignore_sticky_posts'    => true,

        /*
         * Количество мы уже посчитали через wp_count_posts(),
         * поэтому дополнительный SQL_CALC_FOUND_ROWS не нужен.
         */
        'no_found_rows'          => true,

        'update_post_term_cache' => false,
    ]);


    /* ========================================================
     * HTML
     * ======================================================== */

    ob_start();

    ?>

    <div id="lvWards">

        <section
            class="w-list"
            aria-label="Подопечные фонда"
        >

            <div class="w-list__inner">


                <?php if ($query->have_posts()): ?>


                    <div class="w-grid">


                        <?php

                        $card_index = 0;

                        foreach ($query->posts as $ward_post):

                            $ward = lv_get_ward_data(
                                $ward_post->ID
                            );

                            if (!$ward) {
                                continue;
                            }

                            $card_index++;

                            $name = isset($ward['name'])
                                ? $ward['name']
                                : '';

                            $type_label = isset($ward['type_label'])
                                ? $ward['type_label']
                                : '';

                            $text_1 = isset($ward['text_1'])
                                ? $ward['text_1']
                                : '';

                            $text_2 = isset($ward['text_2'])
                                ? $ward['text_2']
                                : '';

                            $photo_id = isset($ward['photo_id'])
                                ? absint($ward['photo_id'])
                                : 0;

                            $history_url = isset($ward['history_url'])
                                ? $ward['history_url']
                                : '';

                            /*
                             * Актуальная база подопечных возвращает
                             * is_deceased на основании переключателя
                             * «Умер» из админки.
                             */
                            $is_deceased = array_key_exists(
                                'is_deceased',
                                $ward
                            )
                                ? !empty(
                                    $ward['is_deceased']
                                )
                                : (
                                    get_post_meta(
                                        $ward_post->ID,
                                        '_lv_ward_deceased',
                                        true
                                    ) === '1'
                                );

                            $help_label = $is_deceased
                                ? 'Помочь другим'
                                : 'Помочь';

                            $help_aria_label = $is_deceased
                                ? 'Помочь другим подопечным фонда'
                                : 'Помочь ' . $name;


                            $card_classes = [
                                'w-card',
                            ];

                            if ($is_deceased) {
                                $card_classes[] =
                                    'w-card--deceased';
                            }


                            /*
                             * Готовим изображение заранее.
                             *
                             * Важно: одного attachment ID недостаточно.
                             * Если файл удалён или метаданные повреждены,
                             * wp_get_attachment_image() вернёт пустую строку.
                             * В таком случае ниже будет показана заглушка.
                             */
                            $image_html = '';

                            if ($photo_id) {

                                $image_alt = trim(
                                    (string) get_post_meta(
                                        $photo_id,
                                        '_wp_attachment_image_alt',
                                        true
                                    )
                                );

                                if ($image_alt === '') {
                                    $image_alt = $name;
                                }


                                $image_attributes = [
                                    'class' => 'w-card__img',

                                    'alt' => $image_alt,

                                    /* Первый кадр — вероятный LCP-элемент. */
                                    'loading' =>
                                        $card_index === 1
                                            ? 'eager'
                                            : 'lazy',

                                    'decoding' => 'async',

                                    'sizes' =>
                                        '(max-width: 620px) calc(100vw - 72px), ' .
                                        '(max-width: 980px) calc(50vw - 70px), ' .
                                        '360px',
                                ];

                                if ($card_index === 1) {
                                    $image_attributes[
                                        'fetchpriority'
                                    ] = 'high';
                                }


                                $image_html =
                                    wp_get_attachment_image(
                                        $photo_id,
                                        'medium_large',
                                        false,
                                        $image_attributes
                                    );

                                $image_html = is_string(
                                    $image_html
                                )
                                    ? $image_html
                                    : '';
                            }

                            ?>


                            <article
                                class="<?php
                                    echo esc_attr(
                                        implode(
                                            ' ',
                                            $card_classes
                                        )
                                    );
                                ?>"
                            >


                                <!-- ============================
                                     ФОТОГРАФИЯ
                                     ============================ -->

                                <div class="w-card__media">

                                    <?php if ($image_html): ?>

                                        <?php
                                        /*
                                         * HTML сформирован WordPress и уже
                                         * содержит безопасные атрибуты,
                                         * srcset, width и height.
                                         */
                                        echo $image_html;
                                        ?>

                                    <?php else: ?>

                                        <div
                                            class="w-card__placeholder"
                                            aria-label="Фотография пока не добавлена"
                                        >

                                            <span>
                                                Фото скоро
                                            </span>

                                        </div>

                                    <?php endif; ?>

                                </div>


                                <!-- ============================
                                     СОДЕРЖИМОЕ
                                     ============================ -->

                                <div class="w-card__body">


                                    <?php if (
                                        $type_label ||
                                        $is_deceased
                                    ): ?>

                                        <div class="w-card__meta">

                                            <?php if ($type_label): ?>

                                                <span class="w-card__label">

                                                    <?php
                                                    echo esc_html(
                                                        $type_label
                                                    );
                                                    ?>

                                                </span>

                                            <?php endif; ?>


                                            <?php if ($is_deceased): ?>

                                                <span
                                                    class="w-card__memory"
                                                    aria-label="Подопечный ушёл из жизни"
                                                >
                                                    Страница памяти
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    <?php endif; ?>


                                    <h2 class="w-card__title">

                                        <?php
                                        echo esc_html($name);
                                        ?>

                                    </h2>


                                    <?php if ($text_1): ?>

                                        <p class="w-card__sub">

                                            <?php
                                            echo esc_html(
                                                $text_1
                                            );
                                            ?>

                                        </p>

                                    <?php endif; ?>


                                    <?php if ($text_2): ?>

                                        <p class="w-card__desc">

                                            <?php
                                            echo esc_html(
                                                $text_2
                                            );
                                            ?>

                                        </p>

                                    <?php endif; ?>


                                    <!-- ========================
                                         КНОПКИ
                                         ======================== -->

                                    <div class="w-cta">


                                        <a
                                            href="<?php
                                                echo esc_url(
                                                    home_url(
                                                        '/support/'
                                                    )
                                                );
                                            ?>"
                                            class="w-help"
                                            aria-label="<?php
                                                echo esc_attr(
                                                    $help_aria_label
                                                );
                                            ?>"
                                        >
                                            <?php
                                            echo esc_html(
                                                $help_label
                                            );
                                            ?>
                                        </a>


                                        <?php if ($history_url): ?>

                                            <a
                                                href="<?php
                                                    echo esc_url(
                                                        $history_url
                                                    );
                                                ?>"
                                                class="w-link"
                                            >

                                                <span
                                                    class="w-arrows"
                                                    aria-hidden="true"
                                                >

                                                    <svg
                                                        viewBox="0 0 34 16"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="2"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    >

                                                        <path
                                                            d="M3 3l6 5-6 5"
                                                        />

                                                        <path
                                                            d="M14 3l6 5-6 5"
                                                        />

                                                    </svg>

                                                </span>

                                                <span>
                                                    История
                                                </span>

                                            </a>

                                        <?php endif; ?>


                                    </div>

                                </div>

                            </article>


                        <?php endforeach; ?>


                    </div>


                    <?php

                    /*
                     * Пагинация показывается только тогда,
                     * когда страниц больше одной.
                     */

                    if ($total_pages > 1) {

                        echo lv_render_wards_pagination(
                            $current_page,
                            $total_pages
                        );
                    }

                    ?>


                <?php else: ?>


                    <div class="w-empty">

                        <h2>
                            Здесь скоро появятся наши подопечные
                        </h2>

                        <p>
                            Мы добавляем истории животных.
                        </p>

                    </div>


                <?php endif; ?>


            </div>

        </section>

    </div>


    <style>

    /* ==========================================================
       БЕСШОВНАЯ СТЫКОВКА С ШАПКОЙ И ПОДВАЛОМ
       ========================================================== */

    /*
     * Раньше белая полоса сверху скрывалась отрицательным
     * margin-top у самого блока. Это смещало элемент, но не меняло
     * фон внешних отступов темы — поэтому полоса появлялась снизу.
     *
     * Теперь весь контентный слой страницы окрашен в фон каталога.
     * Шапка и подвал сохраняют собственные фоны и перекрывают его.
     */
    body.lv-wards-page {
        background-color: #f4efee;
    }


    body.lv-wards-page :where(
        #content,
        .site-content,
        .site-main,
        [data-elementor-type="wp-page"]
    ) {
        background-color: #f4efee !important;
    }


    /*
     * Окрашиваем только те оболочки Elementor, внутри которых
     * действительно находится этот shortcode.
     */
    body.lv-wards-page :where(
        .e-con,
        .elementor-section,
        .elementor-column
    ):has(#lvWards) {
        background-color: #f4efee !important;
    }


    body.lv-wards-page
    .elementor-widget-shortcode:has(
        > .elementor-widget-container > #lvWards
    ),
    body.lv-wards-page
    .elementor-widget-container:has(
        > #lvWards
    ) {
        margin-block: 0 !important;
        padding-block: 0 !important;
    }


    body.lv-wards-page :where(
        footer,
        .site-footer,
        .elementor-location-footer
    ) {
        margin-top: 0 !important;
    }


    /* На случай пустого абзаца, добавленного редактором после shortcode. */
    body.lv-wards-page #lvWards + p:empty {
        display: none !important;
        margin: 0 !important;
    }


    /* ==========================================================
       ROOT
       ========================================================== */

    #lvWards {
        --teal: #18434e;
        --coral: #c13b2e;
        --mint: #8ed4d3;
        --mint-text: #12414c;
        --cream: #f4efee;
        --border: #e7dddb;
        --muted: #6e6a63;
        --ink: #243b40;


        position: relative;
        z-index: 0;

        width: 100%;
        max-width: none;

        margin: 0 !important;
        padding: 0;

        background: var(--cream);
        color: var(--teal);

        /*
         * Полноэкранный фон без width:100vw и отрицательных
         * смещений. Поэтому горизонтальный скролл не появляется,
         * даже если у браузера видимая полоса прокрутки.
         */
        box-shadow:
            0 0 0 100vmax var(--cream);

        clip-path:
            inset(0 -100vmax);

        -webkit-font-smoothing: antialiased;
        text-rendering: optimizeLegibility;
    }


    #lvWards *,
    #lvWards *::before,
    #lvWards *::after {
        box-sizing: border-box;
    }


    #lvWards :where(
        h1,
        h2,
        h3,
        p,
        figure
    ) {
        margin: 0;
    }


    /* ==========================================================
       СПИСОК
       ========================================================== */

    #lvWards .w-list {
        width: 100%;
        background: var(--cream);
    }


    #lvWards .w-list__inner {
        width: 100%;
        max-width: 1200px;

        margin: 0 auto;

        padding:
            clamp(48px, 5vw, 72px)
            clamp(20px, 4vw, 40px)
            clamp(64px, 7vw, 96px);
    }


    /* ==========================================================
       GRID
       ========================================================== */

    #lvWards .w-grid {
        display: grid;

        grid-template-columns:
            repeat(3, minmax(0, 1fr));

        align-items: stretch;

        gap: clamp(16px, 1.8vw, 26px);
    }


    /* ==========================================================
       CARD
       ========================================================== */

    #lvWards .w-card {
        display: flex;
        flex-direction: column;

        min-width: 0;
        height: 100%;

        padding: 22px;

        border: 1px solid var(--border);
        border-radius: 2px;

        background: #fff;

        transition:
            box-shadow .2s ease,
            transform .2s ease;
    }


    #lvWards .w-card:hover {
        box-shadow:
            0 18px 40px rgba(24, 67, 78, .10);

        transform: translateY(-2px);
    }


    #lvWards .w-card--deceased {
        border-color:
            rgba(24, 67, 78, .20);

        background: #fbfaf9;
    }


    /* ==========================================================
       PHOTO
       ========================================================== */

    #lvWards .w-card__media {
        position: relative;

        width: 100%;
        height: 210px;

        overflow: hidden;

        background: #e8eeee;
    }


    #lvWards .w-card__img {
        position: absolute !important;

        inset: 0 !important;

        display: block !important;

        width: 100% !important;
        height: 100% !important;

        max-width: none !important;

        margin: 0 !important;

        object-fit: cover !important;
        object-position: center center !important;
    }


    /* Фотографии ушедших подопечных выводятся чёрно-белыми. */
    #lvWards .w-card--deceased
    .w-card__img {
        filter: grayscale(1);
    }


    /*
     * Если у подопечного ещё нет фотографии.
     */
    #lvWards .w-card__placeholder {
        display: flex;

        align-items: center;
        justify-content: center;

        width: 100%;
        height: 100%;

        color: rgba(24, 67, 78, .55);

        font-size: 13px;
        font-weight: 700;
        letter-spacing: .08em;

        text-transform: uppercase;
    }


    /* ==========================================================
       BODY
       ========================================================== */

    #lvWards .w-card__body {
        display: flex;

        flex: 1 1 auto;
        flex-direction: column;

        min-width: 0;

        padding-top: 22px;
    }


    #lvWards .w-card__meta {
        display: flex;

        align-items: center;
        justify-content: space-between;

        flex-wrap: wrap;

        gap: 8px 12px;

        margin-bottom: 12px;
    }


    #lvWards .w-card__label {
        display: block;

        margin: 0;

        color: var(--coral);

        font-size: 13px;
        font-weight: 700;
        letter-spacing: .03em;

        line-height: 1.2;
    }


    #lvWards .w-card__memory {
        display: inline-flex;

        align-items: center;

        min-height: 24px;

        padding: 4px 9px;

        border: 1px solid
            rgba(24, 67, 78, .18);

        border-radius: 999px;

        background:
            rgba(24, 67, 78, .06);
        color: var(--teal);

        font-size: 10px;
        font-weight: 700;
        letter-spacing: .10em;

        line-height: 1;

        text-transform: uppercase;
    }


    #lvWards .w-card--deceased
    .w-card__label {
        color: var(--muted);
    }


    #lvWards .w-card__title {
        color: var(--teal);

        font-size: 23px;
        font-weight: 400;

        line-height: 1.25;
    }


    #lvWards .w-card__sub {
        min-height: 38px;

        margin-top: 8px;

        color: var(--teal);

        font-size: 14px;
        font-style: italic;

        line-height: 1.35;

        opacity: .85;
    }


    #lvWards .w-card__desc {
        min-height: 64px;

        margin:
            10px
            0
            22px;

        color: var(--muted);

        font-size: 14.5px;

        line-height: 1.45;
    }


    /* ==========================================================
       CTA
       ========================================================== */

    #lvWards .w-cta {
        display: flex;

        align-items: center;
        justify-content: space-between;

        gap: 14px;

        margin-top: auto;

        padding-top: 4px;
    }


    #lvWards .w-help {
        flex: 0 0 auto;

        padding: 12px 28px;

        border-radius: 30px;

        background: var(--coral);
        color: #fff;

        font-size: 14px;
        font-weight: 700;
        letter-spacing: .02em;

        line-height: 1;

        text-decoration: none !important;

        transition:
            filter .2s ease,
            transform .15s ease;
    }


    #lvWards .w-help:hover {
        color: #fff;

        filter: brightness(1.05);

        transform: translateY(-1px);
    }


    /*
     * У умершего подопечного CTA ведёт не к адресной помощи ему,
     * а к поддержке остальных животных фонда.
     */
    #lvWards .w-card--deceased
    .w-help {
        padding-right: 20px;
        padding-left: 20px;

        background: var(--teal);
    }


    #lvWards .w-link {
        display: flex;

        align-items: center;

        gap: 10px;

        color: var(--teal);

        font-size: 12px;
        font-weight: 700;
        letter-spacing: .08em;

        line-height: 1;

        text-decoration: none !important;
        text-transform: uppercase;

        transition: opacity .2s ease;
    }


    #lvWards .w-link:hover {
        color: var(--teal);

        opacity: .7;
    }


    #lvWards .w-arrows {
        display: flex;

        flex: 0 0 auto;

        color: var(--coral);
    }


    #lvWards .w-arrows svg {
        display: block;

        width: 28px;
        height: 14px;
    }


    /* ==========================================================
       ПАГИНАЦИЯ
       ========================================================== */

    #lvWards .w-pagination {
        display: flex;

        align-items: center;
        justify-content: center;

        flex-wrap: wrap;

        gap: 8px;

        margin-top:
            clamp(38px, 5vw, 58px);
    }


    #lvWards .w-page {
        display: inline-flex;

        align-items: center;
        justify-content: center;

        min-width: 44px;
        height: 44px;

        padding: 0 14px;

        border: 1px solid
            rgba(24, 67, 78, .20);

        border-radius: 22px;

        background: transparent;
        color: var(--teal);

        font-size: 14px;
        font-weight: 700;

        line-height: 1;

        text-decoration: none !important;

        transition:
            border-color .18s ease,
            background .18s ease,
            color .18s ease,
            transform .18s ease;
    }


    #lvWards a.w-page:hover {
        border-color: var(--teal);

        background: rgba(24, 67, 78, .05);
        color: var(--teal);

        transform: translateY(-1px);
    }


    #lvWards .w-page--current {
        border-color: var(--coral);

        background: var(--coral);
        color: #fff;
    }


    #lvWards .w-page--prev,
    #lvWards .w-page--next {
        min-width: 94px;
    }


    #lvWards .w-page__arrow {
        position: relative;

        top: -1px;

        font-size: 17px;
        font-weight: 400;
    }


    #lvWards .w-page--prev
    .w-page__arrow {
        margin-right: 7px;
    }


    #lvWards .w-page--next
    .w-page__arrow {
        margin-left: 7px;
    }


    #lvWards .w-page-dots {
        display: inline-flex;

        align-items: center;
        justify-content: center;

        min-width: 30px;
        height: 44px;

        color: rgba(24, 67, 78, .55);

        font-size: 16px;
        font-weight: 700;
    }


    /* ==========================================================
       EMPTY STATE
       ========================================================== */

    #lvWards .w-empty {
        padding:
            clamp(46px, 7vw, 80px)
            20px;

        text-align: center;
    }


    #lvWards .w-empty h2 {
        color: var(--teal);

        font-size: clamp(27px, 4vw, 38px);
        font-weight: 400;

        line-height: 1.2;
    }


    #lvWards .w-empty p {
        margin-top: 12px;

        color: var(--muted);

        font-size: 16px;

        line-height: 1.5;
    }


    /* ==========================================================
       ACCESSIBILITY
       ========================================================== */

    #lvWards a:focus-visible {
        outline: 3px solid var(--mint);

        outline-offset: 4px;
    }


    /* ==========================================================
       TABLET
       ========================================================== */

    @media (max-width: 980px) {

        #lvWards .w-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

    }


    /* ==========================================================
       MOBILE
       ========================================================== */

    @media (max-width: 620px) {

        #lvWards .w-list__inner {
            padding-left: 18px;
            padding-right: 18px;
        }


        #lvWards .w-grid {
            grid-template-columns: 1fr;

            max-width: 460px;

            margin: 0 auto;
        }


        #lvWards .w-card {
            padding: 18px;
        }


        #lvWards .w-card__media {
            height:
                clamp(
                    210px,
                    60vw,
                    300px
                );
        }


        #lvWards .w-card__body {
            padding-top: 18px;
        }


        #lvWards .w-card__sub,
        #lvWards .w-card__desc {
            min-height: 0;
        }


        #lvWards .w-pagination {
            gap: 6px;

            margin-top: 38px;
        }


        #lvWards .w-page {
            min-width: 40px;
            height: 40px;

            padding: 0 12px;
        }


        #lvWards .w-page--prev,
        #lvWards .w-page--next {
            min-width: 40px;

            padding: 0 13px;

            font-size: 0;
        }


        #lvWards .w-page--prev
        .w-page__arrow,
        #lvWards .w-page--next
        .w-page__arrow {
            margin: 0;

            font-size: 18px;
        }


        #lvWards .w-page-dots {
            height: 40px;
        }

    }


    /* ==========================================================
       REDUCED MOTION
       ========================================================== */

    @media (prefers-reduced-motion: reduce) {

        #lvWards .w-card,
        #lvWards .w-help,
        #lvWards .w-link,
        #lvWards .w-page {
            transition: none;
        }

    }

    </style>

    <?php

    wp_reset_postdata();

    return ob_get_clean();
}


/* ============================================================
 * 3. PAGINATION
 * ============================================================ */

function lv_render_wards_pagination(
    $current_page,
    $total_pages
) {

    $current_page = max(
        1,
        absint($current_page)
    );

    $total_pages = max(
        1,
        absint($total_pages)
    );


    if ($total_pages <= 1) {
        return '';
    }


    /*
     * URL страницы, на которой размещён shortcode.
     */
    $base_url = get_permalink(
        get_queried_object_id()
    );

    if (!$base_url) {
        $base_url = home_url('/');
    }


    /*
     * Определяем номера, которые нужно показать.
     *
     * Например:
     *
     * 1 2 3 … 8
     *
     * или:
     *
     * 1 … 4 5 6 … 12
     */
    $pages = [
        1,
        $total_pages,
    ];


    for (
        $i = $current_page - 2;
        $i <= $current_page + 2;
        $i++
    ) {

        if (
            $i >= 1 &&
            $i <= $total_pages
        ) {
            $pages[] = $i;
        }
    }


    $pages = array_values(
        array_unique($pages)
    );

    sort($pages);


    ob_start();

    ?>

    <nav
        class="w-pagination"
        aria-label="Навигация по страницам подопечных"
    >


        <?php if ($current_page > 1): ?>

            <a
                class="w-page w-page--prev"
                href="<?php
                    echo esc_url(
                        lv_wards_page_url(
                            $base_url,
                            $current_page - 1
                        )
                    );
                ?>"
                rel="prev"
                aria-label="Предыдущая страница"
            >

                <span
                    class="w-page__arrow"
                    aria-hidden="true"
                >
                    ←
                </span>

                Назад

            </a>

        <?php endif; ?>


        <?php

        $previous_page_number = 0;

        foreach ($pages as $page_number):

            /*
             * Добавляем многоточие между
             * разорванными диапазонами.
             */
            if (
                $previous_page_number &&
                $page_number >
                    $previous_page_number + 1
            ) {

                ?>

                <span
                    class="w-page-dots"
                    aria-hidden="true"
                >
                    …
                </span>

                <?php
            }


            if ($page_number === $current_page):

                ?>

                <span
                    class="w-page w-page--current"
                    aria-current="page"
                >
                    <?php
                    echo esc_html($page_number);
                    ?>
                </span>

                <?php

            else:

                ?>

                <a
                    class="w-page"
                    href="<?php
                        echo esc_url(
                            lv_wards_page_url(
                                $base_url,
                                $page_number
                            )
                        );
                    ?>"
                    aria-label="<?php
                        echo esc_attr(
                            'Страница ' .
                            $page_number
                        );
                    ?>"
                >
                    <?php
                    echo esc_html($page_number);
                    ?>
                </a>

                <?php

            endif;


            $previous_page_number =
                $page_number;

        endforeach;

        ?>


        <?php if ($current_page < $total_pages): ?>

            <a
                class="w-page w-page--next"
                href="<?php
                    echo esc_url(
                        lv_wards_page_url(
                            $base_url,
                            $current_page + 1
                        )
                    );
                ?>"
                rel="next"
                aria-label="Следующая страница"
            >

                Далее

                <span
                    class="w-page__arrow"
                    aria-hidden="true"
                >
                    →
                </span>

            </a>

        <?php endif; ?>


    </nav>

    <?php

    return ob_get_clean();
}


/* ============================================================
 * 4. URL СТРАНИЦЫ ПАГИНАЦИИ
 * ============================================================ */

function lv_wards_page_url(
    $base_url,
    $page_number
) {

    $page_number = max(
        1,
        absint($page_number)
    );


    /*
     * На первой странице параметр вообще убираем.
     *
     * /wards/
     *
     * вместо:
     *
     * /wards/?wards_page=1
     */
    if ($page_number === 1) {

        $url = remove_query_arg(
            'wards_page',
            $base_url
        );

    } else {

        $url = add_query_arg(
            'wards_page',
            $page_number,
            $base_url
        );

    }


    /*
     * После перехода браузер сразу возвращает
     * пользователя к сетке подопечных.
     */
    return $url . '#lvWards';
}
