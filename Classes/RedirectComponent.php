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
use Neos\Flow\Security\Context as SecurityContext;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\TypeConverter\NodeConverter;

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
     * @Flow\Inject
     * @var SecurityContext
     */
    protected $securityContext;

    /**
     * @var NodeConverter
     * @Flow\Inject
     */
    protected $nodeConverter;

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
            if(isset($routingMatchResults['node'])){
                $nodePathIncludingContextAndDimensions = $routingMatchResults['node'];
                $node = null;
                $this->securityContext->withoutAuthorizationChecks(function () use ($nodePathIncludingContextAndDimensions, &$node) {
                    $node = $this->nodeConverter->convertFrom($nodePathIncludingContextAndDimensions, NodeInterface::class);
                });

                // if the node is found there is no need to redirect, thus return
                if($node instanceof NodeInterface){
                    return;
                }
                // in case the node is hidden, convertFrom() will return an Error() and thus redirect if applicable
            }
            else{
                return;
            }
        }

        $httpRequest = $componentContext->getHttpRequest();
        $response = $this->redirectService->buildResponseIfApplicable($httpRequest);
        if ($response !== null) {
            $componentContext->replaceHttpResponse($response);
            $componentContext->setParameter(ComponentChain::class, 'cancel', true);
        }
    }
}
