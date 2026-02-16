<?php

declare(strict_types=1);

use Modules\Admin\Config\Admin;

if (! function_exists('admin_menu_disabler_plugin_key')) {
    function admin_menu_disabler_plugin_key(): string
    {
        return 'iclic-inc/admin-menu-disabler';
    }
}

if (! function_exists('admin_menu_disabler_sections')) {
    /**
     * @return array<string,array{
     *     menu:string,
     *     routeNames:list<string>,
     *     pathPrefixes:list<string>,
     *     exactPaths:list<string>
     * }>
     */
    function admin_menu_disabler_sections(): array
    {
        return [
            'dashboard' => [
                'menu'        => 'dashboard',
                'routeNames'  => ['admin'],
                'pathPrefixes' => [],
                'exactPaths'  => [''],
            ],
            'podcasts' => [
                'menu'        => 'podcasts',
                'routeNames'  => [
                    'podcast-list',
                    'podcast-create',
                    'all-podcast-imports',
                    'podcast-imports-add',
                    'podcast-imports',
                    'podcast-imports-sync',
                ],
                'pathPrefixes' => ['podcasts', 'imports'],
                'exactPaths'  => [],
            ],
            'plugins' => [
                'menu'        => 'plugins',
                'routeNames'  => ['plugins-installed'],
                'pathPrefixes' => ['plugins'],
                'exactPaths'  => [],
            ],
            'persons' => [
                'menu'        => 'persons',
                'routeNames'  => ['person-list', 'person-create'],
                'pathPrefixes' => ['persons'],
                'exactPaths'  => [],
            ],
            'fediverse' => [
                'menu'        => 'fediverse',
                'routeNames'  => ['fediverse-dashboard', 'fediverse-blocked-actors', 'fediverse-blocked-domains'],
                'pathPrefixes' => ['fediverse'],
                'exactPaths'  => [],
            ],
            'users' => [
                'menu'        => 'users',
                'routeNames'  => ['user-list', 'user-create'],
                'pathPrefixes' => ['users'],
                'exactPaths'  => [],
            ],
            'pages' => [
                'menu'        => 'pages',
                'routeNames'  => ['page-list', 'page-create'],
                'pathPrefixes' => ['pages'],
                'exactPaths'  => [],
            ],
            'settings' => [
                'menu'        => 'settings',
                'routeNames'  => ['settings-general', 'settings-theme', 'admin-about'],
                'pathPrefixes' => ['settings'],
                'exactPaths'  => ['about', 'update'],
            ],
        ];
    }
}

if (! function_exists('admin_menu_disabler_get_disabled_sections')) {
    /**
     * @return list<string>
     */
    function admin_menu_disabler_get_disabled_sections(): array
    {
        helper(['auth', 'plugins']);

        $pluginKey = admin_menu_disabler_plugin_key();
        $isActive = get_plugin_setting($pluginKey, 'active');
        if (! (bool) $isActive) {
            return [];
        }

        $matrix = admin_menu_disabler_get_section_role_matrix();
        if ($matrix !== []) {
            $disabledSections = [];
            foreach ($matrix as $section => $roles) {
                if (admin_menu_disabler_roles_match_current_user($roles)) {
                    $disabledSections[] = $section;
                }
            }

            return $disabledSections;
        }
        return [];
    }
}

if (! function_exists('admin_menu_disabler_parse_roles')) {
    /**
     * @return list<string>
     */
    function admin_menu_disabler_parse_roles(mixed $value): array
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

if (! function_exists('admin_menu_disabler_get_role_options')) {
    /**
     * @return list<array{value:string,label:string,description:string}>
     */
    function admin_menu_disabler_get_role_options(): array
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

if (! function_exists('admin_menu_disabler_get_section_label')) {
    function admin_menu_disabler_get_section_label(string $section): string
    {
        $translationKey = 'Navigation.' . $section;
        $translated = lang($translationKey);
        if (is_string($translated) && $translated !== $translationKey) {
            return $translated;
        }

        return ucfirst(str_replace(['-', '_'], ' ', $section));
    }
}

if (! function_exists('admin_menu_disabler_is_everyone_token')) {
    function admin_menu_disabler_is_everyone_token(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['*', 'all', 'everyone', 'everybody'], true);
    }
}

if (! function_exists('admin_menu_disabler_roles_match_current_user')) {
    /**
     * @param list<string> $roles
     */
    function admin_menu_disabler_roles_match_current_user(array $roles): bool
    {
        helper('auth');

        if (! auth()->loggedIn()) {
            return false;
        }

        if ($roles === []) {
            return false;
        }

        foreach ($roles as $role) {
            if (admin_menu_disabler_is_everyone_token($role)) {
                return true;
            }
        }

        $roles = array_values(array_filter(
            $roles,
            static fn (string $role): bool => ! admin_menu_disabler_is_everyone_token($role),
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

if (! function_exists('admin_menu_disabler_get_section_role_matrix')) {
    /**
     * @return array<string,list<string>>
     */
    function admin_menu_disabler_get_section_role_matrix(): array
    {
        helper('plugins');

        $value = get_plugin_setting(admin_menu_disabler_plugin_key(), 'section_role_matrix');
        if (! is_array($value)) {
            return [];
        }

        $matrix = [];
        foreach (array_keys(admin_menu_disabler_sections()) as $section) {
            $roles = admin_menu_disabler_parse_roles($value[$section] ?? null);
            if ($roles === []) {
                continue;
            }

            $matrix[$section] = $roles;
        }

        return $matrix;
    }
}

if (! function_exists('admin_menu_disabler_filter_navigation')) {
    /**
     * @param array<string,array<string,mixed>> $navigation
     *
     * @return array<string,array<string,mixed>>
     */
    function admin_menu_disabler_filter_navigation(array $navigation): array
    {
        foreach (admin_menu_disabler_get_disabled_sections() as $section) {
            $menuSection = admin_menu_disabler_sections()[$section]['menu'] ?? null;
            if ($menuSection === null) {
                continue;
            }

            unset($navigation[$menuSection]);
        }

        return $navigation;
    }
}

if (! function_exists('admin_menu_disabler_is_self_settings_path')) {
    function admin_menu_disabler_is_self_settings_path(string $adminSubPath): bool
    {
        $selfPluginPath = 'plugins/' . admin_menu_disabler_plugin_key();

        return $adminSubPath === $selfPluginPath
            || str_starts_with($adminSubPath, $selfPluginPath . '/settings');
    }
}

if (! function_exists('admin_menu_disabler_get_admin_subpath')) {
    function admin_menu_disabler_get_admin_subpath(string $requestPath): ?string
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

if (! function_exists('admin_menu_disabler_is_disabled_request')) {
    function admin_menu_disabler_is_disabled_request(?string $routeName, string $requestPath): bool
    {
        $adminSubPath = admin_menu_disabler_get_admin_subpath($requestPath);
        if ($adminSubPath === null) {
            return false;
        }

        if (admin_menu_disabler_is_self_settings_path($adminSubPath)) {
            return false;
        }

        $sectionsConfig = admin_menu_disabler_sections();
        foreach (admin_menu_disabler_get_disabled_sections() as $section) {
            $config = $sectionsConfig[$section] ?? null;
            if ($config === null) {
                continue;
            }

            if ($routeName !== null && in_array($routeName, $config['routeNames'], true)) {
                return true;
            }

            foreach ($config['exactPaths'] as $exactPath) {
                if ($adminSubPath === $exactPath) {
                    return true;
                }
            }

            foreach ($config['pathPrefixes'] as $prefix) {
                if ($adminSubPath === $prefix || str_starts_with($adminSubPath, $prefix . '/')) {
                    return true;
                }
            }
        }

        return false;
    }
}
