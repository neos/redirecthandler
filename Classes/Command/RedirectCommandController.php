<?php
declare(strict_types=1);

namespace Neos\RedirectHandler\Command;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use League\Csv\CannotInsertRecord;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\RedirectHandler\RedirectInterface;
use Neos\RedirectHandler\Service\RedirectExportService;
use Neos\RedirectHandler\Service\RedirectImportService;
use Neos\RedirectHandler\Storage\RedirectStorageInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Command controller for tasks related to redirects
 *
 * @Flow\Scope("singleton")
 */
class RedirectCommandController extends CommandController
{
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
     * @var RedirectExportService
     */
    protected $redirectExportService;

    /**
     * @Flow\Inject
     * @var RedirectImportService
     */
    protected $redirectImportService;

    /**
     * List all redirects
     *
     * Lists all saved redirects. Optionally it is possible to filter by ``host`` and to use the argument ``match`` to
     * look for certain ``source`` or ``target`` paths.
     *
     * @param string $host (optional) Filter redirects by the given hostname
     * @param string $match (optional) A string to match for the ``source`` or the ``target``
     * @return void
     */
    public function listCommand($host = null, $match = null): void
    {
        $outputByHost = function ($host = null) use ($match) {
            $redirects = $host === null ? $this->redirectStorage->getAllWithoutHost() : $this->redirectStorage->getAll($host);
            $this->outputLine();
            if ($host !== null) {
                $this->outputLine('<info>==</info> <b>Redirect for %s</b>', [$host]);
            } else {
                $this->outputLine('<info>==</info> <b>Redirects valid for all hosts</b>', [$host]);
            }
            if ($match !== null) {
                $this->outputLine('   <info>++</info> <b>Only showing redirects where source or target URI matches <u>%s</u></b>', [$match]);
                sleep(1);
            }
            $this->outputLine();
            /** @var $redirect RedirectInterface */
            foreach ($redirects as $redirect) {
                $outputLine = function ($source, $target, $statusCode) {
                    $this->outputLine('   <comment>></comment> %s <info>=></info> %s <comment>(%d)</comment>', [
                        $source,
                        $target,
                        $statusCode
                    ]);
                };
                $source = $redirect->getSourceUriPath();
                $target = $redirect->getTargetUriPath();
                $statusCode = $redirect->getStatusCode();
                if ($match === null) {
                    $outputLine($source, $target, $statusCode);
                } else {
                    $regexp = sprintf('#%s#', $match);
                    $matches = preg_grep($regexp, [$target, $source]);
                    if (count($matches) > 0) {
                        $replace = "<error>$0</error>";
                        $source = preg_replace($regexp, $replace, $source);
                        $target = preg_replace($regexp, $replace, $target);
                        $outputLine($source, $target, $statusCode);
                    }
                }
            }
        };
        if ($host !== null) {
            $outputByHost($host);
        } else {
            $hosts = $this->redirectStorage->getDistinctHosts();
            array_map($outputByHost, $hosts);
        }
        $this->outputLine();
    }

    /**
     * Export redirects to CSV
     *
     * This command will export all redirects in CSV format. You can set a preferred
     * filename before the export with the optional ``filename`` argument. If no ``filename`` argument is supplied, the
     * export will be returned within the CLI.
     *
     * @param string $filename (optional) The filename for the CSV file
     * @param string|null $host (optional) Only export redirects for a specified host
     * @param bool $includeHeader Add line with column names as first line
     * @return void
     */
    public function exportCommand(string $filename = null, ?string $host = null, bool $includeHeader = true): void
    {
        try {
            $csvWriter = $this->redirectExportService->exportCsv($host, false, null, $includeHeader);

            if ($filename === null) {
                $csvWriter->output();
            } else {
                file_put_contents($filename, (string)$csvWriter);
            }
        } catch (CannotInsertRecord $e) {
            $this->logger->error(vsprintf('Redirect export error, host=%s', [$host]), LogEnvironment::fromMethodName(__METHOD__));
        }
    }

    /**
     * Import redirects from CSV file
     *
     * This command is used to (re)import CSV files containing redirects.
     * The argument ``filename`` is the name of the file you uploaded to the root folder.
     * This operation requires the package ``league/csv``. Install it by running ``composer require league/csv``.
     * Structure per line is:
     * ``sourcePath``,``targetPath``,``statusCode``,``host`` (optional)
     * After a successful import a report will be shown. While `++` marks newly created redirects, `~~` marks already
     * existing redirect source paths along with the used status code and ``source``.
     * ``redirect:import`` will not delete pre-existing redirects. To do this run ``redirect:removeall`` before
     * the import. Be aware that this will also delete all automatically generated redirects.
     *
     * @param string $filename CSV file path
     * @param string $delimiter
     * @return void
     * @throws CsvException
     */
    public function importCommand(string $filename, string $delimiter = ','): void
    {
        $hasErrors = false;
        $this->outputLine();
        if (!is_readable($filename)) {
            $this->outputLine('<error>Sorry, but the file "%s" is not readable or does not exist...</error>', [$filename]);
            $this->outputLine();
            $this->sendAndExit(1);
        }
        $this->outputLine('<b>Import redirects from "%s"</b>', [$filename]);
        $this->outputLine();
        $reader = Reader::createFromPath($filename);
        $reader->setDelimiter($delimiter);
        $protocol = $this->redirectImportService->import($reader->getIterator());
        foreach ($protocol as $entry) {
            switch ($entry['type']) {
                case RedirectImportService::REDIRECT_IMPORT_MESSAGE_TYPE_CREATED:
                    $this->outputRedirectLine('<info>++</info>', $entry['redirect']);
                    break;
                case RedirectImportService::REDIRECT_IMPORT_MESSAGE_TYPE_DELETED:
                    $this->outputRedirectLine('<info>--</info>', $entry['redirect']);
                    break;
                case RedirectImportService::REDIRECT_IMPORT_MESSAGE_TYPE_UNCHANGED:
                    $this->outputRedirectLine('<comment>~~</comment>', $entry['redirect']);
                    break;
                case RedirectImportService::REDIRECT_IMPORT_MESSAGE_TYPE_ERROR:
                    $hasErrors = true;
                    if ($entry['arguments']) {
                        $this->outputLine('   <error>!!</error> %s => %s <comment>(%d)</comment> - %s', $entry['arguments']);
                        $this->outputLine('      Message: %s', [$entry['message']]);
                    } else {
                        $this->outputLine('   <error>!!</error> ' . $entry['message']);
                    }
                    break;
                default:
                    $this->outputLine('<info>!!</info> Undefined protocol entry with type ' . $entry['type']);
            }
        }
        $this->outputLine();
        if ($hasErrors === true) {
            $this->outputLine('   <error>!!</error> some errors appeared during import, please check the log or the CLI output.');
        }
        $this->outputLegend();
    }

    /**
     * @param RedirectInterface $redirect
     * @param string $sourceUriPath
     * @param string $targetUriPath
     * @param string|null $host
     * @param integer $statusCode
     * @return bool
     */
    protected function isSame(string $sourceUriPath, string $targetUriPath, ?string $host, int $statusCode, RedirectInterface $redirect = null): bool
    {
        if ($redirect === null) {
            return false;
        }
        return $redirect->getSourceUriPath() === $sourceUriPath && $redirect->getTargetUriPath() === $targetUriPath && $redirect->getHost() === $host && $redirect->getStatusCode() === (integer)$statusCode;
    }

    /**
     * Remove a single redirect
     *
     * This command is used the delete a single redirect. The redirect is identified by the ``source`` argument.
     * When using multiple domains for redirects the ``host`` argument is necessary to identify the correct one.
     *
     * @param string $source The source URI path of the redirect to remove, as given by ``redirect:list``
     * @param string $host (optional) Only remove redirects that use this host
     * @return void
     * @throws StopActionException
     */
    public function removeCommand(string $source, ?string $host = null): void
    {
        $redirect = $this->redirectStorage->getOneBySourceUriPathAndHost($source, $host);
        if ($redirect === null) {
            $this->outputLine('There is no redirect with the source URI path "%s", maybe you forgot the --host argument ?', [$source]);
            $this->quit(1);
        }
        $this->redirectStorage->removeOneBySourceUriPathAndHost($source, $host);
        $this->outputLine('Removed redirect with the source URI path "%s"', [$source]);
    }

    /**
     * Removes all redirects
     *
     * This command deletes all redirects from the RedirectRepository
     *
     * @return void
     */
    public function removeAllCommand(): void
    {
        $this->redirectStorage->removeAll();
        $this->outputLine('Removed all redirects');
    }

    /**
     * Remove all redirects by host
     *
     * This command deletes all redirects from the RedirectRepository by host value.
     * If ``all`` is entered the redirects valid for all hosts are deleted.
     *
     * @param string $host Fully qualified host name or `all` to delete redirects valid for all hosts
     * @return void
     */
    public function removeByHostCommand(string $host): void
    {
        if ($host === 'all') {
            $this->redirectStorage->removeByHost(null);
            $this->outputLine('Removed redirects matching all hosts');
        } else {
            $this->redirectStorage->removeByHost($host);
            $this->outputLine('Removed all redirects for host "%s"', [$host]);
        }
    }

    /**
     * Add a redirect
     *
     * Creates a new custom redirect. The optional argument ``host`` is used to define a specific redirect only valid
     * for a certain domain If no ``host`` argument is supplied, the redirect will act as a fallback redirect for all
     * domains in use. If any redirect exists with the same ``source`` property, it will be replaced if the ``force``
     * property has been set.
     *
     * @param string $source The relative URI path that should trigger the redirect
     * @param string $target The relative URI path that the redirect should point to
     * @param integer $statusCode The status code of the redirect header
     * @param string $host (optional) The host the redirect is valid for. If none is set, the redirect is valid for all
     * @param boolean $force Replace existing redirect (based on the source URI)
     * @return void
     */
    public function addCommand(string $source, string $target, int $statusCode, ?string $host = null, bool $force = false): void
    {
        $this->outputLine();
        $this->outputLine('<b>Create a redirect ...</b>');
        $this->outputLine();
        $redirect = $this->redirectStorage->getOneBySourceUriPathAndHost($source, $host, false);
        $isSame = $this->isSame($source, $target, $host, $statusCode, $redirect);
        if ($redirect !== null && $isSame === false && $force === false) {
            $this->outputLine('A redirect with the same source URI exist, see below:');
            $this->outputLine();
            $this->outputRedirectLine('<error>!!</error>', $redirect);
            $this->outputLine();
            $this->outputLine('Use --force to replace it');
            $this->outputLine();
            $this->sendAndExit(1);
        } elseif ($redirect !== null && $isSame === false && $force === true) {
            $this->redirectStorage->removeOneBySourceUriPathAndHost($source, $host);
            $this->outputRedirectLine('<info>--</info>', $redirect);
            $this->persistenceManager->persistAll();
        } elseif ($redirect !== null && $isSame === true) {
            $this->outputRedirectLine('<comment>~~</comment>', $redirect);
            $this->outputLine();
            $this->outputLegend();
            $this->sendAndExit();
        }
        $redirects = $this->redirectStorage->addRedirect($source, $target, $statusCode, [$host]);
        $redirect = reset($redirects);
        $this->outputRedirectLine('<info>++</info>', $redirect);
        $this->outputLine();
        $this->outputLegend();
    }

    /**
     * @param string $prefix
     * @param RedirectInterface $redirect
     * @return void
     */
    protected function outputRedirectLine(string $prefix, RedirectInterface $redirect): void
    {
        $this->outputLine('   %s %s <info>=></info> %s <comment>(%d)</comment> - %s', [
            $prefix,
            $redirect->getSourceUriPath(),
            $redirect->getTargetUriPath(),
            $redirect->getStatusCode(),
            $redirect->getHost() ?: 'all host'
        ]);
    }

    /**
     * @return void
     */
    protected function outputLegend(): void
    {
        $this->outputLine('<b>Legend</b>');
        $this->outputLine();
        $this->outputLine('   <info>++</info> Redirect created');
        $this->outputLine('   <info>--</info> Redirect removed');
        $this->outputLine('   <comment>~~</comment> Redirect not modified');
        $this->outputLine('   <error>!!</error> Error');
        $this->outputLine();
    }
}
