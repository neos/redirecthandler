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

use Doctrine\ORM\Mapping as ORM;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentChain;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Neos\Flow\Mvc\Routing\RouterCachingService;
use Neos\Flow\Mvc\Routing\RoutingComponent;

/**
 * Redirect HTTP Component
 */
class RedirectComponent implements ComponentInterface
{
    /**
     * @var RouterCachingService
     * @Flow\Inject
     */
    protected $routerCachingService;

    /**
     * @var RedirectService
     * @Flow\Inject
     */
    protected $redirectService;

    /**
     * @Flow\InjectConfiguration(path="cancelHttpChain")
     * @var boolean
     */
    protected $cancelHttpChain;

    /**
     * Check if the current request match a redirect
     *
     * @param ComponentContext $componentContext
     * @return void
     */
    public function handle(ComponentContext $componentContext)
    {
        $routingMatchResults = $componentContext->getParameter(RoutingComponent::class, 'matchResults');
        if ($routingMatchResults !== NULL) {
            return;
        }
        $httpRequest = $componentContext->getHttpRequest();
        $response = $this->redirectService->buildResponseIfApplicable($httpRequest);
        if ($response !== null) {
            $componentContext->replaceHttpResponse($response);
            if ($this->cancelHttpChain) {
                $componentContext->setParameter(ComponentChain::class, 'cancel', true);
            }
        }
    }
}
