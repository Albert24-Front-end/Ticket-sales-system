<?php

namespace App\Services;

use App\Contracts\SignatureContract;

class SignatureService implements SignatureContract
{

    public function sign(array $data, string $key): string
    {
        ksort($data); // сортировка ключей
        $string = implode("|", array_map(fn($key, $val) => "{$key}|{$val}", array_keys($data), array_values($data)));
        return hash_hmac("sha256", $string, $key);
    }

    public function checkSignature(array $data, string $key, string $ctrlSignature): bool
    {
        $recalculatedSignature = $this->sign($data, $key);
        return hash_equals($ctrlSignature, $recalculatedSignature);
    }
}
