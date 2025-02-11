<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\MagentoCloud\Docker\Service;

use Magento\MagentoCloud\Docker\Config\Reader;
use Magento\MagentoCloud\Filesystem\FileSystemException;
use Magento\MagentoCloud\Docker\ConfigurationMismatchException;
use Illuminate\Contracts\Config\Repository;
use Magento\MagentoCloud\Service\Service;

/**
 * Retrieve Service versions/configs from Cloud configuration.
 */
class Config
{
    /**
     * List of services which can be configured in Cloud docker
     *
     * @var array
     */
    private static $configurableServices = [
        Service::NAME_PHP => 'php',
        Service::NAME_DB => 'mysql',
        Service::NAME_NGINX => 'nginx',
        Service::NAME_REDIS => 'redis',
        Service::NAME_ELASTICSEARCH => 'elasticsearch',
        Service::NAME_RABBITMQ => 'rabbitmq',
        Service::NAME_NODE => 'node'
    ];

    /**
     * @var Reader
     */
    private $reader;

    /**
     * @param Reader $reader
     */
    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * Retrieves service versions set in configuration files.
     * Returns null if neither of services is configured or provided in $customVersions.
     *
     * Example of return:
     *
     * ```php
     *  [
     *      'elasticsearch' => '5.6',
     *      'db' => '10.0'
     *  ];
     * ```
     *
     * @param Repository $customVersions custom version which overwrite values from configuration files
     * @return array List of services
     * @throws ConfigurationMismatchException
     */
    public function getAllServiceVersions(Repository $customVersions): array
    {
        $configuredVersions = [];

        foreach (self::$configurableServices as $serviceName) {
            $version = $customVersions->get($serviceName) ?: $this->getServiceVersion($serviceName);
            if ($version) {
                $configuredVersions[$serviceName] = $version;
            }
        }

        return $configuredVersions;
    }

    /**
     * Retrieves service version set in configuration files.
     * Returns null if service was not configured.
     *
     * @param string $serviceName Name of service version need to retrieve
     * @return string|null
     * @throws ConfigurationMismatchException
     */
    public function getServiceVersion(string $serviceName)
    {
        try {
            $version = $serviceName === Service::NAME_PHP
                ? $this->getPhpVersion()
                : $this->reader->read()['services'][$serviceName]['version'] ?? null;

            return $version;
        } catch (FileSystemException $exception) {
            throw new ConfigurationMismatchException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Retrieve version of PHP
     *
     * @return string
     * @throws ConfigurationMismatchException when PHP is not configured
     */
    public function getPhpVersion(): string
    {
        try {
            $config = $this->reader->read();
            list($type, $version) = explode(':', $config['type']);
        } catch (FileSystemException $exception) {
            throw new ConfigurationMismatchException($exception->getMessage(), $exception->getCode(), $exception);
        }

        if ($type !== Service::NAME_PHP) {
            throw new ConfigurationMismatchException(sprintf(
                'Type "%s" is not supported',
                $type
            ));
        }

        /**
         * We don't support release candidates.
         */
        return rtrim($version, '-rc');
    }

    /**
     * Retrieves cron configuration.
     *
     * @return array
     * @throws ConfigurationMismatchException
     */
    public function getCron(): array
    {
        try {
            return $this->reader->read()['crons'] ?? [];
        } catch (FileSystemException $exception) {
            throw new ConfigurationMismatchException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }
}
