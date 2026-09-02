<?php

namespace App\Domain\Cadastre\Geometry;

use App\Models\Dataset;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use JsonException;

final class GeometryValidator
{
    /** @param array<string, mixed> $geometry */
    public function validate(Dataset $dataset, int $srid, array $geometry): ValidationResult
    {
        if ($srid !== $dataset->srid) {
            return ValidationResult::rejected($this->issue(
                'SRID_MISMATCH',
                'Geometry SRID must match the dataset SRID.',
                details: ['expected_srid' => $dataset->srid, 'actual_srid' => $srid],
            ));
        }

        try {
            $geometryJson = json_encode($geometry, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ValidationResult::rejected($this->issue(
                'INVALID_GEOMETRY_JSON',
                'Geometry cannot be encoded as valid GeoJSON.',
            ));
        }

        try {
            $inspection = $this->inspect($geometryJson, $srid);
        } catch (QueryException) {
            return ValidationResult::rejected($this->issue(
                'INVALID_GEOMETRY',
                'PostGIS could not parse the supplied geometry.',
            ));
        }

        if ((bool) $inspection->is_empty) {
            return ValidationResult::rejected($this->issue(
                'EMPTY_GEOMETRY',
                'Geometry must not be empty.',
            ));
        }

        $geometryType = (string) $inspection->geometry_type;
        if ($geometryType !== 'ST_Polygon' && $geometryType !== 'ST_MultiPolygon') {
            return ValidationResult::rejected($this->issue(
                'UNSUPPORTED_GEOMETRY_TYPE',
                'Only polygonal cadastral geometry is supported.',
                details: ['geometry_type' => $geometryType],
            ));
        }

        if ($geometryType === 'ST_MultiPolygon' && ! $dataset->allow_multipolygon) {
            return ValidationResult::rejected($this->issue(
                'MULTIPART_NOT_ALLOWED',
                'This dataset does not allow MultiPolygon parcels.',
            ));
        }

        if ((int) $inspection->dimensions !== 2) {
            return ValidationResult::rejected($this->issue(
                'UNSUPPORTED_DIMENSION',
                'Cadastral geometry must be two-dimensional.',
                details: ['dimensions' => (int) $inspection->dimensions],
            ));
        }

        if (! (bool) $inspection->is_valid) {
            $reason = (string) ($inspection->validity_reason ?? 'Invalid geometry');
            $code = str_contains(strtolower($reason), 'self-intersection')
                ? 'SELF_INTERSECTION'
                : 'INVALID_GEOMETRY';

            return ValidationResult::rejected($this->issue(
                $code,
                $reason,
                $this->decodeProblemCoordinate($inspection->invalid_location ?? null),
                ['postgis_reason' => $reason],
            ));
        }

        if (! $dataset->allow_holes && (int) $inspection->interior_rings > 0) {
            return ValidationResult::rejected($this->issue(
                'HOLES_NOT_ALLOWED',
                'This dataset does not allow interior rings in parcels.',
                details: ['interior_ring_count' => (int) $inspection->interior_rings],
            ));
        }

        if ($dataset->minimum_area !== null && (float) $inspection->area < (float) $dataset->minimum_area) {
            return ValidationResult::rejected($this->issue(
                'AREA_BELOW_MINIMUM',
                'Parcel area is below the dataset minimum.',
                details: [
                    'area' => (float) $inspection->area,
                    'minimum_area' => (float) $dataset->minimum_area,
                ],
            ));
        }

        try {
            $normalized = $this->normalize($geometryJson, $srid, (float) $dataset->precision_grid);
        } catch (QueryException) {
            return ValidationResult::rejected($this->issue(
                'PRECISION_NORMALIZATION_FAILED',
                'PostGIS could not normalize geometry to the dataset precision grid.',
            ));
        }

        if ((bool) $normalized->is_empty) {
            return ValidationResult::rejected($this->issue(
                'PRECISION_COLLAPSE',
                'Geometry collapses when reduced to the dataset precision grid.',
            ));
        }

        if (! (bool) $normalized->is_valid) {
            return ValidationResult::rejected($this->issue(
                'INVALID_AFTER_PRECISION_REDUCTION',
                (string) $normalized->validity_reason,
            ));
        }

        $normalizedGeometry = $this->decodeJsonObject($normalized->geometry);
        if ($normalizedGeometry === null) {
            return ValidationResult::rejected($this->issue(
                'PRECISION_NORMALIZATION_FAILED',
                'Normalized geometry could not be serialized.',
            ));
        }

        return ValidationResult::accepted($normalizedGeometry);
    }

    private function inspect(string $geometryJson, int $srid): object
    {
        return DB::selectOne(<<<'SQL'
            WITH input AS (
                SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), ?) AS geom
            ), detail AS (
                SELECT geom, ST_IsValidDetail(geom) AS validity
                FROM input
            )
            SELECT ST_IsEmpty(geom) AS is_empty,
                   ST_GeometryType(geom) AS geometry_type,
                   ST_NDims(geom) AS dimensions,
                   (validity).valid AS is_valid,
                   (validity).reason AS validity_reason,
                   CASE
                       WHEN (validity).location IS NULL THEN NULL
                       ELSE ST_AsGeoJSON((validity).location, 9)::jsonb
                   END AS invalid_location,
                   CASE
                       WHEN ST_GeometryType(geom) = 'ST_Polygon' THEN ST_NumInteriorRings(geom)
                       WHEN ST_GeometryType(geom) = 'ST_MultiPolygon' THEN (
                           SELECT COALESCE(SUM(ST_NumInteriorRings(part.geom)), 0)
                           FROM ST_Dump(geom) AS part
                       )
                       ELSE 0
                   END AS interior_rings,
                   ST_Area(geom) AS area
            FROM detail
            SQL, [$geometryJson, $srid]) ?? throw new QueryException('', '', [], new \RuntimeException('Geometry inspection returned no row.'));
    }

    private function normalize(string $geometryJson, int $srid, float $precisionGrid): object
    {
        return DB::selectOne(<<<'SQL'
            WITH input AS (
                SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), ?) AS geom
            ), normalized AS (
                SELECT ST_ReducePrecision(geom, ?) AS geom
                FROM input
            )
            SELECT ST_IsEmpty(geom) AS is_empty,
                   ST_IsValid(geom) AS is_valid,
                   ST_IsValidReason(geom) AS validity_reason,
                   ST_AsGeoJSON(geom, 9)::jsonb AS geometry
            FROM normalized
            SQL, [$geometryJson, $srid, $precisionGrid]) ?? throw new QueryException('', '', [], new \RuntimeException('Geometry normalization returned no row.'));
    }

    /**
     * @param list<float>|null $coordinate
     * @param array<string, mixed> $details
     * @return array{code: string, message: string, coordinate?: list<float>, details: array<string, mixed>}
     */
    private function issue(
        string $code,
        string $message,
        ?array $coordinate = null,
        array $details = [],
    ): array {
        $issue = [
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ];

        if ($coordinate !== null) {
            $issue['coordinate'] = $coordinate;
        }

        return $issue;
    }

    /** @return list<float>|null */
    private function decodeProblemCoordinate(mixed $value): ?array
    {
        $object = $this->decodeJsonObject($value);
        if ($object === null || ($object['type'] ?? null) !== 'Point') {
            return null;
        }

        $coordinates = $object['coordinates'] ?? null;
        if (! is_array($coordinates) || count($coordinates) < 2) {
            return null;
        }

        return [(float) $coordinates[0], (float) $coordinates[1]];
    }

    /** @return array<string, mixed>|null */
    private function decodeJsonObject(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }
}
