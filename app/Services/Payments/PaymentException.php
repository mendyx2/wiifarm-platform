<?php

namespace App\Services\Payments;

use RuntimeException;

/** A payment that cannot go ahead, with a message the payer can read. */
class PaymentException extends RuntimeException {}
