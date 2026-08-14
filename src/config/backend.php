<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\AdminPanel\Module;

/**
 * Пер-аппликационный вклад админ-панели в backend (группа `app-backend`) для движка yiisoft/config.
 *
 * Добавляет маршрут индекса палитры в whitelist ядрового гейта — append к `as access.allowActions`
 * (не переопределение). Обоснование — в {@see Module::appConfig()}.
 */
return Module::appConfig()['app-backend'] ?? [];
