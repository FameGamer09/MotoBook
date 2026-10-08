<?php

namespace App\Http\Requests\Customer;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class SubmitOrderReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        return $order instanceof Order && $this->user()?->id === $order->customer_id;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'store_rating' => ['required', 'integer', 'between:1,5'],
            'store_comment' => ['nullable', 'string', 'max:1000'],
            'rider_rating' => ['nullable', 'integer', 'between:1,5'],
            'rider_comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
