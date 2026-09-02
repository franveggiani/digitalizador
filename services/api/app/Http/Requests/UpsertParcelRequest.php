<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertParcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'srid' => ['required', 'integer', 'min:1'],
            'revision' => ['required', 'integer', 'min:1'],
            'geometry' => ['required', 'array'],
            'geometry.type' => ['required', 'string', Rule::in(['Polygon'])],
            'geometry.coordinates' => ['required', 'array', 'min:1'],
            'properties' => ['sometimes', 'array'],
        ];
    }
}
