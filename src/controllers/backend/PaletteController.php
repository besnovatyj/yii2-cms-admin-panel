<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\AdminPanel\controllers\backend;

use Besnovatyj\AdminPanel\services\AdminMenuIndex;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Источник данных палитры команд: JSON-индекс разделов админки для текущего пользователя.
 *
 * Отдаётся ЛЕНИВО — фронт запрашивает индекс при первом открытии палитры, а не на каждой странице,
 * поэтому обычный запрос админки не платит за палитру ничем.
 *
 * Доступ: маршрут добавлен в whitelist ядрового гейта ({@see \Besnovatyj\AdminPanel\Module::appConfig()}),
 * поэтому право на него не нужно выдавать отдельно каждому пользователю. Это безопасно, потому что
 * состав ответа режется RBAC внутри {@see AdminMenuIndex}, а гость отсекается здесь. Ответ приватный —
 * запрещаем его кэширование прокси и браузером.
 */
class PaletteController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly AdminMenuIndex $index,
        array $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['index' => ['GET']],
            ],
        ];
    }

    /**
     * @return array{sections: array<int, array<string, mixed>>}
     * @throws ForbiddenHttpException Если запрос пришёл от неаутентифицированного пользователя.
     */
    public function actionIndex(): array
    {
        if (Yii::$app->user->getIsGuest()) {
            throw new ForbiddenHttpException('Индекс админ-панели доступен только после входа.');
        }

        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->headers->set('Cache-Control', 'no-store, private');

        $locations = (array)($this->module->params['palette']['locations'] ?? ['left-sidebar', 'right-sidebar']);

        return $this->index->toArray($locations);
    }
}
