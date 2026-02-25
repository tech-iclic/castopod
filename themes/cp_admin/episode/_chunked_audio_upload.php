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
    'messages'     => [
        'preparing'    => 'Préparation du fichier…',
        'starting'     => 'Démarrage de l’envoi…',
        'uploading'    => 'Envoi du fichier en cours…',
        'completed'    => 'Fichier envoyé. Enregistrement en cours…',
        'submitLocked' => 'Envoi en cours…',
        'maxSize'      => 'Fichier trop volumineux. Taille maximale : %s MB.',
        'apiError'     => 'Impossible de poursuivre l’envoi pour le moment.',
        'networkError' => 'Échec de l’envoi. Vérifiez votre connexion et réessayez.',
        'aborted'      => 'L’envoi a été interrompu.',
        'partFailed'   => 'Échec de l’envoi du fichier.',
        'storageError' => 'Le serveur de stockage a refusé l’envoi.',
        'retryFailed'  => 'Impossible de terminer l’envoi. Réessayez.',
        'startFailed'  => 'Impossible de démarrer l’envoi du fichier.',
        'sessionError' => 'Impossible de préparer l’envoi du fichier.',
        'signError'    => 'Impossible de continuer l’envoi. Réessayez.',
        'failed'       => 'L’envoi du fichier a échoué.',
    ],
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
    class="flex flex-col p-3 border rounded-md gap-y-2 border-subtle bg-base"
    data-chunked-audio-root
    data-chunked-audio-config="<?= esc((string) json_encode($payload)) ?>"
>
    <p class="text-xs text-skin-muted">
        Pour les gros fichiers audio (plus de <?= esc(formatBytes($thresholdBytes, true)) ?>), l’envoi se fait automatiquement.
    </p>
    <p class="text-xs text-skin-muted">
        Pendant l’envoi, le bouton d’enregistrement est temporairement bloqué.
    </p>

    <div class="hidden text-xs text-skin-muted" data-chunked-audio-progress-wrap>
        <div class="flex items-center justify-between mb-1">
            <span data-chunked-audio-progress-label>Préparation du fichier…</span>
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

  const defaultMessages = {
    preparing: 'Préparation du fichier…',
    starting: 'Démarrage de l’envoi…',
    uploading: 'Envoi du fichier en cours…',
    completed: 'Fichier envoyé. Enregistrement en cours…',
    submitLocked: 'Envoi en cours…',
    maxSize: 'Fichier trop volumineux. Taille maximale : %s MB.',
    apiError: 'Impossible de poursuivre l’envoi pour le moment.',
    networkError: 'Échec de l’envoi. Vérifiez votre connexion et réessayez.',
    aborted: 'L’envoi a été interrompu.',
    partFailed: 'Échec de l’envoi du fichier.',
    storageError: 'Le serveur de stockage a refusé l’envoi.',
    retryFailed: 'Impossible de terminer l’envoi. Réessayez.',
    startFailed: 'Impossible de démarrer l’envoi du fichier.',
    sessionError: 'Impossible de préparer l’envoi du fichier.',
    signError: 'Impossible de continuer l’envoi. Réessayez.',
    failed: 'L’envoi du fichier a échoué.',
  };
  const messages = Object.assign({}, defaultMessages, config.messages || {});
  const storageKey = `castopod:chunked-audio:${config.podcastId}`;
  let isUploading = false;
  const submitControls = Array.from(document.querySelectorAll('button[type="submit"], input[type="submit"]'))
    .filter((control) => {
      const formAttr = control.getAttribute('form');
      if (formAttr) {
        return form.id !== '' && formAttr === form.id;
      }

      return control.closest('form') === form;
    });

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
      progressLabel.textContent = messages.preparing;
      return;
    }

    progressWrap.classList.remove('hidden');
    progressBar.value = Math.max(0, Math.min(100, percent));
    progressValue.textContent = `${Math.round(percent)}%`;
    if (label) {
      progressLabel.textContent = label;
    }
  }

  function setSubmitControlsLocked(locked) {
    submitControls.forEach((control) => {
      if (!('dataset' in control)) {
        return;
      }

      if (typeof control.dataset.chunkedOriginalDisabled === 'undefined') {
        control.dataset.chunkedOriginalDisabled = control.disabled ? '1' : '0';
      }

      if (locked) {
        if (control instanceof HTMLButtonElement) {
          if (typeof control.dataset.chunkedOriginalLabel === 'undefined') {
            control.dataset.chunkedOriginalLabel = control.innerHTML;
          }
          control.textContent = messages.submitLocked;
        } else if (control instanceof HTMLInputElement && control.type === 'submit') {
          if (typeof control.dataset.chunkedOriginalValue === 'undefined') {
            control.dataset.chunkedOriginalValue = control.value;
          }
          control.value = messages.submitLocked;
        }

        control.disabled = true;
        control.classList.add('opacity-70', 'cursor-not-allowed');
        control.setAttribute('aria-disabled', 'true');
        return;
      }

      const wasDisabled = control.dataset.chunkedOriginalDisabled === '1';
      control.disabled = wasDisabled;
      control.classList.remove('opacity-70', 'cursor-not-allowed');
      control.setAttribute('aria-disabled', wasDisabled ? 'true' : 'false');

      if (control instanceof HTMLButtonElement && typeof control.dataset.chunkedOriginalLabel !== 'undefined') {
        control.innerHTML = control.dataset.chunkedOriginalLabel;
      } else if (
        control instanceof HTMLInputElement &&
        control.type === 'submit' &&
        typeof control.dataset.chunkedOriginalValue !== 'undefined'
      ) {
        control.value = control.dataset.chunkedOriginalValue;
      }
    });
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
      const message = (body && body.error) ? body.error : messages.apiError;
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

      xhr.onerror = () => reject(new Error(messages.networkError));
      xhr.onabort = () => reject(new Error(messages.aborted));

      xhr.onload = () => {
        if (xhr.status < 200 || xhr.status >= 300) {
          reject(new Error(messages.partFailed));
          return;
        }

        const etagRaw = xhr.getResponseHeader('ETag');
        const etag = etagRaw ? etagRaw.replace(/\"/g, '').trim() : '';

        if (!etag) {
          reject(new Error(messages.storageError));
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

    throw new Error(messages.retryFailed);
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
      throw new Error(messages.startFailed);
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
      throw new Error(messages.sessionError);
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
            throw new Error(messages.signError);
          }

          inflightProgress.set(partNumber, 0);
          refreshProgress(messages.uploading);

          const etag = await uploadPartWithProgress(url, blob, (loaded) => {
            inflightProgress.set(partNumber, loaded);
            refreshProgress(messages.uploading);
          });

          inflightProgress.delete(partNumber);
          committedBytes += blob.size;
          completedEtags.set(partNumber, etag);
          refreshProgress(messages.uploading);
        });
      }
    }

    setProgress(true, (committedBytes / file.size) * 100, messages.starting);

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
    setProgress(true, 100, messages.completed);
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
      const maxSizeLabel = (Number(config.maxFileSize || 0) / (1024 * 1024)).toFixed(0);
      setError(messages.maxSize.replace('%s', maxSizeLabel));
      return;
    }

    if (!isChunkedCandidate(file)) {
      return;
    }

    event.preventDefault();
    isUploading = true;
    setSubmitControlsLocked(true);

    try {
      await uploadChunkedAudio(file);
      form.submit();
    } catch (error) {
      setProgress(false);
      setError(error instanceof Error ? error.message : messages.failed);
      setSubmitControlsLocked(false);
    } finally {
      isUploading = false;
    }
  });
})();
</script>
