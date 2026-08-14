<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/**
 * Кнопки быстрых действий шапки.
 *
 * Пункт без маршрута (`url` не массив) рендерится кнопкой — так модуль может повесить на неё своё
 * поведение через `linkOptions` (data-атрибуты Bootstrap, обработчики). Пункт с маршрутом — ссылкой.
 * Подпись видна на широких экранах, на узких остаётся иконка (место в шапке ограничено), поэтому
 * подпись дублируется в `title`/`aria-label`.
 *
 * @var yii\web\View $this
 * @var array<int, array<string, mixed>> $items пункты локации, уже отфильтрованные правами
 * @var bool $showPaletteButton показывать ли кнопку палитры команд
 * @var string $buttonClass CSS-класс кнопок по умолчанию
 * @var array<string, mixed> $options HTML-опции обёртки
 */

$renderItem = static function (array $item) use ($buttonClass): string {
    $label = (string)($item['label'] ?? '');
    $icon = (string)($item['iconClass'] ?? '');
    $url = $item['url'] ?? '#';

    $linkOptions = (array)($item['linkOptions'] ?? []);
    $linkOptions['class'] ??= $buttonClass;
    $linkOptions['title'] ??= $label;
    $linkOptions['aria-label'] ??= $label;

    $content = ($icon !== '' ? '<i class="' . Html::encode($icon) . '"></i>' : '')
        . ($label !== ''
            ? '<span class="' . ($icon !== '' ? 'd-none d-xxl-inline ms-1' : '') . '">' . Html::encode($label) . '</span>'
            : '');

    $control = is_array($url)
        ? Html::a($content, $url, $linkOptions)
        : Html::button($content, ArrayHelper::merge(['type' => 'button'], $linkOptions));

    return Html::tag('li', $control, ['class' => 'nav-item text-nowrap px-1']);
};

$controls = [];

if ($showPaletteButton) {
    $controls[] = Html::tag('li', Html::button(
        '<i class="bi bi-search"></i>'
        . '<span class="d-none d-lg-inline ms-1">Поиск</span>'
        . '<kbd class="d-none d-xxl-inline ms-2 small">Ctrl K</kbd>',
        [
            'type' => 'button',
            'class' => $buttonClass,
            // Обработчик вешает палитра: любой элемент с этим атрибутом открывает её.
            'data-command-palette' => '',
            'title' => 'Поиск по админке (Ctrl/Cmd + K)',
            'aria-label' => 'Поиск по админке',
        ],
    ), ['class' => 'nav-item text-nowrap px-1']);
}

foreach ($items as $item) {
    if (is_array($item) && isset($item['label'])) {
        $controls[] = $renderItem($item);
    }
}

echo $controls === [] ? '' : Html::tag('ul', implode("\n", $controls), $options);
