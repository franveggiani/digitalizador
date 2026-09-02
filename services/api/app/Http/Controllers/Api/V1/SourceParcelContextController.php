<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cadastre\Geometry\BoundingBox;
use App\Domain\Cadastre\Source\ParcelDataSource;
use App\Support\ApiProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SourceParcelContextController
{
    public function __invoke(Request $request, ParcelDataSource $source): JsonResponse
    {
        $bbox = BoundingBox::fromQuery($request->query('bbox'));
        $limitValue = $request->query('limit');

        if ($limitValue === null || $limitValue === '') {
            $limit = min(2000, (int) config('cadastral.parcels.context_limit', 2000));
        } elseif (filter_var($limitValue, FILTER_VALIDATE_INT) === false) {
            throw new ApiProblemException(422, 'INVALID_CONTEXT_LIMIT', 'Context limit must be an integer.');
        } else {
            $limit = (int) $limitValue;
        }

        $features = array_map(
            static fn ($feature): array => $feature->toGeoJsonFeature(),
            $source->context($bbox, $limit),
        );

        return response()->json([
            'data' => [
                'type' => 'FeatureCollection',
                'features' => $features,
            ],
        ]);
    }
}
