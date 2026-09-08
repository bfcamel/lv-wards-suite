<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * Фонд «Люди и Верблюды»
 * Подопечные на главной — динамическая карусель
 * Версия без inline <script> внутри shortcode HTML
 * ============================================================
 *
 * Шорткод:
 * [lv_wards_home]
 *
 * Требует:
 * - post type lv_ward
 * - функцию lv_get_ward_data()
 *
 * ============================================================
 */


remove_shortcode('lv_wards_home');

add_shortcode(
    'lv_wards_home',
    'lv_wards_home_render'
);


/* ============================================================
 * ПОДКЛЮЧЕНИЕ JS
 * ============================================================ */

function lv_wards_home_enqueue_script() {

    static $done = false;

    if ($done) {
        return;
    }

    $done = true;


    /*
     * Создаём служебный JS-handle без отдельного файла.
     * Сам код WordPress выведет в footer.
     *
     * Главное преимущество:
     * Elementor больше не обрабатывает содержимое JS.
     */
    wp_register_script(
        'lv-wards-home-carousel',
        '',
        [],
        null,
        true
    );

    wp_enqueue_script(
        'lv-wards-home-carousel'
    );


    $js = <<<'JS'
(function () {
    'use strict';


    function initWardsCarousel() {

        var roots =
            document.querySelectorAll(
                '[data-lv-wards-home]'
            );


        if (!roots.length) {
            return;
        }


        Array.prototype.forEach.call(
            roots,
            function (root) {

                initOneCarousel(root);

            }
        );

    }


    function initOneCarousel(root) {

        if (!root) {
            return;
        }


        if (
            root.getAttribute(
                'data-carousel-ready'
            ) === '1'
        ) {
            return;
        }


        var carousel =
            root.querySelector(
                '[data-cw-carousel]'
            );


        var viewport =
            root.querySelector(
                '.cw-carousel-viewport'
            );


        var track =
            root.querySelector(
                '.cw-carousel-track'
            );


        var cards =
            Array.prototype.slice.call(
                root.querySelectorAll(
                    '.cw-card'
                )
            );


        var dotsContainer =
            root.querySelector(
                '.cw-dots'
            );


        var prevButton =
            root.querySelector(
                '.cw-arrow--prev'
            );


        var nextButton =
            root.querySelector(
                '.cw-arrow--next'
            );


        if (!carousel) {
            return;
        }

        if (!viewport) {
            return;
        }

        if (!track) {
            return;
        }

        if (!cards.length) {
            return;
        }

        if (!dotsContainer) {
            return;
        }

        if (!prevButton) {
            return;
        }

        if (!nextButton) {
            return;
        }


        root.setAttribute(
            'data-carousel-ready',
            '1'
        );


        /* ======================================================
         * STATE
         * ====================================================== */

        var current = 0;

        var perView = 3;

        var timer = null;

        var resizeTimer = null;

        var visible = true;

        var hovered = false;

        var focused = false;

        var pointerStartX = null;

        var pointerStartY = null;


        var reducedMotion =
            window.matchMedia(
                '(prefers-reduced-motion: reduce)'
            );


        /* ======================================================
         * RESPONSIVE
         * ====================================================== */

        function detectPerView() {

            if (!cards[0]) {
                perView = 1;
                return;
            }


            var viewportWidth =
                viewport.getBoundingClientRect()
                    .width;


            var cardWidth =
                cards[0]
                    .getBoundingClientRect()
                    .width;


            var styles =
                window.getComputedStyle(
                    track
                );


            var gap =
                parseFloat(
                    styles.columnGap
                );


            if (!Number.isFinite(gap)) {

                gap =
                    parseFloat(
                        styles.gap
                    );

            }


            if (!Number.isFinite(gap)) {
                gap = 0;
            }


            if (
                viewportWidth <= 0 ||
                cardWidth <= 0
            ) {
                perView = 1;
                return;
            }


            /*
             * Не дублируем CSS-breakpoints в JavaScript.
             * Количество карточек определяется по их фактической
             * ширине, поэтому JS всегда совпадает с CSS 3 / 2 / 1.
             */
            perView = Math.max(
                1,
                Math.min(
                    cards.length,
                    3,
                    Math.round(
                        (viewportWidth + gap) /
                        (cardWidth + gap)
                    )
                )
            );

        }


        function getMaxIndex() {

            return Math.max(
                0,
                cards.length - perView
            );

        }


        function canMove() {

            if (
                cards.length >
                perView
            ) {
                return true;
            }

            return false;

        }


        /* ======================================================
         * РАЗМЕР ШАГА
         * ====================================================== */

        function getStep() {

            if (!cards[0]) {
                return 0;
            }


            var rect =
                cards[0]
                    .getBoundingClientRect();


            var styles =
                window.getComputedStyle(
                    track
                );


            var gap =
                parseFloat(
                    styles.columnGap
                );


            if (
                !Number.isFinite(gap)
            ) {

                gap =
                    parseFloat(
                        styles.gap
                    );

            }


            if (
                !Number.isFinite(gap)
            ) {
                gap = 0;
            }


            return (
                rect.width + gap
            );

        }


        /* ======================================================
         * DOTS
         * ====================================================== */

        function updateDots() {

            var dots =
                dotsContainer.querySelectorAll(
                    '.cw-dot'
                );


            Array.prototype.forEach.call(
                dots,
                function (dot, index) {

                    var active =
                        index === current;


                    if (active) {

                        dot.classList.add(
                            'active'
                        );

                        dot.setAttribute(
                            'aria-current',
                            'true'
                        );

                    } else {

                        dot.classList.remove(
                            'active'
                        );

                        dot.setAttribute(
                            'aria-current',
                            'false'
                        );

                    }

                }
            );

        }


        function buildDots() {

            dotsContainer.innerHTML = '';


            if (!canMove()) {
                return;
            }


            var max =
                getMaxIndex();


            var i;


            for (
                i = 0;
                i <= max;
                i += 1
            ) {

                createDot(i, max);

            }


            updateDots();

        }


        function createDot(
            index,
            max
        ) {

            var dot =
                document.createElement(
                    'button'
                );


            dot.type =
                'button';


            dot.className =
                'cw-dot';


            dot.setAttribute(
                'aria-label',
                'Показать позицию ' +
                String(index + 1) +
                ' из ' +
                String(max + 1)
            );


            dot.addEventListener(
                'click',
                function () {

                    goTo(index);

                    restartAutoplay();

                }
            );


            dotsContainer
                .appendChild(dot);

        }


        /* ======================================================
         * CONTROLS
         * ====================================================== */

        function updateControls() {

            if (canMove()) {

                prevButton.hidden =
                    false;

                nextButton.hidden =
                    false;

                return;
            }


            prevButton.hidden =
                true;

            nextButton.hidden =
                true;

            dotsContainer.innerHTML =
                '';

            current = 0;

        }


        /* ======================================================
         * ACCESSIBILITY OF OFF-SCREEN CARDS
         * ====================================================== */

        function updateCardAccessibility() {

            cards.forEach(
                function (card, index) {

                    var isVisible =
                        index >= current &&
                        index < current + perView;


                    card.setAttribute(
                        'aria-hidden',
                        isVisible
                            ? 'false'
                            : 'true'
                    );


                    /*
                     * Современные браузеры поддерживают inert.
                     * Для старых браузеров оставляем визуальное и
                     * aria-состояние без вмешательства в tabindex.
                     */
                    if ('inert' in card) {
                        card.inert = !isVisible;
                    }

                }
            );

        }


        /* ======================================================
         * RENDER
         * ====================================================== */

        function render() {

            var max =
                getMaxIndex();


            if (current > max) {
                current = max;
            }


            if (current < 0) {
                current = 0;
            }


            var step =
                getStep();


            var offset =
                current * step;


            track.style.transform =
                'translate3d(' +
                String(-offset) +
                'px, 0, 0)';


            updateCardAccessibility();

            updateDots();

        }


        /* ======================================================
         * NAVIGATION
         * ====================================================== */

        function goTo(index) {

            var max =
                getMaxIndex();


            if (index < 0) {
                index = 0;
            }


            if (index > max) {
                index = max;
            }


            current = index;

            render();

        }


        function next() {

            if (!canMove()) {
                return;
            }


            if (
                current >=
                getMaxIndex()
            ) {

                goTo(0);

                return;
            }


            goTo(
                current + 1
            );

        }


        function previous() {

            if (!canMove()) {
                return;
            }


            if (current <= 0) {

                goTo(
                    getMaxIndex()
                );

                return;
            }


            goTo(
                current - 1
            );

        }


        /* ======================================================
         * AUTOPLAY
         * ====================================================== */

        function stopAutoplay() {

            if (timer === null) {
                return;
            }


            window.clearTimeout(
                timer
            );


            timer = null;

        }


        function autoplayAllowed() {

            if (!canMove()) {
                return false;
            }


            if (!visible) {
                return false;
            }


            if (hovered) {
                return false;
            }


            if (focused) {
                return false;
            }


            if (document.hidden) {
                return false;
            }


            if (reducedMotion.matches) {
                return false;
            }


            return true;

        }


        function startAutoplay() {

            stopAutoplay();


            if (!autoplayAllowed()) {
                return;
            }


            timer =
                window.setTimeout(
                    function () {

                        next();

                        startAutoplay();

                    },
                    5000
                );

        }


        function restartAutoplay() {

            stopAutoplay();

            startAutoplay();

        }


        /* ======================================================
         * BUTTONS
         * ====================================================== */

        prevButton.addEventListener(
            'click',
            function () {

                previous();

                restartAutoplay();

            }
        );


        nextButton.addEventListener(
            'click',
            function () {

                next();

                restartAutoplay();

            }
        );


        /* ======================================================
         * KEYBOARD
         * ====================================================== */

        carousel.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key ===
                    'ArrowLeft'
                ) {

                    event.preventDefault();

                    previous();

                    restartAutoplay();

                    return;
                }


                if (
                    event.key ===
                    'ArrowRight'
                ) {

                    event.preventDefault();

                    next();

                    restartAutoplay();

                }

            }
        );


        /* ======================================================
         * SWIPE
         * ====================================================== */

        viewport.addEventListener(
            'pointerdown',
            function (event) {

                var closestControl =
                    event.target.closest(
                        'a, button'
                    );


                if (closestControl) {
                    return;
                }


                if (
                    event.pointerType ===
                    'mouse'
                ) {

                    if (event.button !== 0) {
                        return;
                    }

                }


                pointerStartX =
                    event.clientX;


                pointerStartY =
                    event.clientY;

            }
        );


        viewport.addEventListener(
            'pointerup',
            function (event) {

                if (
                    pointerStartX ===
                    null
                ) {
                    return;
                }


                var diffX =
                    event.clientX -
                    pointerStartX;


                var diffY =
                    event.clientY -
                    pointerStartY;


                pointerStartX =
                    null;

                pointerStartY =
                    null;


                /*
                 * Вертикальный жест —
                 * обычный scroll.
                 */
                if (
                    Math.abs(diffY) >
                    Math.abs(diffX)
                ) {
                    return;
                }


                if (
                    Math.abs(diffX) <
                    45
                ) {
                    return;
                }


                if (diffX < 0) {

                    next();

                } else {

                    previous();

                }


                restartAutoplay();

            }
        );


        viewport.addEventListener(
            'pointercancel',
            function () {

                pointerStartX =
                    null;

                pointerStartY =
                    null;

            }
        );


        /* ======================================================
         * HOVER
         * ====================================================== */

        carousel.addEventListener(
            'mouseenter',
            function () {

                hovered = true;

                stopAutoplay();

            }
        );


        carousel.addEventListener(
            'mouseleave',
            function () {

                hovered = false;

                startAutoplay();

            }
        );


        /* ======================================================
         * FOCUS
         * ====================================================== */

        carousel.addEventListener(
            'focusin',
            function () {

                focused = true;

                stopAutoplay();

            }
        );


        carousel.addEventListener(
            'focusout',
            function (event) {

                var target =
                    event.relatedTarget;


                if (
                    target &&
                    carousel.contains(target)
                ) {
                    return;
                }


                focused = false;

                startAutoplay();

            }
        );


        /* ======================================================
         * VISIBILITY
         * ====================================================== */

        document.addEventListener(
            'visibilitychange',
            function () {

                if (document.hidden) {

                    stopAutoplay();

                    return;
                }


                startAutoplay();

            }
        );


        /* ======================================================
         * INTERSECTION OBSERVER
         * ====================================================== */

        if (
            'IntersectionObserver'
            in window
        ) {

            var observer =
                new IntersectionObserver(
                    function (entries) {

                        if (!entries.length) {
                            return;
                        }


                        visible =
                            entries[0]
                                .isIntersecting;


                        if (visible) {

                            startAutoplay();

                        } else {

                            stopAutoplay();

                        }

                    },
                    {
                        rootMargin:
                            '250px 0px 250px 0px',

                        threshold: 0
                    }
                );


            observer.observe(
                carousel
            );

        }


        /* ======================================================
         * RESIZE
         * ====================================================== */

        function refresh() {

            detectPerView();


            var max =
                getMaxIndex();


            if (current > max) {
                current = max;
            }


            updateControls();

            buildDots();


            window.requestAnimationFrame(
                function () {

                    render();

                }
            );


            restartAutoplay();

        }


        window.addEventListener(
            'resize',
            function () {

                window.clearTimeout(
                    resizeTimer
                );


                resizeTimer =
                    window.setTimeout(
                        refresh,
                        120
                    );

            },
            {
                passive: true
            }
        );


        if (
            'ResizeObserver'
            in window
        ) {

            var resizeObserver =
                new ResizeObserver(
                    function () {

                        window.clearTimeout(
                            resizeTimer
                        );


                        resizeTimer =
                            window.setTimeout(
                                refresh,
                                80
                            );

                    }
                );


            resizeObserver.observe(
                viewport
            );

        }


        /* ======================================================
         * REDUCED MOTION
         * ====================================================== */

        function motionChanged() {

            if (reducedMotion.matches) {

                stopAutoplay();

                return;
            }


            startAutoplay();

        }


        if (
            typeof reducedMotion
                .addEventListener ===
            'function'
        ) {

            reducedMotion.addEventListener(
                'change',
                motionChanged
            );

        } else if (
            typeof reducedMotion
                .addListener ===
            'function'
        ) {

            reducedMotion.addListener(
                motionChanged
            );

        }


        /* ======================================================
         * FIRST RENDER
         * ====================================================== */

        detectPerView();

        updateControls();

        buildDots();


        window.requestAnimationFrame(
            function () {

                window.requestAnimationFrame(
                    function () {

                        render();

                        startAutoplay();

                    }
                );

            }
        );

    }


    /*
     * Скрипт находится в footer, поэтому DOM обычно
     * уже готов. Но оставляем защиту.
     */

    if (
        document.readyState ===
        'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initWardsCarousel,
            {
                once: true
            }
        );

    } else {

        initWardsCarousel();

    }

}());
JS;


    wp_add_inline_script(
        'lv-wards-home-carousel',
        $js,
        'after'
    );

}


/* ============================================================
 * РЕНДЕР SHORTCODE
 * ============================================================ */

function lv_wards_home_render() {

    if (
        !post_type_exists('lv_ward') ||
        !function_exists('lv_get_ward_data')
    ) {

        if (current_user_can('manage_options')) {

            return '
                <div style="
                    max-width:900px;
                    margin:30px auto;
                    padding:18px 20px;
                    background:#fff;
                    border:1px solid #d63638;
                    color:#1d2327;
                ">
                    <strong>
                        База подопечных не найдена.
                    </strong>
                </div>
            ';
        }

        return '';
    }


    $query = new WP_Query([
        'post_type' =>
            'lv_ward',

        'post_status' =>
            'publish',

        'posts_per_page' =>
            -1,

        'orderby' => [
            'menu_order' => 'ASC',
            'title'      => 'ASC',
        ],

        'order' =>
            'ASC',

        'ignore_sticky_posts' =>
            true,

        'no_found_rows' =>
            true,

        'update_post_term_cache' =>
            false,
    ]);


    if (!$query->have_posts()) {
        return '';
    }


    /*
     * JS подключаем только если shortcode
     * реально присутствует на странице.
     */
    lv_wards_home_enqueue_script();


    ob_start();

    ?>


    <div
        id="cfWards"
        data-lv-wards-home
    >

        <section
            class="cw-sec"
            aria-label="Наши подопечные"
        >


            <div
                class="cw-carousel-wrapper"
                data-cw-carousel
                role="region"
                aria-label="Карусель подопечных фонда"
                tabindex="0"
            >


                <!-- PREVIOUS -->

                <button
                    class="cw-arrow cw-arrow--prev"
                    type="button"
                    aria-label="Предыдущие подопечные"
                >

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path
                            d="M15 18l-6-6 6-6"
                        />
                    </svg>

                </button>


                <!-- VIEWPORT -->

                <div class="cw-carousel-viewport">

                    <div
                        class="cw-carousel-track"
                        role="list"
                    >


                        <?php

                        foreach (
                            $query->posts
                            as $ward_post
                        ):

                            $ward =
                                lv_get_ward_data(
                                    $ward_post->ID
                                );


                            if (!$ward) {
                                continue;
                            }


                            $name =
                                !empty($ward['name'])
                                    ? $ward['name']
                                    : 'Подопечный';


                            $type_label =
                                !empty($ward['type_label'])
                                    ? $ward['type_label']
                                    : '';


                            $text_1 =
                                !empty($ward['text_1'])
                                    ? $ward['text_1']
                                    : '';


                            $text_2 =
                                !empty($ward['text_2'])
                                    ? $ward['text_2']
                                    : '';


                            $photo_id =
                                !empty($ward['photo_id'])
                                    ? absint(
                                        $ward['photo_id']
                                    )
                                    : 0;


                            $history_url =
                                !empty($ward['history_url'])
                                    ? $ward['history_url']
                                    : '';


                            /*
                             * Актуальная база возвращает is_deceased.
                             * Прямая проверка meta-поля сохраняет
                             * совместимость со старой версией helper.
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
                                'cw-card',
                            ];

                            if ($is_deceased) {
                                $card_classes[] =
                                    'cw-card--deceased';
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
                                role="listitem"
                            >


                                <!-- PHOTO -->

                                <div class="cw-card__media">


                                    <?php if ($photo_id): ?>


                                        <?php

                                        echo wp_get_attachment_image(
                                            $photo_id,
                                            'medium_large',
                                            false,
                                            [
                                                'class' =>
                                                    'cw-card__img',

                                                'alt' =>
                                                    $name,

                                                'loading' =>
                                                    'lazy',

                                                'decoding' =>
                                                    'async',

                                                'sizes' =>
                                                    '(max-width: 720px) calc(100vw - 76px), ' .
                                                    '(max-width: 1024px) calc(50vw - 70px), ' .
                                                    '350px',
                                            ]
                                        );

                                        ?>


                                    <?php else: ?>


                                        <div
                                            class="cw-card__placeholder"
                                        >
                                            Фото скоро
                                        </div>


                                    <?php endif; ?>


                                </div>


                                <!-- BODY -->

                                <div class="cw-card__body">


                                    <?php if (
                                        $type_label ||
                                        $is_deceased
                                    ): ?>

                                        <div class="cw-card__meta">

                                            <?php if ($type_label): ?>

                                                <span
                                                    class="cw-card__label"
                                                >
                                                    <?php
                                                    echo esc_html(
                                                        $type_label
                                                    );
                                                    ?>
                                                </span>

                                            <?php endif; ?>


                                            <?php if ($is_deceased): ?>

                                                <span
                                                    class="cw-card__memory"
                                                    aria-label="Подопечный ушёл из жизни"
                                                >
                                                    Страница памяти
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    <?php endif; ?>


                                    <h3
                                        class="cw-card__title"
                                    >
                                        <?php
                                        echo esc_html(
                                            $name
                                        );
                                        ?>
                                    </h3>


                                    <?php if ($text_1): ?>

                                        <p
                                            class="cw-card__sub"
                                        >
                                            <?php
                                            echo esc_html(
                                                $text_1
                                            );
                                            ?>
                                        </p>

                                    <?php endif; ?>


                                    <?php if ($text_2): ?>

                                        <p
                                            class="cw-card__desc"
                                        >
                                            <?php
                                            echo esc_html(
                                                $text_2
                                            );
                                            ?>
                                        </p>

                                    <?php endif; ?>


                                    <div class="cw-cta">


                                        <a
                                            href="<?php
                                                echo esc_url(
                                                    home_url(
                                                        '/support/'
                                                    )
                                                );
                                            ?>"
                                            class="cw-help"
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
                                                class="cw-link"
                                            >

                                                <span
                                                    class="cw-arrows"
                                                    aria-hidden="true"
                                                >

                                                    <svg
                                                        viewBox="0 0 34 16"
                                                    >
                                                        <path
                                                            d="M3 3l6 5-6 5"
                                                        />
                                                        <path
                                                            d="M14 3l6 5-6 5"
                                                        />
                                                    </svg>

                                                </span>

                                                История

                                            </a>

                                        <?php endif; ?>


                                    </div>

                                </div>

                            </article>


                        <?php endforeach; ?>


                    </div>

                </div>


                <!-- NEXT -->

                <button
                    class="cw-arrow cw-arrow--next"
                    type="button"
                    aria-label="Следующие подопечные"
                >

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path
                            d="M9 18l6-6-6-6"
                        />
                    </svg>

                </button>


            </div>


            <!-- DOTS -->

            <div
                class="cw-dots"
                aria-label="Навигация по подопечным"
            ></div>


            <!-- ALL -->

            <div class="cw-foot">

                <span class="cw-tagwrap">

                    <span
                        class="cw-tag-echo"
                        aria-hidden="true"
                    ></span>

                    <a
                        href="<?php
                            echo esc_url(
                                function_exists(
                                    'lv_wards_suite_get_catalog_url'
                                )
                                    ? lv_wards_suite_get_catalog_url()
                                    : home_url('/wards/')
                            );
                        ?>"
                        class="cw-tag"
                    >
                        Все подопечные
                    </a>

                </span>

            </div>


        </section>

    </div>


    <style>

    /* ==========================================================
       ROOT
       ========================================================== */

    #cfWards {
        --teal: #18434e;
        --coral: #c13b2e;
        --mint: #8ed4d3;
        --mint-text: #12414c;
        --beige: #f4efee;
        --border: #e7dddb;
        --muted: #6e6a63;

        --gap:
            clamp(
                16px,
                1.8vw,
                26px
            );

        position: relative;
        left: 50%;

        width: 100vw;

        margin-left: -50vw;
        margin-right: -50vw;

        overflow: hidden;

        background:
            var(--beige);

        color:
            var(--teal);

        -webkit-font-smoothing:
            antialiased;
    }


    #cfWards *,
    #cfWards *::before,
    #cfWards *::after {
        box-sizing:
            border-box;
    }


    #cfWards :where(
        h2,
        h3,
        p
    ) {
        margin: 0;
    }


    /* ==========================================================
       SECTION
       ========================================================== */

    #cfWards .cw-sec {
        width: 100%;
        max-width: 1200px;

        margin:
            0 auto;

        padding:
            clamp(56px, 6vw, 80px)
            clamp(20px, 4vw, 40px)
            clamp(64px, 7vw, 90px);
    }


    /* ==========================================================
       CAROUSEL
       ========================================================== */

    #cfWards
    .cw-carousel-wrapper {
        position: relative;

        width: 100%;
    }


    #cfWards
    .cw-carousel-viewport {
        position: relative;

        margin:
            0 22px;

        overflow: hidden;

        touch-action:
            pan-y;
    }


    #cfWards
    .cw-carousel-track {
        display: flex;

        align-items:
            stretch;

        gap:
            var(--gap);

        width: 100%;

        transform:
            translate3d(
                0,
                0,
                0
            );

        transition:
            transform
            .5s
            cubic-bezier(
                .25,
                .46,
                .45,
                .94
            );
    }


    /* ==========================================================
       CARD
       ========================================================== */

    #cfWards .cw-card {
        display: flex;

        flex:
            0 0
            calc(
                (
                    100% -
                    (2 * var(--gap))
                ) / 3
            );

        flex-direction:
            column;

        min-width: 0;

        padding: 22px;

        border:
            1px solid
            var(--border);

        border-radius:
            2px;

        background:
            #fff;

        transition:
            box-shadow .2s ease,
            transform .2s ease;
    }


    #cfWards
    .cw-card:hover {
        box-shadow:
            0 18px 40px
            rgba(
                24,
                67,
                78,
                .10
            );

        transform:
            translateY(-2px);
    }


    #cfWards
    .cw-card--deceased {
        border-color:
            rgba(
                24,
                67,
                78,
                .20
            );

        background:
            #fbfaf9;
    }


    /* ==========================================================
       PHOTO
       ========================================================== */

    #cfWards
    .cw-card__media {
        position: relative;

        width: 100%;
        height: 210px;

        overflow: hidden;

        background:
            #e8eeee;
    }


    #cfWards
    .cw-card__img {
        position:
            absolute !important;

        inset:
            0 !important;

        display:
            block !important;

        width:
            100% !important;

        height:
            100% !important;

        max-width:
            none !important;

        margin:
            0 !important;

        object-fit:
            cover !important;

        object-position:
            center center !important;
    }


    #cfWards
    .cw-card--deceased
    .cw-card__img {
        filter:
            grayscale(1);
    }


    #cfWards
    .cw-card__placeholder {
        display: flex;

        align-items:
            center;

        justify-content:
            center;

        width: 100%;
        height: 100%;

        color:
            rgba(
                24,
                67,
                78,
                .50
            );

        font-size:
            12px;

        font-weight:
            700;

        letter-spacing:
            .08em;

        text-transform:
            uppercase;
    }


    /* ==========================================================
       BODY
       ========================================================== */

    #cfWards
    .cw-card__body {
        display: flex;

        flex:
            1 1 auto;

        flex-direction:
            column;

        min-width: 0;

        padding-top:
            22px;
    }


    #cfWards
    .cw-card__meta {
        display: flex;

        align-items:
            center;

        justify-content:
            space-between;

        flex-wrap:
            wrap;

        gap:
            8px
            12px;

        margin-bottom:
            12px;
    }


    #cfWards
    .cw-card__label {
        display: block;

        margin:
            0;

        color:
            var(--coral);

        font-size:
            13px;

        font-weight:
            700;

        letter-spacing:
            .03em;

        line-height:
            1.2;
    }


    #cfWards
    .cw-card__memory {
        display:
            inline-flex;

        align-items:
            center;

        min-height:
            24px;

        padding:
            4px
            9px;

        border:
            1px solid
            rgba(
                24,
                67,
                78,
                .18
            );

        border-radius:
            999px;

        background:
            rgba(
                24,
                67,
                78,
                .06
            );

        color:
            var(--teal);

        font-size:
            10px;

        font-weight:
            700;

        letter-spacing:
            .08em;

        line-height:
            1;

        text-transform:
            uppercase;
    }


    #cfWards
    .cw-card--deceased
    .cw-card__label {
        color:
            var(--muted);
    }


    #cfWards
    .cw-card__title {
        color:
            var(--teal);

        font-size:
            23px;

        font-weight:
            400;

        line-height:
            1.25;
    }


    #cfWards
    .cw-card__sub {
        min-height:
            38px;

        margin-top:
            8px;

        color:
            var(--teal);

        font-size:
            14px;

        font-style:
            italic;

        line-height:
            1.35;

        opacity:
            .85;
    }


    #cfWards
    .cw-card__desc {
        min-height:
            64px;

        margin-top:
            10px;

        margin-bottom:
            20px;

        color:
            var(--muted);

        font-size:
            14.5px;

        line-height:
            1.45;
    }


    /* ==========================================================
       CTA
       ========================================================== */

    #cfWards .cw-cta {
        display: flex;

        align-items:
            center;

        justify-content:
            space-between;

        gap: 14px;

        margin-top:
            auto;

        padding-top:
            4px;
    }


    #cfWards .cw-help {
        flex:
            0 0 auto;

        padding:
            12px
            28px;

        border-radius:
            30px;

        background:
            var(--coral);

        color:
            #fff;

        font-size:
            14px;

        font-weight:
            700;

        line-height:
            1;

        text-decoration:
            none !important;

        transition:
            filter .2s ease,
            transform .15s ease;
    }


    #cfWards
    .cw-help:hover {
        color: #fff;

        filter:
            brightness(1.05);

        transform:
            translateY(-1px);
    }


    /*
     * У карточки памяти CTA поддерживает других животных,
     * а не умершего подопечного.
     */
    #cfWards
    .cw-card--deceased
    .cw-help {
        padding-right:
            20px;

        padding-left:
            20px;

        background:
            var(--teal);
    }


    #cfWards .cw-link {
        display: flex;

        align-items:
            center;

        gap: 10px;

        color:
            var(--teal);

        font-size:
            12px;

        font-weight:
            700;

        letter-spacing:
            .08em;

        line-height:
            1;

        text-decoration:
            none !important;

        text-transform:
            uppercase;

        transition:
            opacity .2s ease;
    }


    #cfWards
    .cw-link:hover {
        color:
            var(--teal);

        opacity:
            .7;
    }


    #cfWards
    .cw-arrows {
        display: flex;

        color:
            var(--coral);
    }


    #cfWards
    .cw-arrows svg {
        display: block;

        width: 28px;
        height: 14px;

        fill: none;

        stroke:
            currentColor;

        stroke-width:
            2;

        stroke-linecap:
            round;

        stroke-linejoin:
            round;
    }


    /* ==========================================================
       ARROWS
       ========================================================== */

    #cfWards .cw-arrow {
        position:
            absolute;

        z-index:
            20;

        top:
            50%;

        display:
            flex;

        align-items:
            center;

        justify-content:
            center;

        width:
            44px;

        height:
            44px;

        padding:
            0;

        border:
            1.5px solid
            var(--border);

        border-radius:
            50%;

        background:
            #fff;

        color:
            var(--teal);

        box-shadow:
            0 4px 14px
            rgba(
                24,
                67,
                78,
                .10
            );

        cursor:
            pointer;

        transform:
            translateY(-50%);

        transition:
            background-color .2s,
            border-color .2s,
            color .2s;
    }


    #cfWards
    .cw-arrow:hover {
        border-color:
            var(--teal);

        background:
            var(--teal);

        color:
            #fff;
    }


    #cfWards
    .cw-arrow svg {
        width:
            22px;

        height:
            22px;

        pointer-events:
            none;
    }


    #cfWards
    .cw-arrow--prev {
        left: 0;
    }


    #cfWards
    .cw-arrow--next {
        right: 0;
    }


    #cfWards
    .cw-arrow[hidden] {
        display:
            none !important;
    }


    /* ==========================================================
       DOTS
       ========================================================== */

    #cfWards .cw-dots {
        display: flex;

        align-items:
            center;

        justify-content:
            center;

        min-height:
            34px;

        margin-top:
            20px;
    }


    #cfWards
    .cw-dots:empty {
        display: none;
    }


    #cfWards .cw-dot {
        display: grid;

        width:
            34px;

        height:
            34px;

        padding:
            0;

        place-items:
            center;

        border:
            0;

        background:
            transparent;

        cursor:
            pointer;
    }


    #cfWards
    .cw-dot::before {
        display: block;

        width:
            10px;

        height:
            10px;

        border-radius:
            50%;

        background:
            var(--border);

        content:
            '';

        transition:
            background-color .25s,
            transform .25s;
    }


    #cfWards
    .cw-dot.active::before {
        background:
            var(--teal);

        transform:
            scale(1.2);
    }


    /* ==========================================================
       FOOT
       ========================================================== */

    #cfWards .cw-foot {
        display: flex;

        justify-content:
            center;

        margin-top:
            clamp(
                28px,
                3vw,
                42px
            );
    }


    #cfWards
    .cw-tagwrap {
        position:
            relative;

        display:
            inline-block;
    }


    #cfWards .cw-tag {
        position:
            relative;

        z-index:
            2;

        display:
            inline-block;

        padding:
            18px
            46px
            18px
            34px;

        background:
            var(--mint);

        color:
            var(--mint-text);

        font-size:
            14px;

        font-weight:
            700;

        letter-spacing:
            .08em;

        line-height:
            1;

        text-decoration:
            none !important;

        text-transform:
            uppercase;

        -webkit-clip-path:
            polygon(
                0 0,
                calc(100% - 18px) 0,
                100% 50%,
                calc(100% - 18px) 100%,
                0 100%
            );

        clip-path:
            polygon(
                0 0,
                calc(100% - 18px) 0,
                100% 50%,
                calc(100% - 18px) 100%,
                0 100%
            );
    }


    #cfWards
    .cw-tag-echo {
        position:
            absolute;

        z-index:
            1;

        inset:
            0;

        background:
            var(--mint);

        opacity:
            .38;

        transform:
            translateX(12px);

        -webkit-clip-path:
            polygon(
                0 0,
                calc(100% - 18px) 0,
                100% 50%,
                calc(100% - 18px) 100%,
                0 100%
            );

        clip-path:
            polygon(
                0 0,
                calc(100% - 18px) 0,
                100% 50%,
                calc(100% - 18px) 100%,
                0 100%
            );
    }


    /* ==========================================================
       FOCUS
       ========================================================== */

    #cfWards
    :where(
        a,
        button,
        [tabindex]
    ):focus-visible {
        outline:
            3px solid
            var(--mint);

        outline-offset:
            3px;
    }


    /* ==========================================================
       TABLET
       ========================================================== */

    @media (
        max-width:
        1024px
    ) {

        #cfWards
        .cw-card {
            flex-basis:
                calc(
                    (
                        100% -
                        var(--gap)
                    ) / 2
                );
        }

    }


    /* ==========================================================
       MOBILE
       ========================================================== */

    @media (
        max-width:
        720px
    ) {

        #cfWards
        .cw-sec {
            padding:
                56px
                20px
                64px;
        }


        #cfWards
        .cw-carousel-viewport {
            margin:
                0;
        }


        #cfWards
        .cw-card {
            flex-basis:
                100%;

            padding:
                18px;
        }


        #cfWards
        .cw-card__media {
            height:
                clamp(
                    210px,
                    60vw,
                    300px
                );
        }


        #cfWards
        .cw-card__sub,

        #cfWards
        .cw-card__desc {
            min-height:
                0;
        }


        #cfWards
        .cw-arrow {
            top:
                145px;

            width:
                38px;

            height:
                38px;
        }


        #cfWards
        .cw-arrow--prev {
            left:
                8px;
        }


        #cfWards
        .cw-arrow--next {
            right:
                8px;
        }

    }


    /* ==========================================================
       SMALL MOBILE
       ========================================================== */

    @media (
        max-width:
        420px
    ) {

        #cfWards
        .cw-cta {
            gap:
                10px;
        }


        #cfWards
        .cw-help {
            padding:
                11px
                23px;
        }


        #cfWards
        .cw-link {
            gap:
                6px;
        }

    }


    /* ==========================================================
       REDUCED MOTION
       ========================================================== */

    @media (
        prefers-reduced-motion:
        reduce
    ) {

        #cfWards
        .cw-carousel-track,

        #cfWards
        .cw-card,

        #cfWards
        .cw-help,

        #cfWards
        .cw-dot::before {
            transition:
                none;
        }

    }

    </style>


    <?php

    wp_reset_postdata();

    return ob_get_clean();
}
