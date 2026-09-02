<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cadastre\Datasets\DatasetService;
use App\Http\Requests\UpsertDatasetRequest;
use App\Models\Dataset;
use Illuminate\Http\JsonResponse;

final class DatasetController
{
    public function upsert(
        UpsertDatasetRequest $request,
        string $externalKey,
        DatasetService $datasets,
    ): JsonResponse {
        $dataset = $datasets->upsert($externalKey, $request->validated());
        $status = $dataset->wasRecentlyCreated ? 201 : 200;

        return response()->json([
            'data' => $this->serialize($dataset),
        ], $status);
    }

    /** @return array<string, mixed> */
    private function serialize(Dataset $dataset): array
    {
        return [
            'id' => $dataset->getKey(),
            'external_key' => $dataset->external_key,
            'name' => $dataset->name,
            'srid' => $dataset->srid,
            'precision_grid' => $dataset->precision_grid,
            'area_tolerance' => $dataset->area_tolerance,
            'minimum_area' => $dataset->minimum_area,
            'allow_holes' => $dataset->allow_holes,
            'allow_multipolygon' => $dataset->allow_multipolygon,
        ];
    }
}
