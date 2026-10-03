<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Contracts\adminMenu\AdminMenuLocation;

/**
 * Базовая конфигурация Yii-модуля админ-панели.
 *
 * Все настройки лежат в `params` и читаются лениво (при рендере страницы), поэтому их можно
 * переопределить обычным способом — вкладом в группу `common`/`app-backend` или локальным конфигом.
 */
return [
    'id' => 'AdminPanel',
    'params' => [
        'iconClass' => 'bi bi-window-sidebar',

        // Палитра команд (Ctrl/Cmd+K).
        'palette' => [
            // Полностью отключить палитру (виджет не рендерится, индекс не собирается).
            'enabled' => true,
            // Подключать палитру ко всем страницам админки автоматически (см. Bootstrap). Если false —
            // вставить `CommandPaletteWidget::widget()` в layout вручную.
            'autoInject' => true,
            // Локации меню, попадающие в палитру. Локации-действия (HeaderQuickLinks) не индексируются:
            // это кнопки, а не разделы.
            'locations' => [AdminMenuLocation::LeftSidebar, AdminMenuLocation::RightSidebar],
            // Клавиша, открывающая палитру вместе с Ctrl (Windows/Linux) или Cmd (macOS).
            'hotkey' => 'k',
        ],

        // Страница «Настройки» (/AdminPanel/backend/settings/index).
        'settingsPage' => [
            // Локации, разделы которых показываются карточками. По умолчанию — служебный сайдбар.
            'locations' => [AdminMenuLocation::RightSidebar],
        ],

        // Быстрые действия в шапке админки.
        'header' => [
            // Локация меню, из которой модули поставляют кнопки шапки.
            'location' => AdminMenuLocation::HeaderQuickLinks,
        ],
    ],
];
