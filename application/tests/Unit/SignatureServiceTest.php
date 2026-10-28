<?php


use App\Services\SignatureService;
use PHPUnit\Framework\TestCase;

class SignatureServiceTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    public function test_example(): void
    {
        $data = [
            "b" => "c",
            "a" => "d",
            "e" => "f"
        ]; // произвольные неотсортированные данные

        $key = "constant-key";
        // имитируем подпись от сервиса
        $ctrlStrToSign = "a|d|b|c|e|f";
        $ctrlSignature = hash_hmac("sha256", $ctrlStrToSign, $key);

        $signatureService = new SignatureService();
        $signature = $signatureService->sign($data, $key);

        $this->assertTrue(hash_equals($ctrlSignature, $signature));
    }

    public function testCheckSignature() : void
    {
        $data = [
            "b" => "c",
            "a" => "d",
            "e" => "f"
        ];

        $key = "constant-key";
        $ctrlStrToSign = "a|d|b|c|e|f";
        $ctrlSignature = hash_hmac("sha256", $ctrlStrToSign, $key);

        $signatureService = new SignatureService();
        $res = $signatureService->checkSignature($data, $key, $ctrlSignature);
        $this->assertTrue($res);
    }
}
