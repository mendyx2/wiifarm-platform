/** Plain names for the kinds of ledger transaction, as members and admins see them. */
const typeNames: Record<string, string> = {
    'adjustment.credit': 'Added by Wiifarm',
    'adjustment.debit': 'Taken off by Wiifarm',
};

export const transactionName = (type: string) => typeNames[type] ?? type;

export type StatementRow = { id: number; at: string; type: string; memo: string | null; transaction: number; amount: number; balance: number };

/** Wallets every member sees even when empty; bonus wallets show once they hold something. */
export const coreWallets = ['main', 'visa', 'product', 'autoship', 'savings'];
