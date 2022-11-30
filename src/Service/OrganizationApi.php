<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service;

use Dbp\CampusonlineApi\Helpers\Pagination;
use Dbp\CampusonlineApi\LegacyWebService\Api;
use Dbp\CampusonlineApi\LegacyWebService\ApiException;
use Dbp\CampusonlineApi\LegacyWebService\Organization\OrganizationUnitData;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class OrganizationApi implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var Api */
    private $api;

    /** @var array */
    private $config;

    /** @var object|null */
    private $clientHandler;

    /** @var CacheItemPoolInterface|null */
    private $cachePool;

    /** @var int */
    private $cacheTTL;

    public function __construct()
    {
        $this->config = [];
    }

    public function setCache(?CacheItemPoolInterface $cachePool, int $ttl)
    {
        $this->cachePool = $cachePool;
        $this->cacheTTL = $ttl;
    }

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    public function setClientHandler(?object $handler)
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
    public function checkConnection()
    {
        $this->getApi()->OrganizationUnit()->checkConnection();
    }

    /**
     * @throws ApiException
     */
    public function getOrganizationById(string $identifier, array $options = []): OrganizationUnitData
    {
        return $this->getApi()->OrganizationUnit()->getOrganizationUnitById($identifier, $options);
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

        return $this->getApi()->OrganizationUnit()->getOrganizationUnits($options)->getItems();
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
}
