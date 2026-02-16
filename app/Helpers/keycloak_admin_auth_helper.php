<?php

declare(strict_types=1);

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Admin\Config\Admin;
use Modules\Auth\Config\Auth as AuthConfig;
use Modules\Auth\Models\UserModel;
use Throwable;

if (! function_exists('keycloak_admin_auth_plugin_key')) {
    function keycloak_admin_auth_plugin_key(): string
    {
        return 'iclic-inc/keycloak-admin-auth';
    }
}

if (! function_exists('keycloak_admin_auth_state_session_key')) {
    function keycloak_admin_auth_state_session_key(): string
    {
        return 'keycloak_admin_auth.oidc_state';
    }
}

if (! function_exists('keycloak_admin_auth_id_token_session_key')) {
    function keycloak_admin_auth_id_token_session_key(): string
    {
        return 'keycloak_admin_auth.id_token';
    }
}

if (! function_exists('keycloak_admin_auth_is_enabled')) {
    function keycloak_admin_auth_is_enabled(): bool
    {
        helper('plugins');

        return (bool) get_plugin_setting(keycloak_admin_auth_plugin_key(), 'active');
    }
}

if (! function_exists('keycloak_admin_auth_get_setting_string')) {
    function keycloak_admin_auth_get_setting_string(string $name, string $default = ''): string
    {
        helper('plugins');

        $value = get_plugin_setting(keycloak_admin_auth_plugin_key(), $name);
        if (is_string($value)) {
            return trim($value);
        }

        if (is_numeric($value)) {
            return trim((string) $value);
        }

        return $default;
    }
}

if (! function_exists('keycloak_admin_auth_get_setting_bool')) {
    function keycloak_admin_auth_get_setting_bool(string $name, bool $default = false): bool
    {
        helper('plugins');

        $value = get_plugin_setting(keycloak_admin_auth_plugin_key(), $name);

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return $default;
    }
}

if (! function_exists('keycloak_admin_auth_parse_scalar_list')) {
    /**
     * @return list<string>
     */
    function keycloak_admin_auth_parse_scalar_list(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $parts = [];

        if (is_array($value)) {
            foreach ($value as $entry) {
                if (is_scalar($entry)) {
                    $parts[] = (string) $entry;
                }
            }
        } elseif (is_scalar($value)) {
            $parts[] = (string) $value;
        }

        if ($parts === []) {
            return [];
        }

        $merged = implode("\n", $parts);
        if (trim($merged) === '') {
            return [];
        }

        $tokens = preg_split('/[\s,;]+/', $merged);
        if (! is_array($tokens)) {
            return [];
        }

        return keycloak_admin_auth_unique_non_empty($tokens);
    }
}

if (! function_exists('keycloak_admin_auth_unique_non_empty')) {
    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    function keycloak_admin_auth_unique_non_empty(array $values): array
    {
        $result = [];
        $seen = [];

        foreach ($values as $value) {
            $clean = trim($value);
            if ($clean === '') {
                continue;
            }

            $key = keycloak_admin_auth_normalize_token($clean);
            if ($key === '') {
                continue;
            }

            if (array_key_exists($key, $seen)) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $clean;
        }

        return $result;
    }
}

if (! function_exists('keycloak_admin_auth_get_issuer_url')) {
    function keycloak_admin_auth_get_issuer_url(): string
    {
        return rtrim(keycloak_admin_auth_get_setting_string('issuer_url'), '/');
    }
}

if (! function_exists('keycloak_admin_auth_get_client_id')) {
    function keycloak_admin_auth_get_client_id(): string
    {
        return keycloak_admin_auth_get_setting_string('client_id');
    }
}

if (! function_exists('keycloak_admin_auth_get_client_secret')) {
    function keycloak_admin_auth_get_client_secret(): string
    {
        return keycloak_admin_auth_get_setting_string('client_secret');
    }
}

if (! function_exists('keycloak_admin_auth_get_scopes')) {
    function keycloak_admin_auth_get_scopes(): string
    {
        $scopes = keycloak_admin_auth_get_setting_string('scopes', 'openid profile email');

        return $scopes !== '' ? $scopes : 'openid profile email';
    }
}

if (! function_exists('keycloak_admin_auth_get_allowed_roles')) {
    /**
     * @return list<string>
     */
    function keycloak_admin_auth_get_allowed_roles(): array
    {
        return keycloak_admin_auth_parse_scalar_list(keycloak_admin_auth_get_setting_string('allowed_roles'));
    }
}

if (! function_exists('keycloak_admin_auth_get_superadmin_role_tokens')) {
    /**
     * @return list<string>
     */
    function keycloak_admin_auth_get_superadmin_role_tokens(): array
    {
        $raw = keycloak_admin_auth_get_setting_string(
            'superadmin_roles',
            'ROLE_SUPERADMIN, ROLE_ADMIN, superadmin, admin',
        );

        return keycloak_admin_auth_parse_scalar_list($raw);
    }
}

if (! function_exists('keycloak_admin_auth_get_manager_role_tokens')) {
    /**
     * @return list<string>
     */
    function keycloak_admin_auth_get_manager_role_tokens(): array
    {
        $raw = keycloak_admin_auth_get_setting_string('manager_roles', 'ROLE_MANAGER, manager');

        return keycloak_admin_auth_parse_scalar_list($raw);
    }
}

if (! function_exists('keycloak_admin_auth_get_podcaster_role_tokens')) {
    /**
     * @return list<string>
     */
    function keycloak_admin_auth_get_podcaster_role_tokens(): array
    {
        $raw = keycloak_admin_auth_get_setting_string('podcaster_roles', 'ROLE_PODCASTER, podcaster');

        return keycloak_admin_auth_parse_scalar_list($raw);
    }
}

if (! function_exists('keycloak_admin_auth_is_ready')) {
    function keycloak_admin_auth_is_ready(): bool
    {
        return keycloak_admin_auth_get_issuer_url() !== ''
            && keycloak_admin_auth_get_client_id() !== ''
            && keycloak_admin_auth_get_client_secret() !== '';
    }
}

if (! function_exists('keycloak_admin_auth_get_authorization_endpoint')) {
    function keycloak_admin_auth_get_authorization_endpoint(): string
    {
        $issuer = keycloak_admin_auth_get_issuer_url();
        if ($issuer === '') {
            return '';
        }

        return $issuer . '/protocol/openid-connect/auth';
    }
}

if (! function_exists('keycloak_admin_auth_get_token_endpoint')) {
    function keycloak_admin_auth_get_token_endpoint(): string
    {
        $issuer = keycloak_admin_auth_get_issuer_url();
        if ($issuer === '') {
            return '';
        }

        return $issuer . '/protocol/openid-connect/token';
    }
}

if (! function_exists('keycloak_admin_auth_get_userinfo_endpoint')) {
    function keycloak_admin_auth_get_userinfo_endpoint(): string
    {
        $issuer = keycloak_admin_auth_get_issuer_url();
        if ($issuer === '') {
            return '';
        }

        return $issuer . '/protocol/openid-connect/userinfo';
    }
}

if (! function_exists('keycloak_admin_auth_get_end_session_endpoint')) {
    function keycloak_admin_auth_get_end_session_endpoint(): string
    {
        $issuer = keycloak_admin_auth_get_issuer_url();
        if ($issuer === '') {
            return '';
        }

        return $issuer . '/protocol/openid-connect/logout';
    }
}

if (! function_exists('keycloak_admin_auth_get_admin_gateway')) {
    function keycloak_admin_auth_get_admin_gateway(): string
    {
        return trim(config(Admin::class)->gateway, '/');
    }
}

if (! function_exists('keycloak_admin_auth_get_auth_gateway')) {
    function keycloak_admin_auth_get_auth_gateway(): string
    {
        return trim(config(AuthConfig::class)->gateway, '/');
    }
}

if (! function_exists('keycloak_admin_auth_normalize_path')) {
    function keycloak_admin_auth_normalize_path(string $path): string
    {
        return trim($path, '/');
    }
}

if (! function_exists('keycloak_admin_auth_path_matches_gateway')) {
    function keycloak_admin_auth_path_matches_gateway(string $path, string $gateway): bool
    {
        if ($gateway === '') {
            return false;
        }

        return $path === $gateway || str_starts_with($path, $gateway . '/');
    }
}

if (! function_exists('keycloak_admin_auth_is_admin_or_auth_path')) {
    function keycloak_admin_auth_is_admin_or_auth_path(string $path): bool
    {
        return keycloak_admin_auth_path_matches_gateway($path, keycloak_admin_auth_get_admin_gateway())
            || keycloak_admin_auth_path_matches_gateway($path, keycloak_admin_auth_get_auth_gateway());
    }
}

if (! function_exists('keycloak_admin_auth_is_login_path')) {
    function keycloak_admin_auth_is_login_path(string $path): bool
    {
        return $path === keycloak_admin_auth_get_auth_gateway() . '/login';
    }
}

if (! function_exists('keycloak_admin_auth_is_logout_path')) {
    function keycloak_admin_auth_is_logout_path(string $path): bool
    {
        return $path === keycloak_admin_auth_get_auth_gateway() . '/logout';
    }
}

if (! function_exists('keycloak_admin_auth_get_query_params')) {
    /**
     * @return array<string, mixed>
     */
    function keycloak_admin_auth_get_query_params(RequestInterface $request): array
    {
        $queryString = (string) $request->getUri()->getQuery();
        if ($queryString === '') {
            return [];
        }

        $params = [];
        parse_str($queryString, $params);

        return is_array($params) ? $params : [];
    }
}

if (! function_exists('keycloak_admin_auth_get_query_string')) {
    function keycloak_admin_auth_get_query_string(RequestInterface $request, string $name): string
    {
        $params = keycloak_admin_auth_get_query_params($request);
        if (! array_key_exists($name, $params)) {
            return '';
        }

        $value = $params[$name];
        if (! is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }
}

if (! function_exists('keycloak_admin_auth_is_callback_request')) {
    function keycloak_admin_auth_is_callback_request(RequestInterface $request, string $path): bool
    {
        if (! keycloak_admin_auth_is_login_path($path)) {
            return false;
        }

        $params = keycloak_admin_auth_get_query_params($request);

        return array_key_exists('code', $params) || array_key_exists('error', $params);
    }
}

if (! function_exists('keycloak_admin_auth_to_absolute_url')) {
    function keycloak_admin_auth_to_absolute_url(string $value): string
    {
        if (preg_match('~^https?://~i', $value) === 1) {
            return $value;
        }

        $base = rtrim((string) base_url('/'), '/');
        $path = ltrim($value, '/');

        if ($path === '') {
            return $base . '/';
        }

        return $base . '/' . $path;
    }
}

if (! function_exists('keycloak_admin_auth_get_login_url')) {
    function keycloak_admin_auth_get_login_url(): string
    {
        $route = route_to('login');
        if (is_string($route) && $route !== '') {
            return $route;
        }

        return '/' . keycloak_admin_auth_get_auth_gateway() . '/login';
    }
}

if (! function_exists('keycloak_admin_auth_get_admin_url')) {
    function keycloak_admin_auth_get_admin_url(): string
    {
        $route = route_to('admin');
        if (is_string($route) && $route !== '') {
            return $route;
        }

        return '/' . keycloak_admin_auth_get_admin_gateway();
    }
}

if (! function_exists('keycloak_admin_auth_get_post_logout_redirect_url')) {
    function keycloak_admin_auth_get_post_logout_redirect_url(): string
    {
        $configuredUrl = keycloak_admin_auth_get_setting_string('post_logout_redirect_url');
        if ($configuredUrl !== '') {
            return keycloak_admin_auth_to_absolute_url($configuredUrl);
        }

        return keycloak_admin_auth_to_absolute_url(keycloak_admin_auth_get_admin_url());
    }
}

if (! function_exists('keycloak_admin_auth_make_state_value')) {
    function keycloak_admin_auth_make_state_value(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (Throwable) {
            return sha1(uniqid('keycloak', true));
        }
    }
}

if (! function_exists('keycloak_admin_auth_build_authorization_url')) {
    function keycloak_admin_auth_build_authorization_url(): string
    {
        $endpoint = keycloak_admin_auth_get_authorization_endpoint();
        if ($endpoint === '') {
            return keycloak_admin_auth_get_login_url();
        }

        $state = keycloak_admin_auth_make_state_value();
        session()->set(keycloak_admin_auth_state_session_key(), $state);

        $params = [
            'response_type' => 'code',
            'client_id'     => keycloak_admin_auth_get_client_id(),
            'redirect_uri'  => keycloak_admin_auth_to_absolute_url(keycloak_admin_auth_get_login_url()),
            'scope'         => keycloak_admin_auth_get_scopes(),
            'state'         => $state,
            'nonce'         => keycloak_admin_auth_make_state_value(),
        ];

        return $endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}

if (! function_exists('keycloak_admin_auth_verify_callback_state')) {
    function keycloak_admin_auth_verify_callback_state(string $state): bool
    {
        $expected = session()->get(keycloak_admin_auth_state_session_key());
        session()->remove(keycloak_admin_auth_state_session_key());

        if (! is_string($expected) || trim($expected) === '') {
            return false;
        }

        $state = trim($state);
        if ($state === '') {
            return false;
        }

        return hash_equals($expected, $state);
    }
}

if (! function_exists('keycloak_admin_auth_exchange_code_for_tokens')) {
    /**
     * @return array<string,mixed>|null
     */
    function keycloak_admin_auth_exchange_code_for_tokens(string $code): ?array
    {
        $endpoint = keycloak_admin_auth_get_token_endpoint();
        if ($endpoint === '') {
            return null;
        }

        $code = trim($code);
        if ($code === '') {
            return null;
        }

        try {
            $response = service('curlrequest')->post($endpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'form_params' => [
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => keycloak_admin_auth_to_absolute_url(keycloak_admin_auth_get_login_url()),
                    'client_id'     => keycloak_admin_auth_get_client_id(),
                    'client_secret' => keycloak_admin_auth_get_client_secret(),
                ],
                'http_errors' => false,
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'Keycloak token exchange failed: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            log_message('warning', 'Keycloak token exchange returned status {status}.', [
                'status' => $response->getStatusCode(),
            ]);

            return null;
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (! is_array($payload)) {
            return null;
        }

        return $payload;
    }
}

if (! function_exists('keycloak_admin_auth_fetch_userinfo')) {
    /**
     * @return array<string,mixed>
     */
    function keycloak_admin_auth_fetch_userinfo(string $accessToken): array
    {
        $endpoint = keycloak_admin_auth_get_userinfo_endpoint();
        if ($endpoint === '') {
            return [];
        }

        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            return [];
        }

        try {
            $response = service('curlrequest')->get($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept'        => 'application/json',
                ],
                'http_errors' => false,
            ]);
        } catch (Throwable $exception) {
            log_message('warning', 'Keycloak userinfo lookup failed: {message}', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return [];
        }

        $payload = json_decode((string) $response->getBody(), true);

        return is_array($payload) ? $payload : [];
    }
}

if (! function_exists('keycloak_admin_auth_decode_base64_url')) {
    function keycloak_admin_auth_decode_base64_url(string $value): ?string
    {
        $normalized = strtr($value, '-_', '+/');
        $remainder = strlen($normalized) % 4;

        if ($remainder !== 0) {
            $normalized .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($normalized, true);
        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }
}

if (! function_exists('keycloak_admin_auth_decode_jwt_payload')) {
    /**
     * @return array<string,mixed>
     */
    function keycloak_admin_auth_decode_jwt_payload(string $token): array
    {
        $token = trim($token);
        if ($token === '' || ! str_contains($token, '.')) {
            return [];
        }

        $parts = explode('.', $token);
        if (count($parts) < 2) {
            return [];
        }

        $decodedPayload = keycloak_admin_auth_decode_base64_url($parts[1]);
        if ($decodedPayload === null) {
            return [];
        }

        $payload = json_decode($decodedPayload, true);

        return is_array($payload) ? $payload : [];
    }
}

if (! function_exists('keycloak_admin_auth_extract_claims')) {
    /**
     * @param array<string,mixed> $tokens
     *
     * @return array<string,mixed>
     */
    function keycloak_admin_auth_extract_claims(array $tokens): array
    {
        $claims = [];

        $accessToken = $tokens['access_token'] ?? '';
        if (is_string($accessToken) && $accessToken !== '') {
            $accessClaims = keycloak_admin_auth_decode_jwt_payload($accessToken);
            if ($accessClaims !== []) {
                $claims = array_replace_recursive($claims, $accessClaims);
            }
        } else {
            $accessToken = '';
        }

        $idToken = $tokens['id_token'] ?? '';
        if (is_string($idToken) && $idToken !== '') {
            $idClaims = keycloak_admin_auth_decode_jwt_payload($idToken);
            if ($idClaims !== []) {
                $claims = array_replace_recursive($claims, $idClaims);
            }
        }

        $userinfo = keycloak_admin_auth_fetch_userinfo($accessToken);
        if ($userinfo !== []) {
            $claims = array_replace_recursive($claims, $userinfo);
        }

        return $claims;
    }
}

if (! function_exists('keycloak_admin_auth_extract_roles')) {
    /**
     * @param array<string,mixed> $claims
     *
     * @return list<string>
     */
    function keycloak_admin_auth_extract_roles(array $claims): array
    {
        $roles = [];

        $realmAccess = $claims['realm_access'] ?? null;
        if (is_array($realmAccess)) {
            $roles = array_merge($roles, keycloak_admin_auth_parse_scalar_list($realmAccess['roles'] ?? null));
        }

        $resourceAccess = $claims['resource_access'] ?? null;
        if (is_array($resourceAccess)) {
            foreach ($resourceAccess as $client => $resourceValues) {
                if (! is_string($client) || ! is_array($resourceValues)) {
                    continue;
                }

                $clientRoles = keycloak_admin_auth_parse_scalar_list($resourceValues['roles'] ?? null);
                foreach ($clientRoles as $clientRole) {
                    $roles[] = $clientRole;
                    $roles[] = 'role:' . $client . ':' . $clientRole;
                }
            }
        }

        $roles = array_merge($roles, keycloak_admin_auth_parse_scalar_list($claims['groups'] ?? null));
        $roles = array_merge($roles, keycloak_admin_auth_parse_scalar_list($claims['group'] ?? null));
        $roles = array_merge($roles, keycloak_admin_auth_parse_scalar_list($claims['roles'] ?? null));
        $roles = array_merge($roles, keycloak_admin_auth_parse_scalar_list($claims['role'] ?? null));

        return keycloak_admin_auth_unique_non_empty($roles);
    }
}

if (! function_exists('keycloak_admin_auth_normalize_token')) {
    function keycloak_admin_auth_normalize_token(string $value): string
    {
        $value = trim($value);
        $value = trim($value, '[]');
        $value = ltrim($value, '/');

        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return (string) mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }
}

if (! function_exists('keycloak_admin_auth_has_any_matching_role')) {
    /**
     * @param list<string> $actualRoles
     * @param list<string> $expectedRoles
     */
    function keycloak_admin_auth_has_any_matching_role(array $actualRoles, array $expectedRoles): bool
    {
        if ($actualRoles === [] || $expectedRoles === []) {
            return false;
        }

        $normalizedActual = [];
        foreach ($actualRoles as $role) {
            $normalizedRole = keycloak_admin_auth_normalize_token($role);
            if ($normalizedRole !== '') {
                $normalizedActual[] = $normalizedRole;
            }
        }

        if ($normalizedActual === []) {
            return false;
        }

        foreach ($expectedRoles as $expectedRole) {
            $normalizedExpected = keycloak_admin_auth_normalize_token($expectedRole);
            if ($normalizedExpected === '') {
                continue;
            }

            if (in_array($normalizedExpected, ['*', 'all', 'everyone', 'everybody'], true)) {
                return true;
            }

            foreach ($normalizedActual as $normalizedRole) {
                if ($normalizedRole === $normalizedExpected) {
                    return true;
                }

                if (str_ends_with($normalizedRole, ':' . $normalizedExpected)) {
                    return true;
                }

                if (str_ends_with($normalizedRole, '/' . $normalizedExpected)) {
                    return true;
                }

                if (str_starts_with($normalizedExpected, 'role_') && str_ends_with($normalizedRole, $normalizedExpected)) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (! function_exists('keycloak_admin_auth_group_exists')) {
    function keycloak_admin_auth_group_exists(string $group): bool
    {
        $group = trim($group);
        if ($group === '') {
            return false;
        }

        $groups = setting('AuthGroups.groups');
        if (! is_array($groups)) {
            return false;
        }

        foreach ($groups as $groupKey => $_) {
            if (! is_string($groupKey)) {
                continue;
            }

            if (keycloak_admin_auth_normalize_token($groupKey) === keycloak_admin_auth_normalize_token($group)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('keycloak_admin_auth_get_default_group')) {
    function keycloak_admin_auth_get_default_group(): string
    {
        $fallback = setting('AuthGroups.defaultGroup');
        if (! is_string($fallback) || trim($fallback) === '') {
            $fallback = 'manager';
        }

        $configured = keycloak_admin_auth_get_setting_string('default_group');
        if ($configured === '') {
            return keycloak_admin_auth_group_exists($fallback) ? $fallback : 'manager';
        }

        if (keycloak_admin_auth_group_exists($configured)) {
            return $configured;
        }

        return keycloak_admin_auth_group_exists($fallback) ? $fallback : 'manager';
    }
}

if (! function_exists('keycloak_admin_auth_resolve_instance_group')) {
    /**
     * @param list<string> $roles
     */
    function keycloak_admin_auth_resolve_instance_group(array $roles): string
    {
        $groupRoleMap = [
            'superadmin' => keycloak_admin_auth_get_superadmin_role_tokens(),
            'manager'    => keycloak_admin_auth_get_manager_role_tokens(),
            'podcaster'  => keycloak_admin_auth_get_podcaster_role_tokens(),
        ];

        foreach ($groupRoleMap as $group => $groupRoles) {
            if ($groupRoles === []) {
                continue;
            }

            if (! keycloak_admin_auth_has_any_matching_role($roles, $groupRoles)) {
                continue;
            }

            if (! keycloak_admin_auth_group_exists($group)) {
                continue;
            }

            return $group;
        }

        return keycloak_admin_auth_get_default_group();
    }
}

if (! function_exists('keycloak_admin_auth_is_user_allowed')) {
    /**
     * @param list<string> $roles
     */
    function keycloak_admin_auth_is_user_allowed(array $roles): bool
    {
        $allowedRoles = keycloak_admin_auth_get_allowed_roles();
        if ($allowedRoles === []) {
            return true;
        }

        return keycloak_admin_auth_has_any_matching_role($roles, $allowedRoles);
    }
}

if (! function_exists('keycloak_admin_auth_extract_email')) {
    /**
     * @param array<string,mixed> $claims
     */
    function keycloak_admin_auth_extract_email(array $claims): string
    {
        $candidates = [
            $claims['email'] ?? null,
            $claims['upn'] ?? null,
            $claims['preferred_username'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $value = trim((string) $candidate);
            if ($value === '' || ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            return strtolower($value);
        }

        return '';
    }
}

if (! function_exists('keycloak_admin_auth_extract_username_candidate')) {
    /**
     * @param array<string,mixed> $claims
     */
    function keycloak_admin_auth_extract_username_candidate(array $claims, string $email): string
    {
        $candidates = [
            $claims['preferred_username'] ?? null,
            $claims['username'] ?? null,
            $claims['name'] ?? null,
        ];

        $givenName = $claims['given_name'] ?? null;
        $familyName = $claims['family_name'] ?? null;
        if (is_scalar($givenName) || is_scalar($familyName)) {
            $fullName = trim((string) $givenName . ' ' . (string) $familyName);
            if ($fullName !== '') {
                $candidates[] = $fullName;
            }
        }

        foreach ($candidates as $candidate) {
            if (! is_scalar($candidate)) {
                continue;
            }

            $normalized = keycloak_admin_auth_normalize_username((string) $candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        $emailLocalPart = explode('@', $email, 2)[0] ?? '';
        $normalizedEmailLocalPart = keycloak_admin_auth_normalize_username($emailLocalPart);

        return $normalizedEmailLocalPart !== '' ? $normalizedEmailLocalPart : 'User';
    }
}

if (! function_exists('keycloak_admin_auth_normalize_username')) {
    function keycloak_admin_auth_normalize_username(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '@')) {
            $value = explode('@', $value, 2)[0] ?? '';
        }

        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
        $value = preg_replace('/[._-]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }
}

if (! function_exists('keycloak_admin_auth_utf8_length')) {
    function keycloak_admin_auth_utf8_length(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }
}

if (! function_exists('keycloak_admin_auth_truncate_utf8')) {
    function keycloak_admin_auth_truncate_utf8(string $value, int $maxLength): string
    {
        if ($maxLength <= 0) {
            return '';
        }

        if (keycloak_admin_auth_utf8_length($value) <= $maxLength) {
            return $value;
        }

        if (function_exists('mb_substr')) {
            return (string) mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }
}

if (! function_exists('keycloak_admin_auth_username_exists')) {
    function keycloak_admin_auth_username_exists(UserModel $userModel, string $candidate, ?int $excludeUserId = null): bool
    {
        $builder = $userModel->where('username', $candidate);
        if ($excludeUserId !== null) {
            $builder = $builder->where('id !=', $excludeUserId);
        }

        return (int) $builder->countAllResults() > 0;
    }
}

if (! function_exists('keycloak_admin_auth_make_unique_username')) {
    function keycloak_admin_auth_make_unique_username(UserModel $userModel, string $base, ?int $excludeUserId = null): string
    {
        $base = keycloak_admin_auth_normalize_username($base);
        if ($base === '') {
            $base = 'User';
        }

        $maxLength = 30;
        $base = keycloak_admin_auth_truncate_utf8($base, $maxLength);

        $candidate = $base;
        $suffix = 1;

        while (keycloak_admin_auth_username_exists($userModel, $candidate, $excludeUserId)) {
            ++$suffix;
            $suffixText = ' ' . (string) $suffix;
            $baseMaxLength = max(1, $maxLength - keycloak_admin_auth_utf8_length($suffixText));
            $candidate = keycloak_admin_auth_truncate_utf8($base, $baseMaxLength) . $suffixText;
        }

        return $candidate;
    }
}

if (! function_exists('keycloak_admin_auth_generate_password')) {
    function keycloak_admin_auth_generate_password(): string
    {
        try {
            return bin2hex(random_bytes(24));
        } catch (Throwable) {
            return sha1(uniqid('castopod', true) . microtime(true));
        }
    }
}

if (! function_exists('keycloak_admin_auth_apply_instance_group')) {
    function keycloak_admin_auth_apply_instance_group(User $user, string $group): void
    {
        $group = trim($group);
        if ($group === '') {
            return;
        }

        helper('auth');

        if (function_exists('set_instance_group')) {
            set_instance_group($user, $group);
            return;
        }

        $existingGroups = $user->getGroups() ?? [];
        if (! in_array($group, $existingGroups, true)) {
            $user->addGroup($group);
        }
    }
}

if (! function_exists('keycloak_admin_auth_sync_user')) {
    /**
     * @param array<string,mixed> $claims
     * @param list<string> $roles
     */
    function keycloak_admin_auth_sync_user(array $claims, array $roles): ?User
    {
        $email = keycloak_admin_auth_extract_email($claims);
        if ($email === '') {
            log_message('warning', 'Keycloak login was rejected because no email claim was found.');
            return null;
        }

        $userModel = new UserModel();
        $targetGroup = keycloak_admin_auth_resolve_instance_group($roles);
        $usernameCandidate = keycloak_admin_auth_extract_username_candidate($claims, $email);

        $existingUser = $userModel->findByCredentials([
            'email' => $email,
        ]);

        if ($existingUser instanceof User) {
            $userId = is_numeric($existingUser->id) ? (int) $existingUser->id : null;

            if (
                $userId !== null
                && $userId > 0
                && keycloak_admin_auth_get_setting_bool('sync_username', true)
            ) {
                $newUsername = keycloak_admin_auth_make_unique_username($userModel, $usernameCandidate, $userId);
                if ($newUsername !== '' && $newUsername !== (string) $existingUser->username) {
                    $userModel->update($userId, [
                        'username' => $newUsername,
                    ]);

                    $reloadedUser = $userModel->findById($userId);
                    if ($reloadedUser instanceof User) {
                        $existingUser = $reloadedUser;
                    }
                }
            }

            keycloak_admin_auth_apply_instance_group($existingUser, $targetGroup);

            return $existingUser;
        }

        if (! keycloak_admin_auth_get_setting_bool('auto_create_users', true)) {
            return null;
        }

        $username = keycloak_admin_auth_make_unique_username($userModel, $usernameCandidate);

        $newUser = new User([
            'username' => $username,
            'email'    => $email,
            'password' => keycloak_admin_auth_generate_password(),
            'active'   => 1,
        ]);

        if (! $userModel->save($newUser)) {
            log_message('error', 'Could not create Keycloak-backed user: {errors}', [
                'errors' => json_encode($userModel->errors()),
            ]);

            return null;
        }

        $insertId = (int) $userModel->getInsertID();
        if ($insertId <= 0) {
            return null;
        }

        $createdUser = $userModel->findById($insertId);
        if (! $createdUser instanceof User) {
            return null;
        }

        keycloak_admin_auth_apply_instance_group($createdUser, $targetGroup);

        return $createdUser;
    }
}

if (! function_exists('keycloak_admin_auth_authenticate_callback')) {
    function keycloak_admin_auth_authenticate_callback(RequestInterface $request): ?User
    {
        $callbackError = keycloak_admin_auth_get_query_string($request, 'error');
        if ($callbackError !== '') {
            $description = keycloak_admin_auth_get_query_string($request, 'error_description');
            log_message('warning', 'Keycloak callback error: {error} ({description})', [
                'error'       => $callbackError,
                'description' => $description,
            ]);

            return null;
        }

        $code = keycloak_admin_auth_get_query_string($request, 'code');
        $state = keycloak_admin_auth_get_query_string($request, 'state');

        if ($code === '' || ! keycloak_admin_auth_verify_callback_state($state)) {
            log_message('warning', 'Keycloak callback rejected due to missing code/state or invalid state.');
            return null;
        }

        $tokens = keycloak_admin_auth_exchange_code_for_tokens($code);
        if (! is_array($tokens)) {
            return null;
        }

        $idToken = $tokens['id_token'] ?? null;
        if (is_string($idToken) && trim($idToken) !== '') {
            session()->set(keycloak_admin_auth_id_token_session_key(), trim($idToken));
        }

        $claims = keycloak_admin_auth_extract_claims($tokens);
        $roles = keycloak_admin_auth_extract_roles($claims);

        if (! keycloak_admin_auth_is_user_allowed($roles)) {
            log_message('warning', 'Keycloak login denied because user has no allowed role.');
            return null;
        }

        return keycloak_admin_auth_sync_user($claims, $roles);
    }
}

if (! function_exists('keycloak_admin_auth_build_logout_url')) {
    function keycloak_admin_auth_build_logout_url(): string
    {
        $endpoint = keycloak_admin_auth_get_end_session_endpoint();
        if ($endpoint === '') {
            return keycloak_admin_auth_get_login_url();
        }

        $params = [
            'post_logout_redirect_uri' => keycloak_admin_auth_get_post_logout_redirect_url(),
            'client_id'                => keycloak_admin_auth_get_client_id(),
        ];

        $idTokenHint = session()->get(keycloak_admin_auth_id_token_session_key());
        if (is_string($idTokenHint) && trim($idTokenHint) !== '') {
            $params['id_token_hint'] = trim($idTokenHint);
        }

        session()->remove(keycloak_admin_auth_id_token_session_key());

        return $endpoint . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
