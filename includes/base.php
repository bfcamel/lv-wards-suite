<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * Фонд «Люди и Верблюды»
 * База подопечных
 * Чистая версия без миграций
 * ============================================================
 *
 * Возможности:
 *
 * - база подопечных lv_ward;
 * - редактируемые типы через taxonomy;
 * - дата рождения;
 * - автоматический возраст;
 * - статус "Умер";
 * - дата смерти;
 * - Ч/Б фотографии умерших;
 * - два текстовых блока;
 * - фотография из Media Library;
 * - связь с существующей страницей WordPress по ID;
 * - автоматический permalink страницы истории;
 * - ручной порядок.
 *
 * API:
 *
 * lv_get_ward_data()
 * lv_get_wards()
 * lv_get_ward_image()
 * lv_get_ward_history_url()
 *
 * ============================================================
 */


/* ============================================================
 * 1. POST TYPE
 * ============================================================ */

add_action('init', function () {

    $labels = [
        'name'                  => 'Подопечные',
        'singular_name'         => 'Подопечный',
        'menu_name'             => 'Подопечные',
        'name_admin_bar'        => 'Подопечный',

        'add_new'               => 'Добавить',
        'add_new_item'          => 'Добавить подопечного',
        'new_item'              => 'Новый подопечный',
        'edit_item'             => 'Редактировать подопечного',
        'view_item'             => 'Просмотреть',
        'all_items'             => 'Все подопечные',

        'search_items'          => 'Найти подопечного',
        'not_found'             => 'Подопечные не найдены',
        'not_found_in_trash'    => 'В корзине подопечных нет',

        'featured_image'        => 'Фотография',
        'set_featured_image'    => 'Выбрать фотографию',
        'remove_featured_image' => 'Удалить фотографию',
    ];


    register_post_type('lv_ward', [

        'labels' => $labels,

        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,

        'show_ui'           => true,
        'show_in_menu'      => true,
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => false,

        'menu_position' => 20,
        'menu_icon'     => 'dashicons-heart',

        'supports' => [
            'title',
            'page-attributes',
        ],

        'has_archive' => false,
        'rewrite'     => false,

        'show_in_rest' => false,
    ]);

});


/* ============================================================
 * 2. TAXONOMY — ТИПЫ ПОДОПЕЧНЫХ
 * ============================================================ */

add_action('init', function () {

    $labels = [
        'name'          => 'Типы подопечных',
        'singular_name' => 'Тип подопечного',

        'search_items'  => 'Найти тип',
        'all_items'     => 'Все типы',

        'edit_item'     => 'Редактировать тип',
        'update_item'   => 'Обновить тип',

        'add_new_item'  => 'Добавить новый тип',
        'new_item_name' => 'Название нового типа',

        'menu_name'     => 'Типы подопечных',
    ];


    register_taxonomy(
        'lv_ward_type',
        ['lv_ward'],
        [
            'labels' => $labels,

            'hierarchical' => false,

            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => true,

            /*
             * Используем собственный select
             * внутри карточки подопечного.
             */
            'meta_box_cb' => false,

            'show_admin_column' => false,

            'rewrite'      => false,
            'show_in_rest' => false,
        ]
    );

}, 11);


/* ============================================================
 * 3. PLACEHOLDER ИМЕНИ
 * ============================================================ */

add_filter(
    'enter_title_here',
    function ($title, $post) {

        if (
            $post &&
            $post->post_type === 'lv_ward'
        ) {
            return 'Например: Басира Башир';
        }


        return $title;

    },
    10,
    2
);


/* ============================================================
 * 4. ПРОВЕРКА ДАТЫ
 * ============================================================ */

function lv_ward_sanitize_date($value) {

    $value = sanitize_text_field(
        (string) $value
    );


    if (!$value) {
        return '';
    }


    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $value
        )
    ) {
        return '';
    }


    [$year, $month, $day] = array_map(
        'intval',
        explode('-', $value)
    );


    if (
        !checkdate(
            $month,
            $day,
            $year
        )
    ) {
        return '';
    }


    return sprintf(
        '%04d-%02d-%02d',
        $year,
        $month,
        $day
    );

}


/* ============================================================
 * 5. РАСЧЁТ ВОЗРАСТА
 * ============================================================ */

function lv_calculate_ward_age(
    $birth_date,
    $is_deceased = false,
    $death_date = ''
) {

    $birth_date = lv_ward_sanitize_date(
        $birth_date
    );


    if (!$birth_date) {
        return null;
    }


    try {

        $timezone = wp_timezone();


        $birth = new DateTimeImmutable(
            $birth_date,
            $timezone
        );


        /*
         * Для умершего животного возраст
         * фиксируется на дату смерти.
         */
        if ($is_deceased) {

            $death_date = lv_ward_sanitize_date(
                $death_date
            );


            if (!$death_date) {
                return null;
            }


            $end = new DateTimeImmutable(
                $death_date,
                $timezone
            );

        } else {

            $end = new DateTimeImmutable(
                wp_date(
                    'Y-m-d',
                    null,
                    $timezone
                ),
                $timezone
            );

        }


        if ($end < $birth) {
            return null;
        }


        return (int) $birth
            ->diff($end)
            ->y;


    } catch (Throwable $e) {

        return null;

    }

}


/* ============================================================
 * 6. ФОРМАТИРОВАНИЕ ВОЗРАСТА
 * ============================================================ */

function lv_format_ward_age($age) {

    if ($age === null) {
        return '';
    }


    $age = absint($age);


    if ($age === 0) {
        return 'меньше года';
    }


    $mod_100 = $age % 100;
    $mod_10  = $age % 10;


    if (
        $mod_100 >= 11 &&
        $mod_100 <= 14
    ) {

        $word = 'лет';

    } elseif ($mod_10 === 1) {

        $word = 'год';

    } elseif (
        $mod_10 >= 2 &&
        $mod_10 <= 4
    ) {

        $word = 'года';

    } else {

        $word = 'лет';

    }


    return $age . ' ' . $word;

}


/* ============================================================
 * 7. ФОРМАТИРОВАНИЕ ДАТЫ
 * ============================================================ */

function lv_format_ward_date($date) {

    $date = lv_ward_sanitize_date(
        $date
    );


    if (!$date) {
        return '';
    }


    try {

        $datetime = new DateTimeImmutable(
            $date,
            wp_timezone()
        );


        return wp_date(
            'j F Y',
            $datetime->getTimestamp(),
            wp_timezone()
        );


    } catch (Throwable $e) {

        return '';

    }

}


/* ============================================================
 * 8. ТИП ПОДОПЕЧНОГО
 * ============================================================ */

function lv_get_ward_type_term($post_id) {

    $terms = wp_get_object_terms(
        absint($post_id),
        'lv_ward_type'
    );


    if (
        is_wp_error($terms) ||
        empty($terms)
    ) {
        return null;
    }


    return $terms[0];

}


/* ============================================================
 * 9. НАЗВАНИЕ ТИПА
 * ============================================================ */

function lv_get_ward_type_label($type) {

    if (!$type) {
        return '';
    }


    if (is_numeric($type)) {

        $term = get_term(
            absint($type),
            'lv_ward_type'
        );

    } else {

        $term = get_term_by(
            'slug',
            sanitize_title(
                (string) $type
            ),
            'lv_ward_type'
        );

    }


    if (
        !$term ||
        is_wp_error($term)
    ) {
        return '';
    }


    return $term->name;

}


/* ============================================================
 * 10. ID СТРАНИЦЫ ИСТОРИИ
 * ============================================================ */

function lv_get_ward_history_page_id($post_id) {

    $page_id = absint(
        get_post_meta(
            absint($post_id),
            '_lv_ward_history_page_id',
            true
        )
    );


    if (!$page_id) {
        return 0;
    }


    $page = get_post(
        $page_id
    );


    if (
        !$page ||
        $page->post_type !== 'page' ||
        $page->post_status === 'trash'
    ) {
        return 0;
    }


    return $page_id;

}


/* ============================================================
 * 11. URL СТРАНИЦЫ ИСТОРИИ
 * ============================================================ */

function lv_get_ward_history_url($post_id) {

    $page_id = lv_get_ward_history_page_id(
        $post_id
    );


    if (!$page_id) {
        return '';
    }


    /*
     * В публичных карточках ссылка появляется
     * только после публикации страницы.
     */
    if (
        get_post_status($page_id) !== 'publish'
    ) {
        return '';
    }


    $url = get_permalink(
        $page_id
    );


    return $url
        ? $url
        : '';

}


/* ============================================================
 * 12. META BOX
 * ============================================================ */

add_action('add_meta_boxes', function () {

    add_meta_box(
        'lv_ward_info',
        'Карточка подопечного',
        'lv_render_ward_metabox',
        'lv_ward',
        'normal',
        'high'
    );

});


/* ============================================================
 * 13. META BOX — RENDER
 * ============================================================ */

function lv_render_ward_metabox($post) {

    wp_nonce_field(
        'lv_save_ward',
        'lv_ward_nonce'
    );


    /* --------------------------------------------------------
     * TYPE
     * -------------------------------------------------------- */

    $current_terms = wp_get_object_terms(
        $post->ID,
        'lv_ward_type',
        [
            'fields' => 'ids',
        ]
    );


    $type_term_id = (
        !is_wp_error($current_terms) &&
        !empty($current_terms)
    )
        ? absint($current_terms[0])
        : 0;


    $types = get_terms([
        'taxonomy'   => 'lv_ward_type',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ]);


    /* --------------------------------------------------------
     * META
     * -------------------------------------------------------- */

    $text_1 = get_post_meta(
        $post->ID,
        '_lv_ward_text_1',
        true
    );


    $text_2 = get_post_meta(
        $post->ID,
        '_lv_ward_text_2',
        true
    );


    $photo_id = absint(
        get_post_meta(
            $post->ID,
            '_lv_ward_photo_id',
            true
        )
    );


    $birth_date = get_post_meta(
        $post->ID,
        '_lv_ward_birth_date',
        true
    );


    $is_deceased = (
        get_post_meta(
            $post->ID,
            '_lv_ward_deceased',
            true
        ) === '1'
    );


    $death_date = get_post_meta(
        $post->ID,
        '_lv_ward_death_date',
        true
    );


    $history_page_id = lv_get_ward_history_page_id(
        $post->ID
    );


    /* --------------------------------------------------------
     * AGE
     * -------------------------------------------------------- */

    $age = lv_calculate_ward_age(
        $birth_date,
        $is_deceased,
        $death_date
    );


    $age_label = lv_format_ward_age(
        $age
    );


    /* --------------------------------------------------------
     * PHOTO
     * -------------------------------------------------------- */

    $photo_url = '';


    if ($photo_id) {

        $photo_url = wp_get_attachment_image_url(
            $photo_id,
            'medium'
        );

    }


    /* --------------------------------------------------------
     * HISTORY PAGE
     * -------------------------------------------------------- */

    $history_page = null;
    $history_page_title = '';
    $history_page_url = '';
    $history_page_edit_url = '';


    if ($history_page_id) {

        $history_page = get_post(
            $history_page_id
        );


        if ($history_page) {

            $history_page_title = get_the_title(
                $history_page_id
            );


            $history_page_url = get_permalink(
                $history_page_id
            );


            $history_page_edit_url = get_edit_post_link(
                $history_page_id,
                ''
            );

        }

    }


    $types_url = admin_url(
        'edit-tags.php' .
        '?taxonomy=lv_ward_type' .
        '&post_type=lv_ward'
    );


    $today = wp_date(
        'Y-m-d',
        null,
        wp_timezone()
    );


    $page_search_nonce = wp_create_nonce(
        'lv_ward_page_search'
    );

    ?>

    <style>

        .lv-ward-editor {
            max-width: 900px;
            padding: 8px 2px 18px;
        }


        .lv-field-section {
            margin-bottom: 26px;
            padding-bottom: 26px;

            border-bottom: 1px solid #eee;
        }


        .lv-field-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;

            border-bottom: 0;
        }


        .lv-ward-field > label,
        .lv-field-label {
            display: block;

            margin-bottom: 8px;

            color: #1d2327;

            font-size: 14px;
            font-weight: 600;
        }


        .lv-ward-field .description {
            display: block;

            margin-top: 7px;

            color: #646970;
        }


        .lv-ward-field select,
        .lv-ward-field input[type="date"],
        .lv-ward-field input[type="text"] {
            width: 100%;
            max-width: 600px;
        }


        .lv-ward-field textarea {
            width: 100%;
            min-height: 110px;

            resize: vertical;
        }


        /* ======================================================
           TWO COLUMNS
           ====================================================== */

        .lv-fields-row {
            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );

            gap: 24px;
        }


        /* ======================================================
           STATUS
           ====================================================== */

        .lv-status-box {
            display: flex;

            align-items: center;
            justify-content: space-between;

            gap: 20px;

            max-width: 600px;

            padding: 16px 18px;

            border: 1px solid #dcdcde;
            border-radius: 8px;

            background: #f6f7f7;
        }


        .lv-status-info strong {
            display: block;

            margin-bottom: 3px;
        }


        .lv-status-info span {
            color: #646970;

            font-size: 13px;
        }


        /* ======================================================
           SWITCH
           ====================================================== */

        .lv-switch {
            position: relative;

            display: inline-flex;

            align-items: center;

            flex: 0 0 auto;

            cursor: pointer;
        }


        .lv-switch input {
            position: absolute;

            width: 1px;
            height: 1px;

            opacity: 0;
        }


        .lv-switch__track {
            position: relative;

            display: block;

            width: 48px;
            height: 26px;

            border-radius: 30px;

            background: #8c8f94;

            transition: background .18s ease;
        }


        .lv-switch__track::after {
            position: absolute;

            top: 3px;
            left: 3px;

            width: 20px;
            height: 20px;

            border-radius: 50%;

            background: #fff;

            box-shadow:
                0 1px 3px rgba(0, 0, 0, .25);

            content: '';

            transition: transform .18s ease;
        }


        .lv-switch input:checked +
        .lv-switch__track {
            background: #2c3338;
        }


        .lv-switch input:checked +
        .lv-switch__track::after {
            transform: translateX(22px);
        }


        .lv-switch input:focus-visible +
        .lv-switch__track {
            outline: 2px solid #2271b1;

            outline-offset: 2px;
        }


        /* ======================================================
           AGE
           ====================================================== */

        .lv-age-box {
            display: flex;

            align-items: center;

            min-height: 40px;

            padding: 0 14px;

            border: 1px solid #dcdcde;
            border-radius: 4px;

            background: #f6f7f7;

            color: #1d2327;

            font-size: 14px;
            font-weight: 600;
        }


        .lv-age-box.is-empty {
            color: #787c82;

            font-weight: 400;
        }


        /* ======================================================
           PHOTO
           ====================================================== */

        .lv-photo-box {
            display: flex;

            align-items: flex-start;

            gap: 18px;

            flex-wrap: wrap;
        }


        .lv-photo-preview {
            display: flex;

            align-items: center;
            justify-content: center;

            width: 180px;
            height: 130px;

            overflow: hidden;

            border: 1px solid #dcdcde;
            border-radius: 10px;

            background: #f6f7f7;
        }


        .lv-photo-preview img {
            display: block;

            width: 100%;
            height: 100%;

            object-fit: cover;

            transition: filter .2s ease;
        }


        .lv-ward-editor.is-deceased
        .lv-photo-preview img {
            filter: grayscale(1);
        }


        .lv-photo-empty {
            padding: 15px;

            color: #787c82;

            font-size: 13px;

            text-align: center;
        }


        .lv-photo-actions {
            display: flex;

            gap: 8px;

            flex-wrap: wrap;
        }


        /* ======================================================
           PAGE PICKER
           ====================================================== */

        .lv-page-picker {
            position: relative;

            max-width: 600px;
        }


        .lv-page-picker__search {
            width: 100% !important;
            max-width: none !important;
        }


        .lv-page-picker__results {
            position: absolute;
            z-index: 100;

            top: calc(100% + 4px);
            left: 0;
            right: 0;

            max-height: 320px;

            overflow-y: auto;

            border: 1px solid #8c8f94;
            border-radius: 4px;

            background: #fff;

            box-shadow:
                0 8px 24px
                rgba(0, 0, 0, .12);
        }


        .lv-page-picker__results[hidden] {
            display: none !important;
        }


        .lv-page-result {
            display: block;

            width: 100%;

            padding: 10px 12px;

            border: 0;
            border-bottom: 1px solid #eee;

            background: #fff;

            color: #1d2327;

            cursor: pointer;

            text-align: left;
        }


        .lv-page-result:last-child {
            border-bottom: 0;
        }


        .lv-page-result:hover,
        .lv-page-result:focus {
            background: #f0f6fc;
        }


        .lv-page-result__title {
            display: block;

            font-weight: 600;
        }


        .lv-page-result__meta {
            display: block;

            margin-top: 3px;

            color: #646970;

            font-size: 12px;
        }


        .lv-page-picker__loading,
        .lv-page-picker__empty {
            padding: 12px;

            color: #646970;

            font-size: 13px;
        }


        /* ======================================================
           SELECTED PAGE
           ====================================================== */

        .lv-selected-page {
            max-width: 600px;

            margin-top: 12px;
        }


        .lv-selected-page__card {
            padding: 14px 16px;

            border: 1px solid #dcdcde;
            border-radius: 6px;

            background: #f6f7f7;
        }


        .lv-selected-page__title {
            display: block;

            color: #1d2327;

            font-weight: 600;
        }


        .lv-selected-page__url {
            display: block;

            max-width: 540px;

            margin-top: 4px;

            overflow: hidden;

            color: #646970;

            font-size: 12px;

            text-overflow: ellipsis;
            white-space: nowrap;
        }


        .lv-selected-page__actions {
            display: flex;

            gap: 6px;

            flex-wrap: wrap;

            margin-top: 10px;
        }


        .lv-selected-page__empty {
            padding: 12px 14px;

            border: 1px dashed #c3c4c7;
            border-radius: 6px;

            color: #646970;
        }


        /* ======================================================
           DEATH DATE
           ====================================================== */

        .lv-death-date[hidden] {
            display: none !important;
        }


        @media (max-width: 700px) {

            .lv-fields-row {
                grid-template-columns: 1fr;
            }


            .lv-status-box {
                align-items: flex-start;

                flex-direction: column;
            }

        }

    </style>


    <div
        id="lv-ward-editor"
        class="lv-ward-editor<?php echo $is_deceased ? ' is-deceased' : ''; ?>"
    >


        <!-- ====================================================
             TYPE
             ==================================================== -->

        <div class="lv-field-section">

            <div class="lv-ward-field">

                <label for="lv_ward_type_term">
                    Тип подопечного
                </label>


                <select
                    id="lv_ward_type_term"
                    name="lv_ward_type_term"
                >

                    <option value="">
                        — Не указан —
                    </option>


                    <?php if (!is_wp_error($types)): ?>

                        <?php foreach ($types as $type): ?>

                            <option
                                value="<?php echo esc_attr($type->term_id); ?>"
                                <?php selected($type_term_id, $type->term_id); ?>
                            >
                                <?php echo esc_html($type->name); ?>
                            </option>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </select>


                <span class="description">

                    <a
                        href="<?php echo esc_url($types_url); ?>"
                        target="_blank"
                    >
                        Управлять типами подопечных →
                    </a>

                </span>

            </div>

        </div>


        <!-- ====================================================
             BIRTH + AGE
             ==================================================== -->

        <div class="lv-field-section">

            <div class="lv-fields-row">


                <div class="lv-ward-field">

                    <label for="lv_ward_birth_date">
                        Дата рождения
                    </label>

                    <input
                        type="date"
                        id="lv_ward_birth_date"
                        name="lv_ward_birth_date"
                        value="<?php echo esc_attr($birth_date); ?>"
                    >

                </div>


                <div class="lv-ward-field">

                    <span class="lv-field-label">
                        Возраст
                    </span>

                    <div
                        id="lv-age-preview"
                        class="lv-age-box<?php echo $age_label ? '' : ' is-empty'; ?>"
                        data-today="<?php echo esc_attr($today); ?>"
                    >

                        <?php

                        if ($age_label) {

                            echo esc_html($age_label);

                        } elseif (
                            $is_deceased &&
                            $birth_date &&
                            !$death_date
                        ) {

                            echo 'Укажите дату смерти';

                        } else {

                            echo '—';

                        }

                        ?>

                    </div>


                    <span class="description">
                        Рассчитывается автоматически.
                    </span>

                </div>


            </div>

        </div>


        <!-- ====================================================
             STATUS
             ==================================================== -->

        <div class="lv-field-section">

            <div class="lv-ward-field">

                <span class="lv-field-label">
                    Статус
                </span>


                <div class="lv-status-box">

                    <div class="lv-status-info">

                        <strong>
                            Умер
                        </strong>

                        <span>
                            Фотографии подопечного на сайте будут
                            отображаться в чёрно-белом виде.
                        </span>

                    </div>


                    <label class="lv-switch">

                        <input
                            type="checkbox"
                            id="lv_ward_deceased"
                            name="lv_ward_deceased"
                            value="1"
                            <?php checked($is_deceased); ?>
                        >

                        <span
                            class="lv-switch__track"
                            aria-hidden="true"
                        ></span>

                    </label>

                </div>

            </div>

        </div>


        <!-- ====================================================
             DEATH DATE
             ==================================================== -->

        <div
            id="lv-death-date-section"
            class="lv-field-section lv-death-date"
            <?php echo $is_deceased ? '' : 'hidden'; ?>
        >

            <div class="lv-ward-field">

                <label for="lv_ward_death_date">
                    Дата смерти
                </label>

                <input
                    type="date"
                    id="lv_ward_death_date"
                    name="lv_ward_death_date"
                    value="<?php echo esc_attr($death_date); ?>"
                >

                <span class="description">
                    Используется для фиксации возраста.
                </span>

            </div>

        </div>


        <!-- ====================================================
             TEXT 1
             ==================================================== -->

        <div class="lv-field-section">

            <div class="lv-ward-field">

                <label for="lv_ward_text_1">
                    Текстовый блок №1
                </label>

                <textarea
                    id="lv_ward_text_1"
                    name="lv_ward_text_1"
                    rows="5"
                ><?php echo esc_textarea($text_1); ?></textarea>

                <span class="description">
                    Например: краткая история или характеристика.
                </span>

            </div>

        </div>


        <!-- ====================================================
             TEXT 2
             ==================================================== -->

        <div class="lv-field-section">

            <div class="lv-ward-field">

                <label for="lv_ward_text_2">
                    Текстовый блок №2
                </label>

                <textarea
                    id="lv_ward_text_2"
                    name="lv_ward_text_2"
                    rows="5"
                ><?php echo esc_textarea($text_2); ?></textarea>

                <span class="description">
                    Например: что сейчас необходимо животному.
                </span>

            </div>

        </div>


        <!-- ====================================================
             PHOTO
             ==================================================== -->

        <div class="lv-field-section">

            <div class="lv-ward-field">

                <label>
                    Фотография
                </label>


                <input
                    type="hidden"
                    id="lv_ward_photo_id"
                    name="lv_ward_photo_id"
                    value="<?php echo esc_attr($photo_id); ?>"
                >


                <div class="lv-photo-box">


                    <div
                        class="lv-photo-preview"
                        id="lv-photo-preview"
                    >

                        <?php if ($photo_url): ?>

                            <img
                                src="<?php echo esc_url($photo_url); ?>"
                                alt=""
                            >

                        <?php else: ?>

                            <div class="lv-photo-empty">
                                Фотография<br>
                                не выбрана
                            </div>

                        <?php endif; ?>

                    </div>


                    <div class="lv-photo-actions">

                        <button
                            type="button"
                            class="button button-primary"
                            id="lv-select-photo"
                        >
                            Выбрать фотографию
                        </button>


                        <button
                            type="button"
                            class="button"
                            id="lv-remove-photo"
                            <?php echo $photo_id ? '' : 'style="display:none"'; ?>
                        >
                            Удалить
                        </button>

                    </div>


                </div>

            </div>

        </div>


        <!-- ====================================================
             HISTORY PAGE
             ==================================================== -->

        <div class="lv-field-section">

            <div class="lv-ward-field">

                <label for="lv-history-page-search">
                    Страница с историей
                </label>


                <input
                    type="hidden"
                    id="lv_ward_history_page_id"
                    name="lv_ward_history_page_id"
                    value="<?php echo esc_attr($history_page_id); ?>"
                >


                <div
                    class="lv-page-picker"
                    id="lv-page-picker"
                    data-nonce="<?php echo esc_attr($page_search_nonce); ?>"
                >

                    <input
                        type="text"
                        id="lv-history-page-search"
                        class="lv-page-picker__search"
                        autocomplete="off"
                        placeholder="Начните вводить название страницы…"
                        value="<?php echo esc_attr($history_page_title); ?>"
                    >


                    <div
                        id="lv-page-picker-results"
                        class="lv-page-picker__results"
                        hidden
                    ></div>

                </div>


                <div
                    id="lv-selected-page"
                    class="lv-selected-page"
                >

                    <?php if ($history_page): ?>

                        <div class="lv-selected-page__card">

                            <strong class="lv-selected-page__title">
                                <?php
                                echo esc_html(
                                    $history_page_title
                                );
                                ?>
                            </strong>


                            <span class="lv-selected-page__url">
                                <?php
                                echo esc_html(
                                    $history_page_url
                                );
                                ?>
                            </span>


                            <div class="lv-selected-page__actions">

                                <?php if ($history_page_url): ?>

                                    <a
                                        class="button"
                                        href="<?php echo esc_url($history_page_url); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Открыть
                                    </a>

                                <?php endif; ?>


                                <?php if ($history_page_edit_url): ?>

                                    <a
                                        class="button"
                                        href="<?php echo esc_url($history_page_edit_url); ?>"
                                        target="_blank"
                                    >
                                        Редактировать
                                    </a>

                                <?php endif; ?>


                                <button
                                    type="button"
                                    class="button"
                                    id="lv-clear-history-page"
                                >
                                    Убрать связь
                                </button>

                            </div>

                        </div>

                    <?php else: ?>

                        <div class="lv-selected-page__empty">
                            Страница с историей пока не выбрана.
                        </div>

                    <?php endif; ?>

                </div>


                <span class="description">
                    Сохраняется связь со страницей WordPress,
                    а не её URL. Можно менять постоянную ссылку
                    страницы без изменения карточки подопечного.
                </span>

            </div>

        </div>


    </div>

    <?php
}


/* ============================================================
 * 14. MEDIA LIBRARY
 * ============================================================ */

add_action(
    'admin_enqueue_scripts',
    function ($hook) {

        if (
            $hook !== 'post.php' &&
            $hook !== 'post-new.php'
        ) {
            return;
        }


        $screen = get_current_screen();


        if (
            !$screen ||
            $screen->post_type !== 'lv_ward'
        ) {
            return;
        }


        wp_enqueue_media();
        wp_enqueue_script('jquery');

    }
);


/* ============================================================
 * 15. AJAX — ПОИСК СТРАНИЦ
 * ============================================================ */

add_action(
    'wp_ajax_lv_ward_search_pages',
    function () {

        check_ajax_referer(
            'lv_ward_page_search',
            'nonce'
        );


        if (!current_user_can('edit_pages')) {

            wp_send_json_error(
                [
                    'message' =>
                        'Недостаточно прав.',
                ],
                403
            );

        }


        $query = isset($_GET['q'])
            ? sanitize_text_field(
                wp_unslash($_GET['q'])
            )
            : '';


        $args = [
            'post_type'      => 'page',

            'post_status' => [
                'publish',
                'draft',
                'pending',
                'private',
                'future',
            ],

            'posts_per_page' => 20,

            'orderby' => 'title',
            'order'   => 'ASC',
        ];


        if ($query !== '') {
            $args['s'] = $query;
        }


        $pages = get_posts(
            $args
        );


        $items = [];


        foreach ($pages as $page) {

            $status_object =
                get_post_status_object(
                    $page->post_status
                );


            $items[] = [

                'id' =>
                    (int) $page->ID,


                'title' =>
                    get_the_title(
                        $page->ID
                    ),


                'url' =>
                    get_permalink(
                        $page->ID
                    ),


                'edit_url' =>
                    get_edit_post_link(
                        $page->ID,
                        ''
                    ),


                'status' =>
                    $page->post_status,


                'status_label' =>
                    $status_object
                        ? $status_object->label
                        : $page->post_status,

            ];

        }


        wp_send_json_success([
            'items' =>
                $items,
        ]);

    }
);


/* ============================================================
 * 16. ADMIN JS
 * ============================================================ */

add_action(
    'admin_footer',
    function () {

        $screen = get_current_screen();


        if (
            !$screen ||
            $screen->post_type !== 'lv_ward'
        ) {
            return;
        }

        ?>

        <script>
        jQuery(function ($) {

            'use strict';


            /* ==================================================
               PHOTO
               ================================================== */

            var mediaFrame = null;


            $('#lv-select-photo').on(
                'click',
                function (event) {

                    event.preventDefault();


                    if (mediaFrame) {

                        mediaFrame.open();

                        return;
                    }


                    mediaFrame = wp.media({

                        title:
                            'Выберите фотографию подопечного',

                        button: {
                            text:
                                'Использовать фотографию'
                        },

                        multiple:
                            false,

                        library: {
                            type:
                                'image'
                        }

                    });


                    mediaFrame.on(
                        'select',
                        function () {

                            var attachment =
                                mediaFrame
                                    .state()
                                    .get('selection')
                                    .first()
                                    .toJSON();


                            $('#lv_ward_photo_id')
                                .val(
                                    attachment.id
                                );


                            var previewUrl =
                                attachment.url;


                            if (
                                attachment.sizes &&
                                attachment.sizes.medium
                            ) {

                                previewUrl =
                                    attachment
                                        .sizes
                                        .medium
                                        .url;

                            }


                            $('#lv-photo-preview')
                                .html(
                                    $('<img>', {
                                        src:
                                            previewUrl,
                                        alt:
                                            ''
                                    })
                                );


                            $('#lv-remove-photo')
                                .show();

                        }
                    );


                    mediaFrame.open();

                }
            );


            $('#lv-remove-photo').on(
                'click',
                function (event) {

                    event.preventDefault();


                    $('#lv_ward_photo_id')
                        .val('');


                    $('#lv-photo-preview')
                        .html(
                            '<div class="lv-photo-empty">' +
                                'Фотография<br>не выбрана' +
                            '</div>'
                        );


                    $(this).hide();

                }
            );


            /* ==================================================
               DECEASED
               ================================================== */

            var deceased =
                $('#lv_ward_deceased');


            var deathSection =
                $('#lv-death-date-section');


            var editor =
                $('#lv-ward-editor');


            function updateDeceasedUI() {

                var checked =
                    deceased.is(
                        ':checked'
                    );


                editor.toggleClass(
                    'is-deceased',
                    checked
                );


                deathSection.prop(
                    'hidden',
                    !checked
                );


                updateAge();

            }


            deceased.on(
                'change',
                updateDeceasedUI
            );


            /* ==================================================
               AGE
               ================================================== */

            var birthInput =
                $('#lv_ward_birth_date');


            var deathInput =
                $('#lv_ward_death_date');


            var agePreview =
                $('#lv-age-preview');


            function pluralYears(age) {

                var mod100 =
                    age % 100;


                var mod10 =
                    age % 10;


                if (
                    mod100 >= 11 &&
                    mod100 <= 14
                ) {
                    return 'лет';
                }


                if (mod10 === 1) {
                    return 'год';
                }


                if (
                    mod10 >= 2 &&
                    mod10 <= 4
                ) {
                    return 'года';
                }


                return 'лет';

            }


            function calculateYears(
                birthValue,
                endValue
            ) {

                if (
                    !birthValue ||
                    !endValue
                ) {
                    return null;
                }


                var birth =
                    birthValue
                        .split('-')
                        .map(Number);


                var end =
                    endValue
                        .split('-')
                        .map(Number);


                if (
                    birth.length !== 3 ||
                    end.length !== 3
                ) {
                    return null;
                }


                var age =
                    end[0] -
                    birth[0];


                if (
                    end[1] < birth[1] ||
                    (
                        end[1] === birth[1] &&
                        end[2] < birth[2]
                    )
                ) {

                    age -= 1;

                }


                if (age < 0) {
                    return null;
                }


                return age;

            }


            function updateAge() {

                var birth =
                    birthInput.val();


                if (!birth) {

                    agePreview
                        .text('—')
                        .addClass(
                            'is-empty'
                        );

                    return;
                }


                var isDeceased =
                    deceased.is(
                        ':checked'
                    );


                var endDate;


                if (isDeceased) {

                    endDate =
                        deathInput.val();


                    if (!endDate) {

                        agePreview
                            .text(
                                'Укажите дату смерти'
                            )
                            .addClass(
                                'is-empty'
                            );

                        return;
                    }

                } else {

                    endDate =
                        agePreview.data(
                            'today'
                        );

                }


                var age =
                    calculateYears(
                        birth,
                        endDate
                    );


                if (age === null) {

                    agePreview
                        .text('—')
                        .addClass(
                            'is-empty'
                        );

                    return;
                }


                var text;


                if (age === 0) {

                    text =
                        'меньше года';

                } else {

                    text =
                        age +
                        ' ' +
                        pluralYears(age);

                }


                agePreview
                    .text(text)
                    .removeClass(
                        'is-empty'
                    );

            }


            birthInput.on(
                'input change',
                updateAge
            );


            deathInput.on(
                'input change',
                updateAge
            );


            updateDeceasedUI();


            /* ==================================================
               PAGE PICKER
               ================================================== */

            var picker =
                $('#lv-page-picker');


            var searchInput =
                $('#lv-history-page-search');


            var results =
                $('#lv-page-picker-results');


            var pageIdInput =
                $('#lv_ward_history_page_id');


            var selectedContainer =
                $('#lv-selected-page');


            var searchTimer =
                null;


            var currentRequest =
                null;


            var nonce =
                picker.data(
                    'nonce'
                );


            function escapeHtml(value) {

                return String(
                    value || ''
                )
                    .replace(
                        /&/g,
                        '&amp;'
                    )
                    .replace(
                        /</g,
                        '&lt;'
                    )
                    .replace(
                        />/g,
                        '&gt;'
                    )
                    .replace(
                        /"/g,
                        '&quot;'
                    )
                    .replace(
                        /'/g,
                        '&#039;'
                    );

            }


            function hideResults() {

                results
                    .attr(
                        'hidden',
                        true
                    )
                    .empty();

            }


            function showSelectedPage(item) {

                var title =
                    escapeHtml(
                        item.title
                    );


                var url =
                    escapeHtml(
                        item.url
                    );


                var editUrl =
                    escapeHtml(
                        item.edit_url
                    );


                var html =
                    '<div class="lv-selected-page__card">' +

                        '<strong class="lv-selected-page__title">' +
                            title +
                        '</strong>' +

                        '<span class="lv-selected-page__url">' +
                            url +
                        '</span>' +

                        '<div class="lv-selected-page__actions">' +

                            (
                                url
                                    ?
                                    '<a' +
                                        ' class="button"' +
                                        ' href="' +
                                            url +
                                        '"' +
                                        ' target="_blank"' +
                                        ' rel="noopener noreferrer">' +
                                        'Открыть' +
                                    '</a>'
                                    :
                                    ''
                            ) +

                            (
                                editUrl
                                    ?
                                    '<a' +
                                        ' class="button"' +
                                        ' href="' +
                                            editUrl +
                                        '"' +
                                        ' target="_blank">' +
                                        'Редактировать' +
                                    '</a>'
                                    :
                                    ''
                            ) +

                            '<button' +
                                ' type="button"' +
                                ' class="button"' +
                                ' id="lv-clear-history-page">' +
                                'Убрать связь' +
                            '</button>' +

                        '</div>' +

                    '</div>';


                selectedContainer.html(
                    html
                );

            }


            function clearSelectedPage() {

                pageIdInput.val('');

                searchInput.val('');


                selectedContainer.html(
                    '<div class="lv-selected-page__empty">' +
                        'Страница с историей пока не выбрана.' +
                    '</div>'
                );


                hideResults();

            }


            selectedContainer.on(
                'click',
                '#lv-clear-history-page',
                function (event) {

                    event.preventDefault();

                    clearSelectedPage();

                }
            );


            function renderResults(items) {

                results.empty();


                if (
                    !items ||
                    !items.length
                ) {

                    results.html(
                        '<div class="lv-page-picker__empty">' +
                            'Страницы не найдены.' +
                        '</div>'
                    );


                    results.removeAttr(
                        'hidden'
                    );

                    return;
                }


                items.forEach(
                    function (item) {

                        var button =
                            $('<button>', {
                                type:
                                    'button',
                                class:
                                    'lv-page-result'
                            });


                        button.append(
                            $('<span>', {
                                class:
                                    'lv-page-result__title',
                                text:
                                    item.title
                            })
                        );


                        button.append(
                            $('<span>', {
                                class:
                                    'lv-page-result__meta',
                                text:
                                    item.status_label +
                                    ' · ' +
                                    item.url
                            })
                        );


                        button.on(
                            'click',
                            function () {

                                pageIdInput.val(
                                    item.id
                                );


                                searchInput.val(
                                    item.title
                                );


                                showSelectedPage(
                                    item
                                );


                                hideResults();

                            }
                        );


                        results.append(
                            button
                        );

                    }
                );


                results.removeAttr(
                    'hidden'
                );

            }


            function searchPages() {

                var query =
                    searchInput
                        .val()
                        .trim();


                if (currentRequest) {

                    currentRequest.abort();

                    currentRequest = null;

                }


                results
                    .html(
                        '<div class="lv-page-picker__loading">' +
                            'Поиск…' +
                        '</div>'
                    )
                    .removeAttr(
                        'hidden'
                    );


                currentRequest =
                    $.ajax({

                        url:
                            ajaxurl,

                        method:
                            'GET',

                        dataType:
                            'json',

                        data: {
                            action:
                                'lv_ward_search_pages',

                            nonce:
                                nonce,

                            q:
                                query
                        }

                    })
                    .done(
                        function (response) {

                            if (
                                !response ||
                                !response.success
                            ) {

                                renderResults([]);

                                return;
                            }


                            renderResults(
                                response.data.items
                            );

                        }
                    )
                    .fail(
                        function (
                            xhr,
                            status
                        ) {

                            if (
                                status === 'abort'
                            ) {
                                return;
                            }


                            renderResults([]);

                        }
                    );

            }


            searchInput.on(
                'input',
                function () {

                    window.clearTimeout(
                        searchTimer
                    );


                    searchTimer =
                        window.setTimeout(
                            searchPages,
                            250
                        );

                }
            );


            searchInput.on(
                'focus',
                function () {

                    if (
                        searchInput
                            .val()
                            .trim() === ''
                    ) {

                        searchPages();

                    }

                }
            );


            $(document).on(
                'click',
                function (event) {

                    var pickerElement =
                        picker.get(0);


                    if (
                        pickerElement &&
                        !pickerElement.contains(
                            event.target
                        )
                    ) {

                        hideResults();

                    }

                }
            );

        });
        </script>

        <?php

    }
);


/* ============================================================
 * 17. СОХРАНЕНИЕ
 * ============================================================ */

add_action(
    'save_post_lv_ward',
    function ($post_id) {

        /* ----------------------------------------------------
         * SECURITY
         * ---------------------------------------------------- */

        if (
            !isset($_POST['lv_ward_nonce']) ||
            !wp_verify_nonce(
                $_POST['lv_ward_nonce'],
                'lv_save_ward'
            )
        ) {
            return;
        }


        if (
            defined('DOING_AUTOSAVE') &&
            DOING_AUTOSAVE
        ) {
            return;
        }


        if (
            !current_user_can(
                'edit_post',
                $post_id
            )
        ) {
            return;
        }


        /* ----------------------------------------------------
         * TYPE
         * ---------------------------------------------------- */

        $type_term_id = isset(
            $_POST['lv_ward_type_term']
        )
            ? absint(
                $_POST['lv_ward_type_term']
            )
            : 0;


        if ($type_term_id) {

            $term = get_term(
                $type_term_id,
                'lv_ward_type'
            );


            if (
                $term &&
                !is_wp_error($term)
            ) {

                wp_set_object_terms(
                    $post_id,
                    [$type_term_id],
                    'lv_ward_type',
                    false
                );

            }

        } else {

            wp_set_object_terms(
                $post_id,
                [],
                'lv_ward_type',
                false
            );

        }


        /* ----------------------------------------------------
         * BIRTH DATE
         * ---------------------------------------------------- */

        $birth_date = isset(
            $_POST['lv_ward_birth_date']
        )
            ? lv_ward_sanitize_date(
                wp_unslash(
                    $_POST[
                        'lv_ward_birth_date'
                    ]
                )
            )
            : '';


        if ($birth_date) {

            update_post_meta(
                $post_id,
                '_lv_ward_birth_date',
                $birth_date
            );

        } else {

            delete_post_meta(
                $post_id,
                '_lv_ward_birth_date'
            );

        }


        /* ----------------------------------------------------
         * DECEASED
         * ---------------------------------------------------- */

        $is_deceased = isset(
            $_POST['lv_ward_deceased']
        );


        if ($is_deceased) {

            update_post_meta(
                $post_id,
                '_lv_ward_deceased',
                '1'
            );

        } else {

            delete_post_meta(
                $post_id,
                '_lv_ward_deceased'
            );

        }


        /* ----------------------------------------------------
         * DEATH DATE
         * ---------------------------------------------------- */

        $death_date = isset(
            $_POST['lv_ward_death_date']
        )
            ? lv_ward_sanitize_date(
                wp_unslash(
                    $_POST[
                        'lv_ward_death_date'
                    ]
                )
            )
            : '';


        if (
            $is_deceased &&
            $death_date
        ) {

            update_post_meta(
                $post_id,
                '_lv_ward_death_date',
                $death_date
            );

        } else {

            delete_post_meta(
                $post_id,
                '_lv_ward_death_date'
            );

        }


        /* ----------------------------------------------------
         * TEXT 1
         * ---------------------------------------------------- */

        $text_1 = isset(
            $_POST['lv_ward_text_1']
        )
            ? sanitize_textarea_field(
                wp_unslash(
                    $_POST[
                        'lv_ward_text_1'
                    ]
                )
            )
            : '';


        update_post_meta(
            $post_id,
            '_lv_ward_text_1',
            $text_1
        );


        /* ----------------------------------------------------
         * TEXT 2
         * ---------------------------------------------------- */

        $text_2 = isset(
            $_POST['lv_ward_text_2']
        )
            ? sanitize_textarea_field(
                wp_unslash(
                    $_POST[
                        'lv_ward_text_2'
                    ]
                )
            )
            : '';


        update_post_meta(
            $post_id,
            '_lv_ward_text_2',
            $text_2
        );


        /* ----------------------------------------------------
         * PHOTO
         * ---------------------------------------------------- */

        $photo_id = isset(
            $_POST['lv_ward_photo_id']
        )
            ? absint(
                $_POST[
                    'lv_ward_photo_id'
                ]
            )
            : 0;


        if ($photo_id) {

            update_post_meta(
                $post_id,
                '_lv_ward_photo_id',
                $photo_id
            );

        } else {

            delete_post_meta(
                $post_id,
                '_lv_ward_photo_id'
            );

        }


        /* ----------------------------------------------------
         * HISTORY PAGE
         * ---------------------------------------------------- */

        $history_page_id = isset(
            $_POST['lv_ward_history_page_id']
        )
            ? absint(
                $_POST[
                    'lv_ward_history_page_id'
                ]
            )
            : 0;


        $valid_page_id = 0;


        if ($history_page_id) {

            $page = get_post(
                $history_page_id
            );


            if (
                $page &&
                $page->post_type === 'page' &&
                $page->post_status !== 'trash'
            ) {

                $valid_page_id =
                    $history_page_id;

            }

        }


        if ($valid_page_id) {

            update_post_meta(
                $post_id,
                '_lv_ward_history_page_id',
                $valid_page_id
            );

        } else {

            delete_post_meta(
                $post_id,
                '_lv_ward_history_page_id'
            );

        }

    }
);


/* ============================================================
 * 18. ДАННЫЕ ОДНОГО ПОДОПЕЧНОГО
 * ============================================================ */

function lv_get_ward_data($post_id) {

    $post_id = absint(
        $post_id
    );


    if (!$post_id) {
        return null;
    }


    $post = get_post(
        $post_id
    );


    if (
        !$post ||
        $post->post_type !== 'lv_ward'
    ) {
        return null;
    }


    /* --------------------------------------------------------
     * TYPE
     * -------------------------------------------------------- */

    $type_term = lv_get_ward_type_term(
        $post_id
    );


    $type_id = $type_term
        ? (int) $type_term->term_id
        : 0;


    $type_slug = $type_term
        ? (string) $type_term->slug
        : '';


    $type_label = $type_term
        ? (string) $type_term->name
        : '';


    /* --------------------------------------------------------
     * PHOTO
     * -------------------------------------------------------- */

    $photo_id = absint(
        get_post_meta(
            $post_id,
            '_lv_ward_photo_id',
            true
        )
    );


    /* --------------------------------------------------------
     * DATES / STATUS
     * -------------------------------------------------------- */

    $birth_date = get_post_meta(
        $post_id,
        '_lv_ward_birth_date',
        true
    );


    $is_deceased = (
        get_post_meta(
            $post_id,
            '_lv_ward_deceased',
            true
        ) === '1'
    );


    $death_date = get_post_meta(
        $post_id,
        '_lv_ward_death_date',
        true
    );


    $age = lv_calculate_ward_age(
        $birth_date,
        $is_deceased,
        $death_date
    );


    /* --------------------------------------------------------
     * HISTORY
     * -------------------------------------------------------- */

    $history_page_id =
        lv_get_ward_history_page_id(
            $post_id
        );


    $history_url =
        lv_get_ward_history_url(
            $post_id
        );


    /* --------------------------------------------------------
     * RESULT
     * -------------------------------------------------------- */

    return [

        'id' =>
            $post_id,


        'name' =>
            get_the_title(
                $post_id
            ),


        'type' =>
            $type_slug,


        'type_id' =>
            $type_id,


        'type_label' =>
            $type_label,


        'text_1' =>
            get_post_meta(
                $post_id,
                '_lv_ward_text_1',
                true
            ),


        'text_2' =>
            get_post_meta(
                $post_id,
                '_lv_ward_text_2',
                true
            ),


        'birth_date' =>
            $birth_date,


        'birth_date_formatted' =>
            lv_format_ward_date(
                $birth_date
            ),


        'age' =>
            $age,


        'age_label' =>
            lv_format_ward_age(
                $age
            ),


        'is_deceased' =>
            $is_deceased,


        'status' =>
            $is_deceased
                ? 'deceased'
                : 'active',


        'status_label' =>
            $is_deceased
                ? 'Умер'
                : 'Под опекой',


        'death_date' =>
            $death_date,


        'death_date_formatted' =>
            lv_format_ward_date(
                $death_date
            ),


        'photo_id' =>
            $photo_id,


        'photo_url' =>
            $photo_id
                ? wp_get_attachment_image_url(
                    $photo_id,
                    'full'
                )
                : '',


        'photo_class' =>
            $is_deceased
                ? 'lv-ward-photo--deceased'
                : '',


        'history_page_id' =>
            $history_page_id,


        /*
         * Остальные наши сниппеты могут
         * продолжать использовать history_url.
         *
         * Но теперь он всегда вычисляется
         * через get_permalink(page ID).
         */
        'history_url' =>
            $history_url,

    ];

}


/* ============================================================
 * 19. ПОЛУЧЕНИЕ ВСЕХ ПОДОПЕЧНЫХ
 * ============================================================ */

function lv_get_wards($args = []) {

    $defaults = [

        'post_type' =>
            'lv_ward',

        'post_status' =>
            'publish',

        'posts_per_page' =>
            -1,

        'orderby' => [
            'menu_order' =>
                'ASC',

            'title' =>
                'ASC',
        ],

        'order' =>
            'ASC',

        'no_found_rows' =>
            true,
    ];


    $query_args = wp_parse_args(
        $args,
        $defaults
    );


    $query = new WP_Query(
        $query_args
    );


    $result = [];


    foreach (
        $query->posts
        as $post
    ) {

        $data = lv_get_ward_data(
            $post->ID
        );


        if ($data) {

            $result[] =
                $data;

        }

    }


    return $result;

}


/* ============================================================
 * 20. РЕНДЕР ФОТОГРАФИИ
 * ============================================================ */

function lv_get_ward_image(
    $post_id,
    $size = 'medium_large',
    $attributes = []
) {

    $ward = lv_get_ward_data(
        $post_id
    );


    if (
        !$ward ||
        !$ward['photo_id']
    ) {
        return '';
    }


    $classes = [];


    if (
        !empty(
            $attributes['class']
        )
    ) {

        $classes[] =
            $attributes['class'];

    }


    if (
        $ward['is_deceased']
    ) {

        $classes[] =
            'lv-ward-photo--deceased';

    }


    $attributes['class'] = trim(
        implode(
            ' ',
            $classes
        )
    );


    if (
        empty(
            $attributes['alt']
        )
    ) {

        $attributes['alt'] =
            $ward['name'];

    }


    return wp_get_attachment_image(
        $ward['photo_id'],
        $size,
        false,
        $attributes
    );

}


/* ============================================================
 * 21. Ч/Б ФОТО УМЕРШИХ
 * ============================================================ */

function lv_get_deceased_photo_ids() {

    static $photo_ids = null;


    if ($photo_ids !== null) {
        return $photo_ids;
    }


    $photo_ids = [];


    $wards = get_posts([

        'post_type' =>
            'lv_ward',

        'post_status' =>
            'publish',

        'posts_per_page' =>
            -1,

        'fields' =>
            'ids',

        'meta_key' =>
            '_lv_ward_deceased',

        'meta_value' =>
            '1',

        'no_found_rows' =>
            true,

    ]);


    foreach ($wards as $ward_id) {

        $photo_id = absint(
            get_post_meta(
                $ward_id,
                '_lv_ward_photo_id',
                true
            )
        );


        if ($photo_id) {

            $photo_ids[$photo_id] =
                true;

        }

    }


    return $photo_ids;

}


/*
 * Добавляем класс автоматически,
 * поэтому существующие виджеты главной
 * и страницы всех подопечных менять не надо.
 */
add_filter(
    'wp_get_attachment_image_attributes',
    function (
        $attr,
        $attachment,
        $size
    ) {

        if (is_admin()) {
            return $attr;
        }


        $deceased_photos =
            lv_get_deceased_photo_ids();


        $attachment_id =
            absint(
                $attachment->ID
            );


        if (
            empty(
                $deceased_photos[
                    $attachment_id
                ]
            )
        ) {
            return $attr;
        }


        $classes = isset(
            $attr['class']
        )
            ? $attr['class']
            : '';


        if (
            strpos(
                $classes,
                'lv-ward-photo--deceased'
            ) === false
        ) {

            $classes = trim(
                $classes .
                ' lv-ward-photo--deceased'
            );

        }


        $attr['class'] =
            $classes;


        return $attr;

    },
    20,
    3
);


add_action(
    'wp_head',
    function () {

        if (is_admin()) {
            return;
        }

        ?>

        <style id="lv-ward-deceased-style">

            .lv-ward-photo--deceased {
                -webkit-filter:
                    grayscale(1) !important;

                filter:
                    grayscale(1) !important;
            }

        </style>

        <?php

    },
    50
);


/* ============================================================
 * 22. КОЛОНКИ В АДМИНКЕ
 * ============================================================ */

add_filter(
    'manage_lv_ward_posts_columns',
    function ($columns) {

        return [

            'cb' =>
                $columns['cb'],

            'lv_photo' =>
                'Фото',

            'title' =>
                'Имя',

            'lv_type' =>
                'Тип',

            'lv_age' =>
                'Возраст',

            'lv_status' =>
                'Статус',

            'lv_history' =>
                'История',

            'menu_order' =>
                'Порядок',

            'date' =>
                'Дата',

        ];

    }
);


/* ============================================================
 * 23. ЗНАЧЕНИЯ КОЛОНОК
 * ============================================================ */

add_action(
    'manage_lv_ward_posts_custom_column',
    function (
        $column,
        $post_id
    ) {

        $ward = lv_get_ward_data(
            $post_id
        );


        if (!$ward) {
            return;
        }


        switch ($column) {


            case 'lv_photo':

                if (
                    $ward['photo_id']
                ) {

                    $style =
                        'width:70px;' .
                        'height:55px;' .
                        'object-fit:cover;' .
                        'border-radius:6px;';


                    if (
                        $ward[
                            'is_deceased'
                        ]
                    ) {

                        $style .=
                            'filter:grayscale(1);';

                    }


                    echo wp_get_attachment_image(
                        $ward['photo_id'],
                        [70, 55],
                        false,
                        [
                            'style' =>
                                $style,
                        ]
                    );

                } else {

                    echo '—';

                }

                break;


            case 'lv_type':

                echo $ward['type_label']
                    ? esc_html(
                        $ward['type_label']
                    )
                    : '—';

                break;


            case 'lv_age':

                if (
                    $ward[
                        'age_label'
                    ]
                ) {

                    echo esc_html(
                        $ward[
                            'age_label'
                        ]
                    );

                } elseif (
                    $ward[
                        'is_deceased'
                    ] &&
                    $ward[
                        'birth_date'
                    ] &&
                    !$ward[
                        'death_date'
                    ]
                ) {

                    echo
                        '<span class="lv-muted">' .
                        'нужна дата смерти' .
                        '</span>';

                } else {

                    echo '—';

                }

                break;


            case 'lv_status':

                if (
                    $ward[
                        'is_deceased'
                    ]
                ) {

                    echo
                        '<span class="lv-status-badge lv-status-badge--deceased">' .
                        'Умер' .
                        '</span>';

                } else {

                    echo
                        '<span class="lv-status-badge lv-status-badge--active">' .
                        'Под опекой' .
                        '</span>';

                }

                break;


            case 'lv_history':

                $page_id =
                    $ward[
                        'history_page_id'
                    ];


                if (!$page_id) {

                    echo '—';

                    break;
                }


                $title = get_the_title(
                    $page_id
                );


                $edit_url = get_edit_post_link(
                    $page_id,
                    ''
                );


                echo '<strong>';

                echo esc_html(
                    $title
                );

                echo '</strong>';


                if ($edit_url) {

                    echo '<br>';

                    echo
                        '<a href="' .
                        esc_url(
                            $edit_url
                        ) .
                        '">' .
                        'Редактировать →' .
                        '</a>';

                }

                break;


            case 'menu_order':

                $post = get_post(
                    $post_id
                );


                echo $post
                    ? intval(
                        $post->menu_order
                    )
                    : '0';

                break;

        }

    },
    10,
    2
);


/* ============================================================
 * 24. СТИЛИ СПИСКА АДМИНКИ
 * ============================================================ */

add_action(
    'admin_head',
    function () {

        $screen = get_current_screen();


        if (
            !$screen ||
            $screen->post_type !== 'lv_ward'
        ) {
            return;
        }

        ?>

        <style>

            .post-type-lv_ward
            .column-lv_photo {
                width: 90px;
            }


            .post-type-lv_ward
            .column-lv_type {
                width: 130px;
            }


            .post-type-lv_ward
            .column-lv_age {
                width: 120px;
            }


            .post-type-lv_ward
            .column-lv_status {
                width: 120px;
            }


            .post-type-lv_ward
            .column-lv_history {
                width: 170px;
            }


            .post-type-lv_ward
            .column-menu_order {
                width: 80px;

                text-align: center;
            }


            .lv-status-badge {
                display: inline-block;

                padding: 4px 9px;

                border-radius: 20px;

                font-size: 12px;
                font-weight: 600;
            }


            .lv-status-badge--active {
                background: #e5f5ea;

                color: #176b34;
            }


            .lv-status-badge--deceased {
                background: #e5e5e5;

                color: #3c434a;
            }


            .lv-muted {
                color: #787c82;

                font-size: 12px;
            }

        </style>

        <?php

    }
);