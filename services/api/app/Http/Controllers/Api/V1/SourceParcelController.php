<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Cadastre\Source\ParcelDataSource;
use App\Support\ApiProblemException;
use Illuminate\Http\JsonResponse;

final class SourceParcelController
{
    public function __invoke(string $externalId, ParcelDataSource $source): JsonResponse
    {
        $parcel = $source->find($externalId);

        if ($parcel === null) {
            throw new ApiProblemException(
                404,
                'PARCEL_NOT_FOUND',
                'Parcel was not found in the configured cadastral datasource.',
                ['external_id' => $externalId],
            );
        }

        return response()->json(['data' => $parcel->toArray()]);
    }
}
