/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

import { loadRecent, pushRecent } from './recent';
import { matchText, normalize, type MatchResult } from './search';
import type { PaletteConfig, PaletteHandle, PaletteIndexResponse, PaletteLinkDto, PaletteSectionDto } from './types';

/**
 * Время жизни кэша индекса: меню меняется только при установке/удалении модулей, поэтому срок большой.
 * Устаревший индекс сбрасывается кнопкой «Обновить» в футере палитры — ждать истечения TTL не нужно.
 */
const CACHE_TTL_MS = 12 * 60 * 60 * 1000;

/** Строка поиска + карта админки: палитра показывает разделы, а не только результаты запроса. */
interface RenderedSection {
    readonly section: PaletteSectionDto;
    readonly links: readonly RenderedLink[];
    readonly score: number;
}

interface RenderedLink {
    readonly link: PaletteLinkDto;
    readonly match: MatchResult;
    readonly score: number;
}

interface CachedIndex {
    readonly ts: number;
    readonly data: PaletteIndexResponse;
}

type Status = 'idle' | 'loading' | 'ready' | 'error';

export class CommandPalette implements PaletteHandle {
    private readonly root: HTMLDivElement;
    private readonly input: HTMLInputElement;
    private readonly body: HTMLDivElement;
    private readonly refreshButton: HTMLButtonElement;

    private sections: readonly PaletteSectionDto[] = [];
    private status: Status = 'idle';
    private errorMessage = '';

    private linkNodes: HTMLAnchorElement[] = [];
    private activeIndex = 0;
    private isOpen = false;
    private lastFocused: Element | null = null;

    constructor(private readonly config: PaletteConfig) {
        this.root = document.createElement('div');
        this.root.className = 'bes-palette';
        this.root.hidden = true;

        const backdrop = document.createElement('div');
        backdrop.className = 'bes-palette__backdrop';
        backdrop.addEventListener('click', () => this.close());

        const dialog = document.createElement('div');
        dialog.className = 'bes-palette__dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-label', 'Поиск по админке');

        const search = document.createElement('div');
        search.className = 'bes-palette__search';

        const icon = document.createElement('i');
        icon.className = 'bi bi-search bes-palette__search-icon';

        this.input = document.createElement('input');
        this.input.type = 'text';
        this.input.className = 'bes-palette__input';
        this.input.placeholder = 'Раздел, модуль, действие…';
        this.input.setAttribute('aria-label', 'Поиск по разделам админки');
        this.input.autocomplete = 'off';
        this.input.spellcheck = false;
        this.input.addEventListener('input', () => this.render());

        // Закрытие палитры. На десктопе элемент выглядит подсказкой «Esc», на узком экране — крестиком:
        // там диалог занимает весь экран, подложки рядом с ним нет и закрыть палитру больше нечем.
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'bes-palette__close';
        close.setAttribute('aria-label', 'Закрыть');
        close.addEventListener('click', () => this.close());

        const escape = document.createElement('kbd');
        escape.className = 'bes-palette__esc';
        escape.textContent = 'Esc';

        const closeIcon = document.createElement('i');
        closeIcon.className = 'bi bi-x-lg bes-palette__close-icon';
        closeIcon.setAttribute('aria-hidden', 'true');

        close.append(escape, closeIcon);

        search.append(icon, this.input, close);

        this.body = document.createElement('div');
        this.body.className = 'bes-palette__body';

        this.refreshButton = document.createElement('button');
        this.refreshButton.type = 'button';
        this.refreshButton.className = 'bes-palette__refresh';
        this.refreshButton.title = 'Перечитать разделы с сервера (кэш живёт долго, меню меняется при установке модулей)';
        const refreshIcon = document.createElement('i');
        refreshIcon.className = 'bi bi-arrow-clockwise';
        this.refreshButton.append(refreshIcon, document.createTextNode(' Обновить'));
        this.refreshButton.addEventListener('click', () => void this.refresh());

        const footer = document.createElement('div');
        footer.className = 'bes-palette__footer';
        footer.append(
            hint('↑ ↓', 'выбор'),
            hint('Enter', 'открыть'),
            hint('Ctrl + Enter', 'в новой вкладке'),
            hint('Esc', 'закрыть'),
            this.refreshButton,
        );

        dialog.append(search, this.body, footer);
        this.root.append(backdrop, dialog);
        document.body.appendChild(this.root);

        document.addEventListener('keydown', this.keydownHandler);
        document.addEventListener('click', this.clickHandler);
    }

    /** Слушатели документа: держим ссылки на привязанные обработчики, чтобы снять их в destroy(). */
    private readonly keydownHandler = (event: KeyboardEvent): void => this.onDocumentKeydown(event);
    private readonly clickHandler = (event: MouseEvent): void => this.onTriggerClick(event);

    open(): void {
        if (this.isOpen) {
            return;
        }
        this.isOpen = true;
        this.lastFocused = document.activeElement;
        this.root.hidden = false;
        document.body.classList.add('bes-palette-open');
        this.input.value = '';
        this.input.focus();
        this.render();
        void this.ensureIndex();
    }

    close(): void {
        if (!this.isOpen) {
            return;
        }
        this.isOpen = false;
        this.root.hidden = true;
        document.body.classList.remove('bes-palette-open');
        if (this.lastFocused instanceof HTMLElement) {
            this.lastFocused.focus();
        }
    }

    destroy(): void {
        document.removeEventListener('keydown', this.keydownHandler);
        document.removeEventListener('click', this.clickHandler);
        this.root.remove();
        document.body.classList.remove('bes-palette-open');
    }

    /** Ctrl/Cmd + hotkey открывает и закрывает палитру; остальные клавиши — только когда она открыта. */
    private onDocumentKeydown(event: KeyboardEvent): void {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === this.config.hotkey.toLowerCase()) {
            event.preventDefault();
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
            return;
        }

        if (!this.isOpen) {
            return;
        }

        switch (event.key) {
            case 'Escape':
                event.preventDefault();
                this.close();
                break;
            case 'ArrowDown':
                event.preventDefault();
                this.setActive(this.activeIndex + 1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.setActive(this.activeIndex - 1);
                break;
            case 'Home':
                event.preventDefault();
                this.setActive(0);
                break;
            case 'End':
                event.preventDefault();
                this.setActive(this.linkNodes.length - 1);
                break;
            case 'Enter': {
                const node = this.linkNodes[this.activeIndex];
                if (node !== undefined) {
                    event.preventDefault();
                    node.dispatchEvent(new MouseEvent('click', {
                        bubbles: true,
                        ctrlKey: event.ctrlKey,
                        metaKey: event.metaKey,
                    }));
                }
                break;
            }
            default:
                // Печать продолжает идти в поле поиска, даже если фокус ушёл на ссылку.
                if (event.key.length === 1 && document.activeElement !== this.input) {
                    this.input.focus();
                }
        }
    }

    /** Любой элемент с атрибутом `data-command-palette` открывает палитру (кнопка в шапке и т.п.). */
    private onTriggerClick(event: MouseEvent): void {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        const trigger = target.closest('[data-command-palette]');
        if (trigger !== null) {
            event.preventDefault();
            this.open();
        }
    }

    /**
     * Принудительно перечитывает индекс: кэш сбрасывается, палитра остаётся открытой.
     *
     * Нужна, потому что кэш переживает перезагрузку страницы и закрытие вкладки: после установки модуля
     * или recompile меню иначе обновилось бы только по истечении {@see CACHE_TTL_MS}. Сброс чисто
     * клиентский — на сервере индекс не кэшируется, он собирается на каждый запрос.
     */
    private async refresh(): Promise<void> {
        this.dropCache();
        this.status = 'idle';
        this.refreshButton.disabled = true;
        try {
            await this.ensureIndex();
        } finally {
            this.refreshButton.disabled = false;
        }
    }

    private async ensureIndex(): Promise<void> {
        if (this.status === 'loading' || this.status === 'ready') {
            return;
        }

        const cached = this.readCache();
        if (cached !== null) {
            this.sections = cached.sections;
            this.status = 'ready';
            this.render();
            return;
        }

        this.status = 'loading';
        this.render();

        try {
            const response = await fetch(this.config.endpoint, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(await readError(response));
            }

            const data = (await response.json()) as PaletteIndexResponse;
            this.sections = Array.isArray(data.sections) ? data.sections : [];
            this.status = 'ready';
            this.writeCache({ sections: this.sections });
        } catch (error) {
            this.status = 'error';
            this.errorMessage = error instanceof Error ? error.message : String(error);
        }

        this.render();
    }

    /** Перерисовывает тело палитры под текущий запрос: карта разделов, отфильтрованная поиском. */
    private render(): void {
        this.body.replaceChildren();
        this.linkNodes = [];

        if (this.status === 'loading') {
            this.body.append(notice('Загружаем разделы админки…'));
            return;
        }
        if (this.status === 'error') {
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'bes-palette__retry';
            retry.textContent = 'Повторить';
            retry.addEventListener('click', () => {
                this.status = 'idle';
                void this.ensureIndex();
            });
            this.body.append(notice(`Не удалось загрузить разделы: ${this.errorMessage}`), retry);
            return;
        }

        const query = normalize(this.input.value.trim());
        const grid = document.createElement('div');
        grid.className = 'bes-palette__grid';

        if (query === '') {
            const recent = loadRecent(this.config.storageKey);
            if (recent.length > 0) {
                grid.append(this.renderSection(
                    { title: 'Недавние', icon: 'bi bi-clock-history', location: 'recent', links: recent },
                    recent.map((link) => ({ link, match: { score: 0, ranges: [] }, score: 0 })),
                    true,
                ));
            }
        }

        for (const rendered of this.filterSections(query)) {
            grid.append(this.renderSection(rendered.section, rendered.links, false));
        }

        if (this.linkNodes.length === 0) {
            this.body.append(notice(
                this.status === 'ready' && this.sections.length === 0
                    ? 'Разделы недоступны — возможно, у вашей роли нет прав ни на один из них.'
                    : 'Ничего не найдено.',
            ));
            return;
        }

        this.body.append(grid);
        this.setActive(0);
    }

    /** Разделы, в которых хоть что-то совпало; и разделы, и пункты внутри ранжируются по совпадению. */
    private filterSections(query: string): RenderedSection[] {
        const result: RenderedSection[] = [];

        for (const section of this.sections) {
            const sectionMatch = section.title !== null ? matchText(query, section.title) : null;
            const links: RenderedLink[] = [];

            for (const link of section.links) {
                const match = matchText(query, link.label)
                    ?? (link.route !== null ? matchText(query, link.route) : null);

                // Пункт остаётся, если совпал сам или совпал заголовок раздела («logs» показывает всю группу).
                const effective = match ?? (sectionMatch !== null ? { score: 0, ranges: [] } : null);
                if (effective !== null) {
                    links.push({ link, match: effective, score: effective.score + (sectionMatch?.score ?? 0) / 4 });
                }
            }

            if (links.length === 0) {
                continue;
            }

            if (query !== '') {
                links.sort((a, b) => b.score - a.score);
            }

            result.push({
                section,
                links,
                score: Math.max(...links.map((item) => item.score), sectionMatch?.score ?? 0),
            });
        }

        return query === '' ? result : result.sort((a, b) => b.score - a.score);
    }

    private renderSection(
        section: PaletteSectionDto,
        links: readonly RenderedLink[],
        highlighted: boolean,
    ): HTMLElement {
        const node = document.createElement('section');
        node.className = highlighted ? 'bes-palette__section bes-palette__section--recent' : 'bes-palette__section';

        const title = document.createElement('h3');
        title.className = 'bes-palette__section-title';
        if (section.icon !== '') {
            const icon = document.createElement('i');
            icon.className = section.icon;
            title.append(icon);
        }
        title.append(document.createTextNode(section.title ?? 'Прочее'));

        const list = document.createElement('ul');
        list.className = 'bes-palette__list';

        for (const item of links) {
            list.append(this.renderLink(item));
        }

        node.append(title, list);

        return node;
    }

    private renderLink(item: RenderedLink): HTMLElement {
        const row = document.createElement('li');

        const anchor = document.createElement('a');
        anchor.className = 'bes-palette__link';
        anchor.href = item.link.url;
        anchor.addEventListener('click', (event: MouseEvent) => {
            pushRecent(this.config.storageKey, item.link);
            if (event.ctrlKey || event.metaKey) {
                event.preventDefault();
                window.open(item.link.url, '_blank', 'noopener');
                return;
            }
            this.close();
        });
        anchor.addEventListener('mousemove', () => this.setActive(this.linkNodes.indexOf(anchor)));

        if (item.link.icon !== '') {
            const icon = document.createElement('i');
            icon.className = item.link.icon;
            anchor.append(icon);
        }

        const label = document.createElement('span');
        label.className = 'bes-palette__label';
        appendHighlighted(label, item.link.label, item.match.ranges);
        anchor.append(label);

        row.append(anchor);
        this.linkNodes.push(anchor);

        return row;
    }

    private setActive(index: number): void {
        if (this.linkNodes.length === 0) {
            return;
        }
        const clamped = Math.min(Math.max(index, 0), this.linkNodes.length - 1);
        this.linkNodes[this.activeIndex]?.classList.remove('is-active');
        this.activeIndex = clamped;

        const node = this.linkNodes[clamped];
        if (node !== undefined) {
            node.classList.add('is-active');
            node.scrollIntoView({ block: 'nearest' });
        }
    }

    /**
     * Ключ кэша индекса. Хранилище — localStorage, как и у списка недавних: индекс приватный, но ключ
     * привязан к пользователю, а кэш, переживающий закрытие вкладки, экономит запрос на каждом новом
     * окне админки (на медленном сервере разработки это секунды ожидания при каждом открытии палитры).
     */
    private cacheKey(): string {
        return `${this.config.storageKey}.index`;
    }

    private readCache(): PaletteIndexResponse | null {
        try {
            const raw = window.localStorage.getItem(this.cacheKey());
            if (raw === null) {
                return null;
            }
            const cached = JSON.parse(raw) as CachedIndex;

            return Date.now() - cached.ts < CACHE_TTL_MS ? cached.data : null;
        } catch {
            return null;
        }
    }

    private writeCache(data: PaletteIndexResponse): void {
        try {
            const payload: CachedIndex = { ts: Date.now(), data };
            window.localStorage.setItem(this.cacheKey(), JSON.stringify(payload));
        } catch {
            // Кэш — оптимизация; без него палитра просто запросит индекс заново.
        }
    }

    private dropCache(): void {
        try {
            window.localStorage.removeItem(this.cacheKey());
        } catch {
            // Хранилище недоступно — значит и кэша нет, сбрасывать нечего.
        }
    }
}

/** Текст с подсвеченными совпадениями; собирается узлами, а не innerHTML. */
function appendHighlighted(target: HTMLElement, text: string, ranges: readonly (readonly [number, number])[]): void {
    if (ranges.length === 0) {
        target.append(document.createTextNode(text));
        return;
    }

    let cursor = 0;
    for (const [from, to] of ranges) {
        if (from > cursor) {
            target.append(document.createTextNode(text.slice(cursor, from)));
        }
        const mark = document.createElement('mark');
        mark.textContent = text.slice(from, to);
        target.append(mark);
        cursor = to;
    }
    if (cursor < text.length) {
        target.append(document.createTextNode(text.slice(cursor)));
    }
}

function notice(text: string): HTMLElement {
    const node = document.createElement('p');
    node.className = 'bes-palette__notice';
    node.textContent = text;

    return node;
}

function hint(keys: string, text: string): HTMLElement {
    const node = document.createElement('span');
    node.className = 'bes-palette__hint';

    const kbd = document.createElement('kbd');
    kbd.textContent = keys;
    node.append(kbd, document.createTextNode(` ${text}`));

    return node;
}

/** Сообщение об ошибке из нативного ответа Yii: JSON `{message}` либо статус. */
async function readError(response: Response): Promise<string> {
    try {
        const text = await response.text();
        const parsed: unknown = JSON.parse(text);
        if (typeof parsed === 'object' && parsed !== null && 'message' in parsed) {
            const message = (parsed as { message: unknown }).message;
            if (typeof message === 'string' && message !== '') {
                return message;
            }
        }
    } catch {
        // Ответ не JSON (страница ошибки Yii) — довольствуемся статусом.
    }

    return `${response.status} ${response.statusText}`.trim();
}
