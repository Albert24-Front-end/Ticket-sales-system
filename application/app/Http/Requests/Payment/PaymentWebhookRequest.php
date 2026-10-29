<?php

namespace App\Http\Requests\Payment;

use App\Data\Payment\PaymentWebhookData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PaymentWebhookRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            "order_id" => [
                "required",
                "string",
                "regex:/^\d+$/", // только числа в виде строки, без других символов, знаков и пробелов
            ],

            "amount" => [
                "required",
                "integer",
                "min:1",
            ],

            "status" => [
                "required",
                "integer",
                "in:0,1,2",
            ],
        ];
    }
    // доп проверка после Laravel rules - обеспечение строгого соблюдения протокола отправки JSON payload в рамках webhook -
    // ожидаем только заявленные поля, другие неизвестные не пройдут и вызовут validation error
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $allowedFields = [
                    "order_id",
                    "amount",
                    "status",
                ];

                $receivedFields = array_keys($this->all());

                $unexpectedFields = array_diff(
                    $receivedFields,
                    $allowedFields
                );

                if (!empty($unexpectedFields)) {
                    $validator->errors()->add(
                        "payload",
                        "Webhook contains unexpected fields."
                    );
                }
            },
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function toDTO(): PaymentWebhookData
    {
        return new PaymentWebhookData(...$this->validated());
    }
}
