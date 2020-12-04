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
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
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
     * @var ResponseFactoryInterface
     */
    protected $mockResponseFactory;

    /**
     * @var ServerRequestInterface
     */
    protected $mockHttpRequest;

    /**
     * @var UriInterface
     */
    protected $mockUri;

    /**
     * Sets up this test case
     */
    protected function setUp(): void
    {
        $this->redirectService = new RedirectService();

        $this->mockRedirectStorage = $this->getMockBuilder(RedirectStorageInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockResponseFactory = $this->getMockBuilder(ResponseFactoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->inject($this->redirectService, 'redirectStorage', $this->mockRedirectStorage);
        $this->inject($this->redirectService, 'responseFactory', $this->mockResponseFactory);

        $this->mockHttpRequest = $this->getMockBuilder(ServerRequestInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockUri = $this->getMockBuilder(UriInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockHttpRequest
            ->expects($this->any())
            ->method('getUri')
            ->willReturn($this->mockUri);
    }

    /**
     * @test
     */
    public function buildResponseIfApplicableReturnsSilentlyIfRedirectRepositoryThrowsException()
    {
        $this->mockUri
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->will($this->returnValue('some/relative/path'));

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
        $this->mockUri
            ->expects(static::atLeastOnce())
            ->method('getPath')
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
        $this->mockUri
            ->expects(static::atLeastOnce())
            ->method('getPath')
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

        $this->mockResponseFactory
            ->expects(static::once())
            ->method('createResponse')
            ->with(301)
            ->willReturn(new Response(301));

        $this->inject($this->redirectService, 'redirectStorage', $this->mockRedirectStorage);

        $request = $this->redirectService->buildResponseIfApplicable($this->mockHttpRequest);

        static::assertInstanceOf(Response::class, $request);
    }
}
