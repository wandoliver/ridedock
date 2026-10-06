<?php

namespace App\Domain\Privacy\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

final class PersonalDataExport
{
    public function __construct(private readonly PrivateSourceFiles $files) {}

    public function download(User $user): StreamedResponse
    {
        $archive = tmpfile();
        if ($archive === false) {
            throw new RuntimeException('Unable to create private export.');
        }
        $scratch = [];
        try {
            DB::transaction(function () use ($user, $archive, &$scratch): void {
                $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $zip = new ZipArchive();
                if ($zip->open($this->path($archive), ZipArchive::OVERWRITE) !== true) {
                    throw new RuntimeException('Unable to create private export.');
                }
                try {
                    $zip->addFromString('manifest.json', json_encode([
                        'schema_version' => 1, 'exported_at' => now()->toIso8601String(),
                        'format' => 'One JSON object per line in each .ndjson file; originals in files/.',
                        'sensitive_data' => 'Includes unmasked GPS, private zones, notes and original provider data.',
                        'excluded' => ['password hashes', 'session payloads', 'API/OAuth/invitation secrets', 'operator logs and backups'],
                    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                    $activityIds = DB::table('activities')->where('user_id', $user->id)->select('id');
                    $queries = [
                        'account' => DB::table('users')->where('id', $user->id),
                        'athlete_profile' => DB::table('athlete_profiles')->where('user_id', $user->id),
                        'training_zone_profiles' => DB::table('training_zone_profiles')->where('user_id', $user->id),
                        'privacy_zones' => DB::table('privacy_zones')->where('user_id', $user->id),
                        'connections' => DB::table('provider_connections')->where('user_id', $user->id),
                        'activities' => DB::table('activities')->where('user_id', $user->id),
                        'sources' => DB::table('activity_sources')->whereIn('activity_id', clone $activityIds),
                        'streams' => DB::table('activity_streams')->whereIn('activity_id', clone $activityIds),
                        'events' => DB::table('activity_events')->whereIn('activity_id', clone $activityIds),
                        'analyses' => DB::table('ai_analyses')->where('user_id', $user->id),
                        'ai_usage_requests' => DB::table('ai_usage_requests')->where('user_id', $user->id),
                        'ai_usage_attempts' => DB::table('ai_usage_attempts')->where('user_id', $user->id),
                        'operation_events' => DB::table('operation_events')->where('user_id', $user->id),
                        'invitations' => DB::table('user_invitations')->where('user_id', $user->id),
                        'api_tokens' => $user->tokens()->getQuery()->toBase(),
                        'sessions' => DB::table('sessions')->where('user_id', $user->id),
                        'account_events' => DB::table('account_audit_events')->where('subject_user_id', $user->id)->orWhere('actor_user_id', $user->id),
                    ];
                    foreach ($queries as $name => $query) {
                        $file = tmpfile();
                        if ($file === false) {
                            throw new RuntimeException('Unable to create private export.');
                        }
                        $scratch[] = $file;
                        foreach ($query->orderBy('id')->cursor() as $row) {
                            $data = (array) $row;
                            foreach (['password', 'remember_token', 'access_token', 'refresh_token', 'token', 'token_hash', 'payload'] as $secret) {
                                unset($data[$secret]);
                            }
                            if ($name === 'sources') {
                                $data['export_file'] = $row->source_file_path ? 'files/'.$row->id.'.fit' : null;
                                unset($data['source_file_path']);
                                if ($row->source_file_path) {
                                    $this->files->assertOwnedPath($user, $row->source_file_path);
                                    $source = $this->files->disk()->readStream($row->source_file_path);
                                    $copy = tmpfile();
                                    if (! is_resource($source) || $copy === false) {
                                        throw new RuntimeException('An original file is unavailable. Retry the export.');
                                    }
                                    $scratch[] = $copy;
                                    try {
                                        if (stream_copy_to_stream($source, $copy) === false) {
                                            throw new RuntimeException('Unable to read an original file.');
                                        }
                                    } finally {
                                        fclose($source);
                                    }
                                    fflush($copy);
                                    $zip->addFile($this->path($copy), $data['export_file']);
                                }
                            }
                            // Database JSON columns become structured values in the portable export.
                            foreach (['metadata', 'context', 'output', 'raw_payload', 'data', 'scopes', 'capabilities', 'available_days', 'abilities', 'pricing'] as $key) {
                                if (isset($data[$key]) && is_string($data[$key])) {
                                    $decoded = json_decode($data[$key], true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $data[$key] = $decoded;
                                    }
                                }
                            }
                            if (fwrite($file, json_encode($data, JSON_THROW_ON_ERROR)."\n") === false) {
                                throw new RuntimeException('Unable to write private export.');
                            }
                        }
                        fflush($file);
                        $zip->addFile($this->path($file), $name.'.ndjson');
                    }
                } finally {
                    if (! $zip->close()) {
                        throw new RuntimeException('Unable to finish private export.');
                    }
                }
            });
            // ZipArchive replaces the pathname atomically; reopen the completed inode.
            $completed = fopen($this->path($archive), 'rb');
            if ($completed === false) {
                throw new RuntimeException('Unable to read completed private export.');
            }
            fclose($archive);
            $archive = $completed;
        } catch (\Throwable $exception) {
            fclose($archive);
            throw $exception;
        } finally {
            foreach ($scratch as $file) {
                fclose($file);
            }
        }
        // Anonymous 0600 temporary files are unlinked on close, including client disconnects.
        return response()->streamDownload(function () use ($archive): void {
            try {
                rewind($archive);
                fpassthru($archive);
            } finally {
                fclose($archive);
            }
        }, 'ridedock-personal-data.zip', ['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store, private']);
    }

    /** @param resource $file */
    private function path($file): string
    {
        $path = stream_get_meta_data($file)['uri'] ?? null;
        if (! is_string($path)) {
            throw new RuntimeException('Private export file has no path.');
        }
        return $path;
    }
}
