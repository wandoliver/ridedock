<?php

namespace App\Domain\Privacy\Services;

use App\Models\ActivitySource;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class PrivateSourceFiles
{
    public function disk(): FilesystemAdapter
    {
        $name = config('activity-providers.sync.source_file_disk');
        if (config("filesystems.disks.$name.visibility") === 'public' || config("filesystems.disks.$name.serve")) {
            throw new RuntimeException('A private non-serving source disk is required.');
        }
        return Storage::disk($name);
    }

    public function assertOwnedPath(User $user, string $path): void
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\')
            || in_array('..', explode('/', $path), true)
            || ActivitySource::query()->where('source_file_path', $path)
                ->whereHas('activity', fn ($query) => $query->where('user_id', '!=', $user->id))->exists()) {
            throw new RuntimeException('Unsafe or shared private file reference.');
        }
    }

    public function remove(User $user): void
    {
        $disk = $this->disk();
        $paths = ActivitySource::query()->whereHas('activity', fn ($query) => $query->where('user_id', $user->id))
            ->whereNotNull('source_file_path')->pluck('source_file_path')->all();
        // Include orphan files left by interrupted imports, confined to this user's directory.
        $paths = array_unique(array_merge($paths, $disk->allFiles((string) $user->id)));
        foreach ($paths as $path) {
            $this->assertOwnedPath($user, $path);
        }
        foreach ($paths as $path) {
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new RuntimeException('Unable to delete a private file. Retry account deletion.');
            }
        }
    }
}
