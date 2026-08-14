<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\AdminPanel\services\MenuSection;
use yii\helpers\Html;

/**
 * Служебные разделы админки карточками.
 *
 * Разметка намеренно статическая (без JS): состав берётся из того же индекса меню, что и палитра,
 * поэтому страница автоматически знает про каждый установленный модуль. Быстрый поиск здесь не нужен —
 * для него есть палитра (Ctrl/Cmd+K), а эта страница даёт обзор целиком.
 *
 * @var yii\web\View $this
 * @var MenuSection[] $sections разделы, уже отфильтрованные правами пользователя
 */

$this->title = 'Настройки';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="admin-panel-settings">
    <p class="text-body-secondary">
        Служебные разделы установленных модулей. Быстрый переход к любому пункту админки —
        <kbd>Ctrl</kbd>&nbsp;+&nbsp;<kbd>K</kbd> (на macOS <kbd>⌘</kbd>&nbsp;+&nbsp;<kbd>K</kbd>).
    </p>

    <?php if ($sections === []): ?>
        <div class="alert alert-info">
            Доступных служебных разделов нет — либо модули не установлены, либо у вашей роли нет прав
            ни на один из них.
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
            <?php foreach ($sections as $section): ?>
                <div class="col">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center gap-2">
                            <i class="<?= Html::encode($section->icon) ?>"></i>
                            <span class="fw-semibold">
                                <?= Html::encode($section->title ?? 'Прочее') ?>
                            </span>
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($section->links as $link): ?>
                                <li class="list-group-item">
                                    <?= Html::a(
                                        ($link->icon !== '' ? '<i class="' . Html::encode($link->icon) . ' me-2"></i>' : '')
                                        . Html::encode($link->label),
                                        $link->url,
                                        ['class' => 'text-decoration-none link-body-emphasis d-block'],
                                    ) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
