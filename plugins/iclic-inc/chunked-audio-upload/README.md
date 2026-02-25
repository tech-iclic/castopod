# Chunked Audio Upload

Enable resumable multipart upload for large episode audio files on the admin episode create/edit pages.

## What it does

- Uploads `.mp3` and `.m4a` files directly from browser to S3 with multipart uploads.
- Uploads parts in parallel with retries and resume support.
- Avoids reverse-proxy/CDN body-size limits by removing large payloads from Castopod form POSTs.
- Keeps final episode save flow unchanged (Castopod still receives a normal episode form submission).

## Requirements

- Castopod media storage set to S3 (`CP_MEDIA_FILE_MANAGER=s3`).
- Plugin activated in `Admin > Plugins`.
- S3 bucket CORS configured for browser `PUT` multipart part uploads.
- Lifecycle policy recommended for multipart safety and cleanup.

## S3 CORS (example)

```json
[
  {
    "AllowedHeaders": ["*"],
    "AllowedMethods": ["PUT", "GET", "HEAD"],
    "AllowedOrigins": ["https://studio.soneo.ca"],
    "ExposeHeaders": ["ETag"],
    "MaxAgeSeconds": 3000
  }
]
```

## Recommended S3 lifecycle

- `AbortIncompleteMultipartUpload` after 1 day.
- Expire temporary objects under `tmp/chunked-audio/` (or your configured prefix).

## Security model

- Endpoints restricted to authenticated admin users with episode-create permission on the target podcast.
- Endpoints accept only same-origin AJAX requests.
- Temporary upload sessions are bound to user and podcast.

## Operational notes

- Upload sessions are resumable for the configured `session_ttl_minutes`.
- After successful episode save, temporary multipart object is cleaned up by the plugin.
- If a submission fails validation, the completed upload session can be reused.

## References (official docs)

- https://docs.aws.amazon.com/AmazonS3/latest/userguide/mpuoverview.html
- https://docs.aws.amazon.com/AmazonS3/latest/userguide/PresignedUrlUploadObject.html
- https://docs.aws.amazon.com/AmazonS3/latest/userguide/using-presigned-url.html
- https://docs.aws.amazon.com/AmazonS3/latest/userguide/cors.html
- https://docs.aws.amazon.com/AmazonS3/latest/userguide/mpu-abort-incomplete-mpu-lifecycle-config.html
