<?php

/*
 * The messages members receive (specification section 12). Each event has a
 * default subject and text; an admin can change them and switch each one
 * off on the Messages page (the changes are kept in message_templates).
 *
 * Placeholders such as {{amount}} are filled in when the message is sent.
 * {{name}} (the person's first name) is always available. Paragraphs are
 * separated by a blank line.
 */
return [
    'events' => [
        'welcome' => [
            'name' => 'Welcome, package paid',
            'who' => 'The new member',
            'placeholders' => ['name', 'username', 'package'],
            'subject' => 'Welcome to Wiifarm Cooperative',
            'body' => "Your {{package}} package is paid and you are now a member, with the username {{username}}.\n\nYour packs are waiting for you: take the pickup code on your Orders page to any stockist.",
            'action' => ['label' => 'Go to my dashboard', 'route' => 'dashboard'],
        ],
        'team.new_member' => [
            'name' => 'A new member in your team',
            'who' => 'The sponsor',
            'placeholders' => ['name', 'member', 'package'],
            'subject' => 'A new member joined your team',
            'body' => '{{member}} has joined your team with the {{package}} package. Your referral bonus is in your wallet.',
            'action' => ['label' => 'See my team', 'route' => 'team'],
        ],
        'deposit.received' => [
            'name' => 'Deposit received',
            'who' => 'The member',
            'placeholders' => ['name', 'amount'],
            'subject' => 'Your deposit of {{amount}} is in',
            'body' => 'We received your deposit of {{amount}}. It is in your main balance.',
            'action' => ['label' => 'See my wallet', 'route' => 'wallet'],
        ],
        'withdrawal.paid' => [
            'name' => 'Withdrawal paid',
            'who' => 'Whoever asked: the member, the store owner (stockist) or the affiliate',
            'placeholders' => ['name', 'amount', 'payable', 'reference'],
            'subject' => 'Your withdrawal of {{amount}} has been paid',
            'body' => 'Your withdrawal of {{amount}} has been paid. {{payable}} was sent to you, reference {{reference}}.',
        ],
        'withdrawal.rejected' => [
            'name' => 'Withdrawal refused',
            'who' => 'Whoever asked: the member, the store owner (stockist) or the affiliate',
            'placeholders' => ['name', 'amount', 'reason'],
            'subject' => 'Your withdrawal of {{amount}} was not paid',
            'body' => "Your withdrawal of {{amount}} was refused, and the full amount is back in your wallet.\n\nThe reason: {{reason}}",
        ],
        'kyc.approved' => [
            'name' => 'Identity check approved',
            'who' => 'The member',
            'placeholders' => ['name'],
            'subject' => 'Your identity check is approved',
            'body' => 'Your identity check (KYC) is approved. You can now withdraw.',
            'action' => ['label' => 'Go to my dashboard', 'route' => 'dashboard'],
        ],
        'kyc.rejected' => [
            'name' => 'Identity check not approved',
            'who' => 'The member',
            'placeholders' => ['name', 'reason'],
            'subject' => 'Your identity check needs another look',
            'body' => "Your identity check (KYC) was not approved.\n\nThe reason: {{reason}}\n\nPlease send it again with this fixed.",
            'action' => ['label' => 'Send it again', 'route' => 'kyc.edit'],
        ],
        'award.approved' => [
            'name' => 'Award approved',
            'who' => 'The member',
            'placeholders' => ['name', 'award', 'amount'],
            'subject' => 'Congratulations on your {{award}} award',
            'body' => 'Your {{award}} award of {{amount}} is approved. Congratulations!',
            'action' => ['label' => 'See my awards', 'route' => 'awards'],
        ],
        'order.paid' => [
            'name' => 'Order paid, pickup code',
            'who' => 'The buyer (a member, or a buyer in the public shop)',
            'placeholders' => ['name', 'order', 'code'],
            'subject' => 'Your order {{order}} is paid',
            'body' => "Your order {{order}} is paid. Your pickup code is {{code}}.\n\nShow this code at any stockist to collect your products. Keep it private: whoever has it can collect the order.",
            'action' => ['label' => 'See my orders', 'route' => 'orders.index'],
        ],
        'autoship.reminder' => [
            'name' => 'Autoship reminder',
            'who' => 'Members who have not made the month\'s autoship (the 22nd, the 1st and the 7th)',
            'placeholders' => ['name', 'month', 'deadline', 'bought', 'needed'],
            'subject' => 'Your autoship for {{month}}',
            'body' => "Please make your autoship for {{month}}: {{needed}} PV of products. So far you have bought {{bought}} PV for that month.\n\nYou have until {{deadline}}. Until you make it, 20% of your matching bonus for the month stays held. If autoship is missed 3 months in a row, the matching held for those months goes to the cooperative's reserve.",
            'action' => ['label' => 'Go to the shop', 'route' => 'shop'],
        ],
    ],
];
