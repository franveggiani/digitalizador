<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cadastre\Geometry\BoundingBox;
use App\Domain\Cadastre\Parcels\ParcelWorkspaceService;
use App\Models\Dataset;
use App\Support\ApiProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ParcelContextController
{
    public function __invoke(
        Request $request,
        string $externalKey,
        ParcelWorkspaceService $parcels,
    ): JsonResponse {
        $dataset = Dataset::query()->where('external_key', $externalKey)->first();

        if ($dataset === null) {
            throw new ApiProblemException(404, 'DATASET_NOT_FOUND', 'Dataset was not found.');
        }

        $bbox = BoundingBox::fromQuery($request->query('bbox'));

        return response()->json([
            'data' => $parcels->context($dataset, $bbox),
        ]);
    }
}
