<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\AdminPanel\widgets\palette;

use Besnovatyj\AdminPanel\Module;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * Палитра команд админки: поиск по всем разделам + видимая карта админки (Ctrl/Cmd+K).
 *
 * Виджет выводит ТОЛЬКО инициализирующий ES-модуль: разметку палитра строит сама при первом открытии,
 * а список разделов подтягивает по XHR ({@see \Besnovatyj\AdminPanel\controllers\backend\PaletteController}).
 * Поэтому цена присутствия палитры на странице — один `<script type="module">` и один CSS-файл, а не
 * встроенный в каждую страницу индекс меню.
 *
 * Обычно подключается автоматически ({@see \Besnovatyj\AdminPanel\Bootstrap}). Ручная вставка нужна,
 * только если `params.palette.autoInject` выключен:
 * ```php
 * echo \Besnovatyj\AdminPanel\widgets\palette\CommandPaletteWidget::widget();
 * ```
 */
class CommandPaletteWidget extends Widget
{
    /** Клавиша, открывающая палитру вместе с Ctrl/Cmd; null — из настроек модуля. */
    public ?string $hotkey = null;

    /** Имя ESM-модуля внутри опубликованного `dist`. */
    public string $moduleFile = 'admin-palette.js';

    public function run(): string
    {
        $params = (array)(Yii::$app->getModule(Module::MODULE_ID)?->params['palette'] ?? []);
        if (($params['enabled'] ?? true) === false) {
            return '';
        }

        // Пакет поставляется с исходниками TS; пока фронт не собран, палитры просто нет — молча
        // ломать страницу публикацией несуществующего бандла нельзя.
        if (!is_file(__DIR__ . '/../../../dist/' . $this->moduleFile)) {
            Yii::warning(
                'AdminPanel: фронтенд палитры не собран (dist/' . $this->moduleFile . '). Выполните npm run build в пакете.',
                'AdminPanel',
            );

            return '';
        }

        $bundle = PaletteAsset::register($this->view);

        $moduleUrl = Json::encode($bundle->baseUrl . '/' . $this->moduleFile);
        $config = Json::encode([
            'endpoint' => Url::to(['/AdminPanel/backend/palette/index']),
            'hotkey' => (string)($this->hotkey ?? $params['hotkey'] ?? 'k'),
            // Индекс приватный (режется правами), поэтому кэш вкладки привязан к пользователю.
            'storageKey' => 'bescms.adminPanel.palette.' . (string)(Yii::$app->user->getId() ?? 'guest'),
        ]);

        $js = <<<JS
        import($moduleUrl)
            .then(({ createCommandPalette }) => createCommandPalette($config))
            .catch((err) => console.error('Command palette init error:', err));
        JS;

        return Html::script($js, ['type' => 'module']);
    }
}
