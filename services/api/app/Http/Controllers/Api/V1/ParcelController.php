<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cadastre\Parcels\ParcelWorkspaceService;
use App\Http\Requests\UpsertParcelRequest;
use App\Models\Dataset;
use App\Support\ApiProblemException;
use Illuminate\Http\JsonResponse;

final class ParcelController
{
    public function upsert(
        UpsertParcelRequest $request,
        string $externalKey,
        string $externalId,
        ParcelWorkspaceService $parcels,
    ): JsonResponse {
        $dataset = $this->dataset($externalKey);
        $validated = $request->validated();

        $result = $parcels->upsert(
            $dataset,
            $externalId,
            (int) $validated['srid'],
            (int) $validated['revision'],
            $validated['geometry'],
            $validated['properties'] ?? [],
        );

        return response()->json(
            ['data' => $result['parcel']],
            $result['created'] ? 201 : 200,
        );
    }

    public function show(
        string $externalKey,
        string $externalId,
        ParcelWorkspaceService $parcels,
    ): JsonResponse {
        return response()->json([
            'data' => $parcels->get($this->dataset($externalKey), $externalId),
        ]);
    }

    private function dataset(string $externalKey): Dataset
    {
        $dataset = Dataset::query()->where('external_key', $externalKey)->first();

        if ($dataset === null) {
            throw new ApiProblemException(404, 'DATASET_NOT_FOUND', 'Dataset was not found.');
        }

        return $dataset;
    }
}
