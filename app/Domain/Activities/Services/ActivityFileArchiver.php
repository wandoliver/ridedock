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
        $disk = config('activity-providers.sync.source_file_disk');
        if (config("filesystems.disks.$disk.visibility") === 'public' || config("filesystems.disks.$disk.serve")) {
            throw new \RuntimeException('Activity source files require a private non-serving disk.');
        }
        $contents = $provider->downloadActivityFile($connection, $source->external_id);

        $maximumBytes = (int) config('activity-providers.fit.max_file_bytes');
        if ($contents === '' || $maximumBytes < 1 || strlen($contents) > $maximumBytes) {
            throw new \RuntimeException('The activity source file is empty or exceeds the size limit.');
        }

        $filename = hash('sha256', $source->external_id).'.'.$provider->activityFileExtension();
        $path = "{$connection->user_id}/{$connection->provider->value}/{$filename}";
        Storage::disk($disk)->put($path, $contents);

        $source->forceFill([
            'source_file_path' => $path,
            'source_file_checksum' => hash('sha256', $contents),
        ])->save();
    }
}
