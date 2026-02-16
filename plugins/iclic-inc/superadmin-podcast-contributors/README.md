# Superadmin Podcast Contributors

Automatically keeps all `superadmin` users as contributors on every podcast.

## What it does

- Adds missing `superadmin` users to all podcasts.
- Runs automatically on admin requests while the plugin is active.
- Never removes existing contributor memberships.
- Preserves existing podcast-specific roles for already-linked users.

## Settings

- `Podcast role assigned to superadmins`: role used for missing links (default fallback: `admin`).
- `Sync interval (seconds)`: minimum delay between sync runs (default: `300`).

## How to use

1. Activate the plugin in `Admin > Plugins`.
2. (Optional) choose the podcast role and sync interval.
3. Save plugin settings.
4. Browse any admin page once to trigger sync.
