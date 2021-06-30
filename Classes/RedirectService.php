<?php
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

use Neos\RedirectHandler\Storage\RedirectStorageInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Headers;
use Neos\Flow\Http\Request as Request;
use Neos\Flow\Http\Response;
use Neos\Flow\Mvc\Routing\RouterCachingService;

/**
 * Central authority for HTTP redirects.
 *
 * This service is used to redirect to any configured target URI *before* the Routing Framework kicks in and it
 * should be used to create new redirect instances.
 *
 * @Flow\Scope("singleton")
 */
class RedirectService
{
    /**
     * @Flow\Inject
     * @var RedirectStorageInterface
     */
    protected $redirectStorage;

    /**
     * @Flow\Inject
     * @var RouterCachingService
     */
    protected $routerCachingService;

    /**
     * @Flow\InjectConfiguration(path="features")
     * @var array
     */
    protected $featureSwitch;

    /**
     * Searches for a matching redirect for the given HTTP response
     *
     * @param Request $httpRequest
     * @return Response|null
     * @throws Exception
     * @api
     */
    public function buildResponseIfApplicable(Request $httpRequest): ?Response
    {
        try {
            $redirect = $this->redirectStorage->getOneBySourceUriPathAndHost($httpRequest->getRelativePath(), $httpRequest->getBaseUri()->getHost());
            if ($redirect === null) {
                return null;
            }
            $now = new \DateTime();
            if (($redirect->getStartDateTime() && $redirect->getStartDateTime() > $now) || ($redirect->getEndDateTime() && $redirect->getEndDateTime() < $now)) {
                return null;
            }
            if (isset($this->featureSwitch['hitCounter']) && $this->featureSwitch['hitCounter'] === true) {
                $this->redirectStorage->incrementHitCount($redirect);
            }
            return $this->buildResponse($httpRequest, $redirect);
        } catch (\Exception $exception) {
            // Throw exception if it's a \Neos\RedirectHandler\Exception (used for custom exception handling)
            if ($exception instanceof Exception) {
                throw $exception;
            }
            // skip triggering the redirect if there was an error accessing the database (wrong credentials, ...)
            return null;
        }
    }

    /**
     * @param Request $httpRequest
     * @param RedirectInterface $redirect
     * @return Response|null
     * @throws Exception
     */
    protected function buildResponse(Request $httpRequest, RedirectInterface $redirect): ?Response
    {
        if (headers_sent() === true && FLOW_SAPITYPE !== 'CLI') {
            return null;
        }

        $response = new Response();
        $statusCode = $redirect->getStatusCode();
        $response->setStatus($statusCode);

        if ($statusCode >= 300 && $statusCode <= 399) {
            $location = $redirect->getTargetUriPath();

            if (parse_url($location, PHP_URL_SCHEME) === null) {
                $location = $httpRequest->getBaseUri() . $location;
                $location = urldecode($location);
            }

            $response->setHeaders(new Headers([
                'Location' => $location,
                'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
                'Expires' => 'Sat, 26 Jul 1997 05:00:00 GMT'
            ]));
        } elseif ($statusCode >= 400 && $statusCode <= 599) {
            $exception = new Exception();
            $exception->setStatusCode($statusCode);
            throw $exception;
        }

        return $response;
    }

    /**
     * Signals that a redirect has been created.
     *
     * @param RedirectInterface $redirect
     * @return void
     * @Flow\Signal
     * @api
     */
    public function emitRedirectCreated(RedirectInterface $redirect): void
    {
    }
}
