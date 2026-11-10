<?php

namespace App\Services\Ledger;

/** A posting the ledger refuses. Nothing is written when this is thrown. */
class LedgerException extends \InvalidArgumentException {}
