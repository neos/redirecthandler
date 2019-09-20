<?php
namespace Neos\RedirectHandler\Tests\Unit;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\DBALException;
use Neos\Flow\Mvc\RequestInterface;
use Neos\RedirectHandler\Redirect;
use Neos\RedirectHandler\RedirectService;
use Neos\RedirectHandler\Storage\RedirectStorageInterface;
use Neos\Flow\Tests\UnitTestCase;

/**
 * Test case for the RedirectService class
 */
class RedirectServiceTest extends UnitTestCase
{
    /**
     * @var RedirectService
     */
    protected $redirectService;

    /**
     * @var RedirectStorageInterface
     */
    protected $mockRedirectStorage;

    /**
     * @var RequestInterface
     */
    protected $mockHttpRequest;

    /**
     * Sets up this test case
     */
    protected function setUp(): void
    {
        $this->redirectService = new RedirectService();

        $this->mockRedirectStorage = $this->getMockBuilder(RedirectStorageInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->inject($this->redirectService, 'redirectStorage', $this->mockRedirectStorage);

        $this->mockHttpRequest = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockUri = $this->getMockBuilder(Uri::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockHttpRequest
            ->expects($this->any())
            ->method('getBaseUri')
            ->willReturn($mockUri);
    }

    /**
     * @test
     */
    public function buildResponseIfApplicableReturnsSilentlyIfRedirectRepositoryThrowsException()
    {
        $this->mockRedirectStorage
            ->expects(static::atLeastOnce())
            ->method('getOneBySourceUriPathAndHost')
            ->will($this->throwException(new DBALException()));

        $this->redirectService->buildResponseIfApplicable($this->mockHttpRequest);
    }

    /**
     * @test
     */
    public function buildResponseIfApplicableReturnsNullIfNoApplicableRedirectIsFound()
    {
        $this->mockHttpRequest
            ->expects(static::atLeastOnce())
            ->method('getRelativePath')
            ->will(static::returnValue('some/relative/path'));

        $this->mockRedirectStorage
            ->expects($this->once())
            ->method('getOneBySourceUriPathAndHost')
            ->with('some/relative/path')
            ->will(static::returnValue(null));

        static::assertNull($this->redirectService->buildResponseIfApplicable($this->mockHttpRequest));
    }

    /**
     * @test
     */
    public function buildResponseIfApplicableRetunsHttpRequestIfApplicableRedirectIsFound()
    {
        $this->mockHttpRequest
            ->expects(static::atLeastOnce())
            ->method('getRelativePath')
            ->willReturn('some/relative/path');

        $mockRedirect = $this->getMockBuilder(Redirect::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockRedirect
            ->expects(static::atLeastOnce())
            ->method('getStatusCode')
            ->willReturn(301);

        $this->mockRedirectStorage
            ->expects(static::once())
            ->method('getOneBySourceUriPathAndHost')
            ->with('some/relative/path')
            ->willReturn($mockRedirect);

        $this->inject($this->redirectService, 'redirectStorage', $this->mockRedirectStorage);

        $request = $this->redirectService->buildResponseIfApplicable($this->mockHttpRequest);

        static::assertInstanceOf(Response::class, $request);
    }
}
