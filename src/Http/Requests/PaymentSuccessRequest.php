<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentSuccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // 允许未认证用户访问，因为支付回调时可能未登录
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'session_id' => 'required|string|max:500',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // 清理输入，防止XSS
        if ($this->has('session_id')) {
            $this->merge([
                'session_id' => strip_tags($this->input('session_id')),
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'session_id.required' => 'Payment session ID is required.',
            'session_id.string' => 'Payment session ID must be a valid string.',
            'session_id.max' => 'Payment session ID is too long.',
        ];
    }
}
