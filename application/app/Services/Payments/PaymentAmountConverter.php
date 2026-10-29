<?php

namespace App\Services\Payments;

use InvalidArgumentException;
class PaymentAmountConverter
{
    private const TIYIN_PER_UZS = 100;
    public function toTiyin(int $amountUZS): int
    {
        if ($amountUZS < 0) {
            throw new InvalidArgumentException("Amount must be positive");
        }

        return $amountUZS * self::TIYIN_PER_UZS;
    }

    public function toUzs(int $amountTiyin): int
    {
        if ($amountTiyin < 0) {
            throw new InvalidArgumentException("Amount must be positive");
        }

        if ($amountTiyin % self::TIYIN_PER_UZS !== 0) {
            throw new InvalidArgumentException(
                "Amount in tiyin cannot be represented as whole UZS."
            );
        }

        return intdiv($amountTiyin, self::TIYIN_PER_UZS);
    }
}
