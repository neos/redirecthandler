<?php
namespace Neos\RedirectHandler\Service;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Iterator;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\SystemLoggerInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\RedirectHandler\Exception;
use Neos\RedirectHandler\RedirectInterface;
use Neos\RedirectHandler\Storage\RedirectStorageInterface;
use Neos\Utility\Arrays;

/**
 * This service allows exporting redirects
 *
 * @Flow\Scope("singleton")
 */
class RedirectImportService
{
    const REDIRECT_IMPORT_MESSAGE_TYPE_CREATED = 'created';
    const REDIRECT_IMPORT_MESSAGE_TYPE_DELETED = 'deleted';
    const REDIRECT_IMPORT_MESSAGE_TYPE_UNCHANGED = 'unchanged';
    const REDIRECT_IMPORT_MESSAGE_TYPE_ERROR = 'error';

    /**
     * @Flow\Inject
     * @var RedirectStorageInterface
     */
    protected $redirectStorage;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var SystemLoggerInterface
     */
    protected $logger;

    /**
     * @param Iterator $iterator
     * @return array
     */
    public function import(Iterator $iterator): array
    {
        $counter = 0;
        $protocol = [];
        foreach ($iterator as $index => $row) {
            $skipped = false;

            if ($counter === 0 && count($row) < 4) {
                $protocol[] = [
                    'type' => self::REDIRECT_IMPORT_MESSAGE_TYPE_ERROR,
                    'arguments' => [],
                    'message' => 'Invalid csv format, did you set the correct delimiter?',
                ];
                break;
            }

            list($sourceUriPath, $targetUriPath, $statusCode, $hosts, $startDateTime, $endDateTime, $comment, $creator, $type) = $row;

            // Skip first line with headers
            if ($counter === 0 && $sourceUriPath === 'Source Uri') {
                continue;
            }

            // Set defaults for empty values
            if (empty($startDateTime)) {
                $startDateTime = null;
            }
            if (empty($endDateTime)) {
                $endDateTime = null;
            }
            if (empty($creator)) {
                $creator = 'CLI';
            }
            if (empty($type)) {
                $type = RedirectInterface::REDIRECT_TYPE_MANUAL;
            }

            $hosts = Arrays::trimExplode('|', $hosts);
            if ($hosts === []) {
                $hosts = [null];
            }
            $forcePersist = false;
            foreach ($hosts as $key => $host) {
                $host = trim($host);
                $host = $host === '' ? null : $host;
                $redirect = $this->redirectStorage->getOneBySourceUriPathAndHost($sourceUriPath, $host);
                $isSame = $this->isSame($sourceUriPath, $targetUriPath, $host, $statusCode, $redirect);
                if ($redirect !== null && $isSame === false) {
                    $protocol[] = ['type' => self::REDIRECT_IMPORT_MESSAGE_TYPE_DELETED, 'redirect' => $redirect];
                    $this->redirectStorage->removeOneBySourceUriPathAndHost($sourceUriPath, $host);
                    $forcePersist = true;
                } elseif ($isSame === true) {
                    $protocol[] = ['type' => self::REDIRECT_IMPORT_MESSAGE_TYPE_UNCHANGED, 'redirect' => $redirect];
                    unset($hosts[$key]);
                    $skipped = true;
                }
            }
            if ($skipped === true && $hosts === []) {
                continue;
            }
            if ($forcePersist) {
                $this->persistenceManager->persistAll();
            }
            try {
                $redirects = $this->redirectStorage->addRedirect($sourceUriPath, $targetUriPath, $statusCode, $hosts,
                    $creator, $comment, $type, $startDateTime, $endDateTime);
                /** @var RedirectInterface $redirect */
                foreach ($redirects as $redirect) {
                    $protocol[] = ['type' => self::REDIRECT_IMPORT_MESSAGE_TYPE_CREATED, 'redirect' => $redirect];
                    $messageArguments = [
                        $redirect->getSourceUriPath(),
                        $redirect->getTargetUriPath(),
                        $redirect->getStatusCode(),
                        $redirect->getHost() ?: 'all hosts'
                    ];
                    $this->logger->log(vsprintf('Redirect import success, sourceUriPath=%s, targetUriPath=%s, statusCode=%d, hosts=%s',
                        $messageArguments), LOG_ERR);
                }
                $this->persistenceManager->persistAll();
            } catch (Exception $exception) {
                $messageArguments = [
                    $sourceUriPath,
                    $targetUriPath,
                    $statusCode,
                    $hosts ? json_encode($hosts) : 'all hosts'
                ];
                $protocol[] = [
                    'type' => self::REDIRECT_IMPORT_MESSAGE_TYPE_ERROR,
                    'arguments' => $messageArguments,
                    'message' => $exception->getMessage()
                ];
                $this->logger->log(vsprintf('Redirect import error, sourceUriPath=%s, targetUriPath=%s, statusCode=%d, hosts=%s',
                    $messageArguments), LOG_ERR);
                $this->logger->logException($exception);
            }
            $counter++;
            if ($counter % 50 === 0) {
                $this->persistenceManager->persistAll();
                $this->persistenceManager->clearState();
            }
        }
        return $protocol;
    }

    /**
     * @param RedirectInterface $redirect
     * @param string $sourceUriPath
     * @param string $targetUriPath
     * @param string $host
     * @param integer $statusCode
     * @return bool
     */
    protected function isSame($sourceUriPath, $targetUriPath, $host, $statusCode, RedirectInterface $redirect = null): bool
    {
        if ($redirect === null) {
            return false;
        }
        return $redirect->getSourceUriPath() === $sourceUriPath && $redirect->getTargetUriPath() === $targetUriPath
            && $redirect->getHost() === $host && $redirect->getStatusCode() === (integer)$statusCode;
    }
}

