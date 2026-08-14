<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\AdminPanel\services\AdminMenuIndex;
use Besnovatyj\Modman\menu\MenuProvider;
use yii\di\Container;

/**
 * DI-проводка админ-панели.
 *
 * Загружается ГЛОБАЛЬНО из {@see \Besnovatyj\AdminPanel\Bootstrap} (registry-gated L2), а не только
 * при инициализации модуля: индекс меню нужен палитре, которая рендерится на любой странице админки,
 * в том числе на маршрутах чужих модулей.
 *
 * Здесь же — единственный шов с приложением: вклады группы `admin-menu` даёт движок конфигов
 * приложения (`common\config\ConfigFactory`). Сервис индекса про него не знает и получает готовое
 * замыкание, поэтому пакет не зависит от классов app-скелета: подменить источник вкладов можно,
 * переопределив эту регистрацию.
 */
return function (Container $container): void {
    $container->setSingleton(AdminMenuIndex::class, static function (Container $c): AdminMenuIndex {
        return new AdminMenuIndex(
            $c->get(MenuProvider::class),
            static function (): array {
                // Мягкая связь с движком конфигов приложения: без него меню просто пустое, а не фатал.
                $factoryClass = '\\common\\config\\ConfigFactory';
                if (!class_exists($factoryClass)) {
                    Yii::warning(
                        'AdminPanel: движок конфигов ' . $factoryClass . ' недоступен — меню не собрано.',
                        'AdminPanel',
                    );
                    return [];
                }
                $factory = new $factoryClass();

                return $factory->has('admin-menu') ? $factory->get('admin-menu') : [];
            },
        );
    });
};
