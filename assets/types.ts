/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

/** Пункт админ-меню в ответе индекса (см. services\MenuLink::toArray). */
export interface PaletteLinkDto {
    readonly label: string;
    readonly url: string;
    readonly icon: string;
    readonly route: string | null;
}

/** Раздел админ-меню в ответе индекса (см. services\MenuSection::toArray). */
export interface PaletteSectionDto {
    readonly title: string | null;
    readonly icon: string;
    readonly location: string;
    readonly links: readonly PaletteLinkDto[];
}

/** Тело ответа `GET /AdminPanel/backend/palette/index`. */
export interface PaletteIndexResponse {
    readonly sections: readonly PaletteSectionDto[];
}

/** Конфигурация, которую передаёт PHP-виджет при инициализации. */
export interface PaletteConfig {
    /** URL индекса разделов. */
    readonly endpoint: string;
    /** Клавиша, открывающая палитру вместе с Ctrl/Cmd (по умолчанию 'k'). */
    readonly hotkey: string;
    /** Ключ кэша индекса и списка недавних — привязан к пользователю. */
    readonly storageKey: string;
}

/** Публичный интерфейс созданной палитры (на случай программного управления). */
export interface PaletteHandle {
    open(): void;
    close(): void;
    destroy(): void;
}
