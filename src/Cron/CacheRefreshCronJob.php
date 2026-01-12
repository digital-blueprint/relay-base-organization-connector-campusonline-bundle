<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Cron;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection\Configuration;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Dbp\Relay\CoreBundle\Cron\CronJobInterface;
use Dbp\Relay\CoreBundle\Cron\CronOptions;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

class CacheRefreshCronJob implements CronJobInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const DEFAULT_INTERVAL = '15 3 * * *'; // Daily at 3:15 AM

    private string $interval = self::DEFAULT_INTERVAL;

    public function __construct(
        private readonly OrganizationProvider $organizationProvider)
    {
    }

    public function setConfig(array $config): void
    {
        $this->interval = $config[Configuration::CACHE_REFRESH_INTERVAL_NODE] ?? self::DEFAULT_INTERVAL;
    }

    public function getName(): string
    {
        return 'BaseRoom Cache Refresh';
    }

    public function getInterval(): string
    {
        return $this->interval;
    }

    public function run(CronOptions $options): void
    {
        try {
            $this->organizationProvider->recreateOrganizationsCache();
        } catch (\Throwable $throwable) {
            $this->logger->error('Error refreshing base organization cache: '.$throwable->getMessage(), [
                'exception' => $throwable,
            ]);
        }
    }
}
