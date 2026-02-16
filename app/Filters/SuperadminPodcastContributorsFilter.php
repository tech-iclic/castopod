<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Override;
use Throwable;

class SuperadminPodcastContributorsFilter implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     *
     * @return RequestInterface|ResponseInterface|string|null
     */
    #[Override]
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('superadmin_podcast_contributors');

        try {
            superadmin_podcast_contributors_sync();
        } catch (Throwable $exception) {
            log_message(
                'error',
                '[SuperadminPodcastContributorsFilter] {message}',
                ['message' => $exception->getMessage()],
            );
        }

        return null;
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
