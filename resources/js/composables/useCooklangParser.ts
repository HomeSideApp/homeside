/**
 * Parser de sintaxis Cooklang para frontend.
 *
 * Port de CooklangParser.php — detecta @ingredient, #cookware, ~timer
 * en texto de pasos de receta.
 */

export interface ParsedIngredient {
    refId: string
    name: string
    quantity: number | null
    unit: string | null
    preparation: string | null
}

export interface ParsedCookware {
    refId: string
    name: string
    quantity: number | null
    unit: string | null
}

export interface ParsedTimer {
    name: string | null
    durationRaw: string
    durationSeconds: number | null
    unit: 'seconds' | 'minutes' | 'hours'
    start: number
    end: number
}

export interface ParsedStep {
    ingredients: ParsedIngredient[]
    cookware: ParsedCookware[]
    timers: ParsedTimer[]
    recipes: ParsedRecipeReference[]
}

export interface ParsedRecipeReference {
    path: string
    quantity: number | null
    unit: string | null
}

export interface CooklangSegment {
    type: 'text' | 'ingredient' | 'cookware' | 'timer' | 'recipe'
    content: string
    start: number
    end: number
    quantity?: number | null
    unit?: string | null
    preparation?: string | null
    durationRaw?: string
    durationSeconds?: number | null
}

const cooklangReferencePattern = /@((?:\.\.?\/)+[^@#~{}\n]+)\{([^}]*)\}|@((?:[^@#~{}\n]|\.?\/)+)\{([^}]*)\}(\([^)]*\))?|#([^@#~{\n]+)\{([^}]*)\}|~([^@#~{\n]*)\{([^}]*)\}|@([\p{L}][\p{L}\p{N}\-]*)|#([\p{L}\p{N}][\p{L}\p{N}\-]*)/gu

/**
 * Detecta si el texto contiene sintaxis Cooklang.
 */
export function hasCooklangSyntax(text: string): boolean {
    return /[@#~]\S/.test(text)
}

/**
 * Extrae todas las referencias de ingredientes del texto.
 */
export function parseIngredients(text: string): ParsedIngredient[] {
    const refs: ParsedIngredient[] = []
    const seen = new Set<string>()

    for (const segment of parseCooklangSegments(text)) {
        if (segment.type !== 'ingredient') {
continue
}

        const refId = md5('ing_' + segment.content)

        if (!seen.has(refId)) {
            seen.add(refId)
            refs.push({
                refId,
                name: segment.content,
                quantity: segment.quantity ?? null,
                unit: segment.unit ?? null,
                preparation: segment.preparation ?? null,
            })
        }
    }

    return refs
}

/**
 * Extrae todas las referencias de utensilios del texto.
 */
export function parseCookware(text: string): ParsedCookware[] {
    const refs: ParsedCookware[] = []
    const seen = new Set<string>()

    for (const segment of parseCooklangSegments(text)) {
        if (segment.type !== 'cookware') {
continue
}

        const refId = md5('cw_' + segment.content)

        if (!seen.has(refId)) {
            seen.add(refId)
            refs.push({
                refId,
                name: segment.content,
                quantity: segment.quantity ?? null,
                unit: segment.unit ?? null,
            })
        }
    }

    return refs
}

/**
 * Extrae todas las referencias de temporizadores del texto.
 */
export function parseTimers(text: string): ParsedTimer[] {
    const refs: ParsedTimer[] = []

    for (const segment of parseCooklangSegments(text)) {
        if (segment.type !== 'timer' || !segment.durationRaw) {
continue
}

        refs.push({
            name: segment.content || null,
            durationRaw: segment.durationRaw,
            durationSeconds: segment.durationSeconds ?? null,
            unit: detectUnitFromRaw(segment.durationRaw),
            start: segment.start,
            end: segment.end,
        })
    }

    return refs
}

export function parseCooklangSegments(text: string): CooklangSegment[] {
    const segments: CooklangSegment[] = []
    let lastIndex = 0

    cooklangReferencePattern.lastIndex = 0
    let match: RegExpExecArray | null

    while ((match = cooklangReferencePattern.exec(text)) !== null) {
        if (match.index > lastIndex) {
            segments.push({ type: 'text', content: text.substring(lastIndex, match.index), start: lastIndex, end: match.index })
        }

        const start = match.index
        const end = match.index + match[0].length

        if (match[1] !== undefined) {
            const { quantity, unit } = parseBracesContent(match[2])
            segments.push({ type: 'recipe', content: match[1].trim(), quantity, unit, start, end })
        } else if (match[3] !== undefined) {
            const { quantity, unit } = parseBracesContent(match[4])
            const preparation = match[5] ? match[5].slice(1, -1).trim() : null
            segments.push({ type: 'ingredient', content: match[3].trim(), quantity, unit, preparation, start, end })
        } else if (match[6] !== undefined) {
            const { quantity, unit } = parseBracesContent(match[7])
            segments.push({ type: 'cookware', content: match[6].trim(), quantity, unit, start, end })
        } else if (match[8] !== undefined) {
            const durationRaw = match[9].trim()

            if (durationRaw) {
                segments.push({
                    type: 'timer',
                    content: match[8].trim(),
                    durationRaw,
                    durationSeconds: parseDurationToSeconds(durationRaw),
                    start,
                    end,
                })
            } else {
                segments.push({ type: 'text', content: match[0], start, end })
            }
        } else if (match[10] !== undefined) {
            segments.push({ type: 'ingredient', content: match[10].trim(), quantity: null, unit: null, preparation: null, start, end })
        } else if (match[11] !== undefined) {
            segments.push({ type: 'cookware', content: match[11].trim(), quantity: null, unit: null, start, end })
        }

        lastIndex = end
    }

    if (lastIndex < text.length) {
        segments.push({ type: 'text', content: text.substring(lastIndex), start: lastIndex, end: text.length })
    }

    return segments.length > 0 ? segments : [{ type: 'text', content: text, start: 0, end: text.length }]
}

/**
 * Extrae todos los constructos Cooklang de un texto.
 */
export function parseAll(text: string): ParsedStep {
    return {
        ingredients: parseIngredients(text),
        cookware: parseCookware(text),
        timers: parseTimers(text),
        recipes: parseCooklangSegments(text)
            .filter((segment) => segment.type === 'recipe')
            .map((segment) => ({ path: segment.content, quantity: segment.quantity ?? null, unit: segment.unit ?? null })),
    }
}

/**
 * Convierte una cadena de duración Cooklang a segundos.
 * "5%minutes" → 300, "1%hour" → 3600
 */
export function parseDurationToSeconds(durationRaw: string): number | null {
    const parts = durationRaw.trim().split(/[%\s]+/, 2)
    const value = parseInt(parts[0]?.trim() || '0', 10)
    const unit = (parts[1] || '').trim().toLowerCase()

    const multipliers: Record<string, number> = {
        s: 1, sec: 1, second: 1, seconds: 1,
        segundo: 1, segundos: 1,
        m: 60, min: 60, minute: 60, minutes: 60,
        minuto: 60, minutos: 60,
        h: 3600, hr: 3600, hour: 3600, hours: 3600,
        hora: 3600, horas: 3600,
    }

    return value * (multipliers[unit] ?? 60)
}

function parseBracesContent(content: string): { quantity: number | null; unit: string | null } {
    if (!content) {
return { quantity: null, unit: null }
}

    const raw = content.startsWith('=') ? content.substring(1) : content

    if (raw.includes('%')) {
        const parts = raw.split('%', 2)

        return {
            quantity: parts[0] ? parseQuantityValue(parts[0]) : null,
            unit: parts[1] || null,
        }
    }

    return /^\d+(?:\.\d+)?$/.test(raw)
        ? { quantity: parseFloat(raw), unit: null }
        : { quantity: null, unit: null }
}

/**
 * Detecta la unidad canónica desde el raw duration.
 * "5%minutes" → 'minutes', "1%hour" → 'hours'
 */
function detectUnitFromRaw(durationRaw: string): 'seconds' | 'minutes' | 'hours' {
    const parts = durationRaw.trim().split(/[%\s]+/, 2)
    const unit = (parts[1] || '').trim().toLowerCase()

    if (['h', 'hr', 'hour', 'hours', 'hora', 'horas'].includes(unit)) {
return 'hours'
}

    if (['m', 'min', 'minute', 'minutes', 'minuto', 'minutos'].includes(unit)) {
return 'minutes'
}

    return 'seconds'
}

/**
 * Parsea un valor de cantidad (número o fracción como "1/2").
 */
function parseQuantityValue(value: string): number | null {
    const trimmed = value.trim()

    if (!trimmed) {
return null
}

    if (trimmed.includes('/')) {
        const parts = trimmed.split('/')

        if (parts.length === 2 && parseFloat(parts[1]) !== 0) {
            return parseFloat(parts[0]) / parseFloat(parts[1])
        }

        return null
    }

    return parseFloat(trimmed) || null
}

/**
 * Simple MD5 hash (solo para generar ref IDs consistentes con el backend).
 * Para uso en el frontend, un hash simple es suficiente.
 */
function md5(str: string): string {
    let hash = 0

    for (let i = 0; i < str.length; i++) {
        const char = str.charCodeAt(i)
        hash = ((hash << 5) - hash) + char
        hash = hash & hash // Convert to 32bit integer
    }

    // Convert to hex and pad to look like md5
    return Math.abs(hash).toString(16).padStart(8, '0')
}
