<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class Parcel extends Model
{
    use HasUuids;

    protected $table = 'parcels';

    /** @var list<string> */
    protected $guarded = ['id', 'geom'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'properties' => 'array',
            'active' => 'boolean',
        ];
    }
}
