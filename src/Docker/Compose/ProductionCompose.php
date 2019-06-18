<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MagentoCloud\Docker\Compose;

use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;
use Illuminate\Contracts\Config\Repository;
use Magento\MagentoCloud\Docker\Service\Config;
use Magento\MagentoCloud\Docker\ComposeInterface;
use Magento\MagentoCloud\Docker\Config\Converter;
use Magento\MagentoCloud\Docker\ConfigurationMismatchException;
use Magento\MagentoCloud\Docker\Service\ServiceFactory;
use Magento\MagentoCloud\Filesystem\FileList;
use Magento\MagentoCloud\Service\Service;

/**
 * Production compose configuration.
 *
 * @codeCoverageIgnore
 */
class ProductionCompose implements ComposeInterface
{
    const DEFAULT_NGINX_VERSION = 'latest';
    const DEFAULT_VARNISH_VERSION = 'latest';
    const DEFAULT_TLS_VERSION = 'latest';

    const DIR_MAGENTO = '/app';

    const DEFAULT_PHP_EXTENSIONS = [
        'bcmath',
        'bz2',
        'calendar',
        'exif',
        'gd',
        'gettext',
        'intl',
        'mysqli',
        'pcntl',
        'pdo_mysql',
        'soap',
        'sockets',
        'sysvmsg',
        'sysvsem',
        'sysvshm',
        'opcache',
        'zip',
    ];

    const AVAILABLE_PHP_EXTENSIONS = [
        'bcmath' => '7.*',
        'bz2' => '7.*',
        'calendar' => '7.*',
        'exif' => '7.*',
        'gd' => '7.*',
        'geoip' => '7.*',
        'gettext' => '7.*',
        'gmp' => '7.*',
        'igbinary' => '7.*',
        'imagick' => '7.*',
        'imap' => '7.*',
        'intl' => '7.*',
        'ldap' => '7.*',
        'mailparse' => '7.*',
        'mcrypt' => '7.0.* | 7.1.*',
        'msgpack' => '7.*',
        'mysqli' => '7.*',
        'oauth' => '7.*',
        'opcache' => '7.*',
        'pdo_mysql' => '7.*',
        'propro' => '7.*',
        'pspell' => '7.*',
        'raphf' => '7.*',
        'recode' => '7.*',
        'redis' => '7.*',
        'shmop' => '7.*',
        'soap' => '7.*',
        'sockets' => '7.*',
        'sodium' => '7.*',
        'ssh2' => '7.*',
        'sysvmsg' => '7.*',
        'sysvsem' => '7.*',
        'sysvshm' => '7.*',
        'tidy' => '7.*',
        'xdebug' => '7.*',
        'xmlrpc' => '7.*',
        'xsl' => '7.*',
        'yaml' => '7.*',
        'zip' => '7.*',
        'pcntl' => '7.*',
    ];

    /**
     * @var ServiceFactory
     */
    private $serviceFactory;

    /**
     * @var FileList
     */
    private $fileList;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Converter
     */
    private $converter;

    /**
     * @var VersionParser
     */
    private $versionParser;

    /**
     * @param ServiceFactory $serviceFactory
     * @param FileList $fileList
     * @param Config $config
     * @param Converter $converter
     * @param VersionParser $versionParser
     */
    public function __construct(
        ServiceFactory $serviceFactory,
        FileList $fileList,
        Config $config,
        Converter $converter,
        VersionParser $versionParser
    ) {
        $this->serviceFactory = $serviceFactory;
        $this->fileList = $fileList;
        $this->config = $config;
        $this->converter = $converter;
        $this->versionParser = $versionParser;
    }

    /**
     * {@inheritdoc}
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function build(Repository $config): array
    {
        $phpVersion = $config->get(Service::NAME_PHP, '') ?: $this->config->getPhpVersion();
        $dbVersion = $config->get(Service::NAME_DB, '') ?: $this->config->getServiceVersion(Service::NAME_DB);

        $services = [
            'db' => $this->serviceFactory->create(
                ServiceFactory::SERVICE_DB,
                $dbVersion,
                [
                    'ports' => [3306],
                    'volumes' => [
                        '/var/lib/mysql',
                        './.docker/mysql/docker-entrypoint-initdb.d:/docker-entrypoint-initdb.d',
                    ],
                    'environment' => [
                        'MYSQL_ROOT_PASSWORD=magento2',
                        'MYSQL_DATABASE=magento2',
                        'MYSQL_USER=magento2',
                        'MYSQL_PASSWORD=magento2',
                    ],
                ]
            )
        ];

        $redisVersion = $config->get(Service::NAME_REDIS) ?: $this->config->getServiceVersion(Service::NAME_REDIS);

        if ($redisVersion) {
            $services['redis'] = $this->serviceFactory->create(
                ServiceFactory::SERVICE_REDIS,
                $redisVersion
            );
        }

        $esVersion = $config->get(Service::NAME_ELASTICSEARCH)
            ?: $this->config->getServiceVersion(Service::NAME_ELASTICSEARCH);

        if ($esVersion) {
            $services['elasticsearch'] = $this->serviceFactory->create(
                ServiceFactory::SERVICE_ELASTICSEARCH,
                $esVersion
            );
        }

        $nodeVersion = $config->get(Service::NAME_NODE);

        if ($nodeVersion) {
            $services['node'] = $this->serviceFactory->create(
                ServiceFactory::SERVICE_NODE,
                $nodeVersion,
                ['volumes' => $this->getMagentoVolumes(false)]
            );
        }

        $rabbitMQVersion = $config->get(Service::NAME_RABBITMQ)
            ?: $this->config->getServiceVersion(Service::NAME_RABBITMQ);

        if ($rabbitMQVersion) {
            $services['rabbitmq'] = $this->serviceFactory->create(
                ServiceFactory::SERVICE_RABBIT_MQ,
                $rabbitMQVersion
            );
        }

        $cliDepends = array_keys($services);

        $services['fpm'] = $this->serviceFactory->create(
            ServiceFactory::SERVICE_FPM,
            $phpVersion,
            [
                'ports' => [9000],
                'depends_on' => ['db'],
                'extends' => 'generic',
                'volumes' => $this->getMagentoVolumes(true),
            ]
        );
        $services['build'] = $this->getCliService($phpVersion, false, $cliDepends, 'build.magento2.docker');
        $services['deploy'] = $this->getCliService($phpVersion, true, $cliDepends, 'deploy.magento2.docker');
        $services['web'] = $this->serviceFactory->create(
            ServiceFactory::SERVICE_NGINX,
            $config->get(Service::NAME_NGINX, self::DEFAULT_NGINX_VERSION),
            [
                'hostname' => 'web.magento2.docker',
                'depends_on' => ['fpm'],
                'extends' => 'generic',
                'volumes' => $this->getMagentoVolumes(true),
            ]
        );
        $services['varnish'] = $this->serviceFactory->create(
            ServiceFactory::SERVICE_VARNISH,
            self::DEFAULT_VARNISH_VERSION,
            ['depends_on' => ['web']]
        );
        $services['tls'] = $this->serviceFactory->create(
            ServiceFactory::SERVICE_TLS,
            self::DEFAULT_TLS_VERSION,
            ['depends_on' => ['varnish']]
        );

        $phpExtensions = array_diff(
            array_merge(self::DEFAULT_PHP_EXTENSIONS, $this->config->getEnabledPhpExtensions()),
            $this->config->getDisabledPhpExtensions()
        );

        foreach ($phpExtensions as $phpExtName => $phpExtVersion) {
            if (!in_array($phpExtName, self::AVAILABLE_PHP_EXTENSIONS)) {
                $message = "PHP extension $phpExtName not supported. Fix it in your .magento.app.yaml";
                throw new ConfigurationMismatchException($message);
            }
            $phpVersionConstraint = new Constraint('==', $this->versionParser->normalize($phpVersion));
            if ($phpVersionConstraint->matches($this->versionParser->parseConstraints($phpExtVersion))) {
                $message = "Extension $phpExtName not available for PHP version $phpExtVersion";
                throw new ConfigurationMismatchException($message);
            }
        }
        $services['cron'] = $this->getCronCliService($phpVersion, true, $cliDepends, 'cron.magento2.docker');
        $services['generic'] = [
            'image' => 'alpine',
            'environment' => $this->converter->convert(array_merge(
                $this->getVariables(),
                ['PHP_EXTENSIONS' => implode(' ', $phpExtensions)]
            )),
            'env_file' => [
                './.docker/config.env',
            ],
        ];

        $volumeConfig = [];

        return [
            'version' => '2',
            'services' => $services,
            'volumes' => [
                'magento' => [
                    'driver_opts' => [
                        'type' => 'none',
                        'device' => '${PWD}',
                        'o' => 'bind'
                    ]
                ],
                'magento-vendor' => $volumeConfig,
                'magento-generated' => $volumeConfig,
                'magento-setup' => $volumeConfig,
                'magento-var' => $volumeConfig,
                'magento-etc' => $volumeConfig,
                'magento-static' => $volumeConfig,
                'magento-media' => $volumeConfig,
            ]
        ];
    }

    /**
     * @param string $version
     * @param bool $isReadOnly
     * @param array $depends
     * @param string $hostname
     * @return array
     * @throws ConfigurationMismatchException
     */
    private function getCronCliService(string $version, bool $isReadOnly, array $depends, string $hostname): array
    {
        $config = $this->getCliService($version, $isReadOnly, $depends, $hostname);

        if ($cronConfig = $this->config->getCron()) {
            $preparedCronConfig = [];

            foreach ($cronConfig as $job) {
                $preparedCronConfig[] = sprintf(
                    '%s root cd %s && %s >> %s/var/log/cron.log',
                    $job['spec'],
                    self::DIR_MAGENTO,
                    str_replace('php ', '/usr/local/bin/php ', $job['cmd']),
                    self::DIR_MAGENTO
                );
            }

            $config['environment'] = [
                'CRONTAB' => implode(PHP_EOL, $preparedCronConfig)
            ];
        }

        $config['command'] = 'run-cron';

        return $config;
    }

    /**
     * @param string $version
     * @param bool $isReadOnly
     * @param array $depends
     * @param string $hostname
     * @return array
     * @throws ConfigurationMismatchException
     */
    private function getCliService(
        string $version,
        bool $isReadOnly,
        array $depends,
        string $hostname
    ): array {
        $config = $this->serviceFactory->create(
            ServiceFactory::SERVICE_CLI,
            $version,
            [
                'hostname' => $hostname,
                'depends_on' => $depends,
                'extends' => 'generic',
                'volumes' => array_merge(
                    $this->getMagentoVolumes($isReadOnly),
                    $this->getComposerVolumes(),
                    [
                        './.docker/mnt:/mnt',
                        './.docker/tmp:/tmp'
                    ]
                )
            ]
        );

        return $config;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->fileList->getMagentoDockerCompose();
    }

    /**
     * @param bool $isReadOnly
     * @return array
     */
    protected function getMagentoVolumes(bool $isReadOnly): array
    {
        $flag = $isReadOnly ? ':ro' : ':rw';

        return [
            'magento:' . self::DIR_MAGENTO . $flag,
            'magento-vendor:' . self::DIR_MAGENTO . '/vendor' . $flag,
            'magento-generated:' . self::DIR_MAGENTO . '/generated' . $flag,
            'magento-setup:' . self::DIR_MAGENTO . '/setup' . $flag,
            'magento-var:' . self::DIR_MAGENTO . '/var:delegated',
            'magento-etc:' . self::DIR_MAGENTO . '/app/etc:delegated',
            'magento-static:' . self::DIR_MAGENTO . '/pub/static:delegated',
            'magento-media:' . self::DIR_MAGENTO . '/pub/media:delegated',
        ];
    }

    /***
     * @return array
     */
    private function getComposerVolumes(): array
    {
        $composeCacheDirectory = file_exists(getenv('HOME') . '/.cache/composer')
            ? '~/.cache/composer'
            : '~/.composer/cache';

        return [
            $composeCacheDirectory . ':/root/.composer/cache:delegated',
        ];
    }

    /**
     * @return array
     */
    protected function getVariables(): array
    {
        return [
            'PHP_MEMORY_LIMIT' => '2048M',
            'UPLOAD_MAX_FILESIZE' => '64M',
            'MAGENTO_ROOT' => self::DIR_MAGENTO,
            # Name of your server in IDE
            'PHP_IDE_CONFIG' => 'serverName=magento_cloud_docker',
            # Docker host for developer environments, can be different for your OS
            'XDEBUG_CONFIG' => 'remote_host=host.docker.internal',
        ];
    }
}
