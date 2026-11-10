/** Lifetime and pairing PV per leg, in hundredths of a PV, as the server sends it. */
export type LegPv = { lifetime: number; pairing: number };
export type PvTotals = { L: LegPv; R: LegPv };

export type TreeNode = {
    id: number;
    username: string;
    name: string;
    package: string | null;
    banned: boolean;
    pv: PvTotals;
    more_below: boolean;
    children?: { L: TreeNode | null; R: TreeNode | null };
};

export type PvLogRow = { id: number; at: string; source: string; memo: string | null; leg: 'L' | 'R' | null; lifetime: number; pairing: number };

export const emptyPv: PvTotals = { L: { lifetime: 0, pairing: 0 }, R: { lifetime: 0, pairing: 0 } };

/** "1,234.5" from hundredths of a PV. */
export const pv = (hundredths: number) => new Intl.NumberFormat('en-NG', { maximumFractionDigits: 2 }).format(hundredths / 100);

const sources: Record<string, string> = {
    'pv.registration': 'Joined',
    'pv.upgrade': 'Upgraded',
    'pv.purchase': 'Product purchase',
    'pv.store': 'Stockist store (awards only)',
};

export const pvSourceName = (type: string) => sources[type] ?? type;
