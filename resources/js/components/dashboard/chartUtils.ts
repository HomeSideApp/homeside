/**
 * Format a minor-unit amount as a decimal string with two places.
 */
export function formatMinor(minor: number): string {
    return (minor / 100).toFixed(2);
}

/**
 * Format a minor-unit amount using the decimal precision of its account.
 *
 * Accounts may hold currencies with a different number of minor units per unit, so the caller
 * supplies the precision instead of assuming two decimal places.
 */
export function formatAmountWithPrecision(
    minor: number,
    decimalPlaces: number,
): string {
    return (minor / 10 ** decimalPlaces).toFixed(decimalPlaces);
}

/**
 * Format a minor-unit amount as a displayable money string with explicit precision.
 */
export function formatAccountAmount(
    minor: number,
    currency: string,
    decimalPlaces: number,
): string {
    const value = formatAmountWithPrecision(minor, decimalPlaces);

    return currency === 'EUR' ? `${value} €` : `${value} ${currency}`;
}

/**
 * Format a minor-unit amount as a displayable money string for a currency.
 */
export function formatMoney(
    minor: number,
    currency: string | null,
): string {
    const value = formatMinor(minor);

    if (!currency) {
        return value;
    }

    return currency === 'EUR' ? `${value} €` : `${value} ${currency}`;
}

/**
 * Convert a "YYYY-MM" period into a Date at the start of that month.
 */
export function monthToDate(month: string): Date {
    const [year, m] = month.split('-').map(Number);

    return new Date(year, (m || 1) - 1, 1);
}

/**
 * Convert a minor-unit amount into a chart-friendly decimal number.
 */
export function minorToChartValue(minor: number): number {
    return Number((minor / 100).toFixed(2));
}

/**
 * Format a "YYYY-MM" period as a localized short month label.
 */
export function monthLabel(
    month: string,
    locale: string,
    withYear = false,
): string {
    const options: Intl.DateTimeFormatOptions = { month: 'short' };

    if (withYear) {
        options.year = '2-digit';
    }

    return new Intl.DateTimeFormat(locale, options).format(
        monthToDate(month),
    );
}
