<?php

/*
 * Every admin permission (specification section 11: "Every admin page and
 * every admin action has a named permission"). A role is a set of these.
 * Later phases add their own groups (money, plan, commerce, finance, ...).
 *
 * "super_only" permissions belong to the super-admin role alone and cannot be
 * given to any other role: admins and roles, and system settings.
 */
return [
    'groups' => [
        'Members' => [
            'members.view' => 'See members and their details',
            'members.edit' => 'Edit a member\'s contact details',
            'members.ban' => 'Ban and unban members',
            'members.block_withdrawals' => 'Block and unblock a member\'s withdrawals',
            'members.change_sponsor' => 'Change a member\'s sponsor',
            'members.adjust_wallet' => 'Add to or subtract from a member\'s wallet',
        ],
        'KYC' => [
            'kyc.view' => 'See KYC submissions and documents',
            'kyc.review' => 'Approve or reject KYC submissions',
        ],
        'Settings' => [
            'settings.view' => 'See the settings',
            'settings.edit' => 'Change the settings',
        ],
        'Audit' => [
            'audit.view' => 'See the audit log',
        ],
        'Admins' => [
            'admins.manage' => 'Add, change and deactivate admins',
            'roles.manage' => 'Create and change roles',
        ],
    ],

    'super_only' => ['admins.manage', 'roles.manage', 'settings.edit'],
];
