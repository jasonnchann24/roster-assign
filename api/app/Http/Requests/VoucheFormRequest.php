<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\Vouche;
use Illuminate\Validation\Rule;

class VoucheFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vouched_for_id' => [
                'required',
                'integer',
                'exists:suppliers,id',
                'different:vouched_by_id',
                Rule::notIn([Auth::id()]),
                Rule::unique('vouches')
                    ->where('vouched_by_id', Auth::id())
            ],
            'message' => [
                'nullable',
                'string',
                'max:1000',
                'min:10'
            ],
            'vouched_by_id' => [
                'required',
                'integer',
                'exists:suppliers,id',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'vouched_by_id' => Auth::id(),
        ]);
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vouched_for_id.different' => 'You cannot vouch for yourself.',
            'vouched_for_id.not_in' => 'You cannot vouch for yourself.',
            'vouched_for_id.unique' => 'You have already vouched for this supplier.',
        ];
    }

    /**
     * Get custom validation attributes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'vouched_for_id' => 'supplier',
            'message' => 'vouch message',
        ];
    }
}
