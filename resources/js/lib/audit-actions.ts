/** Plain-English names for the actions in the audit log. */
export const auditActionNames: Record<string, string> = {
    'admin.signed_in': 'Admin signed in',
    'admins.create': 'Admin added',
    'admins.edit': 'Admin changed',
    'admins.deactivate': 'Admin deactivated',
    'admins.activate': 'Admin reactivated',
    'roles.create': 'Role created',
    'roles.edit': 'Role changed',
    'roles.delete': 'Role deleted',
    'members.edit': 'Member details edited',
    'members.ban': 'Member banned',
    'members.unban': 'Member ban lifted',
    'members.block_withdrawals': 'Withdrawals blocked',
    'members.unblock_withdrawals': 'Withdrawals opened',
    'members.change_sponsor': 'Sponsor changed',
    'members.wallet_add': 'Added to a wallet',
    'members.wallet_subtract': 'Subtracted from a wallet',
    'kyc.approve': 'KYC approved',
    'kyc.reject': 'KYC rejected',
    'settings.edit': 'Setting changed',
};

export const auditActionName = (action: string) => auditActionNames[action] ?? action;
