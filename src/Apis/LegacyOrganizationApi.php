<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Apis;

use Dbp\CampusonlineApi\Helpers\Filters;
use Dbp\CampusonlineApi\Helpers\Pagination;
use Dbp\CampusonlineApi\LegacyWebService\Api;
use Dbp\CampusonlineApi\LegacyWebService\ApiException;
use Dbp\CampusonlineApi\LegacyWebService\Organization\OrganizationUnitApi;
use Dbp\CampusonlineApi\LegacyWebService\Organization\OrganizationUnitData;
use Dbp\CampusonlineApi\LegacyWebService\ResourceApi;
use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\RebuildingOrganizationCacheEvent;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class LegacyOrganizationApi implements OrganizationApiInterface
{
    private Api $api;
    private OrganizationUnitApi $organizationUnitApi;

    public function __construct(private readonly EventDispatcherInterface $eventDispatcher,
        array $config, ?CacheItemPoolInterface $cachePool = null, int $cacheTTL = 0, ?LoggerInterface $logger = null)
    {
        $baseUrl = $config['api_url'] ?? '';
        $accessToken = $config['api_token'] ?? '';
        $rootOrgUnitId = $config['org_root_id'] ?? '';

        $this->api = new Api($baseUrl, $accessToken, $rootOrgUnitId,
            $logger, $cachePool, $cacheTTL);
        $this->organizationUnitApi = $this->api->OrganizationUnit();
        $this->organizationUnitApi->setOnRebuildingResourceCacheCallback(function () {
            $this->onRebuildingResourceCacheCallback();
        });
    }

    public function setClientHandler(?object $handler): void
    {
        $this->api->setClientHandler($handler);
    }

    /**
     * @throws ApiException
     */
    public function checkConnection(): void
    {
        $this->organizationUnitApi->checkConnection();
    }

    public function recreateOrganizationsCache(): void
    {
    }

    public function getOrganizationById(string $identifier, array $options = []): OrganizationAndExtraData
    {
        return self::createOrganizationAndExtraDataFromOrganizationUnitData(
            $this->organizationUnitApi->getOrganizationUnitById($identifier, $options));
    }

    public function getOrganizations(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): iterable
    {
        if ($searchParameter = $options[Organization::SEARCH_PARAMETER_NAME] ?? null) {
            unset($options[Organization::SEARCH_PARAMETER_NAME]);
            ResourceApi::addFilter($options,
                OrganizationUnitData::NAME_ATTRIBUTE, Filters::CONTAINS_CI_OPERATOR, $searchParameter);
        }

        $options[Pagination::CURRENT_PAGE_NUMBER_PARAMETER_NAME] = $currentPageNumber;
        $options[Pagination::MAX_NUM_ITEMS_PER_PAGE_PARAMETER_NAME] = $maxNumItemsPerPage;

        foreach ($this->organizationUnitApi->getOrganizationUnits($options)->getItems() as $organizationUnitData) {
            yield self::createOrganizationAndExtraDataFromOrganizationUnitData($organizationUnitData);
        }
    }

    private static function createOrganizationAndExtraDataFromOrganizationUnitData(OrganizationUnitData $organizationUnitData): OrganizationAndExtraData
    {
        $organization = new Organization();
        $organization->setIdentifier($organizationUnitData->getIdentifier());
        $organization->setName($organizationUnitData->getName());

        return new OrganizationAndExtraData($organization, $organizationUnitData->getData());
    }

    private function onRebuildingResourceCacheCallback(): void
    {
        $this->eventDispatcher->dispatch(new RebuildingOrganizationCacheEvent($this));
    }

    public function setIsOrganizationCallback(?callable $isOrganizationIsOrganizationCallback): void
    {
        $this->organizationUnitApi->setIsResourceNodeCallback($isOrganizationIsOrganizationCallback);
    }
}
