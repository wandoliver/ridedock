<?php

namespace Tests\Unit;

use App\Infrastructure\Fit\NodeFitFileParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class NodeFitFileParserTest extends TestCase
{
    public function test_it_decodes_a_valid_parser_result(): void
    {
        $source = $this->temporaryFile('fit contents');
        $script = $this->temporaryFile('<?php echo json_encode(['
            .'"parser" => [], "summary" => [], "streams" => [], "events" => []]);');

        try {
            $result = (new NodeFitFileParser(PHP_BINARY, $script, 1024, 5))->parse($source);

            $this->assertSame([], $result['streams']);
            $this->assertSame([], $result['events']);
        } finally {
            unlink($source);
            unlink($script);
        }
    }

    public function test_it_rejects_an_invalid_parser_result(): void
    {
        $source = $this->temporaryFile('fit contents');
        $script = $this->temporaryFile('<?php echo "{}";');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('invalid result');

            (new NodeFitFileParser(PHP_BINARY, $script, 1024, 5))->parse($source);
        } finally {
            unlink($source);
            unlink($script);
        }
    }

    private function temporaryFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ridedock-test-');

        if ($path === false) {
            self::fail('Could not create a temporary test file.');
        }

        file_put_contents($path, $contents);

        return $path;
    }
}
