# besnovatyj/yii2-cms-admin-panel

Модуль самой админ-панели: отвечает не за контент, а за то, **как админка доступна пользователю**.

Когда установленных модулей несколько десятков, навигация перестаёт быть свойством layout'а и
становится предметной областью со своей логикой: собрать пункты всех модулей, отфильтровать правами,
разложить по локациям, дать поиск. Этим и занимается модуль.

## Что даёт

| Возможность | Где | Зачем |
|---|---|---|
| Палитра команд `Ctrl/Cmd + K` | любая страница админки | поиск по всем разделам **и видимая карта админки** — не нужно помнить, где что лежит |
| Кнопки быстрых действий | шапка админки | модули кладут свои кнопки в локацию `header-quick-links` вместо правки layout'а приложения |
| Страница «Настройки» | `/AdminPanel/backend/settings/index` | служебные разделы карточками на полном экране, а не в узком offcanvas |

Всё это — **один источник данных**: вклады `adminMenu.php` установленных модулей, скомпилированные
modman по локациям (`Besnovatyj\Modman\menu\MenuProvider`) и отфильтрованные RBAC
(`Besnovatyj\Kernel\security\MenuAccessFilter`). Модуль ничего не знает о конкретных модулях и не
имеет своей БД.

## Установка

```bash
docker-compose exec php php yii Modman/modules/install AdminPanel
```

Затем — сборка фронтенда палитры (в контейнере node):

```bash
docker compose exec node sh -c 'cd /home/node/app/vendor/besnovatyj/yii2-cms-admin-panel && npm install && npm run build'
```

Пока `dist/admin-palette.js` не собран, палитра просто не подключается (в лог пишется warning),
остальные возможности модуля работают.

## Палитра команд

Открывается по `Ctrl+K` (`⌘K` на macOS) или кликом по любому элементу с атрибутом
`data-command-palette`. Пока запрос пуст, показывается карта разделов целиком плюс блок «Недавние»;
по мере ввода разделы и пункты фильтруются и ранжируются.

Поиск понимает три вида совпадений: подстроку, подпоследовательность (`блпст` → `Блог: посты`) и
транслитерацию (`blog` находит `Блог`, и наоборот) — админка двуязычная по факту, метки модулей
приходят и на русском, и на английском.

Клавиши: `↑`/`↓` — выбор, `Enter` — открыть, `Ctrl/Cmd + Enter` — в новой вкладке, `Esc` — закрыть.

Индекс запрашивается **лениво**, при первом открытии палитры, и кэшируется во вкладке на 10 минут,
поэтому обычная страница админки не платит за палитру ничем, кроме одного `<script type="module">`
и небольшого CSS. Ответ приватный (режется правами пользователя) — HTTP-кэш для него запрещён.

## Кнопки в шапке

В layout приложения виджет ставится один раз:

```php
<?= \Besnovatyj\AdminPanel\widgets\header\HeaderActionsWidget::widget() ?>
```

Дальше кнопки добавляют сами модули — обычным вкладом в своём `src/config/adminMenu.php`:

```php
[
    'label' => 'Файлы',
    'iconClass' => 'bi bi-folder2-open',
    'url' => ['/File/backend/file/index'],
    'linkOptions' => ['class' => 'btn btn-sm btn-warning'],
    '_meta' => ['placements' => [[
        'location' => 'header-quick-links',
        'priority' => 100,
    ]]],
]
```

Пункт с маршрутом (`url` массивом) проходит проверку прав и рендерится ссылкой. Пункт-действие
(`url => '#'` со своими `linkOptions`, например `data-bs-toggle`) рендерится кнопкой; кому он виден,
решает объявивший его модуль.

## Страница «Настройки»

Показывает разделы локаций из `params.settingsPage.locations` (по умолчанию `right-sidebar`)
карточками. Пункт «Настройки» модуль добавляет себе в правый сайдбар сам.

## Настройки модуля

`src/config/config.php`, ключ `params`:

| Параметр | По умолчанию | Смысл |
|---|---|---|
| `palette.enabled` | `true` | полностью отключить палитру |
| `palette.autoInject` | `true` | подключать палитру ко всем страницам админки автоматически; при `false` виджет ставится в layout вручную |
| `palette.locations` | `['left-sidebar', 'right-sidebar']` | какие локации попадают в индекс |
| `palette.hotkey` | `'k'` | клавиша вместе с Ctrl/Cmd |
| `settingsPage.locations` | `['right-sidebar']` | что показывает страница «Настройки» |
| `header.location` | `'header-quick-links'` | откуда берутся кнопки шапки |

## Доступ

Маршрут индекса палитры `AdminPanel/backend/palette/index` модуль добавляет в whitelist ядрового
гейта (`ProvidesAppConfig`) — иначе право на него пришлось бы выдавать каждой роли отдельно. Это
безопасно: состав ответа режется RBAC для каждого пользователя, а гостя отсекает контроллёр.

Страница «Настройки» — обычный маршрут под RBAC: выдайте право `AdminPanel/backend/settings/index`
нужным ролям (модуль `user` → Routes/Permissions).

## Архитектура

```
src/
├── Bootstrap.php                        L2: DI + автоподключение палитры к страницам админки
├── Module.php                           контракты модуля (меню, bootstrap, whitelist маршрута)
├── config/                              common / app-backend / adminMenu / container / config
├── controllers/backend/
│   ├── PaletteController.php            JSON-индекс разделов для палитры
│   └── SettingsController.php           страница «Настройки»
├── services/
│   ├── AdminMenuIndex.php               единый индекс меню: локации → разделы, права, URL
│   ├── MenuSection.php                  раздел (группа пунктов одной локации)
│   └── MenuLink.php                     пункт с уже разрешённым URL
├── views/backend/settings/index.php
└── widgets/
    ├── header/HeaderActionsWidget.php   кнопки локации header-quick-links
    └── palette/CommandPaletteWidget.php инициализация палитры (ESM + AssetBundle)

assets/                                  TypeScript (strict) палитры → dist/ через esbuild
├── index.ts      точка входа: createCommandPalette()
├── palette.ts    UI: оверлей, карта разделов, клавиатура
├── search.ts     подстрока → подпоследовательность → транслитерация
├── recent.ts     недавно открытые разделы (localStorage)
└── palette.css   стили на CSS-переменных Bootstrap (следуют теме админки)
```

Единственный шов с приложением — в `src/config/container.php`: замыкание, которое отдаёт вклады
группы `admin-menu` из движка конфигов приложения. Сервис индекса о нём не знает, поэтому источник
можно подменить, не трогая пакет.
