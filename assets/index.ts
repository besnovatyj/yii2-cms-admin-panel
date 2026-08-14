/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

import './palette.css';
import { CommandPalette } from './palette';
import type { PaletteConfig, PaletteHandle } from './types';

export type { PaletteConfig, PaletteHandle } from './types';

/**
 * Точка входа бандла: создаёт палитру команд и возвращает управление ею.
 *
 * Вызывается инициализирующим модулем PHP-виджета
 * ({@link ../src/widgets/palette/CommandPaletteWidget.php}). Повторный вызов на той же странице
 * возвращает уже созданный экземпляр — палитра глобальна, дублировать её слушатели не нужно.
 */
export function createCommandPalette(config: PaletteConfig): PaletteHandle {
    const existing = window.besAdminPalette;
    if (existing !== undefined) {
        return existing;
    }

    const palette = new CommandPalette(config);
    window.besAdminPalette = palette;

    return palette;
}

declare global {
    interface Window {
        besAdminPalette?: PaletteHandle;
    }
}
