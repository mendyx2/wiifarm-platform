<?php

namespace App\Services\Ledger;

/** A member's wallet would go below zero. */
class InsufficientFunds extends LedgerException {}
