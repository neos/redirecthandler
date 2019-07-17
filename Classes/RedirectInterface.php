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

use DateTime;

/**
 * Redirect Interface
 */
interface RedirectInterface
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
     * @return null|DateTime
     */
    public function getStartDateTime(): ?DateTime;

    /**
     * @return null|DateTime
     */
    public function getEndDateTime(): ?DateTime;
}
