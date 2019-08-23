<?php
declare(strict_types=1);

namespace Neos\RedirectHandler\Storage;

/*
 * This file is part of the Neos.RedirectHandler package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\RedirectHandler\Redirect as RedirectDto;
use Neos\RedirectHandler\RedirectInterface;

/**
 * Redirect Storage Interface
 */
interface RedirectStorageInterface
{
    /**
     * Returns one redirect DTO for the given $sourceUriPath or NULL if it doesn't exist
     *
     * @param string $sourceUriPath
     * @param string $host Full qualified host name
     * @param boolean $fallback If not redirect found, match a redirect with host value as null
     * @return RedirectInterface|null if no redirect exists for the given $sourceUriPath
     * @api
     */
    public function getOneBySourceUriPathAndHost(string $sourceUriPath, ?string $host = null, bool $fallback = true): ?RedirectInterface;

    /**
     * Returns all registered redirects matching the given parameters
     *
     * @param string $host Full qualified host name, a value of `null` will not filter the host and return all
     * @param bool $onlyActive Filters redirects which start and end datetime don't match the current datetime
     * @param string|null $type Filters redirects by their type
     * @return \Generator<RedirectDto>
     * @api
     */
    public function getAll(string $host = null, bool $onlyActive = false, ?string $type = null): \Generator;

    /**
     * Returns all registered redirects without a host and matching the given parameters
     *
     * @param bool $onlyActive Filters redirects which start and end datetime don't match the current datetime
     * @param string|null $type Filters redirects by their type
     * @return \Generator<RedirectDto>
     * @api
     */
    public function getAllWithoutHost(bool $onlyActive = false, ?string $type = null): \Generator;

    /**
     * Return a list of all hosts
     *
     * @return array
     * @api
     */
    public function getDistinctHosts(): array;

    /**
     * Removes a redirect for the given $sourceUriPath if it exists
     *
     * @param string $sourceUriPath
     * @param string $host Full qualified host name
     * @return void
     * @api
     */
    public function removeOneBySourceUriPathAndHost(string $sourceUriPath, ?string $host = null): void;

    /**
     * Removes all registered redirects
     *
     * @return void
     * @api
     */
    public function removeAll(): void;

    /**
     * Removes all registered redirects by host
     *
     * @param string $host Full qualified host name
     * @return void
     * @api
     */
    public function removeByHost(?string $host = null): void;

    /**
     * Adds a redirect to the repository and updates related redirects accordingly
     *
     * @param string $sourceUriPath the relative URI path that should trigger a redirect
     * @param string $targetUriPath the relative URI path the redirect should point to
     * @param int $statusCode the status code of the redirect header
     * @param array $hosts list of full qualified host name
     * @param string|null $creator
     * @param string|null $comment
     * @param string|null $type
     * @param \DateTime|null $startDateTime
     * @param \DateTime|null $endDateTime
     * @return array<RedirectDto> the freshly generated redirects
     * @api
     */
    public function addRedirect(
        string $sourceUriPath,
        string $targetUriPath,
        int $statusCode = null,
        array $hosts = [],
        ?string $creator = null,
        ?string $comment = null,
        ?string $type = null,
        \DateTime $startDateTime = null,
        \DateTime $endDateTime = null
    ): array;

    /**
     * Increment the hit counter for the given redirect
     *
     * @param RedirectInterface $redirect
     * @return void
     * @api
     */
    public function incrementHitCount(RedirectInterface $redirect): void;
}
