<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service;

use Dbp\CampusonlineApi\Helpers\Pagination;
use Dbp\CampusonlineApi\LegacyWebService\Api;
use Dbp\CampusonlineApi\LegacyWebService\ApiException;
use Dbp\CampusonlineApi\LegacyWebService\Organization\OrganizationUnitApi;
use Dbp\CampusonlineApi\LegacyWebService\Organization\OrganizationUnitData;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\RebuildingOrganizationCacheEvent;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrganizationApi implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ?Api $api = null;
    private ?OrganizationUnitApi $orgUnitApi = null;
    private array $config = [];
    private ?object $clientHandler = null;
    private ?CacheItemPoolInterface $cachePool = null;
    private int $cacheTTL = 0;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function setCache(?CacheItemPoolInterface $cachePool, int $ttl): void
    {
        $this->cachePool = $cachePool;
        $this->cacheTTL = $ttl;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function setClientHandler(?object $handler): void
    {
        $this->clientHandler = $handler;
        if ($this->api !== null) {
            $this->api->setClientHandler($handler);
        }
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        if ($this->api !== null) {
            $this->api->setLogger($logger);
        }
    }

    /**
     * @throws ApiException
     */
    public function checkConnection(): void
    {
        $this->getOrganizationUnitApi()->checkConnection();
    }

    /**
     * @throws ApiException
     */
    public function getOrganizationById(string $identifier, array $options = []): OrganizationUnitData
    {
        return $this->getOrganizationUnitApi()->getOrganizationUnitById($identifier, $options);
    }

    /**
     * @return OrganizationUnitData[]
     *
     * @throws ApiException
     */
    public function getOrganizations(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): array
    {
        $options[Pagination::CURRENT_PAGE_NUMBER_PARAMETER_NAME] = $currentPageNumber;
        $options[Pagination::MAX_NUM_ITEMS_PER_PAGE_PARAMETER_NAME] = $maxNumItemsPerPage;

        return $this->getOrganizationUnitApi()->getOrganizationUnits($options)->getItems();
    }

    private function getApi(): Api
    {
        if ($this->api === null) {
            $accessToken = $this->config['api_token'] ?? '';
            $baseUrl = $this->config['api_url'] ?? '';
            $rootOrgUnitId = $this->config['org_root_id'] ?? '';

            $this->api = new Api($baseUrl, $accessToken, $rootOrgUnitId,
                $this->logger, $this->cachePool, $this->cacheTTL, $this->clientHandler);
        }

        return $this->api;
    }

    private function getOrganizationUnitApi(): OrganizationUnitApi
    {
        if ($this->orgUnitApi === null) {
            $this->orgUnitApi = $this->getApi()->OrganizationUnit();
            $this->orgUnitApi->setOnRebuildingResourceCacheCallback(function () {
                $this->onRebuildingResourceCacheCallback();
            });
        }

        return $this->orgUnitApi;
    }

    private function onRebuildingResourceCacheCallback(): void
    {
        $this->eventDispatcher->dispatch(new RebuildingOrganizationCacheEvent($this));
    }

    public function setIsOrganizationCallback($isOrganizationCallback): void
    {
        $this->getOrganizationUnitApi()->setIsResourceNodeCallback($isOrganizationCallback);
    }
}
