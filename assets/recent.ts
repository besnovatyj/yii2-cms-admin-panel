/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

import type { PaletteLinkDto } from './types';

/**
 * Недавно открытые разделы.
 *
 * При 70+ пунктах меню персональные «последние пять» экономят больше времени, чем любая иерархия,
 * поэтому палитра показывает их первым блоком, пока запрос пуст. Хранение — localStorage под ключом,
 * привязанным к пользователю (индекс приватный, чужой список показывать нельзя).
 */

const LIMIT = 6;

/** Ссылки, сохранённые ранее; при недоступном или повреждённом хранилище — пустой список. */
export function loadRecent(storageKey: string): PaletteLinkDto[] {
    try {
        const raw = window.localStorage.getItem(`${storageKey}.recent`);
        if (raw === null) {
            return [];
        }
        const parsed: unknown = JSON.parse(raw);

        return Array.isArray(parsed) ? parsed.filter(isLink).slice(0, LIMIT) : [];
    } catch {
        return [];
    }
}

/** Кладёт ссылку в начало списка, убирая её прежнее вхождение. */
export function pushRecent(storageKey: string, link: PaletteLinkDto): void {
    try {
        const next = [link, ...loadRecent(storageKey).filter((item) => item.url !== link.url)].slice(0, LIMIT);
        window.localStorage.setItem(`${storageKey}.recent`, JSON.stringify(next));
    } catch {
        // Приватный режим/переполненное хранилище: недавние — удобство, а не функциональность.
    }
}

function isLink(value: unknown): value is PaletteLinkDto {
    if (typeof value !== 'object' || value === null) {
        return false;
    }
    const candidate = value as Partial<PaletteLinkDto>;

    return typeof candidate.label === 'string' && typeof candidate.url === 'string';
}
