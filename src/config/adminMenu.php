<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

return [[
    'label' => 'Настройки',
    'iconClass' => 'bi bi-sliders2 me-1',
    'url' => ['/AdminPanel/backend/settings/index'],
    'active' => static function (): bool {
        return str_contains(\Yii::$app->request->url, 'AdminPanel/backend/settings');
    },
    '_meta' => [
        'placements' => [
            [
                'location' => 'right-sidebar',
                'group' => 'Service',
                'groupIcon' => 'bi bi-sliders',
                // Выше остальных служебных пунктов: это точка входа во все настройки.
                'priority' => 10,
                'groupPriority' => 100,
            ],
        ],
    ],
]];
