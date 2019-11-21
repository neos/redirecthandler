<?php
declare(strict_types=1);

namespace Neos\RedirectHandler\Traits;

/*
 * This file is part of the Neos.RedirectHandler package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\RedirectHandler\Exception;
use Neos\RedirectHandler\RedirectInterface;
use Neos\Flow\Annotations as Flow;
use Neos\RedirectHandler\RedirectService;
use Psr\Log\LoggerInterface;

/**
 * RedirectSignal
 */
trait RedirectSignalTrait
{
    /**
     * @Flow\Inject
     * @var RedirectService
     */
    protected $_redirectService;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @Flow\Inject
     * @var ThrowableStorageInterface
     */
    protected $_throwableStorage;

    /**
     * @param array $redirects
     * @return void
     * @throws Exception
     */
    public function emitRedirectCreated(array $redirects): void
    {
        foreach ($redirects as $redirect) {
            if (!$redirect instanceof RedirectInterface) {
                throw new Exception('Redirect should implement RedirectInterface', 1460139669);
            }
            $this->_redirectService->emitRedirectCreated($redirect);
            $this->_logger->debug(sprintf('Redirect from %s %s -> %s (%d) added', $redirect->getHost(), $redirect->getSourceUriPath(), $redirect->getTargetUriPath(), $redirect->getStatusCode()), LogEnvironment::fromMethodName(__METHOD__));
        }
    }
}
