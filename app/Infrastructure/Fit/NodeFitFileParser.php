<?php

namespace App\Infrastructure\Fit;

use App\Domain\Activities\Contracts\ActivitySourceFileParser;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Process;

final class NodeFitFileParser implements ActivitySourceFileParser
{
    public function __construct(
        private readonly ?string $nodeBinary = null,
        private readonly ?string $scriptPath = null,
        private readonly ?int $maximumBytes = null,
        private readonly ?int $timeoutSeconds = null,
    ) {}

    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('The archived FIT source file does not exist.');
        }

        $process = new Process([
            $this->nodeBinary ?? (string) config('activity-providers.fit.node_binary'),
            $this->scriptPath ?? (string) config('activity-providers.fit.parser_script'),
            $path,
            (string) ($this->maximumBytes ?? (int) config('activity-providers.fit.max_file_bytes')),
        ]);
        $process->setTimeout($this->timeoutSeconds ?? (int) config('activity-providers.fit.timeout_seconds'));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('The archived FIT source file could not be parsed.');
        }

        try {
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The FIT parser returned invalid JSON.', previous: $exception);
        }

        if (! is_array($result)
            || ! is_array($result['parser'] ?? null)
            || ! is_array($result['summary'] ?? null)
            || ! is_array($result['streams'] ?? null)
            || ! is_array($result['events'] ?? null)) {
            throw new RuntimeException('The FIT parser returned an invalid result.');
        }

        return $result;
    }
}
