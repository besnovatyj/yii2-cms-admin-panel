<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\AdminPanel\services;

use Besnovatyj\Kernel\security\MenuAccessFilter;
use Besnovatyj\Modman\menu\MenuProvider;
use Closure;
use yii\helpers\Url;

/**
 * Единый индекс админ-меню: одно место, где дерево пунктов превращается в плоские разделы.
 *
 * Источник — тот же, что у сайдбаров: вклады группы `admin-menu`, скомпилированные
 * {@see MenuProvider} по локациям. Здесь дерево дополнительно:
 *  - фильтруется правами текущего пользователя ({@see MenuAccessFilter}) — пользователь никогда не
 *    видит в палитре и настройках то, чего ему не открыть;
 *  - разрешается в готовые URL и очищается от замыканий `active`, т.е. становится сериализуемым;
 *  - дедуплицируется по маршруту при сборке НЕСКОЛЬКИХ локаций сразу (пункт, размещённый модулем и
 *    в левом, и в правом сайдбаре, попадает в индекс один раз — по первой локации в списке).
 *
 * Результат мемоизируется на запрос: палитра и страница настроек в одном запросе не пересобирают меню.
 */
final class AdminMenuIndex
{
    /** @var array<string, MenuSection[]> location => разделы (кэш на запрос) */
    private array $byLocation = [];

    /** @var array<int, array>|null Вклады группы `admin-menu` (кэш на запрос). */
    private ?array $contributions = null;

    /**
     * @param MenuProvider $provider          Компилятор меню по локациям (modman).
     * @param Closure      $contributionsHook Поставщик вкладов группы `admin-menu`: `fn(): array`.
     *                                        Задаётся в `config/container.php` — это шов с движком
     *                                        конфигов приложения.
     */
    public function __construct(
        private readonly MenuProvider $provider,
        private readonly Closure $contributionsHook,
    ) {}

    /**
     * Разделы одной локации.
     *
     * @param string $location Локация меню (`left-sidebar`, `right-sidebar`, `header-quick-links`, …).
     * @return MenuSection[]
     */
    public function sections(string $location): array
    {
        return $this->byLocation[$location] ??= $this->build($location);
    }

    /**
     * Разделы нескольких локаций подряд, без повторов одного и того же маршрута.
     *
     * @param string[] $locations Локации в порядке показа.
     * @return MenuSection[]
     */
    public function sectionsFor(array $locations): array
    {
        $sections = [];
        $seenRoutes = [];

        foreach ($locations as $location) {
            foreach ($this->sections($location) as $section) {
                $links = [];
                foreach ($section->links as $link) {
                    if ($link->route !== null) {
                        if (isset($seenRoutes[$link->route])) {
                            continue;
                        }
                        $seenRoutes[$link->route] = true;
                    }
                    $links[] = $link;
                }

                if ($links !== []) {
                    $sections[] = new MenuSection($section->title, $section->icon, $section->location, $links);
                }
            }
        }

        return $sections;
    }

    /**
     * Сырые пункты локации-действий (шапка админки) — БЕЗ приведения к {@see MenuLink}.
     *
     * Кнопка в шапке не обязана быть переходом по маршруту: модуль вправе отдать пункт с `url = '#'`
     * и своими `linkOptions` (например, открывающий offcanvas). Такие пункты {@see MenuAccessFilter}
     * отбрасывает как «заголовки», поэтому права проверяются здесь точечно — только для пунктов с
     * маршрутом. Пункт-действие показывается всем, кому доступна сама админка; ограничить его —
     * ответственность модуля, который его объявил.
     *
     * @param string $location Локация меню (по умолчанию модуль использует `header-quick-links`).
     * @return array<int, array<string, mixed>>
     */
    public function actions(string $location): array
    {
        return $this->filterActions($this->provider->forLocation($location, $this->contributions()));
    }

    /**
     * Индекс нескольких локаций в виде массива — тело JSON-ответа для палитры.
     *
     * @param string[] $locations
     * @return array{sections: array<int, array<string, mixed>>}
     */
    public function toArray(array $locations): array
    {
        return [
            'sections' => array_map(
                static fn(MenuSection $section): array => $section->toArray(),
                $this->sectionsFor($locations),
            ),
        ];
    }

    /**
     * Сборка разделов одной локации из скомпилированного дерева меню.
     *
     * @return MenuSection[]
     */
    private function build(string $location): array
    {
        $tree = MenuAccessFilter::filter($this->provider->forLocation($location, $this->contributions()));

        $sections = [];
        $ungrouped = [];

        foreach ($tree as $item) {
            // Строки-разделители и заголовки, которые модули кладут в меню как готовый HTML.
            if (!is_array($item)) {
                continue;
            }

            if (isset($item['items']) && is_array($item['items'])) {
                $links = $this->toLinks($item['items']);
                if ($links !== []) {
                    $sections[] = new MenuSection(
                        (string)($item['label'] ?? ''),
                        (string)($item['iconClass'] ?? 'bi bi-folder'),
                        $location,
                        $links,
                    );
                }
                continue;
            }

            $link = $this->toLink($item);
            if ($link !== null) {
                $ungrouped[] = $link;
            }
        }

        // Пункты вне групп — последним разделом: они реже всего нужны и не должны разрывать группы.
        if ($ungrouped !== []) {
            $sections[] = new MenuSection(null, 'bi bi-dot', $location, $ungrouped);
        }

        return $sections;
    }

    /**
     * Плоский список пунктов-кнопок с точечной проверкой прав (см. {@see actions()}). Группы, если
     * модуль их всё-таки задал, разворачиваются: в шапке иерархия не нужна.
     *
     * @param array<int, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    private function filterActions(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($item['items']) && is_array($item['items'])) {
                $result = array_merge($result, $this->filterActions($item['items']));
                continue;
            }
            if (!is_array($item['url'] ?? null) || MenuAccessFilter::filter([$item]) !== []) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $items
     * @return MenuLink[]
     */
    private function toLinks(array $items): array
    {
        $links = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            // Вложенность глубже двух уровней в админ-меню не используется, но если модуль её задал —
            // разворачиваем в плоский список, чтобы пункты не потерялись в индексе.
            if (isset($item['items']) && is_array($item['items'])) {
                $links = array_merge($links, $this->toLinks($item['items']));
                continue;
            }
            $link = $this->toLink($item);
            if ($link !== null) {
                $links[] = $link;
            }
        }

        return $links;
    }

    /**
     * Пункт меню → ссылка индекса. Пункты без адреса (заголовки, `#`) отбрасываются: перейти по ним
     * нельзя, а в палитре они были бы шумом.
     *
     * @param array<string, mixed> $item
     */
    private function toLink(array $item): ?MenuLink
    {
        $label = trim((string)($item['label'] ?? ''));
        $url = $item['url'] ?? null;

        if ($label === '' || $url === null || $url === '#' || $url === '') {
            return null;
        }

        $route = is_array($url) ? ltrim((string)($url[0] ?? ''), '/') : null;

        return new MenuLink(
            label: $label,
            url: Url::to($url),
            icon: trim((string)($item['iconClass'] ?? '')),
            route: $route !== '' ? $route : null,
        );
    }

    /**
     * @return array<int, array>
     */
    private function contributions(): array
    {
        return $this->contributions ??= ($this->contributionsHook)();
    }
}
