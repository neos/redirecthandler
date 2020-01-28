<?php
declare(strict_types=1);

namespace Neos\RedirectHandler\Service;

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
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\RedirectHandler\Exception;
use Neos\RedirectHandler\RedirectInterface;
use Neos\RedirectHandler\Storage\RedirectStorageInterface;
use Neos\Utility\Arrays;
use Psr\Log\LoggerInterface;

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

    const REDIRECT_EXPORT_DATETIME_FORMAT = 'Y-m-d\TH:i:sP';

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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var ThrowableStorageInterface
     */
    protected $throwableStorage;

    /**
     * @Flow\Inject
     * @var SecurityContext
     */
    protected $securityContext;

    /**
     * @Flow\InjectConfiguration(path="validation", package="Neos.RedirectHandler")
     * @var array
     */
    protected $validationOptions;

    /**
     * @param \Iterator $iterator
     * @return array
     */
    public function import(\Iterator $iterator): array
    {
        $counter = 0;
        $protocol = [];

        $authenticatedAccount = $this->securityContext->canBeInitialized() ? $this->securityContext->getAccount() : null;
        $currentUserIdentifier = $authenticatedAccount !== null ? $authenticatedAccount->getAccountIdentifier() : 'imported';

        foreach ($iterator as $index => $row) {
            $skipped = false;
            $columnCount = count($row);

            if ($counter === 0 && $columnCount < 3) {
                $protocol[] = [
                    'type' => self::REDIRECT_IMPORT_MESSAGE_TYPE_ERROR,
                    'arguments' => [],
                    'message' => 'Invalid csv format, did you set the correct delimiter?',
                ];
                break;
            }

            if ($columnCount < 3) {
                $protocol[] = [
                    'type' => self::REDIRECT_IMPORT_MESSAGE_TYPE_ERROR,
                    'message' => 'Row skipped as it has not all required fields set: ' . join(',', $row)
                ];
                continue;
            }

            [
                $sourceUriPath,
                $targetUriPath,
                $statusCode,
            ] = $row;

            // Skip first line with headers
            if ($counter === 0 && $sourceUriPath === 'Source Uri') {
                continue;
            }

            // Retrieve field by field if csv doesn't have all columns
            $hosts = isset($row[3]) && !empty($row[3]) ? $row[3] : '';
            $rawStartDateTime = isset($row[4]) && !empty($row[4]) ? $row[4] : null;
            $rawEndDateTime = isset($row[5]) && !empty($row[5]) ? $row[5] : null;
            $comment = isset($row[6]) && !empty($row[6]) ? $row[6] : null;
            $type = isset($row[8]) && !empty($row[8]) ? $row[8] : RedirectInterface::REDIRECT_TYPE_MANUAL;
            $statusCode = intval($statusCode);

            $hosts = Arrays::trimExplode('|', $hosts);
            if ($hosts === []) {
                $hosts = [null];
            }

            if ($rawStartDateTime !== null) {
                $startDateTime = \DateTime::createFromFormat(self::REDIRECT_EXPORT_DATETIME_FORMAT, $rawStartDateTime);
                if (!$startDateTime instanceof \DateTime) {
                    $protocol[] = [
                        'type' => self::REDIRECT_IMPORT_MESSAGE_TYPE_ERROR,
                        'arguments' => [],
                        'message' => 'Start date time "' . $rawStartDateTime . '" does not match the format "' . self::REDIRECT_EXPORT_DATETIME_FORMAT . '"'
                    ];
                    continue;
                }
            } else {
                $startDateTime = null;
            }

            if ($rawEndDateTime !== null) {
                $endDateTime = \DateTime::createFromFormat(self::REDIRECT_EXPORT_DATETIME_FORMAT, $rawEndDateTime);
                if (!$endDateTime instanceof \DateTime) {
                    $protocol[] = [
                        'type' => self::REDIRECT_IMPORT_MESSAGE_TYPE_ERROR,
                        'arguments' => [],
                        'message' => 'End date time "' . $rawEndDateTime . '" does not match the format "' . self::REDIRECT_EXPORT_DATETIME_FORMAT . '"'
                    ];
                    continue;
                }
            } else {
                $endDateTime = null;
            }

            if (!preg_match($this->validationOptions['sourceUriPath'], $sourceUriPath)) {
                $protocol[] = [
                    'type' => self::REDIRECT_IMPORT_MESSAGE_TYPE_ERROR,
                    'arguments' => [],
                    'message' => 'Source path "' . $sourceUriPath . '" does not have a valid format'
                ];
                continue;
            }

            $forcePersist = false;
            foreach ($hosts as $key => $host) {
                $host = empty($host) ? null : $host;
                $redirect = $this->redirectStorage->getOneBySourceUriPathAndHost($sourceUriPath, $host);
                $isSame = $this->isSame($sourceUriPath, $targetUriPath, $host, $statusCode, $startDateTime,
                    $endDateTime, $comment, $redirect);
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
                $statusCode = intval($statusCode);

                // Set creator to current user if the redirect has changed
                $creator = $currentUserIdentifier;

                $changedRedirects = $this->redirectStorage->addRedirect($sourceUriPath, $targetUriPath, $statusCode,
                    $hosts,
                    $creator, $comment, $type, $startDateTime, $endDateTime);
                /** @var RedirectInterface $redirect */
                foreach ($changedRedirects as $redirect) {
                    $protocol[] = ['type' => self::REDIRECT_IMPORT_MESSAGE_TYPE_CREATED, 'redirect' => $redirect];
                    $messageArguments = [
                        $redirect->getSourceUriPath(),
                        $redirect->getTargetUriPath(),
                        $redirect->getStatusCode(),
                        $redirect->getHost() ?: 'all hosts'
                    ];
                    $this->logger->error(vsprintf('Redirect import success, sourceUriPath=%s, targetUriPath=%s, statusCode=%d, hosts=%s', $messageArguments), LogEnvironment::fromMethodName(__METHOD__));
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
                $this->logger->error(vsprintf('Redirect import error, sourceUriPath=%s, targetUriPath=%s, statusCode=%d, hosts=%s', $messageArguments), LogEnvironment::fromMethodName(__METHOD__));
                $this->throwableStorage->logThrowable($exception);
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
     * @param string $sourceUriPath
     * @param string $targetUriPath
     * @param string $host
     * @param integer $statusCode
     * @param \DateTime|null $startDateTime
     * @param \DateTime|null $endDateTime
     * @param string|null $comment
     * @param RedirectInterface $redirect
     * @return bool
     */
    protected function isSame(
        string $sourceUriPath,
        string $targetUriPath,
        ?string $host,
        int $statusCode,
        ?\DateTime $startDateTime = null,
        ?\DateTime $endDateTime = null,
        ?string $comment = null,
        RedirectInterface $redirect = null
    ): bool {
        if ($redirect === null) {
            return false;
        }
        return $redirect->getSourceUriPath() === $sourceUriPath && $redirect->getTargetUriPath() === $targetUriPath
            && $redirect->getHost() === $host && $redirect->getStatusCode() === (integer)$statusCode
            && $redirect->getStartDateTime() == $startDateTime && $redirect->getEndDateTime() == $endDateTime
            && $redirect->getComment() === $comment;
    }
}
