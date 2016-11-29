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
use Neos\RedirectHandler\Redirect;
use Neos\RedirectHandler\RedirectService;
use Neos\RedirectHandler\Storage\RedirectStorageInterface;
use Neos\Flow\Http\Request;
use Neos\Flow\Http\Response;
use Neos\Flow\Http\Uri;
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
     * @var Request
     */
    protected $mockHttpRequest;

    /**
     * Sets up this test case
     */
    protected function setUp()
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
            ->expects($this->atLeastOnce())
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
            ->expects($this->atLeastOnce())
            ->method('getRelativePath')
            ->will($this->returnValue('some/relative/path'));

        $this->mockRedirectStorage
            ->expects($this->once())
            ->method('getOneBySourceUriPathAndHost')
            ->with('some/relative/path')
            ->will($this->returnValue(null));

        $this->assertNull($this->redirectService->buildResponseIfApplicable($this->mockHttpRequest));
    }

    /**
     * @test
     */
    public function buildResponseIfApplicableRetunsHttpRequestIfApplicableRedirectIsFound()
    {
        $this->mockHttpRequest
            ->expects($this->atLeastOnce())
            ->method('getRelativePath')
            ->willReturn('some/relative/path');

        $mockRedirect = $this->getMockBuilder(Redirect::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockRedirect
            ->expects($this->atLeastOnce())
            ->method('getStatusCode')
            ->willReturn(301);

        $this->mockRedirectStorage
            ->expects($this->once())
            ->method('getOneBySourceUriPathAndHost')
            ->with('some/relative/path')
            ->willReturn($mockRedirect);

        $this->inject($this->redirectService, 'redirectStorage', $this->mockRedirectStorage);

        $request = $this->redirectService->buildResponseIfApplicable($this->mockHttpRequest);

        $this->assertInstanceOf(Response::class, $request);
    }
}
