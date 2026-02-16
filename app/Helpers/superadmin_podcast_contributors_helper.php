<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseConnection;
use Modules\Auth\Config\AuthGroups;

if (! function_exists('superadmin_podcast_contributors_plugin_key')) {
    function superadmin_podcast_contributors_plugin_key(): string
    {
        return 'iclic-inc/superadmin-podcast-contributors';
    }
}

if (! function_exists('superadmin_podcast_contributors_is_enabled')) {
    function superadmin_podcast_contributors_is_enabled(): bool
    {
        helper('plugins');

        return (bool) get_plugin_setting(superadmin_podcast_contributors_plugin_key(), 'active');
    }
}

if (! function_exists('superadmin_podcast_contributors_get_target_group')) {
    function superadmin_podcast_contributors_get_target_group(): string
    {
        helper('plugins');

        $authGroups = config(AuthGroups::class);
        $fallbackGroup = $authGroups->mostPowerfulPodcastGroup;

        $value = get_plugin_setting(superadmin_podcast_contributors_plugin_key(), 'podcast_group');
        if (! is_string($value) || trim($value) === '') {
            return $fallbackGroup;
        }

        $candidateGroup = strtolower(trim($value));
        $allowedGroups = setting('AuthGroups.podcastBaseGroups');
        if (! is_array($allowedGroups)) {
            return $fallbackGroup;
        }

        foreach ($allowedGroups as $allowedGroup) {
            if (! is_string($allowedGroup)) {
                continue;
            }

            if ($candidateGroup === strtolower($allowedGroup)) {
                return $allowedGroup;
            }
        }

        return $fallbackGroup;
    }
}

if (! function_exists('superadmin_podcast_contributors_get_sync_interval_seconds')) {
    function superadmin_podcast_contributors_get_sync_interval_seconds(): int
    {
        helper('plugins');

        $value = get_plugin_setting(superadmin_podcast_contributors_plugin_key(), 'sync_interval_seconds');
        if (! is_numeric($value)) {
            return 300;
        }

        $seconds = (int) $value;

        return max(10, min($seconds, 86_400));
    }
}

if (! function_exists('superadmin_podcast_contributors_sync_cache_key')) {
    function superadmin_podcast_contributors_sync_cache_key(): string
    {
        return 'plugin#iclic-inc_superadmin-podcast-contributors#last-sync';
    }
}

if (! function_exists('superadmin_podcast_contributors_should_sync_now')) {
    function superadmin_podcast_contributors_should_sync_now(): bool
    {
        $cacheValue = cache(superadmin_podcast_contributors_sync_cache_key());
        if (! is_numeric($cacheValue)) {
            return true;
        }

        $lastSync = (int) $cacheValue;
        $secondsSinceLastSync = time() - $lastSync;

        return $secondsSinceLastSync >= superadmin_podcast_contributors_get_sync_interval_seconds();
    }
}

if (! function_exists('superadmin_podcast_contributors_get_superadmin_user_ids')) {
    /**
     * @return list<int>
     */
    function superadmin_podcast_contributors_get_superadmin_user_ids(BaseConnection $db): array
    {
        $superadminGroup = config(AuthGroups::class)->mostPowerfulGroup;

        $rows = $db->table('auth_groups_users')
            ->select('auth_groups_users.user_id')
            ->join('users', 'users.id = auth_groups_users.user_id')
            ->where('auth_groups_users.group', $superadminGroup)
            ->where('users.deleted_at', null)
            ->distinct()
            ->get()
            ->getResultArray();

        $ids = [];
        foreach ($rows as $row) {
            $userId = $row['user_id'] ?? null;
            if (is_numeric($userId)) {
                $ids[] = (int) $userId;
            }
        }

        return array_values(array_unique($ids));
    }
}

if (! function_exists('superadmin_podcast_contributors_get_podcast_ids')) {
    /**
     * @return list<int>
     */
    function superadmin_podcast_contributors_get_podcast_ids(BaseConnection $db): array
    {
        $rows = $db->table('podcasts')
            ->select('id')
            ->get()
            ->getResultArray();

        $ids = [];
        foreach ($rows as $row) {
            $podcastId = $row['id'] ?? null;
            if (is_numeric($podcastId)) {
                $ids[] = (int) $podcastId;
            }
        }

        return array_values(array_unique($ids));
    }
}

if (! function_exists('superadmin_podcast_contributors_get_existing_memberships')) {
    /**
     * @param list<int> $superadminUserIds
     *
     * @return array<string,true>
     */
    function superadmin_podcast_contributors_get_existing_memberships(
        BaseConnection $db,
        array $superadminUserIds,
    ): array {
        if ($superadminUserIds === []) {
            return [];
        }

        $rows = $db->table('auth_groups_users')
            ->select('user_id, group')
            ->whereIn('user_id', $superadminUserIds)
            ->like('group', 'podcast#', 'after')
            ->get()
            ->getResultArray();

        $existing = [];
        foreach ($rows as $row) {
            $userId = $row['user_id'] ?? null;
            $group = $row['group'] ?? null;
            if (! is_numeric($userId) || ! is_string($group)) {
                continue;
            }

            $matches = [];
            if (preg_match('~^podcast#(\d+)-.+$~', $group, $matches) !== 1) {
                continue;
            }

            $existing[(int) $userId . ':' . (int) $matches[1]] = true;
        }

        return $existing;
    }
}

if (! function_exists('superadmin_podcast_contributors_sync')) {
    function superadmin_podcast_contributors_sync(): void
    {
        if (! superadmin_podcast_contributors_is_enabled()) {
            return;
        }

        if (! superadmin_podcast_contributors_should_sync_now()) {
            return;
        }

        $db = db_connect();

        $superadminUserIds = superadmin_podcast_contributors_get_superadmin_user_ids($db);
        $podcastIds = superadmin_podcast_contributors_get_podcast_ids($db);

        if ($superadminUserIds === [] || $podcastIds === []) {
            cache()->save(
                superadmin_podcast_contributors_sync_cache_key(),
                time(),
                superadmin_podcast_contributors_get_sync_interval_seconds(),
            );
            return;
        }

        $existingMemberships = superadmin_podcast_contributors_get_existing_memberships($db, $superadminUserIds);
        $targetGroup = superadmin_podcast_contributors_get_target_group();

        $rowsToInsert = [];
        $updatedUserIds = [];
        $updatedPodcastIds = [];

        foreach ($superadminUserIds as $superadminUserId) {
            foreach ($podcastIds as $podcastId) {
                $key = $superadminUserId . ':' . $podcastId;
                if (array_key_exists($key, $existingMemberships)) {
                    continue;
                }

                $rowsToInsert[] = [
                    'user_id' => $superadminUserId,
                    'group'   => "podcast#{$podcastId}-{$targetGroup}",
                ];

                $updatedUserIds[$superadminUserId] = true;
                $updatedPodcastIds[$podcastId] = true;

                if (count($rowsToInsert) >= 500) {
                    $db->table('auth_groups_users')
                        ->ignore(true)
                        ->insertBatch($rowsToInsert);
                    $rowsToInsert = [];
                }
            }
        }

        if ($rowsToInsert !== []) {
            $db->table('auth_groups_users')
                ->ignore(true)
                ->insertBatch($rowsToInsert);
        }

        foreach (array_keys($updatedUserIds) as $updatedUserId) {
            cache()->delete("user{$updatedUserId}_podcasts");
        }

        foreach (array_keys($updatedPodcastIds) as $updatedPodcastId) {
            cache()->delete("podcast#{$updatedPodcastId}_contributors");
        }

        cache()->save(
            superadmin_podcast_contributors_sync_cache_key(),
            time(),
            superadmin_podcast_contributors_get_sync_interval_seconds(),
        );
    }
}
