<?php

namespace App\Domain\Cadastre\Geometry;

final readonly class ValidationResult
{
    /**
     * @param list<array{code: string, message: string, coordinate?: list<float>, details: array<string, mixed>}> $errors
     * @param list<array{code: string, message: string, coordinate?: list<float>, details: array<string, mixed>}> $warnings
     * @param array<string, mixed>|null $normalizedGeometry
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
        public array $warnings = [],
        public ?array $normalizedGeometry = null,
    ) {
    }

    /** @param array<string, mixed> $normalizedGeometry */
    public static function accepted(array $normalizedGeometry): self
    {
        return new self(true, [], [], $normalizedGeometry);
    }

    /**
     * @param array{code: string, message: string, coordinate?: list<float>, details: array<string, mixed>} $error
     */
    public static function rejected(array $error): self
    {
        return new self(false, [$error], [], null);
    }

    /** @return array{valid: bool, errors: array, warnings: array, normalized_geometry: array<string, mixed>|null} */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'normalized_geometry' => $this->normalizedGeometry,
        ];
    }
}
