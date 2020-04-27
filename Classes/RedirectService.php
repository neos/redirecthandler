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

use Neos\RedirectHandler\Storage\RedirectStorageInterface;
use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
     * @var ResponseFactoryInterface
     */
    protected $responseFactory;

    /**
     * @Flow\InjectConfiguration(path="features")
     * @var array
     */
    protected $featureSwitch;

    /**
     * Searches for a matching redirect for the given HTTP response
     *
     * @param ServerRequestInterface $httpRequest
     * @return ResponseInterface|null
     * @throws Exception
     * @api
     */
    public function buildResponseIfApplicable(ServerRequestInterface $httpRequest): ?ResponseInterface
    {
        try {
            $redirect = $this->redirectStorage->getOneBySourceUriPathAndHost($httpRequest->getUri()->getPath(), $httpRequest->getUri()->getHost());
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
     * @param ServerRequestInterface $httpRequest
     * @param RedirectInterface $redirect
     * @return ResponseInterface|null
     * @throws Exception
     */
    protected function buildResponse(ServerRequestInterface $httpRequest, RedirectInterface $redirect): ?ResponseInterface
    {
        if (headers_sent() === true && FLOW_SAPITYPE !== 'CLI') {
            return null;
        }

        $statusCode = $redirect->getStatusCode();
        $response = $this->responseFactory->createResponse($statusCode);

        if ($statusCode >= 300 && $statusCode <= 399) {
            $targetUri = $redirect->getTargetUriPath();

            // Relative redirects will be turned into absolute redirects
            if (parse_url($targetUri, PHP_URL_SCHEME) === null) {
                $targetUriParts = parse_url($targetUri);
                $absoluteTargetUri = $httpRequest->getUri();

                if (isset($targetUriParts['path'])) {
                    $absoluteTargetUri = $absoluteTargetUri->withPath($targetUriParts['path']);
                }

                if (isset($targetUriParts['query'])) {
                    $absoluteTargetUri = $absoluteTargetUri->withQuery($targetUriParts['query']);
                }

                if (isset($targetUriParts['fragment'])) {
                    $absoluteTargetUri = $absoluteTargetUri->withFragment($targetUriParts['fragment']);
                }

                $targetUri = (string)$absoluteTargetUri;
            }

            $response = $response->withHeader('Location', $targetUri)
                ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0')
                ->withHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
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
