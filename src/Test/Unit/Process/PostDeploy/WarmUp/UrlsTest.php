<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\MagentoCloud\Test\Unit\Process\PostDeploy\WarmUp;

use Magento\MagentoCloud\Config\Stage\PostDeployInterface;
use Magento\MagentoCloud\Process\PostDeploy\WarmUp\Urls;
use Magento\MagentoCloud\Process\PostDeploy\WarmUp\UrlsPattern;
use Magento\MagentoCloud\Util\UrlManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @inheritdoc
 */
class UrlsTest extends TestCase
{
    /**
     * @var Urls
     */
    private $urls;

    /**
     * @var PostDeployInterface|MockObject
     */
    private $postDeployMock;

    /**
     * @var UrlManager|MockObject
     */
    private $urlManagerMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @var UrlsPattern|MockObject
     */
    private $urlsPatternMock;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->postDeployMock = $this->getMockForAbstractClass(PostDeployInterface::class);
        $this->urlManagerMock = $this->createMock(UrlManager::class);
        $this->loggerMock = $this->getMockForAbstractClass(LoggerInterface::class);
        $this->urlsPatternMock = $this->createPartialMock(UrlsPattern::class, ['get']);

        $this->urls = new Urls(
            $this->postDeployMock,
            $this->urlManagerMock,
            $this->loggerMock,
            $this->urlsPatternMock
        );
    }

    public function testGetAll()
    {
        $this->postDeployMock->expects($this->once())
            ->method('get')
            ->with(PostDeployInterface::VAR_WARM_UP_PAGES)
            ->willReturn([
                'category:*:*',
                'http://site1.com/',
                'http://site2.com/',
                'http://site3.com/',
                'http://site4.com/',
                'category:*',
                'somepage',
                'somepage2'
            ]);
        $this->urlManagerMock->expects($this->once())
            ->method('getBaseUrls')
            ->willReturn([
                'http://site1.com/',
                'http://site2.com/',
            ]);
        $this->urlManagerMock->expects($this->once())
            ->method('getBaseUrl')
            ->willReturn('http://site1.com/');
        $this->urlsPatternMock->expects($this->once())
            ->method('get')
            ->with('category:*:*')
            ->willReturn([
                'http://site1.com/category1',
                'http://site2.com/category1',
            ]);
        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with('Found 2 urls for pattern "category:*:*"');
        $this->loggerMock->expects($this->exactly(3))
            ->method('error')
            ->withConsecutive(
                [
                    'Page "http://site3.com/" can\'t be warmed-up because such domain ' .
                    'is not registered in current Magento installation'
                ],
                [
                    'Page "http://site4.com/" can\'t be warmed-up because such domain ' .
                    'is not registered in current Magento installation'
                ],
                ['Page "category:*" isn\'t correct and can\'t be warmed-up']
            );

        $this->assertEquals(
            [
                'http://site1.com/category1',
                'http://site2.com/category1',
                'http://site1.com/',
                'http://site2.com/',
                'http://site1.com/somepage',
                'http://site1.com/somepage2',
            ],
            $this->urls->getAll()
        );
    }
}
