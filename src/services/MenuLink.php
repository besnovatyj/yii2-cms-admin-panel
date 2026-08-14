<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\AdminPanel\services;

/**
 * Один пункт админ-меню, приведённый к плоскому виду.
 *
 * Отличие от «сырого» пункта меню модуля: URL уже разрешён в строку, а замыкание `active` отброшено —
 * это данные, годные и для JSON-индекса палитры, и для рендера страницы настроек.
 */
final readonly class MenuLink
{
    /**
     * @param string      $label Подпись пункта.
     * @param string      $url   Готовый href.
     * @param string      $icon  CSS-класс иконки (может быть пустым).
     * @param string|null $route Маршрут Yii без ведущего слэша (напр. `Blog/backend/post/index`);
     *                           null — если url задан строкой. Служит ключом дедупликации и
     *                           дополнительным словом для поиска.
     */
    public function __construct(
        public string  $label,
        public string  $url,
        public string  $icon = '',
        public ?string $route = null,
    ) {}

    /**
     * @return array<string, mixed> Плоское представление для JSON-индекса палитры.
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'url' => $this->url,
            'icon' => $this->icon,
            'route' => $this->route,
        ];
    }
}
