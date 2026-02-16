<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Override;
use RuntimeException;

class AdminMenuDisablerFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     *
     * @return RequestInterface|ResponseInterface|string|null
     */
    #[Override]
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('admin_menu_disabler');

        $routeOptions = service('router')->getMatchedRouteOptions();
        $routeName = null;
        if (is_array($routeOptions) && array_key_exists('as', $routeOptions)) {
            $routeName = (string) $routeOptions['as'];
        }

        if (! admin_menu_disabler_is_disabled_request($routeName, $request->getUri()->getPath())) {
            return null;
        }

        throw new RuntimeException(
            'This admin section is disabled by the "Admin Menu Disabler" plugin configuration.',
            403,
        );
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
