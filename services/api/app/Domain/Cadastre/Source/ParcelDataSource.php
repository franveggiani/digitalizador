<?php

namespace App\Domain\Cadastre\Source;

use App\Domain\Cadastre\Geometry\BoundingBox;

interface ParcelDataSource
{
    public function find(string $externalId): ?ParcelFeature;

    /** @return list<ParcelFeature> */
    public function context(BoundingBox $bbox, int $limit = 2000): array;
}
