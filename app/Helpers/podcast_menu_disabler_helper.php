<?php

declare(strict_types=1);

use Modules\Admin\Config\Admin;

if (! function_exists('podcast_menu_disabler_plugin_key')) {
    function podcast_menu_disabler_plugin_key(): string
    {
        return 'iclic-inc/podcast-menu-disabler';
    }
}

if (! function_exists('podcast_menu_disabler_access_targets')) {
    /**
     * @return array<string,array{
     *     menu:string,
     *     item:string|null,
     *     routeNames:list<string>,
     *     pathRegexes:list<string>
     * }>
     */
    function podcast_menu_disabler_access_targets(): array
    {
        return [
            // Dashboard (full menu + subpages)
            'dashboard' => [
                'menu'       => 'dashboard',
                'item'       => null,
                'routeNames' => [
                    'podcast-view',
                    'podcast-edit',
                    'podcast-publish',
                    'podcast-publish_edit',
                    'podcast-publish-cancel',
                    'podcast-banner-delete',
                    'podcast-delete',
                    'podcast-persons-manage',
                    'podcast-person-remove',
                    'podcast-imports',
                    'podcast-imports-sync',
                ],
                'pathRegexes' => [
                    '~^podcasts/\d+$~',
                    '~^podcasts/\d+/edit$~',
                    '~^podcasts/\d+/publish(?:-edit|-cancel)?$~',
                    '~^podcasts/\d+/edit/delete-banner$~',
                    '~^podcasts/\d+/delete$~',
                    '~^podcasts/\d+/persons(?:/\d+/remove)?$~',
                    '~^podcasts/\d+/imports$~',
                    '~^podcasts/\d+/sync-feeds$~',
                ],
            ],
            'podcast-view' => [
                'menu'       => 'dashboard',
                'item'       => 'podcast-view',
                'routeNames' => ['podcast-view'],
                'pathRegexes' => [
                    '~^podcasts/\d+$~',
                ],
            ],
            'podcast-edit' => [
                'menu'       => 'dashboard',
                'item'       => 'podcast-edit',
                'routeNames' => ['podcast-edit', 'podcast-banner-delete'],
                'pathRegexes' => [
                    '~^podcasts/\d+/edit(?:/delete-banner)?$~',
                ],
            ],
            'podcast-persons-manage' => [
                'menu'       => 'dashboard',
                'item'       => 'podcast-persons-manage',
                'routeNames' => ['podcast-persons-manage', 'podcast-person-remove'],
                'pathRegexes' => [
                    '~^podcasts/\d+/persons(?:/\d+/remove)?$~',
                ],
            ],
            'podcast-imports' => [
                'menu'       => 'dashboard',
                'item'       => 'podcast-imports',
                'routeNames' => ['podcast-imports'],
                'pathRegexes' => [
                    '~^podcasts/\d+/imports$~',
                ],
            ],
            'podcast-imports-sync' => [
                'menu'       => 'dashboard',
                'item'       => 'podcast-imports-sync',
                'routeNames' => ['podcast-imports-sync'],
                'pathRegexes' => [
                    '~^podcasts/\d+/sync-feeds$~',
                ],
            ],

            // Episodes (full menu + subpages)
            'episodes' => [
                'menu'       => 'episodes',
                'item'       => null,
                'routeNames' => [
                    'episode-list',
                    'episode-create',
                    'episode-view',
                    'episode-edit',
                    'episode-publish',
                    'episode-publish_edit',
                    'episode-publish-cancel',
                    'episode-publish_date_edit',
                    'episode-unpublish',
                    'episode-delete',
                    'episode-persons-manage',
                    'episode-person-remove',
                    'embed-add',
                    'comment-attempt-create',
                    'comment-attempt-reply',
                    'comment-attempt-delete',
                    'video-clips-list',
                    'video-clips-create',
                    'video-clips-delete',
                    'soundbites-list',
                    'soundbites-create',
                    'soundbites-delete',
                ],
                'pathRegexes' => [
                    '~^podcasts/\d+/episodes(?:/.*)?$~',
                ],
            ],
            'episode-list' => [
                'menu'       => 'episodes',
                'item'       => 'episode-list',
                'routeNames' => ['episode-list'],
                'pathRegexes' => [
                    '~^podcasts/\d+/episodes$~',
                ],
            ],
            'episode-create' => [
                'menu'       => 'episodes',
                'item'       => 'episode-create',
                'routeNames' => ['episode-create'],
                'pathRegexes' => [
                    '~^podcasts/\d+/episodes/new$~',
                ],
            ],

            // Plugins (full menu only)
            'plugins' => [
                'menu'       => 'plugins',
                'item'       => null,
                'routeNames' => [
                    'plugins-settings-podcast',
                    'plugins-settings-podcast-action',
                    'plugins-settings-episode',
                    'plugins-settings-episode-action',
                ],
                'pathRegexes' => [
                    '~^plugins/[^/]+/[^/]+/\d+(?:/\d+)?$~',
                ],
            ],

            // Analytics (full menu + subpages)
            'analytics' => [
                'menu'       => 'analytics',
                'item'       => null,
                'routeNames' => [
                    'podcast-analytics',
                    'podcast-analytics-unique-listeners',
                    'podcast-analytics-listening-time',
                    'podcast-analytics-players',
                    'podcast-analytics-locations',
                    'podcast-analytics-time-periods',
                    'podcast-analytics-webpages',
                ],
                'pathRegexes' => [
                    '~^podcasts/\d+/analytics(?:/.*)?$~',
                ],
            ],
            'podcast-analytics' => [
                'menu'       => 'analytics',
                'item'       => 'podcast-analytics',
                'routeNames' => ['podcast-analytics'],
                'pathRegexes' => [
                    '~^podcasts/\d+/analytics$~',
                ],
            ],
            'podcast-analytics-unique-listeners' => [
                'menu'       => 'analytics',
                'item'       => 'podcast-analytics-unique-listeners',
                'routeNames' => ['podcast-analytics-unique-listeners'],
                'pathRegexes' => [
                    '~^podcasts/\d+/analytics/unique-listeners$~',
                ],
            ],
            'podcast-analytics-listening-time' => [
                'menu'       => 'analytics',
                'item'       => 'podcast-analytics-listening-time',
                'routeNames' => ['podcast-analytics-listening-time'],
                'pathRegexes' => [
                    '~^podcasts/\d+/analytics/listening-time$~',
                ],
            ],
            'podcast-analytics-players' => [
                'menu'       => 'analytics',
                'item'       => 'podcast-analytics-players',
                'routeNames' => ['podcast-analytics-players'],
                'pathRegexes' => [
                    '~^podcasts/\d+/analytics/players$~',
                ],
            ],
            'podcast-analytics-locations' => [
                'menu'       => 'analytics',
                'item'       => 'podcast-analytics-locations',
                'routeNames' => ['podcast-analytics-locations'],
                'pathRegexes' => [
                    '~^podcasts/\d+/analytics/locations$~',
                ],
            ],
            'podcast-analytics-time-periods' => [
                'menu'       => 'analytics',
                'item'       => 'podcast-analytics-time-periods',
                'routeNames' => ['podcast-analytics-time-periods'],
                'pathRegexes' => [
                    '~^podcasts/\d+/analytics/time-periods$~',
                ],
            ],
            'podcast-analytics-webpages' => [
                'menu'       => 'analytics',
                'item'       => 'podcast-analytics-webpages',
                'routeNames' => ['podcast-analytics-webpages'],
                'pathRegexes' => [
                    '~^podcasts/\d+/analytics/webpages$~',
                ],
            ],

            // Broadcast (full menu + subpages)
            'broadcast' => [
                'menu'       => 'broadcast',
                'item'       => null,
                'routeNames' => ['platforms-podcasting', 'platforms-social'],
                'pathRegexes' => [
                    '~^podcasts/\d+/platforms$~',
                    '~^podcasts/\d+/platforms/social$~',
                    '~^podcasts/\d+/platforms/save/(?:podcasting|social)$~',
                    '~^podcasts/\d+/platforms/(?:podcasting|social)/[^/]+/podcast-platform-remove$~',
                ],
            ],
            'platforms-podcasting' => [
                'menu'       => 'broadcast',
                'item'       => 'platforms-podcasting',
                'routeNames' => ['platforms-podcasting'],
                'pathRegexes' => [
                    '~^podcasts/\d+/platforms$~',
                    '~^podcasts/\d+/platforms/save/podcasting$~',
                    '~^podcasts/\d+/platforms/podcasting/[^/]+/podcast-platform-remove$~',
                ],
            ],
            'platforms-social' => [
                'menu'       => 'broadcast',
                'item'       => 'platforms-social',
                'routeNames' => ['platforms-social'],
                'pathRegexes' => [
                    '~^podcasts/\d+/platforms/social$~',
                    '~^podcasts/\d+/platforms/save/social$~',
                    '~^podcasts/\d+/platforms/social/[^/]+/podcast-platform-remove$~',
                ],
            ],

            // Monetization (full menu + subpages)
            'monetization' => [
                'menu'       => 'monetization',
                'item'       => null,
                'routeNames' => [
                    'subscription-list',
                    'subscription-create',
                    'subscription-view',
                    'subscription-edit',
                    'subscription-link-save',
                    'subscription-regenerate-token',
                    'subscription-suspend',
                    'subscription-resume',
                    'subscription-delete',
                    'platforms-funding',
                ],
                'pathRegexes' => [
                    '~^podcasts/\d+/subscriptions(?:/.*)?$~',
                    '~^podcasts/\d+/platforms/funding$~',
                    '~^podcasts/\d+/platforms/save/funding$~',
                    '~^podcasts/\d+/platforms/funding/[^/]+/podcast-platform-remove$~',
                ],
            ],
            'subscription-list' => [
                'menu'       => 'monetization',
                'item'       => 'subscription-list',
                'routeNames' => ['subscription-list'],
                'pathRegexes' => [
                    '~^podcasts/\d+/subscriptions$~',
                ],
            ],
            'subscription-create' => [
                'menu'       => 'monetization',
                'item'       => 'subscription-create',
                'routeNames' => ['subscription-create'],
                'pathRegexes' => [
                    '~^podcasts/\d+/subscriptions/new$~',
                ],
            ],
            'platforms-funding' => [
                'menu'       => 'monetization',
                'item'       => 'platforms-funding',
                'routeNames' => ['platforms-funding'],
                'pathRegexes' => [
                    '~^podcasts/\d+/platforms/funding$~',
                    '~^podcasts/\d+/platforms/save/funding$~',
                    '~^podcasts/\d+/platforms/funding/[^/]+/podcast-platform-remove$~',
                ],
            ],

            // Contributors (full menu + subpages)
            'contributors' => [
                'menu'       => 'contributors',
                'item'       => null,
                'routeNames' => [
                    'contributor-list',
                    'contributor-add',
                    'contributor-view',
                    'contributor-edit',
                    'contributor-remove',
                ],
                'pathRegexes' => [
                    '~^podcasts/\d+/contributors(?:/.*)?$~',
                ],
            ],
            'contributor-list' => [
                'menu'       => 'contributors',
                'item'       => 'contributor-list',
                'routeNames' => ['contributor-list'],
                'pathRegexes' => [
                    '~^podcasts/\d+/contributors$~',
                ],
            ],
            'contributor-add' => [
                'menu'       => 'contributors',
                'item'       => 'contributor-add',
                'routeNames' => ['contributor-add'],
                'pathRegexes' => [
                    '~^podcasts/\d+/contributors/add$~',
                ],
            ],
        ];
    }
}

if (! function_exists('podcast_menu_disabler_sections')) {
    /**
     * @return array<string,array{
     *     menu:string,
     *     routeNames:list<string>,
     *     pathRegexes:list<string>
     * }>
     */
    function podcast_menu_disabler_sections(): array
    {
        $sections = [];

        foreach (podcast_menu_disabler_access_targets() as $key => $target) {
            if ($target['item'] !== null) {
                continue;
            }

            $sections[$key] = [
                'menu'       => $target['menu'],
                'routeNames' => $target['routeNames'],
                'pathRegexes' => $target['pathRegexes'],
            ];
        }

        return $sections;
    }
}

if (! function_exists('podcast_menu_disabler_get_disabled_targets')) {
    /**
     * @return list<string>
     */
    function podcast_menu_disabler_get_disabled_targets(): array
    {
        helper(['auth', 'plugins']);

        $isActive = get_plugin_setting(podcast_menu_disabler_plugin_key(), 'active');
        if (! (bool) $isActive) {
            return [];
        }

        $matrix = podcast_menu_disabler_get_section_role_matrix();
        if ($matrix === []) {
            return [];
        }

        $disabledTargets = [];
        foreach ($matrix as $target => $roles) {
            if (podcast_menu_disabler_roles_match_current_user($roles)) {
                $disabledTargets[] = $target;
            }
        }

        return $disabledTargets;
    }
}

if (! function_exists('podcast_menu_disabler_get_disabled_sections')) {
    /**
     * @return list<string>
     */
    function podcast_menu_disabler_get_disabled_sections(): array
    {
        $targets = podcast_menu_disabler_access_targets();
        $sections = [];

        foreach (podcast_menu_disabler_get_disabled_targets() as $targetKey) {
            $target = $targets[$targetKey] ?? null;
            if ($target === null || $target['item'] !== null) {
                continue;
            }

            $sections[] = $targetKey;
        }

        return $sections;
    }
}

if (! function_exists('podcast_menu_disabler_parse_roles')) {
    /**
     * @return list<string>
     */
    function podcast_menu_disabler_parse_roles(mixed $value): array
    {
        if (is_array($value)) {
            $value = implode(',', array_map(
                static fn (mixed $part): string => is_scalar($part) ? (string) $part : '',
                $value,
            ));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $roles = preg_split('/[\s,;]+/', $value);
        if (! is_array($roles)) {
            return [];
        }

        $roles = array_map(static fn (string $role): string => trim($role), $roles);
        $roles = array_filter($roles, static fn (string $role): bool => $role !== '');

        $seen = [];
        $uniqueRoles = [];
        foreach ($roles as $role) {
            $normalized = strtolower($role);
            if (array_key_exists($normalized, $seen)) {
                continue;
            }

            $seen[$normalized] = true;
            $uniqueRoles[] = $role;
        }

        return $uniqueRoles;
    }
}

if (! function_exists('podcast_menu_disabler_get_role_options')) {
    /**
     * @return list<array{value:string,label:string,description:string}>
     */
    function podcast_menu_disabler_get_role_options(): array
    {
        $options = [[
            'value'       => 'everyone',
            'label'       => 'Everyone',
            'description' => 'Deny access for all users.',
        ]];

        $groups = setting('AuthGroups.groups');
        if (! is_array($groups)) {
            return $options;
        }

        $groupOptions = [];
        foreach ($groups as $groupKey => $groupInfo) {
            if (! is_string($groupKey) || $groupKey === '') {
                continue;
            }

            if (! is_array($groupInfo)) {
                $groupInfo = [];
            }

            $title = $groupInfo['title'] ?? $groupKey;
            if (! is_string($title) || $title === '') {
                $title = $groupKey;
            }

            $description = $groupInfo['description'] ?? '';
            if (! is_string($description)) {
                $description = '';
            }

            $groupOptions[] = [
                'value'       => $groupKey,
                'label'       => $title,
                'description' => $description !== '' ? $description : $groupKey,
            ];
        }

        usort(
            $groupOptions,
            static fn (array $left, array $right): int => strnatcasecmp($left['label'], $right['label']),
        );

        return [...$options, ...$groupOptions];
    }
}

if (! function_exists('podcast_menu_disabler_get_section_label')) {
    function podcast_menu_disabler_get_section_label(string $section): string
    {
        $translationKey = 'PodcastNavigation.' . $section;
        $translated = lang($translationKey);
        if (is_string($translated) && $translated !== $translationKey) {
            return $translated;
        }

        return ucfirst(str_replace(['-', '_'], ' ', $section));
    }
}

if (! function_exists('podcast_menu_disabler_is_everyone_token')) {
    function podcast_menu_disabler_is_everyone_token(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['*', 'all', 'everyone', 'everybody'], true);
    }
}

if (! function_exists('podcast_menu_disabler_roles_match_current_user')) {
    /**
     * @param list<string> $roles
     */
    function podcast_menu_disabler_roles_match_current_user(array $roles): bool
    {
        helper('auth');

        if (! auth()->loggedIn()) {
            return false;
        }

        if ($roles === []) {
            return false;
        }

        foreach ($roles as $role) {
            if (podcast_menu_disabler_is_everyone_token($role)) {
                return true;
            }
        }

        $roles = array_values(array_filter(
            $roles,
            static fn (string $role): bool => ! podcast_menu_disabler_is_everyone_token($role),
        ));

        if ($roles === []) {
            return false;
        }

        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return $user->inGroup(...$roles);
    }
}

if (! function_exists('podcast_menu_disabler_get_section_role_matrix')) {
    /**
     * @return array<string,list<string>>
     */
    function podcast_menu_disabler_get_section_role_matrix(): array
    {
        helper('plugins');

        $value = get_plugin_setting(podcast_menu_disabler_plugin_key(), 'section_role_matrix');
        if (! is_array($value)) {
            return [];
        }

        $matrix = [];
        foreach (array_keys(podcast_menu_disabler_access_targets()) as $section) {
            $roles = podcast_menu_disabler_parse_roles($value[$section] ?? null);
            if ($roles === []) {
                continue;
            }

            $matrix[$section] = $roles;
        }

        return $matrix;
    }
}

if (! function_exists('podcast_menu_disabler_filter_navigation')) {
    /**
     * @param array<string,array<string,mixed>> $navigation
     *
     * @return array<string,array<string,mixed>>
     */
    function podcast_menu_disabler_filter_navigation(array $navigation): array
    {
        $disabledTargets = podcast_menu_disabler_get_disabled_targets();
        if ($disabledTargets === []) {
            return $navigation;
        }

        $targetsConfig = podcast_menu_disabler_access_targets();

        foreach ($disabledTargets as $targetKey) {
            $target = $targetsConfig[$targetKey] ?? null;
            if ($target === null || $target['item'] !== null) {
                continue;
            }

            unset($navigation[$target['menu']]);
        }

        foreach ($disabledTargets as $targetKey) {
            $target = $targetsConfig[$targetKey] ?? null;
            if ($target === null || $target['item'] === null) {
                continue;
            }

            $menu = $target['menu'];
            $item = $target['item'];
            if (! isset($navigation[$menu]) || ! is_array($navigation[$menu])) {
                continue;
            }

            if (isset($navigation[$menu]['items']) && is_array($navigation[$menu]['items'])) {
                $navigation[$menu]['items'] = array_values(array_filter(
                    $navigation[$menu]['items'],
                    static fn (mixed $menuItem): bool => $menuItem !== $item,
                ));
            }

            if (isset($navigation[$menu]['items-permissions']) && is_array($navigation[$menu]['items-permissions'])) {
                unset($navigation[$menu]['items-permissions'][$item]);
            }

            if (isset($navigation[$menu]['items-labels']) && is_array($navigation[$menu]['items-labels'])) {
                unset($navigation[$menu]['items-labels'][$item]);
            }

            if (array_key_exists('add-cta', $navigation[$menu]) && $navigation[$menu]['add-cta'] === $item) {
                unset($navigation[$menu]['add-cta']);
            }

            if (array_key_exists('count-route', $navigation[$menu]) && $navigation[$menu]['count-route'] === $item) {
                if (isset($navigation[$menu]['items']) && is_array($navigation[$menu]['items']) && $navigation[$menu]['items'] !== []) {
                    $navigation[$menu]['count-route'] = (string) $navigation[$menu]['items'][0];
                } else {
                    unset($navigation[$menu]['count-route'], $navigation[$menu]['count']);
                }
            }
        }

        return $navigation;
    }
}

if (! function_exists('podcast_menu_disabler_is_self_settings_path')) {
    function podcast_menu_disabler_is_self_settings_path(string $adminSubPath): bool
    {
        $selfPluginPath = 'plugins/' . podcast_menu_disabler_plugin_key();

        return $adminSubPath === $selfPluginPath
            || str_starts_with($adminSubPath, $selfPluginPath . '/settings');
    }
}

if (! function_exists('podcast_menu_disabler_get_admin_subpath')) {
    function podcast_menu_disabler_get_admin_subpath(string $requestPath): ?string
    {
        $adminGateway = trim(config(Admin::class)->gateway, '/');
        $path = trim($requestPath, '/');

        if ($path === $adminGateway) {
            return '';
        }

        if (! str_starts_with($path, $adminGateway . '/')) {
            return null;
        }

        return (string) substr($path, strlen($adminGateway) + 1);
    }
}

if (! function_exists('podcast_menu_disabler_is_disabled_request')) {
    function podcast_menu_disabler_is_disabled_request(?string $routeName, string $requestPath): bool
    {
        $adminSubPath = podcast_menu_disabler_get_admin_subpath($requestPath);
        if ($adminSubPath === null) {
            return false;
        }

        if (podcast_menu_disabler_is_self_settings_path($adminSubPath)) {
            return false;
        }

        $targetsConfig = podcast_menu_disabler_access_targets();
        foreach (podcast_menu_disabler_get_disabled_targets() as $target) {
            $config = $targetsConfig[$target] ?? null;
            if ($config === null) {
                continue;
            }

            if ($routeName !== null && in_array($routeName, $config['routeNames'], true)) {
                return true;
            }

            foreach ($config['pathRegexes'] as $pathRegex) {
                if (preg_match($pathRegex, $adminSubPath) === 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
