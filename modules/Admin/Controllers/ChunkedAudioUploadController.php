<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

class ChunkedAudioUploadController extends BaseController
{
    public function start(int $podcastId): ResponseInterface
    {
        helper('chunked_audio_upload');

        if (! chunked_audio_upload_is_ready()) {
            return $this->jsonError('Chunked upload plugin is disabled or not configured for S3.', 409);
        }

        if (! chunked_audio_upload_is_valid_api_request($this->request)) {
            return $this->jsonError('Invalid request origin.', 403);
        }

        $userId = (int) user_id();
        if ($userId <= 0) {
            return $this->jsonError('Not authenticated.', 401);
        }

        if (! $this->canUploadToPodcast($podcastId)) {
            return $this->jsonError('Not enough privilege.', 403);
        }

        try {
            $payload = $this->jsonPayload();
            $fileName = trim((string) ($payload['fileName'] ?? ''));
            $fileSize = (int) ($payload['fileSize'] ?? 0);
            $mimeType = trim((string) ($payload['mimeType'] ?? 'application/octet-stream'));

            if ($fileName === '') {
                throw new RuntimeException('Missing fileName.');
            }

            $resumeSessionId = chunked_audio_upload_normalize_session_id($payload['sessionId'] ?? null);
            if ($resumeSessionId !== '') {
                $resumeSession = chunked_audio_upload_get_session($resumeSessionId);
                if (
                    is_array($resumeSession)
                    && chunked_audio_upload_assert_session_owner($resumeSession, $podcastId, $userId)
                    && (string) ($resumeSession['file_name'] ?? '') === $fileName
                    && (int) ($resumeSession['file_size'] ?? 0) === $fileSize
                ) {
                    return $this->jsonSession($resumeSession);
                }
            }

            $session = chunked_audio_upload_create_session($podcastId, $userId, $fileName, $fileSize, $mimeType);

            return $this->jsonSession($session, 201);
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function status(int $podcastId, string $sessionId): ResponseInterface
    {
        helper('chunked_audio_upload');

        if (! chunked_audio_upload_is_ready()) {
            return $this->jsonError('Chunked upload plugin is disabled or not configured for S3.', 409);
        }

        if (! chunked_audio_upload_is_valid_api_request($this->request)) {
            return $this->jsonError('Invalid request origin.', 403);
        }

        $userId = (int) user_id();
        if ($userId <= 0) {
            return $this->jsonError('Not authenticated.', 401);
        }

        if (! $this->canUploadToPodcast($podcastId)) {
            return $this->jsonError('Not enough privilege.', 403);
        }

        $normalizedSessionId = chunked_audio_upload_normalize_session_id($sessionId);
        if ($normalizedSessionId === '') {
            return $this->jsonError('Invalid session id.', 422);
        }

        $session = chunked_audio_upload_get_session($normalizedSessionId);
        if (! is_array($session) || ! chunked_audio_upload_assert_session_owner($session, $podcastId, $userId)) {
            return $this->jsonError('Upload session not found.', 404);
        }

        return $this->jsonSession($session);
    }

    public function sign(int $podcastId, string $sessionId): ResponseInterface
    {
        helper('chunked_audio_upload');

        if (! chunked_audio_upload_is_ready()) {
            return $this->jsonError('Chunked upload plugin is disabled or not configured for S3.', 409);
        }

        if (! chunked_audio_upload_is_valid_api_request($this->request)) {
            return $this->jsonError('Invalid request origin.', 403);
        }

        $userId = (int) user_id();
        if ($userId <= 0) {
            return $this->jsonError('Not authenticated.', 401);
        }

        if (! $this->canUploadToPodcast($podcastId)) {
            return $this->jsonError('Not enough privilege.', 403);
        }

        try {
            $normalizedSessionId = chunked_audio_upload_normalize_session_id($sessionId);
            if ($normalizedSessionId === '') {
                throw new RuntimeException('Invalid session id.');
            }

            $session = chunked_audio_upload_get_session($normalizedSessionId);
            if (! is_array($session) || ! chunked_audio_upload_assert_session_owner($session, $podcastId, $userId)) {
                return $this->jsonError('Upload session not found.', 404);
            }

            if ((string) ($session['status'] ?? '') !== 'initiated') {
                throw new RuntimeException('Upload session is not active.');
            }

            $payload = $this->jsonPayload();

            $partNumbers = [];
            if (array_key_exists('partNumber', $payload)) {
                $partNumbers[] = (int) $payload['partNumber'];
            }

            if (array_key_exists('partNumbers', $payload) && is_array($payload['partNumbers'])) {
                foreach ($payload['partNumbers'] as $partNumber) {
                    $partNumbers[] = (int) $partNumber;
                }
            }

            if ($partNumbers === []) {
                throw new RuntimeException('Missing part number(s).');
            }

            $partCount = (int) ($session['part_count'] ?? 0);
            $partNumbers = array_values(array_unique($partNumbers));
            foreach ($partNumbers as $partNumber) {
                if ($partNumber < 1 || $partNumber > $partCount) {
                    throw new RuntimeException('Part number out of bounds.');
                }
            }

            $urls = chunked_audio_upload_sign_upload_part_urls($session, $partNumbers);

            return $this->response->setJSON([
                'sessionId' => (string) $session['session_id'],
                'urls'      => $urls,
                'ttl'       => chunked_audio_upload_presigned_ttl_seconds(),
            ]);
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function complete(int $podcastId, string $sessionId): ResponseInterface
    {
        helper('chunked_audio_upload');

        if (! chunked_audio_upload_is_ready()) {
            return $this->jsonError('Chunked upload plugin is disabled or not configured for S3.', 409);
        }

        if (! chunked_audio_upload_is_valid_api_request($this->request)) {
            return $this->jsonError('Invalid request origin.', 403);
        }

        $userId = (int) user_id();
        if ($userId <= 0) {
            return $this->jsonError('Not authenticated.', 401);
        }

        if (! $this->canUploadToPodcast($podcastId)) {
            return $this->jsonError('Not enough privilege.', 403);
        }

        try {
            $normalizedSessionId = chunked_audio_upload_normalize_session_id($sessionId);
            if ($normalizedSessionId === '') {
                throw new RuntimeException('Invalid session id.');
            }

            $session = chunked_audio_upload_get_session($normalizedSessionId);
            if (! is_array($session) || ! chunked_audio_upload_assert_session_owner($session, $podcastId, $userId)) {
                return $this->jsonError('Upload session not found.', 404);
            }

            if ((string) ($session['status'] ?? '') !== 'initiated') {
                throw new RuntimeException('Upload session is not active.');
            }

            $payload = $this->jsonPayload();
            $rawParts = $payload['parts'] ?? null;
            if (! is_array($rawParts) || $rawParts === []) {
                throw new RuntimeException('Missing multipart completion payload.');
            }

            $partCount = (int) ($session['part_count'] ?? 0);
            $parts = [];
            foreach ($rawParts as $rawPart) {
                if (! is_array($rawPart)) {
                    continue;
                }

                $partNumber = (int) ($rawPart['partNumber'] ?? 0);
                $etag = trim((string) ($rawPart['etag'] ?? ''), '"');

                if ($partNumber < 1 || $partNumber > $partCount || $etag === '') {
                    throw new RuntimeException('Invalid part in completion payload.');
                }

                $parts[$partNumber] = [
                    'PartNumber' => $partNumber,
                    'ETag'       => $etag,
                ];
            }

            if ($parts === []) {
                throw new RuntimeException('No valid parts to complete upload.');
            }

            $session = chunked_audio_upload_complete_session($session, array_values($parts));

            return $this->jsonSession($session);
        } catch (Throwable $e) {
            return $this->jsonError($e->getMessage(), 422);
        }
    }

    public function abort(int $podcastId, string $sessionId): ResponseInterface
    {
        helper('chunked_audio_upload');

        if (! chunked_audio_upload_is_ready()) {
            return $this->jsonError('Chunked upload plugin is disabled or not configured for S3.', 409);
        }

        if (! chunked_audio_upload_is_valid_api_request($this->request)) {
            return $this->jsonError('Invalid request origin.', 403);
        }

        $userId = (int) user_id();
        if ($userId <= 0) {
            return $this->jsonError('Not authenticated.', 401);
        }

        if (! $this->canUploadToPodcast($podcastId)) {
            return $this->jsonError('Not enough privilege.', 403);
        }

        $normalizedSessionId = chunked_audio_upload_normalize_session_id($sessionId);
        if ($normalizedSessionId === '') {
            return $this->jsonError('Invalid session id.', 422);
        }

        $session = chunked_audio_upload_get_session($normalizedSessionId);
        if (! is_array($session) || ! chunked_audio_upload_assert_session_owner($session, $podcastId, $userId)) {
            return $this->jsonError('Upload session not found.', 404);
        }

        chunked_audio_upload_abort_session($session);

        return $this->response->setJSON([
            'ok' => true,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function jsonPayload(): array
    {
        $payload = $this->request->getJSON(true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string,mixed> $session
     */
    private function jsonSession(array $session, int $statusCode = 200): ResponseInterface
    {
        helper('chunked_audio_upload');

        $uploadedParts = [];
        if ((string) ($session['status'] ?? '') === 'initiated') {
            try {
                $uploadedParts = chunked_audio_upload_list_parts($session);
            } catch (Throwable) {
                $uploadedParts = [];
            }
        }

        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON([
                'sessionId'       => (string) ($session['session_id'] ?? ''),
                'status'          => (string) ($session['status'] ?? ''),
                'fileName'        => (string) ($session['file_name'] ?? ''),
                'fileSize'        => (int) ($session['file_size'] ?? 0),
                'mimeType'        => (string) ($session['mime_type'] ?? ''),
                'partSize'        => (int) ($session['part_size'] ?? 0),
                'partCount'       => (int) ($session['part_count'] ?? 0),
                'parallelUploads' => chunked_audio_upload_parallel_uploads(),
                'thresholdBytes'  => chunked_audio_upload_threshold_bytes(),
                'maxFileSize'     => chunked_audio_upload_max_file_size_bytes(),
                'uploadedParts'   => $uploadedParts,
            ]);
    }

    private function jsonError(string $message, int $statusCode): ResponseInterface
    {
        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON([
                'error' => $message,
            ]);
    }

    private function canUploadToPodcast(int $podcastId): bool
    {
        if (! auth()->loggedIn()) {
            return false;
        }

        $user = auth()->user();

        return $user->can("podcast#{$podcastId}.episodes.create")
            || $user->can("podcast#{$podcastId}.episodes.edit");
    }
}
