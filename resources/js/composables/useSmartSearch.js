/**
 * useSmartSearch.js
 * Composable para búsqueda difusa (fuzzy search), tolerancia a erratas (typos)
 * y detección preventiva de duplicados en tiempo real.
 */

/**
 * Normaliza un texto para comparaciones insensibles a mayúsculas, acentos, 'ñ', espacios y signos.
 * Convierte 'Español' y 'Espanol' a la misma base 'espanol'.
 */
export function normalizeText(str) {
    if (!str && str !== 0) return '';
    return str
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // Quita tildes
        .replace(/ñ/g, 'n')              // Normaliza ñ a n
        .replace(/[^a-z0-9\s]/g, ' ')   // Quita puntuación y caracteres raros
        .replace(/\s+/g, ' ')            // Colapsa espacios múltiples
        .trim();
}

/**
 * Algoritmo Damerau-Levenshtein:
 * Calcula la distancia de edición mínima entre dos cadenas considerando:
 * Inserciones, Eliminaciones, Sustituciones y Transposiciones de caracteres adyacentes.
 */
export function damerauLevenshtein(a, b) {
    const s1 = a || '';
    const s2 = b || '';
    const len1 = s1.length;
    const len2 = s2.length;

    if (len1 === 0) return len2;
    if (len2 === 0) return len1;

    // Matriz de distancias
    const matrix = [];
    for (let i = 0; i <= len1; i++) {
        matrix[i] = [i];
    }
    for (let j = 0; j <= len2; j++) {
        matrix[0][j] = j;
    }

    for (let i = 1; i <= len1; i++) {
        for (let j = 1; j <= len2; j++) {
            const cost = s1[i - 1] === s2[j - 1] ? 0 : 1;
            matrix[i][j] = Math.min(
                matrix[i - 1][j] + 1,       // Eliminación
                matrix[i][j - 1] + 1,       // Inserción
                matrix[i - 1][j - 1] + cost // Sustitución
            );

            // Transposición
            if (i > 1 && j > 1 && s1[i - 1] === s2[j - 2] && s1[i - 2] === s2[j - 1]) {
                matrix[i][j] = Math.min(matrix[i][j], matrix[i - 2][j - 2] + cost);
            }
        }
    }

    return matrix[len1][len2];
}

/**
 * Calcula un porcentaje de similitud (0 a 100) entre dos cadenas.
 */
export function calculateSimilarity(query, target) {
    const normQ = normalizeText(query);
    const normT = normalizeText(target);

    if (!normQ || !normT) return 0;
    if (normQ === normT) return 100;

    const maxLen = Math.max(normQ.length, normT.length);
    const minLen = Math.min(normQ.length, normT.length);

    // 1. Inclusión directa de subcadena completa
    if (normT.includes(normQ) || normQ.includes(normT)) {
        // Puntuación proporcional al tamaño de la coincidencia
        const subRatio = minLen / maxLen;
        const subScore = Math.round(80 + (subRatio * 20));
        return Math.min(100, subScore);
    }

    // 2. Coincidencia por palabras / tokens (ej. "Oda Eiichiro" vs "Eiichiro Oda")
    const wordsQ = normQ.split(' ').filter(Boolean);
    const wordsT = normT.split(' ').filter(Boolean);

    if (wordsQ.length > 0 && wordsT.length > 0) {
        let matchingTokens = 0;
        for (const wQ of wordsQ) {
            if (wordsT.some(wT => wT === wQ || (wT.length > 3 && wQ.length > 3 && damerauLevenshtein(wT, wQ) <= 1))) {
                matchingTokens++;
            }
        }
        if (matchingTokens === wordsQ.length) {
            // Todos los tokens de la búsqueda están presentes
            return Math.min(95, Math.round(85 + (10 * (wordsQ.length / wordsT.length))));
        }
    }

    // 3. Distancia Damerau-Levenshtein global
    const dist = damerauLevenshtein(normQ, normT);
    const levScore = Math.max(0, Math.round((1 - (dist / maxLen)) * 100));

    // Si la distancia es muy pequeña (1 o 2 letras de diferencia) en cadenas medianas,
    // garantizamos un score alto (ej: "espanol" vs "espanol" con errata "espaniel" -> dist 2 -> ~80%)
    if (dist <= 1 && maxLen >= 4) {
        return Math.max(levScore, 90);
    }
    if (dist <= 2 && maxLen >= 6) {
        return Math.max(levScore, 82);
    }

    return levScore;
}

/**
 * Busca y clasifica elementos de una lista según su coincidencia aproximada.
 * Retorna los elementos ordenados por score descendente.
 */
export function findFuzzyMatches(query, items, getLabelFn = (i) => i.nombre, threshold = 40) {
    if (!items || !Array.isArray(items)) return [];
    const q = (query || '').trim();
    if (!q) return items;

    const scored = items.map(item => {
        const label = getLabelFn ? getLabelFn(item) : (item.nombre || '');
        const score = calculateSimilarity(q, label);
        return { item, score, label };
    });

    return scored
        .filter(entry => entry.score >= threshold)
        .sort((a, b) => b.score - a.score)
        .map(entry => entry.item);
}

/**
 * Detecta si un texto que el usuario intenta ingresar coincide o es muy cercano a un registro existente.
 */
export function detectPotentialDuplicate(inputName, items, getLabelFn = (i) => i.nombre, threshold = 70) {
    if (!items || !Array.isArray(items) || !inputName) {
        return { hasDuplicate: false, topMatch: null, score: 0 };
    }

    const trimmedInput = inputName.trim();
    if (trimmedInput.length < 2) {
        return { hasDuplicate: false, topMatch: null, score: 0 };
    }

    let topMatch = null;
    let highestScore = 0;

    for (const item of items) {
        const label = getLabelFn ? getLabelFn(item) : (item.nombre || '');
        const score = calculateSimilarity(trimmedInput, label);

        if (score > highestScore) {
            highestScore = score;
            topMatch = { item, label, score };
        }
    }

    const hasDuplicate = highestScore >= threshold && topMatch !== null;

    return {
        hasDuplicate,
        topMatch: topMatch?.item || null,
        matchedLabel: topMatch?.label || '',
        score: highestScore,
        isExact: highestScore === 100,
        isCritical: highestScore >= 85
    };
}
