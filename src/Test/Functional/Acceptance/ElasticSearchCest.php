<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MagentoCloud\Test\Functional\Acceptance;

use Magento\MagentoCloud\Test\Functional\Codeception\Docker;

/**
 * This test runs on the latest version of PHP
 */
class ElasticSearchCest extends AbstractCest
{
    /**
     * @param \CliTester $I
     */
    public function _before(\CliTester $I)
    {
        // Do nothing
    }

    /**
     * @param \CliTester $I
     * @param \Codeception\Example $data
     * @throws \Robo\Exception\TaskException
     * @dataProvider elasticDataProvider
     */
    public function testElastic(\CliTester $I, \Codeception\Example $data)
    {
        $I->generateDockerCompose($data['services']);
        $I->resetEnvironment();
        $I->cloneTemplate($data['magento']);
        $I->composerInstall();
        $I->assertTrue($I->runEceToolsCommand('build', Docker::BUILD_CONTAINER));
        $I->assertTrue($I->runEceToolsCommand('deploy', Docker::DEPLOY_CONTAINER));
        $I->assertTrue($I->runEceToolsCommand('post-deploy', Docker::DEPLOY_CONTAINER));

        $I->amOnPage('/');
        $I->see('Home page');

        $config = $this->getConfig($I);
        $I->assertArraySubset(
            $data['expectedResult'],
            $config['system']['default']['catalog']['search']
        );

        $relationships = [
            'MAGENTO_CLOUD_RELATIONSHIPS' => [
                'database' => [
                    $I->getDbCredential(),
                ],
            ],
        ];

        $I->assertTrue($I->runEceToolsCommand('deploy', Docker::DEPLOY_CONTAINER, $relationships));
        $I->assertTrue($I->runEceToolsCommand('post-deploy', Docker::DEPLOY_CONTAINER, $relationships));

        $I->amOnPage('/');
        $I->see('Home page');

        $config = $this->getConfig($I);
        $I->assertArraySubset(
            ['engine' => 'mysql'],
            $config['system']['default']['catalog']['search']
        );
    }

    /**
     * @param \CliTester $I
     * @return array
     */
    private function getConfig(\CliTester $I): array
    {
        $destination = sys_get_temp_dir() . '/app/etc/env.php';
        $I->assertTrue($I->downloadFromContainer('/app/etc/env.php', $destination, Docker::DEPLOY_CONTAINER));
        return require $destination;
    }

    /**
     * @return array
     */
    protected function elasticDataProvider(): array
    {
        return [
            [
                'magento' => '2.2.8',
                'services' => [],
                'expectedResult' => ['engine' => 'mysql'],
            ],
            [
                'magento' => '2.2.8',
                'services' => ['es' => '2.4'],
                'expectedResult' => [
                    'engine' => 'elasticsearch',
                    'elasticsearch_server_hostname' => 'elasticsearch',
                    'elasticsearch_server_port' => '9200'
                ],
            ],
            [
                'magento' => '2.3.0',
                'services' => ['es' => '5.2'],
                'expectedResult' => [
                    'engine' => 'elasticsearch5',
                    'elasticsearch5_server_hostname' => 'elasticsearch',
                    'elasticsearch5_server_port' => '9200'
                ],
            ],
            [
                'magento' => '2.3.1',
                'services' => ['es' => '6.5'],
                'expectedResult' => [
                    'engine' => 'elasticsearch6',
                    'elasticsearch6_server_hostname' => 'elasticsearch',
                    'elasticsearch6_server_port' => '9200'
                ],
            ],
        ];
    }
}