<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\AdminPanel\Module;

/**
 * Yii2-конфиг модуля для движка yiisoft/config (группа `common` — общий для всех приложений).
 *
 * Объявляется через `extra.config-plugin`, собирается modman в merge-plan и мёржится в рантайме.
 * Регистрирует модуль и его L2-bootstrap (registry-gated: попадает в конфиг только когда модуль
 * активен). Значения — из статических методов {@see Module}, без дублирования.
 */
return [
    'modules' => [
        Module::moduleId() => array_merge(
            ['class' => Module::class],
            Module::moduleConfig(),
        ),
    ],
    // L2-bootstrap: DI-проводка индекса меню + автоподключение палитры к страницам админки. Нужен
    // глобально, а не только на маршрутах модуля: палитра живёт на КАЖДОЙ странице бэкенда.
    'bootstrap' => array_values(Module::bootstrapClasses()),
];
