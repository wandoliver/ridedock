<?php

namespace App\Domain\Activities\Contracts;

interface ActivitySourceFileParser
{
    /**
     * @return array{
     *     parser: array<string, mixed>,
     *     summary: array<string, int|float|string>,
     *     streams: array<string, array{data: array<int, mixed>, metadata: array<string, mixed>}>,
     *     events: array<int, array{type: string, elapsed_seconds: ?int, recorded_at: ?string, data: array<string, mixed>}>
     * }
     */
    public function parse(string $path): array;
}
