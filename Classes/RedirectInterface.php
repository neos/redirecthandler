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

/**
 * Redirect Interface
 */
interface RedirectInterface extends \JsonSerializable
{
    const REDIRECT_TYPE_MANUAL = 'manual';
    const REDIRECT_TYPE_GENERATED = 'generated';

    /**
     * @return string
     */
    public function getSourceUriPath(): string;

    /**
     * @return string
     */
    public function getTargetUriPath(): string;

    /**
     * @return int
     */
    public function getStatusCode(): int;

    /**
     * @return null|string
     */
    public function getHost(): ?string;

    /**
     * @return null|string
     */
    public function getCreator(): ?string;

    /**
     * @return null|string
     */
    public function getComment(): ?string;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @return null| \DateTimeInterface
     */
    public function getStartDateTime(): ? \DateTimeInterface;

    /**
     * @return null| \DateTimeInterface
     */
    public function getEndDateTime(): ? \DateTimeInterface;

    /**
     * @return null| \DateTimeInterface
     */
    public function getCreationDateTime(): ? \DateTimeInterface;

    /**
     * @return null| \DateTimeInterface
     */
    public function getLastModificationDateTime(): ? \DateTimeInterface;

    /**
     * @return integer
     */
    public function getHitCounter(): int;

    /**
     * @return  \DateTimeInterface|null
     */
    public function getLastHit(): ? \DateTimeInterface;
}
