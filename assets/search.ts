/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

/**
 * Поиск по пунктам меню: подстрока → подпоследовательность → транслитерация.
 *
 * Админка двуязычная по факту (часть меток модулей на английском, часть на русском), поэтому запрос и
 * цель дополнительно сравниваются в транслитерации: «blog» находит «Блог», «блог» находит «Blog».
 * Транслитерация меняет длину строки, поэтому подсветка для таких совпадений не строится — только ранг.
 */

/** Результат совпадения: чем больше score, тем выше пункт в выдаче. */
export interface MatchResult {
    readonly score: number;
    /** Диапазоны [начало, конец) в ИСХОДНОЙ строке для подсветки; пусто — подсвечивать нечего. */
    readonly ranges: readonly (readonly [number, number])[];
}

const TRANSLIT: Readonly<Record<string, string>> = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ж: 'zh', з: 'z', и: 'i', й: 'i',
    к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u',
    ф: 'f', х: 'h', ц: 'c', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e',
    ю: 'yu', я: 'ya',
};

/** Регистр и «ё» — вне игры; длина строки сохраняется, поэтому индексы подсветки остаются валидными. */
export function normalize(value: string): string {
    return value.toLowerCase().replace(/ё/g, 'е');
}

/** Кириллица → латиница для сравнения запросов и меток на разных языках. */
export function translit(value: string): string {
    let out = '';
    for (const char of value) {
        out += TRANSLIT[char] ?? char;
    }
    return out;
}

/**
 * Совпадение запроса с текстом.
 *
 * @param query  Уже нормализованный запрос (см. {@link normalize}).
 * @param target Исходный текст пункта.
 * @returns null, если совпадения нет.
 */
export function matchText(query: string, target: string): MatchResult | null {
    if (query === '') {
        return { score: 0, ranges: [] };
    }

    const haystack = normalize(target);

    const direct = matchSubstring(query, haystack);
    if (direct !== null) {
        return direct;
    }

    const sequence = matchSubsequence(query, haystack);
    if (sequence !== null) {
        return sequence;
    }

    // Последняя попытка — сравнение в транслитерации (подсветка невозможна: длины разошлись).
    const translitQuery = translit(query);
    const translitTarget = translit(haystack);
    if (translitTarget.includes(translitQuery)) {
        return { score: 40, ranges: [] };
    }

    return null;
}

/**
 * Прямое вхождение подстроки — самый уверенный случай. Совпадение в начале слова ценнее, чем в
 * середине: «post» в «Posts» должно опережать «post» в «Blog post parser».
 */
function matchSubstring(query: string, haystack: string): MatchResult | null {
    const at = haystack.indexOf(query);
    if (at < 0) {
        return null;
    }

    const atStart = at === 0;
    const atWordStart = atStart || /[\s\-–—/.,:(]/.test(haystack.charAt(at - 1));
    const score = 100 + (atStart ? 40 : atWordStart ? 25 : 0) + Math.max(0, 20 - haystack.length / 4);

    return { score, ranges: [[at, at + query.length]] };
}

/**
 * Подпоследовательность («блпст» → «Блог: посты»): символы идут по порядку, но не подряд.
 * Ранг ниже подстроки и тем выше, чем плотнее совпадение.
 */
function matchSubsequence(query: string, haystack: string): MatchResult | null {
    const ranges: [number, number][] = [];
    let cursor = 0;
    let gaps = 0;

    for (const char of query) {
        const at = haystack.indexOf(char, cursor);
        if (at < 0) {
            return null;
        }
        const previous = ranges[ranges.length - 1];
        if (previous !== undefined && previous[1] === at) {
            previous[1] = at + 1; // продолжение непрерывного куска — склеиваем диапазоны
        } else {
            ranges.push([at, at + 1]);
            gaps += 1;
        }
        cursor = at + 1;
    }

    return { score: Math.max(10, 70 - gaps * 5), ranges };
}
