<?php

declare(strict_types=1);

helper('chunked_audio_upload');

if (! chunked_audio_upload_is_ready()) {
    return;
}

$adminGateway = trim(config('Admin')->gateway, '/');
$baseApiUrl = base_url($adminGateway . '/podcasts/' . $podcast->id . '/episodes/chunked-audio');
$sessionId = (string) old('chunked_audio_upload_session_id');
$thresholdBytes = chunked_audio_upload_threshold_bytes();
$maxFileSizeBytes = chunked_audio_upload_max_file_size_bytes();

$payload = [
    'podcastId'    => (int) $podcast->id,
    'baseApiUrl'   => $baseApiUrl,
    'threshold'    => $thresholdBytes,
    'maxFileSize'  => $maxFileSizeBytes,
    'sessionId'    => $sessionId,
    'pluginActive' => true,
];
?>

<input
    type="hidden"
    name="chunked_audio_upload_session_id"
    value="<?= esc($sessionId) ?>"
    data-chunked-audio-session-id
/>

<div
    class="flex flex-col p-3 mt-2 border rounded-md gap-y-2 border-subtle bg-base"
    data-chunked-audio-root
    data-chunked-audio-config="<?= esc((string) json_encode($payload)) ?>"
>
    <p class="text-xs text-skin-muted">
        Large files use resumable chunked upload automatically above <?= esc(formatBytes($thresholdBytes, true)) ?>.
    </p>

    <div class="hidden text-xs text-skin-muted" data-chunked-audio-progress-wrap>
        <div class="flex items-center justify-between mb-1">
            <span data-chunked-audio-progress-label>Preparing upload…</span>
            <span data-chunked-audio-progress-value>0%</span>
        </div>
        <progress class="w-full h-2" max="100" value="0" data-chunked-audio-progress></progress>
    </div>

    <p class="hidden text-xs text-red-700" data-chunked-audio-error></p>
</div>

<script>
(() => {
  const root = document.querySelector('[data-chunked-audio-root]');
  if (!root) {
    return;
  }

  const configRaw = root.getAttribute('data-chunked-audio-config');
  if (!configRaw) {
    return;
  }

  let config;
  try {
    config = JSON.parse(configRaw);
  } catch {
    return;
  }

  const form = root.closest('form');
  if (!form) {
    return;
  }

  const audioInput = form.querySelector('input[name="audio_file"]');
  const sessionInput = form.querySelector('[data-chunked-audio-session-id]');
  const progressWrap = root.querySelector('[data-chunked-audio-progress-wrap]');
  const progressBar = root.querySelector('[data-chunked-audio-progress]');
  const progressLabel = root.querySelector('[data-chunked-audio-progress-label]');
  const progressValue = root.querySelector('[data-chunked-audio-progress-value]');
  const errorEl = root.querySelector('[data-chunked-audio-error]');

  if (!audioInput || !sessionInput || !progressWrap || !progressBar || !progressLabel || !progressValue || !errorEl) {
    return;
  }

  const storageKey = `castopod:chunked-audio:${config.podcastId}`;
  let isUploading = false;

  function setError(message) {
    if (!message) {
      errorEl.classList.add('hidden');
      errorEl.textContent = '';
      return;
    }

    errorEl.textContent = message;
    errorEl.classList.remove('hidden');
  }

  function setProgress(visible, percent = 0, label = '') {
    if (!visible) {
      progressWrap.classList.add('hidden');
      progressBar.value = 0;
      progressValue.textContent = '0%';
      progressLabel.textContent = 'Preparing upload…';
      return;
    }

    progressWrap.classList.remove('hidden');
    progressBar.value = Math.max(0, Math.min(100, percent));
    progressValue.textContent = `${Math.round(percent)}%`;
    if (label) {
      progressLabel.textContent = label;
    }
  }

  function isChunkedCandidate(file) {
    if (!file) {
      return false;
    }

    const ext = (file.name.split('.').pop() || '').toLowerCase();
    if (!['mp3', 'm4a'].includes(ext)) {
      return false;
    }

    return file.size > Number(config.threshold || 0);
  }

  function getFileFingerprint(file) {
    return `${file.name}:${file.size}:${file.lastModified}`;
  }

  function readStoredSession(fileFingerprint) {
    try {
      const raw = localStorage.getItem(storageKey);
      if (!raw) {
        return null;
      }

      const parsed = JSON.parse(raw);
      if (!parsed || parsed.fingerprint !== fileFingerprint || typeof parsed.sessionId !== 'string') {
        return null;
      }

      return parsed.sessionId;
    } catch {
      return null;
    }
  }

  function storeSession(fileFingerprint, sessionId) {
    try {
      localStorage.setItem(storageKey, JSON.stringify({
        fingerprint: fileFingerprint,
        sessionId,
        updatedAt: Date.now(),
      }));
    } catch {
      // no-op
    }
  }

  async function apiCall(method, url, payload) {
    const options = {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    };

    if (payload !== undefined) {
      options.body = JSON.stringify(payload);
    }

    const response = await fetch(url, options);
    const isJson = (response.headers.get('content-type') || '').includes('application/json');
    const body = isJson ? await response.json() : null;

    if (!response.ok) {
      const message = (body && body.error) ? body.error : `Upload API error (${response.status})`;
      throw new Error(message);
    }

    return body || {};
  }

  function uploadPartWithProgress(url, blob, onProgress) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open('PUT', url, true);

      xhr.upload.onprogress = (event) => {
        if (event.lengthComputable) {
          onProgress(event.loaded);
        }
      };

      xhr.onerror = () => reject(new Error('Part upload network error'));
      xhr.onabort = () => reject(new Error('Part upload aborted'));

      xhr.onload = () => {
        if (xhr.status < 200 || xhr.status >= 300) {
          reject(new Error(`Part upload failed (${xhr.status})`));
          return;
        }

        const etagRaw = xhr.getResponseHeader('ETag');
        const etag = etagRaw ? etagRaw.replace(/\"/g, '').trim() : '';

        if (!etag) {
          reject(new Error('Missing ETag in S3 response. Check bucket CORS ExposeHeaders.'));
          return;
        }

        resolve(etag);
      };

      xhr.send(blob);
    });
  }

  async function retry(fn, maxAttempts = 5) {
    let attempt = 0;

    while (attempt < maxAttempts) {
      try {
        return await fn();
      } catch (error) {
        attempt += 1;
        if (attempt >= maxAttempts) {
          throw error;
        }

        const waitMs = Math.min(15000, (2 ** attempt) * 500) + Math.floor(Math.random() * 250);
        await new Promise((resolve) => setTimeout(resolve, waitMs));
      }
    }

    throw new Error('Upload retry loop failed unexpectedly');
  }

  async function uploadChunkedAudio(file) {
    const fingerprint = getFileFingerprint(file);
    const candidateSessionId = readStoredSession(fingerprint) || (sessionInput.value || '').trim();

    const startPayload = {
      fileName: file.name,
      fileSize: file.size,
      mimeType: file.type || 'application/octet-stream',
      sessionId: candidateSessionId || undefined,
    };

    const session = await apiCall('POST', `${config.baseApiUrl}/start`, startPayload);
    const sessionId = String(session.sessionId || '').trim();
    if (!sessionId) {
      throw new Error('Upload session initialization failed.');
    }

    storeSession(fingerprint, sessionId);

    if (session.status === 'completed') {
      sessionInput.value = sessionId;
      audioInput.value = '';
      return;
    }

    const partSize = Number(session.partSize || 0);
    const partCount = Number(session.partCount || 0);
    if (!partSize || !partCount) {
      throw new Error('Invalid multipart session details.');
    }

    const uploadedParts = Array.isArray(session.uploadedParts) ? session.uploadedParts : [];
    const completedEtags = new Map();

    let committedBytes = 0;
    for (const part of uploadedParts) {
      const partNumber = Number(part.partNumber || 0);
      const etag = String(part.etag || '').trim();
      const size = Number(part.size || 0);
      if (partNumber > 0 && etag) {
        completedEtags.set(partNumber, etag);
        committedBytes += size > 0 ? size : 0;
      }
    }

    const inflightProgress = new Map();

    const refreshProgress = (label) => {
      let inflightBytes = 0;
      for (const value of inflightProgress.values()) {
        inflightBytes += value;
      }

      const percent = ((committedBytes + inflightBytes) / file.size) * 100;
      setProgress(true, percent, label);
    };

    const pendingParts = [];
    for (let partNumber = 1; partNumber <= partCount; partNumber += 1) {
      if (!completedEtags.has(partNumber)) {
        pendingParts.push(partNumber);
      }
    }

    const concurrency = Math.max(1, Number(session.parallelUploads || 4));
    let cursor = 0;

    async function worker() {
      while (cursor < pendingParts.length) {
        const currentIndex = cursor;
        cursor += 1;

        const partNumber = pendingParts[currentIndex];
        const start = (partNumber - 1) * partSize;
        const end = Math.min(start + partSize, file.size);
        const blob = file.slice(start, end);

        await retry(async () => {
          const signData = await apiCall('POST', `${config.baseApiUrl}/${sessionId}/sign`, { partNumber });
          const url = signData && signData.urls ? signData.urls[String(partNumber)] || signData.urls[partNumber] : '';
          if (!url) {
            throw new Error(`Missing signed URL for part ${partNumber}.`);
          }

          inflightProgress.set(partNumber, 0);
          refreshProgress(`Uploading part ${partNumber}/${partCount}…`);

          const etag = await uploadPartWithProgress(url, blob, (loaded) => {
            inflightProgress.set(partNumber, loaded);
            refreshProgress(`Uploading part ${partNumber}/${partCount}…`);
          });

          inflightProgress.delete(partNumber);
          committedBytes += blob.size;
          completedEtags.set(partNumber, etag);
          refreshProgress(`Uploaded part ${partNumber}/${partCount}.`);
        });
      }
    }

    setProgress(true, (committedBytes / file.size) * 100, 'Starting multipart upload…');

    const workers = [];
    for (let i = 0; i < concurrency; i += 1) {
      workers.push(worker());
    }
    await Promise.all(workers);

    const completionParts = Array.from(completedEtags.entries())
      .sort((a, b) => a[0] - b[0])
      .map(([partNumber, etag]) => ({ partNumber, etag }));

    await apiCall('POST', `${config.baseApiUrl}/${sessionId}/complete`, {
      parts: completionParts,
    });

    sessionInput.value = sessionId;
    audioInput.value = '';
    setProgress(true, 100, 'Chunked upload complete. Submitting episode form…');
  }

  audioInput.addEventListener('change', () => {
    if (audioInput.files && audioInput.files.length > 0) {
      sessionInput.value = '';
    }

    setError('');
    setProgress(false);
  });

  form.addEventListener('submit', async (event) => {
    if (isUploading) {
      event.preventDefault();
      return;
    }

    setError('');

    const file = audioInput.files && audioInput.files.length > 0 ? audioInput.files[0] : null;
    const hasSession = (sessionInput.value || '').trim() !== '';

    if (!file) {
      if (hasSession) {
        return;
      }

      return;
    }

    if (file.size > Number(config.maxFileSize || 0)) {
      event.preventDefault();
      setError(`File too large. Maximum allowed is ${(Number(config.maxFileSize || 0) / (1024 * 1024)).toFixed(0)} MB.`);
      return;
    }

    if (!isChunkedCandidate(file)) {
      return;
    }

    event.preventDefault();
    isUploading = true;

    try {
      await uploadChunkedAudio(file);
      form.submit();
    } catch (error) {
      setProgress(false);
      setError(error instanceof Error ? error.message : 'Chunked upload failed.');
    } finally {
      isUploading = false;
    }
  });
})();
</script>
