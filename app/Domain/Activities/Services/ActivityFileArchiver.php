<?php

namespace App\Domain\Activities\Services;

use App\Domain\Activities\Contracts\ActivityFileProvider;
use App\Models\ActivitySource;
use App\Models\ProviderConnection;
use Illuminate\Support\Facades\Storage;

final class ActivityFileArchiver
{
    public function archive(
        ActivityFileProvider $provider,
        ProviderConnection $connection,
        ActivitySource $source,
    ): void {
        $contents = $provider->downloadActivityFile($connection, $source->external_id);

        if ($contents === '') {
            throw new \RuntimeException('The provider returned an empty activity source file.');
        }

        $filename = hash('sha256', $source->external_id).'.'.$provider->activityFileExtension();
        $path = "{$connection->user_id}/{$connection->provider->value}/{$filename}";
        $disk = config('activity-providers.sync.source_file_disk');

        Storage::disk($disk)->put($path, $contents);

        $source->forceFill([
            'source_file_path' => $path,
            'source_file_checksum' => hash('sha256', $contents),
        ])->save();
    }
}
