<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\AdminPanel\widgets\palette;

use yii\web\AssetBundle;

/**
 * Собранная палитра команд: публикует `dist/` пакета (ESM-модуль + его стили).
 *
 * JS в `$js` не объявляется — модуль подгружается виджетом через динамический `import()`, поэтому
 * на страницах, где палитру не открывали, браузер не парсит её код. Стили, наоборот, объявлены
 * обычным `$css`: они нужны в момент первого открытия и слишком малы, чтобы городить ленивую загрузку.
 */
class PaletteAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/../../../dist';

    public $css = [
        'admin-palette.css',
    ];
}
