/**
 * Money is stored and sent from the server in kobo (whole numbers), never in
 * floating-point naira. These helpers only format it for display.
 */
const naira = new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 2 });

export function formatKobo(kobo: number): string {
    return naira.format(kobo / 100);
}

export function formatPv(pv: number): string {
    return `${new Intl.NumberFormat('en-NG', { maximumFractionDigits: 2 }).format(pv)} PV`;
}
