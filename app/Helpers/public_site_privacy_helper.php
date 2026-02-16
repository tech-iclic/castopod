<?php

declare(strict_types=1);

use Modules\Admin\Config\Admin;
use Modules\Auth\Config\Auth;
use Modules\Install\Config\Install;

if (! function_exists('public_site_privacy_plugin_key')) {
    function public_site_privacy_plugin_key(): string
    {
        return 'iclic-inc/public-site-privacy';
    }
}

if (! function_exists('public_site_privacy_is_enabled')) {
    function public_site_privacy_is_enabled(): bool
    {
        helper('plugins');

        return (bool) get_plugin_setting(public_site_privacy_plugin_key(), 'active');
    }
}

if (! function_exists('public_site_privacy_is_home_redirect_enabled')) {
    function public_site_privacy_is_home_redirect_enabled(): bool
    {
        helper('plugins');

        return (bool) get_plugin_setting(public_site_privacy_plugin_key(), 'redirect_home_to_admin');
    }
}

if (! function_exists('public_site_privacy_is_podcast_pages_blocked')) {
    function public_site_privacy_is_podcast_pages_blocked(): bool
    {
        helper('plugins');

        return (bool) get_plugin_setting(public_site_privacy_plugin_key(), 'block_public_podcast_pages');
    }
}

if (! function_exists('public_site_privacy_normalize_path')) {
    function public_site_privacy_normalize_path(string $requestPath): string
    {
        return trim($requestPath, '/');
    }
}

if (! function_exists('public_site_privacy_path_matches_gateway')) {
    function public_site_privacy_path_matches_gateway(string $path, string $gateway): bool
    {
        if ($gateway === '') {
            return false;
        }

        return $path === $gateway || str_starts_with($path, $gateway . '/');
    }
}

if (! function_exists('public_site_privacy_is_internal_path')) {
    function public_site_privacy_is_internal_path(string $path): bool
    {
        $adminGateway = trim(config(Admin::class)->gateway, '/');
        if (public_site_privacy_path_matches_gateway($path, $adminGateway)) {
            return true;
        }

        $authGateway = trim(config(Auth::class)->gateway, '/');
        if (public_site_privacy_path_matches_gateway($path, $authGateway)) {
            return true;
        }

        $installGateway = trim(config(Install::class)->gateway, '/');

        return public_site_privacy_path_matches_gateway($path, $installGateway);
    }
}

if (! function_exists('public_site_privacy_is_podcast_page_path')) {
    function public_site_privacy_is_podcast_page_path(string $path): bool
    {
        return preg_match('~^@[a-zA-Z0-9_]{1,32}(?:/.*)?$~', $path) === 1;
    }
}

if (! function_exists('public_site_privacy_is_allowed_public_podcast_path')) {
    function public_site_privacy_is_allowed_public_podcast_path(string $path): bool
    {
        return preg_match('~^@[a-zA-Z0-9_]{1,32}/feed(?:\\.xml)?$~', $path) === 1;
    }
}

if (! function_exists('public_site_privacy_should_redirect')) {
    function public_site_privacy_should_redirect(string $requestPath): bool
    {
        if (! public_site_privacy_is_enabled()) {
            return false;
        }

        $path = public_site_privacy_normalize_path($requestPath);
        if (public_site_privacy_is_internal_path($path)) {
            return false;
        }

        if ($path === '' && public_site_privacy_is_home_redirect_enabled()) {
            return true;
        }

        if (public_site_privacy_is_allowed_public_podcast_path($path)) {
            return false;
        }

        if (public_site_privacy_is_podcast_pages_blocked() && public_site_privacy_is_podcast_page_path($path)) {
            return true;
        }

        return false;
    }
}

if (! function_exists('public_site_privacy_get_admin_url')) {
    function public_site_privacy_get_admin_url(): string
    {
        $adminRoute = route_to('admin');
        if (is_string($adminRoute) && $adminRoute !== '') {
            return $adminRoute;
        }

        return '/' . trim(config(Admin::class)->gateway, '/');
    }
}
