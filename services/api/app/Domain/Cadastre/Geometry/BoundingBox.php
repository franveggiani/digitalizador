<?php

namespace App\Domain\Cadastre\Geometry;

use App\Support\ApiProblemException;

final readonly class BoundingBox
{
    public function __construct(
        public float $minX,
        public float $minY,
        public float $maxX,
        public float $maxY,
    ) {
        if (! is_finite($minX) || ! is_finite($minY) || ! is_finite($maxX) || ! is_finite($maxY)) {
            throw self::invalid();
        }

        if ($minX >= $maxX || $minY >= $maxY) {
            throw self::invalid('Bounding box minimum coordinates must be lower than maximum coordinates.');
        }
    }

    public static function fromQuery(?string $value): self
    {
        if ($value === null || trim($value) === '') {
            throw self::invalid();
        }

        $parts = array_map('trim', explode(',', $value));

        if (count($parts) !== 4) {
            throw self::invalid();
        }

        foreach ($parts as $part) {
            if ($part === '' || ! is_numeric($part)) {
                throw self::invalid();
            }
        }

        return new self(
            (float) $parts[0],
            (float) $parts[1],
            (float) $parts[2],
            (float) $parts[3],
        );
    }

    private static function invalid(string $message = 'Bounding box must contain four finite coordinates: minX,minY,maxX,maxY.'): ApiProblemException
    {
        return new ApiProblemException(422, 'INVALID_BBOX', $message);
    }
}
