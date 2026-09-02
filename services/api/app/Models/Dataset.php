<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Dataset extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'external_key',
        'name',
        'srid',
        'precision_grid',
        'area_tolerance',
        'minimum_area',
        'allow_holes',
        'allow_multipolygon',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'srid' => 'integer',
            'precision_grid' => 'float',
            'area_tolerance' => 'float',
            'minimum_area' => 'float',
            'allow_holes' => 'boolean',
            'allow_multipolygon' => 'boolean',
        ];
    }
}
