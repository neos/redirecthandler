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

use AppendIterator;
use Generator;
use SplTempFileObject;
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
     * @return Writer
     * @throws CannotInsertRecord
     */
    public function exportCsv($host = null): Writer
    {
        $writer = Writer::createFromFileObject(new SplTempFileObject());
        $redirects = $this->getRedirects($host);

        /** @var $redirect RedirectInterface */
        foreach ($redirects as $redirect) {
            $writer->insertOne([
                $redirect->getSourceUriPath(),
                $redirect->getTargetUriPath(),
                $redirect->getStatusCode(),
                $redirect->getHost()
            ]);
        }

        return $writer;
    }

    /**
     * Retrieves all redirects or only the redirects for a given host
     *
     * @param string $host
     * @return Generator<RedirectInterface>
     */
    protected function getRedirects($host = null): Generator
    {
        if ($host !== null) {
            $redirects = $this->redirectStorage->getAll($host);
        } else {
            $redirects = new AppendIterator();
            foreach ($this->redirectStorage->getDistinctHosts() as $host) {
                $redirects->append($this->redirectStorage->getAll($host));
            }
        }
        yield $redirects;
    }
}

