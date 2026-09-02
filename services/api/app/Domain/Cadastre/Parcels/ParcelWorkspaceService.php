<?php

namespace App\Domain\Cadastre\Parcels;

use App\Models\Dataset;
use App\Support\ApiProblemException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final class ParcelWorkspaceService
{
    /**
     * @param array<string, mixed> $geometry
     * @param array<string, mixed> $properties
     * @return array{created: bool, parcel: array<string, mixed>}
     */
    public function upsert(
        Dataset $dataset,
        string $externalId,
        int $srid,
        int $revision,
        array $geometry,
        array $properties,
    ): array {
        if ($srid !== $dataset->srid) {
            throw new ApiProblemException(
                422,
                'SRID_MISMATCH',
                'Parcel SRID must match the dataset SRID.',
                ['expected_srid' => $dataset->srid, 'actual_srid' => $srid],
            );
        }

        try {
            $geometryJson = json_encode($geometry, JSON_THROW_ON_ERROR);
            $propertiesJson = json_encode($properties, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ApiProblemException(422, 'INVALID_JSON', 'Geometry or properties cannot be encoded.', [], $exception);
        }

        try {
            $created = DB::transaction(function () use (
                $dataset,
                $externalId,
                $srid,
                $revision,
                $geometryJson,
                $propertiesJson,
            ): bool {
                $existingId = DB::table('parcels')
                    ->where('dataset_id', $dataset->getKey())
                    ->where('external_id', $externalId)
                    ->value('id');

                $now = now();

                if ($existingId === null) {
                    DB::statement(<<<'SQL'
                        INSERT INTO parcels (
                            id, dataset_id, external_id, revision, properties, active, created_at, updated_at, geom
                        ) VALUES (
                            ?, ?, ?, ?, ?::jsonb, true, ?, ?, ST_SetSRID(ST_GeomFromGeoJSON(?), ?)
                        )
                        SQL, [
                        (string) Str::uuid(),
                        $dataset->getKey(),
                        $externalId,
                        $revision,
                        $propertiesJson,
                        $now,
                        $now,
                        $geometryJson,
                        $srid,
                    ]);

                    return true;
                }

                DB::statement(<<<'SQL'
                    UPDATE parcels
                    SET revision = ?,
                        properties = ?::jsonb,
                        active = true,
                        updated_at = ?,
                        geom = ST_SetSRID(ST_GeomFromGeoJSON(?), ?)
                    WHERE id = ?
                    SQL, [
                    $revision,
                    $propertiesJson,
                    $now,
                    $geometryJson,
                    $srid,
                    $existingId,
                ]);

                return false;
            });
        } catch (QueryException $exception) {
            throw new ApiProblemException(
                422,
                'INVALID_GEOMETRY',
                'PostGIS rejected the supplied parcel geometry.',
                [],
                $exception,
            );
        }

        return [
            'created' => $created,
            'parcel' => $this->get($dataset, $externalId),
        ];
    }

    /** @return array<string, mixed> */
    public function get(Dataset $dataset, string $externalId): array
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT p.id,
                   p.external_id,
                   p.revision,
                   p.properties,
                   p.active,
                   ST_SRID(p.geom) AS srid,
                   ST_AsGeoJSON(p.geom, 9)::jsonb AS geometry
            FROM parcels p
            WHERE p.dataset_id = ? AND p.external_id = ?
            SQL, [$dataset->getKey(), $externalId]);

        if ($row === null) {
            throw new ApiProblemException(404, 'PARCEL_NOT_FOUND', 'Parcel was not found.');
        }

        return [
            'id' => $row->id,
            'external_id' => $row->external_id,
            'revision' => (int) $row->revision,
            'srid' => (int) $row->srid,
            'geometry' => $this->decodeObject($row->geometry),
            'properties' => $this->decodeObject($row->properties),
            'active' => (bool) $row->active,
        ];
    }

    /** @return array<string, mixed> */
    private function decodeObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }
}
