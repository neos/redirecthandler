<?php
declare(strict_types=1);

namespace Neos\RedirectHandler;

/*
 * This file is part of the Neos.RedirectHandler package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\ServerRequestAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RedirectMiddleware implements MiddlewareInterface
{
    /**
     * @var RedirectService
     * @Flow\Inject
     */
    protected $redirectService;

    /**
     * Checks if the current request has no match from routing but a
     * matching redirect and build a new response.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $next
     * @return ResponseInterface
     * @throws Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        $routingMatchResults = $request->getAttribute(ServerRequestAttributes::ROUTING_RESULTS);
        if ($routingMatchResults !== null) {
            return $next->handle($request);
        }
        $response = $this->redirectService->buildResponseIfApplicable($request);
        return $response ?? $next->handle($request);
    }
}
