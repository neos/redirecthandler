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

use League\Csv\CannotInsertRecord;
use League\Csv\Writer;
use Neos\RedirectHandler\RedirectInterface;
use Neos\RedirectHandler\Storage\RedirectStorageInterface;
use Neos\Flow\Annotations as Flow;

/**
 * This service allows exporting redirects
 *
 * @Flow\Scope("singleton")
 */
class RedirectExportService
{
    /**
     * @Flow\Inject
     * @var RedirectStorageInterface
     */
    protected $redirectStorage;

    /**
     * Export redirects to a CSV object than can then be used to write a file or print the content
     *
     * @param string $host (optional) Only export hosts for a specified host
     * @param bool $onlyActive will filter inactive redirects based on start and end datetime if true
     * @param null|string $type will filter redirects based on their type
     * @param bool $includeHeader will a header line with column names as first line when true
     * @return Writer
     * @throws CannotInsertRecord
     */
    public function exportCsv(?string $host = null, bool $onlyActive = false, ?string $type = null, bool $includeHeader = true): Writer
    {
        $writer = Writer::createFromFileObject(new \SplTempFileObject());
        $redirects = $this->getRedirects($host, $onlyActive, $type);

        if ($includeHeader) {
            $writer->insertOne([
                'Source Uri',
                'Target Uri',
                'Status Code',
                'Host',
                'Start DateTime',
                'End DateTime',
                'Comment',
                'Creator',
                'Type',
            ]);
        }

        /** @var RedirectInterface $redirect */
        foreach ($redirects as $redirect) {
            $writer->insertOne([
                $redirect->getSourceUriPath(),
                $redirect->getTargetUriPath(),
                $redirect->getStatusCode(),
                $redirect->getHost(),
                $redirect->getStartDateTime() ? $redirect->getStartDateTime()->format(RedirectImportService::REDIRECT_EXPORT_DATETIME_FORMAT) : '',
                $redirect->getEndDateTime() ? $redirect->getEndDateTime()->format(RedirectImportService::REDIRECT_EXPORT_DATETIME_FORMAT) : '',
                $redirect->getComment(),
                $redirect->getCreator(),
                $redirect->getType(),
            ]);
        }

        return $writer;
    }

    /**
     * Retrieves all redirects or only the redirects for a given host
     *
     * @param string $host
     * @param bool $onlyActive will filter inactive redirects based on start and end datetime if true
     * @param null|string $type will filter redirects based on their type
     * @return \Generator<RedirectInterface>|\AppendIterator
     */
    public function getRedirects(?string $host = null, bool $onlyActive = false, ?string $type = null)
    {
        return $this->redirectStorage->getAll($host, $onlyActive, $type);
    }
}
