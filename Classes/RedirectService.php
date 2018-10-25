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
     * @api
     */
    public function buildResponseIfApplicable(Request $httpRequest)
    {
        try {
            $redirect = $this->redirectStorage->getOneBySourceUriPathAndHost($httpRequest->getRelativePath(), $httpRequest->getBaseUri()->getHost());
            if ($redirect === null) {
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
     */
    protected function buildResponse(Request $httpRequest, RedirectInterface $redirect)
    {
        if (headers_sent() === true && FLOW_SAPITYPE !== 'CLI') {
            return null;
        }

        $statusCode = $redirect->getStatusCode();

        $response = new Response();
        $response = $response->withStatus($statusCode);

        if ($statusCode >= 300 && $statusCode <= 399) {
            $location = $redirect->getTargetUriPath();

            if (parse_url($location, PHP_URL_SCHEME) === null) {
                $location = $httpRequest->getBaseUri() . $location;
            }

            $response = $response->withHeader('Location', $location);
            $response = $response->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            $response = $response->withHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');

        } elseif ($statusCode >= 400 && $statusCode <= 599) {

            $responseBody = '
                <!DOCTYPE html>
                <html>
                    <head>
                        <meta charset="UTF-8">
                        <title>' . $statusCode . ' Gone</title>
                        <style type="text/css">
                            body {
                                font-family: Helvetica, Arial, sans-serif;
                                margin: 50px;
                            }
    
                            h1 {
                                color: #00ADEE;
                                font-weight: normal;
                            }
                        </style>
                    </head>
                    <body>
                        <h1>' . $statusCode . ' Gone</h1>
                    </body>
                </html>';

            $response = $response->withBody(
                new \Neos\Flow\Http\ContentStream(
                    fopen('data://text/plain,' . $responseBody,'r'),
                    'r'
                )
            );
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
    public function emitRedirectCreated(RedirectInterface $redirect)
    {
    }
}
