<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\AdminPanel;

use Besnovatyj\Contracts\module\DeclaresModule;
use Besnovatyj\Contracts\module\ProvidesAdminMenu;
use Besnovatyj\Contracts\module\ProvidesAppConfig;
use Besnovatyj\Contracts\module\ProvidesBootstrap;
use Besnovatyj\Kernel\module\CmsModule;

/**
 * Модуль самой админ-панели: навигация как предметная область.
 *
 * Отвечает не за контент, а за то, КАК админка доступна пользователю. Собирает меню всех локаций
 * (вклады `adminMenu.php` модулей, скомпилированные modman) в единый индекс и предъявляет его тремя
 * способами:
 *  - палитра команд ({@see widgets\palette\CommandPaletteWidget}) — Ctrl/Cmd+K, поиск + видимая карта
 *    всех разделов; закрывает проблему «служебных пунктов много, где что — не помню»;
 *  - быстрые действия в шапке ({@see widgets\header\HeaderActionsWidget}) — локация `header-quick-links`,
 *    куда любой модуль кладёт свою кнопку вместо правки layout'а приложения;
 *  - страница «Настройки» ({@see controllers\backend\SettingsController}) — служебные разделы
 *    карточками на полноценном экране, а не в узком offcanvas.
 *
 * Модуль не имеет своей БД: единственный источник данных — индекс меню ({@see services\AdminMenuIndex}),
 * который фильтруется правами текущего пользователя.
 */
class Module extends CmsModule implements
    DeclaresModule, ProvidesAdminMenu,
    ProvidesAppConfig, ProvidesBootstrap
{
    public const bool EDITABLE = true;
    public const string VERSION = '1.0.0';
    public const string MODULE_ID = 'AdminPanel';

    public static function moduleId(): string { return self::MODULE_ID; }
    public static function moduleVersion(): string { return self::VERSION; }
    public static function isEditable(): bool { return self::EDITABLE; }
    public static function adminMenu(): array { return require __DIR__ . '/config/adminMenu.php'; }
    public static function moduleConfig(): array { return require __DIR__ . '/config/config.php'; }
    public static function bootstrapClasses(): array { return [Bootstrap::class]; }

    /**
     * Индекс палитры отдаётся любому ВОШЕДШЕМУ в админку пользователю, поэтому маршрут добавляется в
     * whitelist ядрового гейта: иначе палитра требовала бы отдельной выдачи прав каждому — при том, что
     * её содержимое и так режется RBAC ({@see \Besnovatyj\Kernel\security\MenuAccessFilter}), а гостя
     * отсекает сам контроллёр. Гейт остаётся у ядра — модуль лишь ДОПОЛНЯЕТ allowlist.
     */
    public static function appConfig(): array
    {
        return [
            'app-backend' => [
                'as access' => [
                    'allowActions' => [
                        'AdminPanel/backend/palette/index',
                    ],
                ],
            ],
        ];
    }
}
