<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\AdminPanel;

use Besnovatyj\AdminPanel\widgets\palette\CommandPaletteWidget;
use Throwable;
use Yii;
use yii\base\BootstrapInterface;
use yii\web\Application;
use yii\web\View;

/**
 * Глобальный bootstrap админ-панели (L2, гейт modman).
 *
 * Делает две вещи:
 *  1. Регистрирует DI-проводку индекса меню — он нужен и вне маршрутов модуля (палитра рендерится
 *     на страницах чужих модулей).
 *  2. Подключает палитру команд ко ВСЕМ страницам админки, подписываясь на конец body. Так модуль
 *     остаётся самодостаточным: установил — палитра работает, выключил — исчезла, layout приложения
 *     править не нужно. Отключается настройкой `params.palette.autoInject` (тогда виджет ставится
 *     в layout руками).
 *
 * Все проверки внутри обработчика ленивые: пока страница не дошла до `endBody()`, модуль не
 * инстанцируется и меню не собирается. Событие срабатывает только при полном рендере layout'а,
 * поэтому на AJAX/JSON-ответах палитра не подмешивается.
 */
final class Bootstrap implements BootstrapInterface
{
    public function bootstrap($app): void
    {
        (require __DIR__ . '/config/container.php')(Yii::$container);

        if (!$app instanceof Application || $app->id !== 'app-backend') {
            return;
        }

        $app->view->on(View::EVENT_END_BODY, static function () use ($app): void {
            try {
                if ($app->user->getIsGuest()) {
                    return;
                }

                $palette = (array)($app->getModule(Module::MODULE_ID)?->params['palette'] ?? []);
                if (($palette['enabled'] ?? true) === false || ($palette['autoInject'] ?? true) === false) {
                    return;
                }

                echo CommandPaletteWidget::widget();
            } catch (Throwable $e) {
                // Навигационная надстройка не имеет права ронять страницу админки.
                Yii::$app->errorHandler->logException($e);
            }
        });
    }
}
