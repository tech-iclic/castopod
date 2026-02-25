<?php

declare(strict_types=1);

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use CodeIgniter\Files\File;
use CodeIgniter\HTTP\RequestInterface;
use Modules\Media\Config\Media as MediaConfig;

if (! function_exists('chunked_audio_upload_plugin_key')) {
    function chunked_audio_upload_plugin_key(): string
    {
        return 'iclic-inc/chunked-audio-upload';
    }
}

if (! function_exists('chunked_audio_upload_allowed_extensions')) {
    /**
     * @return list<string>
     */
    function chunked_audio_upload_allowed_extensions(): array
    {
        return ['mp3', 'm4a'];
    }
}

if (! function_exists('chunked_audio_upload_get_setting_int')) {
    function chunked_audio_upload_get_setting_int(string $key, int $default): int
    {
        helper('plugins');

        $value = get_plugin_setting(chunked_audio_upload_plugin_key(), $key);
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && trim($value) !== '' && is_numeric(trim($value))) {
            return (int) trim($value);
        }

        return $default;
    }
}

if (! function_exists('chunked_audio_upload_get_setting_string')) {
    function chunked_audio_upload_get_setting_string(string $key, string $default): string
    {
        helper('plugins');

        $value = get_plugin_setting(chunked_audio_upload_plugin_key(), $key);
        if (is_string($value)) {
            $clean = trim($value);
            return $clean === '' ? $default : $clean;
        }

        return $default;
    }
}

if (! function_exists('chunked_audio_upload_is_enabled')) {
    function chunked_audio_upload_is_enabled(): bool
    {
        helper('plugins');

        if (! (bool) get_plugin_setting(chunked_audio_upload_plugin_key(), 'active')) {
            return false;
        }

        $mediaConfig = config(MediaConfig::class);

        return $mediaConfig->fileManager === 's3';
    }
}

if (! function_exists('chunked_audio_upload_is_ready')) {
    function chunked_audio_upload_is_ready(): bool
    {
        if (! chunked_audio_upload_is_enabled()) {
            return false;
        }

        $mediaConfig = config(MediaConfig::class);

        return (string) ($mediaConfig->s3['bucket'] ?? '') !== ''
            && (string) ($mediaConfig->s3['region'] ?? '') !== ''
            && (string) ($mediaConfig->s3['endpoint'] ?? '') !== ''
            && (string) ($mediaConfig->s3['key'] ?? '') !== ''
            && (string) ($mediaConfig->s3['secret'] ?? '') !== '';
    }
}

if (! function_exists('chunked_audio_upload_max_file_size_bytes')) {
    function chunked_audio_upload_max_file_size_bytes(): int
    {
        $maxFileSizeMb = max(100, chunked_audio_upload_get_setting_int('max_file_size_mb', 2048));

        return $maxFileSizeMb * 1024 * 1024;
    }
}

if (! function_exists('chunked_audio_upload_threshold_bytes')) {
    function chunked_audio_upload_threshold_bytes(): int
    {
        $thresholdMb = max(1, chunked_audio_upload_get_setting_int('chunked_threshold_mb', 90));

        return $thresholdMb * 1024 * 1024;
    }
}

if (! function_exists('chunked_audio_upload_chunk_size_bytes')) {
    function chunked_audio_upload_chunk_size_bytes(): int
    {
        $minimum = 5 * 1024 * 1024;
        $configured = max(5, chunked_audio_upload_get_setting_int('chunk_size_mb', 16)) * 1024 * 1024;

        return max($minimum, $configured);
    }
}

if (! function_exists('chunked_audio_upload_parallel_uploads')) {
    function chunked_audio_upload_parallel_uploads(): int
    {
        $parallelUploads = chunked_audio_upload_get_setting_int('parallel_uploads', 4);

        return max(1, min(16, $parallelUploads));
    }
}

if (! function_exists('chunked_audio_upload_presigned_ttl_seconds')) {
    function chunked_audio_upload_presigned_ttl_seconds(): int
    {
        $seconds = chunked_audio_upload_get_setting_int('presigned_url_ttl_seconds', 900);

        return max(60, min(3600, $seconds));
    }
}

if (! function_exists('chunked_audio_upload_session_ttl_seconds')) {
    function chunked_audio_upload_session_ttl_seconds(): int
    {
        $minutes = chunked_audio_upload_get_setting_int('session_ttl_minutes', 720);

        return max(30, min(7 * 24 * 60, $minutes)) * 60;
    }
}

if (! function_exists('chunked_audio_upload_temp_prefix')) {
    function chunked_audio_upload_temp_prefix(): string
    {
        $prefix = chunked_audio_upload_get_setting_string('temp_key_prefix', 'tmp/chunked-audio');

        return trim($prefix, '/');
    }
}

if (! function_exists('chunked_audio_upload_to_full_bucket_key')) {
    function chunked_audio_upload_to_full_bucket_key(string $relativeKey): string
    {
        $relativeKey = ltrim($relativeKey, '/');
        $mediaConfig = config(MediaConfig::class);
        $mediaPrefix = trim((string) ($mediaConfig->s3['keyPrefix'] ?? ''), '/');

        if ($mediaPrefix === '') {
            return $relativeKey;
        }

        return $mediaPrefix . '/' . $relativeKey;
    }
}

if (! function_exists('chunked_audio_upload_create_s3_client')) {
    function chunked_audio_upload_create_s3_client(): S3Client
    {
        $mediaConfig = config(MediaConfig::class);

        return new S3Client([
            'version'                 => 'latest',
            'region'                  => $mediaConfig->s3['region'],
            'endpoint'                => $mediaConfig->s3['endpoint'],
            'credentials'             => new Credentials((string) $mediaConfig->s3['key'], (string) $mediaConfig->s3['secret']),
            'debug'                   => $mediaConfig->s3['debug'],
            'use_path_style_endpoint' => $mediaConfig->s3['pathStyleEndpoint'],
        ]);
    }
}

if (! function_exists('chunked_audio_upload_bucket_name')) {
    function chunked_audio_upload_bucket_name(): string
    {
        $mediaConfig = config(MediaConfig::class);

        return (string) $mediaConfig->s3['bucket'];
    }
}

if (! function_exists('chunked_audio_upload_compute_part_size')) {
    function chunked_audio_upload_compute_part_size(int $fileSize): int
    {
        $partSize = chunked_audio_upload_chunk_size_bytes();
        $minimumByPartCount = (int) ceil($fileSize / 10000);

        return max(5 * 1024 * 1024, $partSize, $minimumByPartCount);
    }
}

if (! function_exists('chunked_audio_upload_part_count')) {
    function chunked_audio_upload_part_count(int $fileSize, int $partSize): int
    {
        return (int) ceil($fileSize / $partSize);
    }
}

if (! function_exists('chunked_audio_upload_normalize_extension')) {
    function chunked_audio_upload_normalize_extension(string $filename): string
    {
        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($ext, chunked_audio_upload_allowed_extensions(), true) ? $ext : '';
    }
}

if (! function_exists('chunked_audio_upload_make_session_id')) {
    function chunked_audio_upload_make_session_id(): string
    {
        return bin2hex(random_bytes(16));
    }
}

if (! function_exists('chunked_audio_upload_normalize_session_id')) {
    function chunked_audio_upload_normalize_session_id(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $sessionId = strtolower(trim($value));

        return preg_match('/^[a-f0-9]{32}$/', $sessionId) === 1 ? $sessionId : '';
    }
}

if (! function_exists('chunked_audio_upload_cache_key')) {
    function chunked_audio_upload_cache_key(string $sessionId): string
    {
        return 'chunked-audio-upload:session:' . $sessionId;
    }
}

if (! function_exists('chunked_audio_upload_store_session')) {
    /**
     * @param array<string, mixed> $session
     */
    function chunked_audio_upload_store_session(array $session): void
    {
        cache()->save(
            chunked_audio_upload_cache_key((string) $session['session_id']),
            $session,
            chunked_audio_upload_session_ttl_seconds(),
        );
    }
}

if (! function_exists('chunked_audio_upload_get_session')) {
    /**
     * @return ?array<string, mixed>
     */
    function chunked_audio_upload_get_session(string $sessionId): ?array
    {
        $session = cache(chunked_audio_upload_cache_key($sessionId));

        return is_array($session) ? $session : null;
    }
}

if (! function_exists('chunked_audio_upload_delete_session')) {
    function chunked_audio_upload_delete_session(string $sessionId): void
    {
        cache()->delete(chunked_audio_upload_cache_key($sessionId));
    }
}

if (! function_exists('chunked_audio_upload_is_same_origin')) {
    function chunked_audio_upload_is_same_origin(RequestInterface $request): bool
    {
        $baseUrlParts = parse_url((string) base_url('/'));
        $baseHost = strtolower((string) ($baseUrlParts['host'] ?? ''));

        if ($baseHost === '') {
            return true;
        }

        $baseScheme = strtolower((string) ($baseUrlParts['scheme'] ?? 'https'));
        $basePort = (int) ($baseUrlParts['port'] ?? ($baseScheme === 'https' ? 443 : 80));

        $origin = trim($request->getHeaderLine('Origin'));
        if ($origin !== '') {
            $originParts = parse_url($origin);
            $originHost = strtolower((string) ($originParts['host'] ?? ''));
            $originScheme = strtolower((string) ($originParts['scheme'] ?? $baseScheme));
            $originPort = (int) ($originParts['port'] ?? ($originScheme === 'https' ? 443 : 80));

            return $originHost === $baseHost && $originScheme === $baseScheme && $originPort === $basePort;
        }

        $referer = trim($request->getHeaderLine('Referer'));
        if ($referer === '') {
            return false;
        }

        $refererParts = parse_url($referer);
        $refererHost = strtolower((string) ($refererParts['host'] ?? ''));
        $refererScheme = strtolower((string) ($refererParts['scheme'] ?? $baseScheme));
        $refererPort = (int) ($refererParts['port'] ?? ($refererScheme === 'https' ? 443 : 80));

        return $refererHost === $baseHost && $refererScheme === $baseScheme && $refererPort === $basePort;
    }
}

if (! function_exists('chunked_audio_upload_is_valid_api_request')) {
    function chunked_audio_upload_is_valid_api_request(RequestInterface $request): bool
    {
        $requestedWith = strtolower(trim($request->getHeaderLine('X-Requested-With')));

        return $requestedWith === 'xmlhttprequest' && chunked_audio_upload_is_same_origin($request);
    }
}

if (! function_exists('chunked_audio_upload_create_session')) {
    /**
     * @return array<string, mixed>
     */
    function chunked_audio_upload_create_session(
        int $podcastId,
        int $userId,
        string $originalFilename,
        int $fileSize,
        string $mimeType,
    ): array {
        $extension = chunked_audio_upload_normalize_extension($originalFilename);
        if ($extension === '') {
            throw new RuntimeException('Unsupported audio file extension.');
        }

        if ($fileSize <= 0) {
            throw new RuntimeException('File size must be greater than zero.');
        }

        if ($fileSize > chunked_audio_upload_max_file_size_bytes()) {
            throw new RuntimeException('Audio file exceeds configured max file size.');
        }

        $partSize = chunked_audio_upload_compute_part_size($fileSize);
        $partCount = chunked_audio_upload_part_count($fileSize, $partSize);

        if ($partCount < 1 || $partCount > 10000) {
            throw new RuntimeException('Invalid multipart configuration (part count out of bounds).');
        }

        $sessionId = chunked_audio_upload_make_session_id();
        $objectRelativeKey = sprintf(
            '%s/podcast-%d/user-%d/%s.%s',
            chunked_audio_upload_temp_prefix(),
            $podcastId,
            $userId,
            $sessionId,
            $extension,
        );
        $objectFullKey = chunked_audio_upload_to_full_bucket_key($objectRelativeKey);

        $bucket = chunked_audio_upload_bucket_name();
        $s3 = chunked_audio_upload_create_s3_client();

        $createResult = $s3->createMultipartUpload([
            'Bucket'      => $bucket,
            'Key'         => $objectFullKey,
            'ContentType' => $mimeType !== '' ? $mimeType : 'application/octet-stream',
            'CacheControl' => 'max-age=' . YEAR,
        ]);

        $uploadId = (string) ($createResult['UploadId'] ?? '');
        if ($uploadId === '') {
            throw new RuntimeException('Could not initialize multipart upload.');
        }

        $session = [
            'session_id'      => $sessionId,
            'podcast_id'      => $podcastId,
            'user_id'         => $userId,
            'status'          => 'initiated',
            'upload_id'       => $uploadId,
            'bucket'          => $bucket,
            'object_key'      => $objectRelativeKey,
            'object_key_full' => $objectFullKey,
            'file_name'       => $originalFilename,
            'file_size'       => $fileSize,
            'mime_type'       => $mimeType,
            'extension'       => $extension,
            'part_size'       => $partSize,
            'part_count'      => $partCount,
            'created_at'      => time(),
            'updated_at'      => time(),
            'completed_at'    => null,
        ];

        chunked_audio_upload_store_session($session);

        return $session;
    }
}

if (! function_exists('chunked_audio_upload_assert_session_owner')) {
    /**
     * @param array<string, mixed> $session
     */
    function chunked_audio_upload_assert_session_owner(array $session, int $podcastId, int $userId): bool
    {
        return (int) ($session['podcast_id'] ?? 0) === $podcastId && (int) ($session['user_id'] ?? 0) === $userId;
    }
}

if (! function_exists('chunked_audio_upload_list_parts')) {
    /**
     * @param array<string, mixed> $session
     *
     * @return list<array{partNumber:int,etag:string,size:int}>
     */
    function chunked_audio_upload_list_parts(array $session): array
    {
        if ((string) ($session['status'] ?? '') !== 'initiated') {
            return [];
        }

        $s3 = chunked_audio_upload_create_s3_client();

        $params = [
            'Bucket'   => (string) $session['bucket'],
            'Key'      => (string) $session['object_key_full'],
            'UploadId' => (string) $session['upload_id'],
        ];

        $parts = [];

        do {
            $result = $s3->listParts($params);
            $resultParts = $result['Parts'] ?? [];

            if (is_array($resultParts)) {
                foreach ($resultParts as $part) {
                    $parts[] = [
                        'partNumber' => (int) ($part['PartNumber'] ?? 0),
                        'etag'       => trim((string) ($part['ETag'] ?? ''), '"'),
                        'size'       => (int) ($part['Size'] ?? 0),
                    ];
                }
            }

            $isTruncated = (bool) ($result['IsTruncated'] ?? false);
            if (! $isTruncated) {
                break;
            }

            $params['PartNumberMarker'] = (int) ($result['NextPartNumberMarker'] ?? 0);
        } while (true);

        return $parts;
    }
}

if (! function_exists('chunked_audio_upload_sign_upload_part_urls')) {
    /**
     * @param array<string, mixed> $session
     * @param list<int> $partNumbers
     *
     * @return array<int,string>
     */
    function chunked_audio_upload_sign_upload_part_urls(array $session, array $partNumbers): array
    {
        $urls = [];
        $s3 = chunked_audio_upload_create_s3_client();
        $expiresIn = '+' . chunked_audio_upload_presigned_ttl_seconds() . ' seconds';

        foreach ($partNumbers as $partNumber) {
            $cmd = $s3->getCommand('UploadPart', [
                'Bucket'     => (string) $session['bucket'],
                'Key'        => (string) $session['object_key_full'],
                'UploadId'   => (string) $session['upload_id'],
                'PartNumber' => $partNumber,
            ]);

            $request = $s3->createPresignedRequest($cmd, $expiresIn);
            $urls[$partNumber] = (string) $request->getUri();
        }

        return $urls;
    }
}

if (! function_exists('chunked_audio_upload_complete_session')) {
    /**
     * @param array<string, mixed> $session
     * @param list<array{PartNumber:int,ETag:string}> $parts
     *
     * @return array<string,mixed>
     */
    function chunked_audio_upload_complete_session(array $session, array $parts): array
    {
        if ((string) ($session['status'] ?? '') !== 'initiated') {
            throw new RuntimeException('Upload session is not in an uploadable state.');
        }

        if ($parts === []) {
            throw new RuntimeException('Cannot complete upload without parts.');
        }

        usort($parts, static fn (array $a, array $b): int => $a['PartNumber'] <=> $b['PartNumber']);

        $s3 = chunked_audio_upload_create_s3_client();
        $s3->completeMultipartUpload([
            'Bucket'          => (string) $session['bucket'],
            'Key'             => (string) $session['object_key_full'],
            'UploadId'        => (string) $session['upload_id'],
            'MultipartUpload' => [
                'Parts' => $parts,
            ],
        ]);

        $headObject = $s3->headObject([
            'Bucket' => (string) $session['bucket'],
            'Key'    => (string) $session['object_key_full'],
        ]);

        $session['status'] = 'completed';
        $session['completed_at'] = time();
        $session['updated_at'] = time();
        $session['upload_id'] = (string) ($session['upload_id'] ?? '');
        $session['completed_size'] = (int) ($headObject['ContentLength'] ?? $session['file_size']);
        $session['completed_etag'] = trim((string) ($headObject['ETag'] ?? ''), '"');

        chunked_audio_upload_store_session($session);

        return $session;
    }
}

if (! function_exists('chunked_audio_upload_abort_session')) {
    /**
     * @param array<string, mixed> $session
     */
    function chunked_audio_upload_abort_session(array $session): void
    {
        $status = (string) ($session['status'] ?? '');
        $bucket = (string) ($session['bucket'] ?? '');
        $key = (string) ($session['object_key_full'] ?? '');

        try {
            $s3 = chunked_audio_upload_create_s3_client();

            if ($status === 'initiated') {
                $uploadId = (string) ($session['upload_id'] ?? '');
                if ($uploadId !== '') {
                    $s3->abortMultipartUpload([
                        'Bucket'   => $bucket,
                        'Key'      => $key,
                        'UploadId' => $uploadId,
                    ]);
                }
            }

            if ($status === 'completed') {
                $s3->deleteObject([
                    'Bucket' => $bucket,
                    'Key'    => $key,
                ]);
            }
        } catch (Throwable) {
            // Best effort cleanup.
        }

        chunked_audio_upload_delete_session((string) ($session['session_id'] ?? ''));
    }
}

if (! function_exists('chunked_audio_upload_load_completed_audio_file')) {
    function chunked_audio_upload_load_completed_audio_file(string $sessionId, int $podcastId, int $userId): ?File
    {
        $session = chunked_audio_upload_get_session($sessionId);
        if (! is_array($session)) {
            return null;
        }

        if (! chunked_audio_upload_assert_session_owner($session, $podcastId, $userId)) {
            return null;
        }

        if ((string) ($session['status'] ?? '') !== 'completed') {
            return null;
        }

        $extension = (string) ($session['extension'] ?? '');
        if (! in_array($extension, chunked_audio_upload_allowed_extensions(), true)) {
            return null;
        }

        $tmpPath = WRITEPATH . 'uploads/' . bin2hex(random_bytes(16)) . '.' . $extension;

        $s3 = chunked_audio_upload_create_s3_client();
        $s3->getObject([
            'Bucket' => (string) $session['bucket'],
            'Key'    => (string) $session['object_key_full'],
            'SaveAs' => $tmpPath,
        ]);

        return new File($tmpPath, true);
    }
}

if (! function_exists('chunked_audio_upload_cleanup_completed_session')) {
    function chunked_audio_upload_cleanup_completed_session(string $sessionId, int $podcastId, int $userId): void
    {
        $session = chunked_audio_upload_get_session($sessionId);
        if (! is_array($session)) {
            return;
        }

        if (! chunked_audio_upload_assert_session_owner($session, $podcastId, $userId)) {
            return;
        }

        chunked_audio_upload_abort_session($session);
    }
}
