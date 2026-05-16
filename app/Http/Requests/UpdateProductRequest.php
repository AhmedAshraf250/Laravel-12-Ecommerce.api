<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product')->id;

        return [
            'name'          => 'sometimes|required|string|max:255',
            'description'   => 'sometimes|nullable|string',
            'price'         => 'sometimes|required|numeric|min:0',
            'stock'         => 'sometimes|integer|min:0',
            'is_active'     => 'sometimes|boolean',
            'categories'    => 'sometimes|array',
            'categories.*'  => 'exists:categories,id',
            'image'         => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:2048',
            'sku'           => 'sometimes|required|string|unique:products,sku,' . $productId,
            'gallery'       => 'sometimes|array',
            'gallery.*'     => 'image|mimes:jpeg,png,jpg|max:2048',
        ];
    }
}
