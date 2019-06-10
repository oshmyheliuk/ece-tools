<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MagentoCloud\Command\Docker;

use Magento\MagentoCloud\Config\Environment;
use Magento\MagentoCloud\Config\RepositoryFactory;
use Magento\MagentoCloud\Docker\ComposeFactory;
use Magento\MagentoCloud\Docker\ComposeInterface;
use Magento\MagentoCloud\Docker\ConfigurationMismatchException;
use Magento\MagentoCloud\Filesystem\Driver\File;
use Magento\MagentoCloud\Filesystem\FileSystemException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Docker build for internal integration testing.
 *
 * @codeCoverageIgnore
 */
class BuildIntegration extends Command
{
    const NAME = 'docker:build:integration';
    const ARGUMENT_VERSION = 'version';
    const OPTION_PHP = 'php';
    const OPTION_NGINX = 'nginx';
    const OPTION_DB = 'db';

    /**
     * @var ComposeFactory
     */
    private $builderFactory;

    /**
     * @var File
     */
    private $file;

    /**
     * @var RepositoryFactory
     */
    private $configFactory;

    /**
     * @var Environment
     */
    private $environment;

    /**
     * @param ComposeFactory $composeFactory
     * @param File $file
     * @param RepositoryFactory $configFactory
     * @param Environment $environment
     */
    public function __construct(
        ComposeFactory $composeFactory,
        File $file,
        RepositoryFactory $configFactory,
        Environment $environment
    ) {
        $this->builderFactory = $composeFactory;
        $this->file = $file;
        $this->configFactory = $configFactory;
        $this->environment = $environment;

        parent::__construct();
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this->setName(self::NAME)
            ->setDescription('Build test docker configuration')
            ->addArgument(
                self::ARGUMENT_VERSION,
                InputArgument::REQUIRED,
                sprintf(
                    'Version of integration framework configuration (%s/%s)',
                    ComposeFactory::COMPOSE_INTEGRATION,
                    ComposeFactory::COMPOSE_FUNCTIONAL
                )
            )->addOption(
                self::OPTION_PHP,
                null,
                InputOption::VALUE_OPTIONAL,
                'PHP version',
                '7.2'
            )->addOption(
                self::OPTION_DB,
                null,
                InputOption::VALUE_OPTIONAL,
                'DB version',
                '10.2'
            )->addOption(
                self::OPTION_NGINX,
                null,
                InputOption::VALUE_OPTIONAL,
                'Nginx version',
                'latest'
            );

        parent::configure();
    }

    /**
     * {@inheritdoc}
     *
     * @throws FileSystemException
     * @throws ConfigurationMismatchException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $strategy = $input->getArgument(self::ARGUMENT_VERSION);
        $allowedStrategies = [ComposeFactory::COMPOSE_INTEGRATION];

        if (!in_array($strategy, $allowedStrategies, true)) {
            throw new ConfigurationMismatchException('Wrong framework version');
        }

        $builder = $this->builderFactory->create($strategy);
        $config = $this->configFactory->create();

        $map = [
            self::OPTION_PHP => ComposeInterface::PHP_VERSION,
            self::OPTION_DB => ComposeInterface::DB_VERSION,
            self::OPTION_NGINX => ComposeInterface::NGINX_VERSION,
        ];

        array_walk($map, function ($key, $option) use ($config, $input) {
            $config->set($key, $input->getOption($option));
        });

        $this->file->filePutContents(
            $builder->getPath(),
            Yaml::dump($builder->build($config), 4, 2)
        );

        $output->writeln('<info>Configuration was built</info>');
    }

    /**
     * @inheritdoc
     */
    public function isEnabled(): bool
    {
        return !$this->environment->isMasterBranch();
    }
}
