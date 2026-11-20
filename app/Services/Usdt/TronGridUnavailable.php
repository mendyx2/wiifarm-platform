<?php

namespace App\Services\Usdt;

use RuntimeException;

/** TronGrid could not be asked, or answered nonsense: nothing is decided. */
class TronGridUnavailable extends RuntimeException {}
