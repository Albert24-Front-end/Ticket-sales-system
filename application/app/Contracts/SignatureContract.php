<?php

namespace App\Contracts;

interface SignatureContract
{
    public function sign(array $data, string $key): string;

    public function checkSignature(array $data, string $key, string $ctrlSignature): bool;
}
