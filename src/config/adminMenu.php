<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Contracts\adminMenu\AdminMenuLocation;
use Besnovatyj\Contracts\adminMenu\AdminMenuPlacement;

return [[
    'label' => 'Настройки',
    'iconClass' => 'bi bi-sliders2 me-1',
    'url' => ['/AdminPanel/backend/settings/index'],
    'active' => static function (): bool {
        return str_contains(\Yii::$app->request->url, 'AdminPanel/backend/settings');
    },
    '_meta' => [
        'placements' => [
            new AdminMenuPlacement(
                location: AdminMenuLocation::RightSidebar,
                group: 'Service',
                groupIcon: 'bi bi-sliders',
                // Выше остальных служебных пунктов: это точка входа во все настройки.
                groupPriority: 100,
                priority: 10,
            ),
        ],
    ],
]];
