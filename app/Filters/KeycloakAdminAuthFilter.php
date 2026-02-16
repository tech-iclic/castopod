<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Override;
use RuntimeException;

class KeycloakAdminAuthFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     *
     * @return RequestInterface|ResponseInterface|string|null
     */
    #[Override]
    public function before(RequestInterface $request, $arguments = null)
    {
        helper(['auth', 'keycloak_admin_auth']);

        if (! keycloak_admin_auth_is_enabled() || ! keycloak_admin_auth_is_ready()) {
            return null;
        }

        $path = keycloak_admin_auth_normalize_path($request->getUri()->getPath());
        if (! keycloak_admin_auth_is_admin_or_auth_path($path)) {
            return null;
        }

        if (keycloak_admin_auth_is_logout_path($path)) {
            if (auth()->loggedIn()) {
                auth()->logout();
            }

            return redirect()->to(keycloak_admin_auth_build_logout_url());
        }

        if (auth()->loggedIn()) {
            if (keycloak_admin_auth_is_login_path($path)) {
                return redirect()->to(keycloak_admin_auth_get_admin_url());
            }

            return null;
        }

        if (keycloak_admin_auth_is_callback_request($request, $path)) {
            $user = keycloak_admin_auth_authenticate_callback($request);
            if ($user === null) {
                throw new RuntimeException('Unable to authenticate with Keycloak. Verify plugin configuration and roles.', 403);
            }

            auth()->login($user);

            return redirect()->to(keycloak_admin_auth_get_admin_url());
        }

        return redirect()->to(keycloak_admin_auth_build_authorization_url());
    }

    /**
     * @param list<string>|null $arguments
     */
    #[Override]
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
