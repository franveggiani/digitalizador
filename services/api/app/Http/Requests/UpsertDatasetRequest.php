<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpsertDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is decided by the embedding host system, outside this service.
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'srid' => ['required', 'integer', 'min:1'],
            'precision_grid' => ['required', 'numeric', 'gt:0'],
            'area_tolerance' => ['required', 'numeric', 'gte:0'],
            'minimum_area' => ['nullable', 'numeric', 'gte:0'],
            'allow_holes' => ['required', 'boolean'],
            'allow_multipolygon' => ['required', 'boolean'],
        ];
    }
}
