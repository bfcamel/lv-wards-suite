<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * Фонд «Люди и Верблюды»
 * Блок «Другие истории»
 * ============================================================
 *
 * Источник данных:
 * база подопечных lv_ward.
 *
 * Больше НЕ используется:
 * - /wards/
 * - fetch()
 * - DOMParser
 * - парсинг карточек
 * - localStorage-кэш списка
 *
 * Сохраняется:
 * - 3 случайные истории;
 * - исключение текущего подопечного;
 * - защита от повторения одного набора два раза подряд;
 * - sessionStorage только для предыдущей комбинации.
 *
 * ============================================================
 */


add_action(
    'wp_footer',
    'lv_other_ward_stories_script',
    50
);


function lv_other_ward_stories_script() {

    /*
     * В админке ничего не выводим.
     */
    if (is_admin()) {
        return;
    }


    /*
     * Модуль нужен только на странице истории, которая реально
     * связана с опубликованным подопечным. Раньше весь список
     * животных и JavaScript формировались на каждой странице сайта.
     */
    if (
        !is_page() ||
        !function_exists('lv_wards_suite_get_ward_id_by_history_page') ||
        !lv_wards_suite_get_ward_id_by_history_page()
    ) {
        return;
    }


    /*
     * Проверяем наличие базы.
     */
    if (
        !post_type_exists('lv_ward') ||
        !function_exists('lv_get_wards')
    ) {
        return;
    }


    /* ========================================================
     * ПОЛУЧАЕМ ПОДОПЕЧНЫХ ИЗ БАЗЫ
     * ======================================================== */

    $source_wards = lv_get_wards([
        'posts_per_page' => -1,
    ]);


    if (!$source_wards) {
        return;
    }


    $wards = [];


    foreach ($source_wards as $ward) {

        /*
         * Без страницы истории эта запись
         * для блока "Другие истории" бесполезна.
         */
        if (empty($ward['history_url'])) {
            continue;
        }


        $photo_id = !empty($ward['photo_id'])
            ? absint($ward['photo_id'])
            : 0;


        $image_url = '';


        if ($photo_id) {

            /*
             * Для мини-карточки достаточно thumbnail.
             *
             * WordPress / плагины оптимизации сами
             * вернут реальный существующий URL файла.
             */
            $image_url = wp_get_attachment_image_url(
                $photo_id,
                'thumbnail'
            );


            /*
             * Если thumbnail по какой-либо причине
             * отсутствует — используем medium.
             */
            if (!$image_url) {

                $image_url = wp_get_attachment_image_url(
                    $photo_id,
                    'medium'
                );

            }


            /*
             * Последний fallback — full.
             */
            if (!$image_url) {

                $image_url = wp_get_attachment_image_url(
                    $photo_id,
                    'full'
                );

            }

        }


        $wards[] = [
            'name' => !empty($ward['name'])
                ? (string) $ward['name']
                : '',

            /*
             * В старом блоке использовался
             * первый текстовый блок.
             */
            'text' => !empty($ward['text_1'])
                ? (string) $ward['text_1']
                : '',

            'url' => (string) $ward['history_url'],

            'image' => $image_url
                ? (string) $image_url
                : '',
        ];

    }


    /*
     * Для выбора трёх других историй желательно
     * иметь минимум четыре записи:
     *
     * текущая + три другие.
     */
    if (count($wards) < 4) {
        return;
    }


    /*
     * Безопасно передаём PHP-массив в JavaScript.
     *
     * HEX-флаги дополнительно защищают встроенный
     * JSON от проблем со специальными HTML-символами.
     */
    $wards_json = wp_json_encode(
        $wards,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    );


    $all_wards_url = function_exists(
        'lv_wards_suite_get_catalog_url'
    )
        ? lv_wards_suite_get_catalog_url()
        : home_url('/wards/');

    ?>

    <script>
    (function () {
        'use strict';


        /* ======================================================
           ДАННЫЕ ИЗ WORDPRESS
           ====================================================== */

        var wards =
            <?php echo $wards_json; ?>;


        var STORIES_COUNT = 3;


        var ALL_WARDS_URL =
            <?php echo wp_json_encode($all_wards_url); ?>;


        /* ======================================================
           HELPERS
           ====================================================== */

        function normalizePath(value) {

            if (!value) {
                return '';
            }


            try {

                return new URL(
                    value,
                    window.location.origin
                )
                    .pathname
                    .replace(/\/+$/, '')
                    .toLowerCase();

            } catch (error) {

                return String(value)
                    .replace(/\/+$/, '')
                    .toLowerCase();

            }

        }


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


        function randomNumber() {

            if (
                window.crypto &&
                window.crypto.getRandomValues
            ) {

                var array =
                    new Uint32Array(1);


                window.crypto
                    .getRandomValues(
                        array
                    );


                return (
                    array[0] /
                    4294967296
                );

            }


            return Math.random();

        }


        function shuffle(items) {

            var result =
                items.slice();


            for (
                var i =
                    result.length - 1;

                i > 0;

                i -= 1
            ) {

                var j =
                    Math.floor(
                        randomNumber() *
                        (i + 1)
                    );


                var temporary =
                    result[i];


                result[i] =
                    result[j];


                result[j] =
                    temporary;

            }


            return result;

        }


        /* ======================================================
           ВЫБОР СЛУЧАЙНЫХ ИСТОРИЙ
           ====================================================== */

        function selectRandomStories() {

            var current =
                normalizePath(
                    window.location.pathname
                );


            /*
             * Исключаем текущую страницу подопечного.
             */
            var available =
                wards.filter(
                    function (ward) {

                        return (
                            normalizePath(
                                ward.url
                            ) !== current
                        );

                    }
                );


            if (
                available.length <
                STORIES_COUNT
            ) {
                return [];
            }


            /*
             * Запоминаем предыдущий набор только
             * на время текущей сессии браузера.
             *
             * Сам список животных уже НЕ кэшируется.
             */
            var previousKey =
                'lvPreviousStories:' +
                current;


            var previous = '';


            try {

                previous =
                    sessionStorage
                        .getItem(
                            previousKey
                        ) || '';

            } catch (error) {
                /* sessionStorage недоступен */
            }


            var selected = [];

            var selectedKey = '';

            var attempts = 0;


            /*
             * При достаточном количестве вариантов
             * стараемся не показывать точно тот же
             * набор при следующем открытии.
             */
            do {

                selected =
                    shuffle(
                        available
                    )
                        .slice(
                            0,
                            STORIES_COUNT
                        );


                selectedKey =
                    selected
                        .map(
                            function (ward) {

                                return normalizePath(
                                    ward.url
                                );

                            }
                        )
                        .sort()
                        .join('|');


                attempts += 1;


            } while (
                selectedKey === previous &&
                attempts < 50
            );


            try {

                sessionStorage.setItem(
                    previousKey,
                    selectedKey
                );

            } catch (error) {
                /* sessionStorage недоступен */
            }


            return selected;

        }


        /* ======================================================
           РЕНДЕР
           ====================================================== */

        function render(side) {

            var selected =
                selectRandomStories();


            if (
                selected.length !==
                STORIES_COUNT
            ) {
                return;
            }


            var html =
                '<h2 class="st-side__title">' +
                    'Другие истории' +
                '</h2>';


            selected.forEach(
                function (ward) {

                    var url =
                        escapeHtml(
                            ward.url
                        );


                    var name =
                        escapeHtml(
                            ward.name
                        );


                    var text =
                        escapeHtml(
                            ward.text
                        );


                    var image =
                        escapeHtml(
                            ward.image
                        );


                    html +=
                        '<a' +
                            ' class="st-mini"' +
                            ' href="' +
                                url +
                            '"' +
                        '>' +


                            (
                                image
                                    ?
                                    '<img' +
                                        ' class="st-mini__img"' +
                                        ' src="' +
                                            image +
                                        '"' +
                                        ' alt=""' +
                                        ' width="150"' +
                                        ' height="150"' +
                                        ' loading="lazy"' +
                                        ' decoding="async"' +
                                    '>'
                                    :
                                    ''
                            ) +


                            '<span class="st-mini__body">' +


                                '<span class="st-mini__name">' +
                                    name +
                                '</span>' +


                                (
                                    text
                                        ?
                                        '<span class="st-mini__sub">' +
                                            text +
                                        '</span>'
                                        :
                                        ''
                                ) +


                            '</span>' +


                        '</a>';

                }
            );


            html +=
                '<a' +
                    ' class="st-side__all"' +
                    ' href="' +
                        escapeHtml(
                            ALL_WARDS_URL
                        ) +
                    '"' +
                '>' +
                    'Все подопечные →' +
                '</a>';


            side.innerHTML =
                html;

        }


        /* ======================================================
           INIT
           ====================================================== */

        function init() {

            var side =
                document.querySelector(
                    '#lvStory .st-side'
                );


            /*
             * Если это не страница истории,
             * ничего не делаем.
             */
            if (!side) {
                return;
            }


            render(side);

        }


        if (
            document.readyState ===
            'loading'
        ) {

            document.addEventListener(
                'DOMContentLoaded',
                init,
                {
                    once: true
                }
            );

        } else {

            init();

        }

    }());
    </script>

    <?php
}