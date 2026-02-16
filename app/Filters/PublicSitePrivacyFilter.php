<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Override;

class PublicSitePrivacyFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     *
     * @return RequestInterface|ResponseInterface|string|null
     */
    #[Override]
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('public_site_privacy');

        if (! public_site_privacy_should_redirect($request->getUri()->getPath())) {
            return null;
        }

        return redirect()->to(public_site_privacy_get_admin_url());
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
