<?php

declare(strict_types=1);

namespace Config;

use App\Filters\AdminMenuDisablerFilter;
use App\Filters\AllowCorsFilter;
use App\Filters\KeycloakAdminAuthFilter;
use App\Filters\PublicSitePrivacyFilter;
use App\Filters\PodcastMenuDisablerFilter;
use App\Filters\SuperadminPodcastContributorsFilter;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;
use Modules\Auth\Filters\PermissionFilter;

class Filters extends BaseConfig
{
    /**
     * Configures aliases for Filter classes to make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>>
     *
     * [filter_name => classname]
     * or [filter_name => [classname1, classname2, ...]]
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'allow-cors'    => AllowCorsFilter::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
    ];

    /**
     * List of special required filters.
     *
     * The filters listed here are special. They are applied before and after
     * other kinds of filters, and always applied even if a route does not exist.
     *
     * Filters set by default provide framework functionality. If removed,
     * those functions will no longer work.
     *
     * @see https://codeigniter.com/user_guide/incoming/filters.html#provided-filters
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'forcehttps', // Force Global Secure Requests
            'pagecache',  // Web Page Caching
        ],
        'after' => [
            'pagecache',   // Web Page Caching
            'performance', // Performance Metrics
            'toolbar',     // Debug Toolbar
        ],
    ];

    /**
     * List of filter aliases that are always applied before and after every request.
     *
     * @var array{
     *     before: array<string, array{except: list<string>|string}>|list<string>,
     *     after: array<string, array{except: list<string>|string}>|list<string>
     * }
     */
    public array $globals = [
        'before' => [
            // 'honeypot',
            'csrf' => [
                'except' => [
                    '@[a-zA-Z0-9\_]{1,32}/inbox',
                    'api/rest/v1/episodes',
                    'api/rest/v1/episodes/[0-9]+/publish',
                ],
            ],
            // 'invalidchars',
        ],
        'after' => [
            // 'honeypot',
            // 'secureheaders',
        ],
    ];

    /**
     * List of filter aliases that works on a particular HTTP method (GET, POST, etc.).
     *
     * Example: 'POST' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing permits any HTTP method to access a
     * controller. Accessing the controller with a method you don’t expect could bypass the filter.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any before or after URI patterns.
     *
     * Example: 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];

    public function __construct()
    {
        parent::__construct();

        $adminGateway = trim(config('Admin')->gateway, '/');
        $chunkedUploadPathPattern = $adminGateway . '/podcasts/*/episodes/chunked-audio*';

        if (
            isset($this->globals['before']['csrf']['except'])
            && is_array($this->globals['before']['csrf']['except'])
            && ! in_array($chunkedUploadPathPattern, $this->globals['before']['csrf']['except'], true)
        ) {
            $this->globals['before']['csrf']['except'][] = $chunkedUploadPathPattern;
        }

        $this->filters = [
            'session' => [
                'before' => [config('Admin')->gateway . '*', config('Analytics')->gateway . '*'],
            ],
            'keycloak-admin-auth' => [
                'before' => [config('Admin')->gateway . '*', config('Auth')->gateway . '*'],
            ],
            'public-site-privacy' => [
                'before' => ['*'],
            ],
            'admin-menu-disabler' => [
                'before' => [config('Admin')->gateway . '*'],
            ],
            'superadmin-podcast-contributors' => [
                'before' => [config('Admin')->gateway . '*'],
            ],
            'podcast-menu-disabler' => [
                'before' => [config('Admin')->gateway . '*'],
            ],
            'podcast-unlock' => [
                'before' => ['*@*/episodes/*'],
            ],
        ];

        $this->aliases['permission'] = PermissionFilter::class;
        $this->aliases['admin-menu-disabler'] = AdminMenuDisablerFilter::class;
        $this->aliases['keycloak-admin-auth'] = KeycloakAdminAuthFilter::class;
        $this->aliases['public-site-privacy'] = PublicSitePrivacyFilter::class;
        $this->aliases['superadmin-podcast-contributors'] = SuperadminPodcastContributorsFilter::class;
        $this->aliases['podcast-menu-disabler'] = PodcastMenuDisablerFilter::class;
    }
}
