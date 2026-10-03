<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\AdminPanel\services;

use Besnovatyj\Contracts\adminMenu\AdminMenuLocation;

/**
 * Раздел админ-меню: группа пунктов одной локации (`Blog`, `Service`, `Logs`, …).
 *
 * Пункты, объявленные модулями без группы, собираются в раздел с {@see $title} = null — потребитель
 * решает, как его показать (палитра — блоком «Прочее», страница настроек — карточкой без заголовка).
 */
final readonly class MenuSection
{
    /**
     * @param string|null $title    Заголовок группы; null — пункты вне групп.
     * @param string      $icon     CSS-класс иконки группы.
     * @param AdminMenuLocation $location Локация меню, из которой собран раздел.
     * @param MenuLink[]  $links    Пункты раздела в порядке, заданном приоритетами модулей.
     */
    public function __construct(
        public ?string $title,
        public string  $icon,
        public AdminMenuLocation $location,
        public array   $links,
    ) {}

    /**
     * @return array<string, mixed> Плоское представление для JSON-индекса палитры.
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'icon' => $this->icon,
            'location' => $this->location->value,
            'links' => array_map(static fn(MenuLink $link): array => $link->toArray(), $this->links),
        ];
    }
}
