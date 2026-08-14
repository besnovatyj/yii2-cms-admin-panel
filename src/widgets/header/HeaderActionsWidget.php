<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\AdminPanel\widgets\header;

use Besnovatyj\AdminPanel\Module;
use Besnovatyj\AdminPanel\services\AdminMenuIndex;
use Yii;
use yii\base\Widget;

/**
 * Кнопки быстрых действий в шапке админки.
 *
 * Заменяет хардкод кнопок в layout'е приложения: любой модуль кладёт свою кнопку в локацию меню
 * `header-quick-links` (обычным `_meta.placements` в своём `adminMenu.php`) и она появляется в шапке —
 * менять layout под каждый новый модуль больше не нужно.
 *
 * Первой рендерится кнопка палитры команд (дублёр Ctrl/Cmd+K для тех, кто про хоткей не знает);
 * отключается настройкой `params.palette.enabled` или свойством {@see $showPaletteButton}.
 *
 * Пункт-кнопка может как вести по маршруту (`url` массивом — тогда работает RBAC), так и быть чистым
 * действием (`url = '#'` + свои `linkOptions`, например `data-bs-toggle`). Во втором случае модуль
 * сам отвечает за то, кому кнопка видна.
 *
 * Пример вклада модуля:
 * ```php
 * // src/config/adminMenu.php
 * [
 *     'label' => 'Файлы',
 *     'iconClass' => 'bi bi-folder2-open',
 *     'url' => ['/File/backend/file/index'],
 *     'linkOptions' => ['class' => 'btn btn-sm btn-warning', 'title' => 'Файловый менеджер'],
 *     '_meta' => ['placements' => [['location' => 'header-quick-links', 'priority' => 100]]],
 * ]
 * ```
 */
class HeaderActionsWidget extends Widget
{
    /** Локация меню, из которой берутся кнопки; null — из настроек модуля. */
    public ?string $location = null;

    /** Показывать ли кнопку открытия палитры команд. */
    public bool $showPaletteButton = true;

    /** CSS-класс кнопок по умолчанию (модуль может переопределить в `linkOptions.class` пункта). */
    public string $buttonClass = 'btn btn-sm btn-outline-light';

    /** HTML-опции обёртки `<ul>`. */
    public array $options = ['class' => 'navbar-nav flex-row align-items-center'];

    /**
     * @param AdminMenuIndex       $index  Индекс меню (резолвится DI-контейнером при `::widget()`).
     * @param array<string, mixed> $config Конфигурация виджета.
     */
    public function __construct(
        private readonly AdminMenuIndex $index,
        array $config = [],
    ) {
        parent::__construct($config);
    }

    public function run(): string
    {
        $module = Yii::$app->getModule(Module::MODULE_ID);
        $params = (array)($module?->params ?? []);

        $location = $this->location ?? (string)($params['header']['location'] ?? 'header-quick-links');
        $paletteEnabled = $this->showPaletteButton && ($params['palette']['enabled'] ?? true) !== false;

        return $this->render('actions', [
            'items' => $this->index->actions($location),
            'showPaletteButton' => $paletteEnabled,
            'buttonClass' => $this->buttonClass,
            'options' => $this->options,
        ]);
    }
}
