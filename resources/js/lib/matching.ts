/** One member's line in a nightly matching run. PV is in hundredths, money in kobo. */
export type MatchRow = {
    id: number;
    package: string;
    left_pv: number;
    right_pv: number;
    matches: number;
    capped: boolean;
    amount: number;
    autoship: number;
    status: 'paid' | 'held';
};

export type MatchRunRow = {
    id: number;
    day: string;
    status: 'running' | 'done';
    members: number;
    matches: number;
    amount: number;
    held: number;
    finished_at?: string | null;
};
