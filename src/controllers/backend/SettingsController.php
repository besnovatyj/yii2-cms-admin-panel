<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\AdminPanel\controllers\backend;

use Besnovatyj\AdminPanel\services\AdminMenuIndex;
use yii\web\Controller;

/**
 * Страница «Настройки» — служебные разделы админки на полноценном экране.
 *
 * Замена навигации в узком offcanvas: те же пункты (по умолчанию локация `right-sidebar`), но
 * карточками, с местом под заголовки групп и без охоты за маленькой кнопкой в шапке. Состав
 * определяется настройкой `params.settingsPage.locations` — страница ничего не знает о конкретных
 * модулях и показывает то, что они сами объявили в своих `adminMenu.php`.
 */
class SettingsController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly AdminMenuIndex $index,
        array $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function actionIndex(): string
    {
        $locations = (array)($this->module->params['settingsPage']['locations'] ?? ['right-sidebar']);

        return $this->render('index', [
            'sections' => $this->index->sectionsFor($locations),
        ]);
    }
}
