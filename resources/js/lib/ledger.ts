/** Plain names for the kinds of ledger transaction, as members and admins see them. */
const typeNames: Record<string, string> = {
    'adjustment.credit': 'Added by Wiifarm',
    'adjustment.debit': 'Taken off by Wiifarm',
    'order.payment': 'Order paid',
    'payout.stockist_rebate': 'Stockist rebate',
    'payout.stockist_sponsor': 'Stockist sponsor bonus',
    'payout.unilevel': 'Unilevel bonus',
    'payout.direct': 'Direct referral bonus',
    'payout.indirect': 'Indirect referral bonus',
    'payout.keyin': 'Key-in bonus',
    'payout.upgrade': 'Upgrade bonus',
    'payout.affiliate': 'Affiliate commission',
};

export const transactionName = (type: string) => typeNames[type] ?? type;

export type StatementRow = { id: number; at: string; type: string; memo: string | null; transaction: number; amount: number; balance: number };

/** Wallets every member sees even when empty; bonus wallets show once they hold something. */
export const coreWallets = ['main', 'visa', 'product', 'autoship', 'savings'];
