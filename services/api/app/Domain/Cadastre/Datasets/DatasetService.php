<?php

namespace App\Domain\Cadastre\Datasets;

use App\Models\Dataset;

final class DatasetService
{
    /** @param array<string, mixed> $policy */
    public function upsert(string $externalKey, array $policy): Dataset
    {
        return Dataset::query()->updateOrCreate(
            ['external_key' => $externalKey],
            $policy,
        );
    }
}
