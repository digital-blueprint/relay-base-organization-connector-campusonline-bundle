<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service;

use Dbp\CampusonlineApi\Helpers\ApiException;
use Dbp\CampusonlineApi\PublicRestApi\Connection;
use Dbp\CampusonlineApi\PublicRestApi\Organizations\OrganizationApi;
use Dbp\CampusonlineApi\PublicRestApi\Organizations\OrganizationResource;
use Dbp\Relay\BaseOrganizationBundle\API\OrganizationProviderInterface;
use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection\Configuration;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationName;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationNameStaging;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationStaging;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPostEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPreEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\RebuildingOrganizationCacheEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber\OrganizationEventSubscriber;
use Dbp\Relay\CoreBundle\Doctrine\QueryHelper;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\LocalData\LocalDataEventDispatcher;
use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Filter;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterException;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterTools;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterTreeBuilder;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

class OrganizationProvider implements OrganizationProviderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const DEFAULT_LANGUAGE = 'de';

    private const MAX_NUM_ORGANIZATION_UIDS_PER_REQUEST = 50;

    private ?OrganizationApi $organizationApi = null;
    private ?\Closure $isOrganizationCallback = null;

    private LocalDataEventDispatcher $localDataEventDispatcher;
    private array $config = [];
    private ?object $clientHandler = null;
    /** @var string[] */
    private array $currentResultOrganizationUids = [];
    /** @var OrganizationResource[] */
    private array $organizationResourcesRequestCache = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher)
    {
        $this->localDataEventDispatcher = new LocalDataEventDispatcher(Organization::class, $eventDispatcher);
    }

    /**
     * @internal For testing purposes only
     */
    public function reset(): void
    {
        $this->organizationApi = null;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config[Configuration::CAMPUS_ONLINE_NODE];
    }

    /**
     * @internal
     *
     * Just for unit testing
     */
    public function setClientHandler(?object $handler): void
    {
        $this->clientHandler = $handler;
        $this->organizationApi?->setClientHandler($handler);
    }

    /**
     * @param callable(CachedOrganizationStaging): bool $isOrganizationCallback
     */
    public function setIsOrganizationCallback(callable $isOrganizationCallback): void
    {
        $this->isOrganizationCallback = $isOrganizationCallback(...);
    }

    /**
     * @throws ApiException
     */
    public function checkConnection(): void
    {
        $this->getOrganizationApi()->getOrganizationsCursorBased(maxNumItems: 1);
    }

    /**
     * @throws \Throwable
     * @throws Exception
     */
    public function recreateOrganizationsCache(): void
    {
        $connection = $this->entityManager->getConnection();
        $organizationNamesStagingTable = CachedOrganizationNameStaging::TABLE_NAME;
        $organizationsStagingTable = CachedOrganizationStaging::TABLE_NAME;

        try {
            $this->onRebuildingResourceCacheCallback();

            $nextCursor = null;
            $replacementParentsForOrganizationsToDelete = [];

            do {
                $organizationsResourcePage = $this->getOrganizationApi()->getOrganizationsCursorBased([
                    'only_active' => 'true',
                ], $nextCursor, 1000);

                /** @var OrganizationResource $organizationResource */
                foreach ($organizationsResourcePage->getResources() as $organizationResource) {
                    $cachedOrganizationStaging = self::createCachedOrganizationStagingFromOrganizationResource($organizationResource);
                    if ($this->isOrganizationCallback !== null) {
                        if (false === ($this->isOrganizationCallback)($cachedOrganizationStaging)) {
                            $replacementParentsForOrganizationsToDelete[$organizationResource->getUid()] =
                                $cachedOrganizationStaging->getParentUid();
                        }
                    }

                    $this->entityManager->persist($cachedOrganizationStaging);
                }
                $this->entityManager->flush();
                $this->entityManager->clear();
            } while ($nextCursor = $organizationsResourcePage->getNextCursor());

            if ([] !== $replacementParentsForOrganizationsToDelete) {
                $uidColumn = CachedOrganizationStaging::UID;
                $parentUidColumn = CachedOrganizationStaging::PARENT_UID;

                foreach ($replacementParentsForOrganizationsToDelete as $organizationUid => $parentUid) {
                    // go back in the ancestral line until we find an ancestor that is not to be replaced/deleted (or null)
                    while ($grandparentUid = $replacementParentsForOrganizationsToDelete[$parentUid] ?? null) {
                        $parentUid = $grandparentUid;
                    }
                    $parentUid ??= 'NULL';
                    $connection->executeStatement(<<<STMT
                           UPDATE $organizationsStagingTable
                           SET $parentUidColumn = $parentUid
                           WHERE $parentUidColumn = ?;
                        STMT, [$organizationUid]);
                }

                $inClause = implode(',', array_fill(0, count($replacementParentsForOrganizationsToDelete), '?'));
                $connection->executeStatement(<<<STMT
                        DELETE FROM $organizationsStagingTable
                        WHERE $uidColumn IN ($inClause);
                    STMT, array_keys($replacementParentsForOrganizationsToDelete));
            }

            $organizationsLiveTable = CachedOrganization::TABLE_NAME;
            $organizationsTempTable = CachedOrganization::TABLE_NAME.'_old';
            $organizationNamesLiveTable = CachedOrganizationName::TABLE_NAME;
            $organizationNamesTempTable = CachedOrganizationName::TABLE_NAME.'_old';

            // swap live and staging tables
            $connection->executeStatement(<<<STMT
                RENAME TABLE
                    $organizationsLiveTable TO $organizationsTempTable,
                    $organizationsStagingTable TO $organizationsLiveTable,
                    $organizationsTempTable TO $organizationsStagingTable,
                    $organizationNamesLiveTable TO $organizationNamesTempTable,
                    $organizationNamesStagingTable TO $organizationNamesLiveTable,
                    $organizationNamesTempTable TO $organizationNamesStagingTable
                STMT);
        } catch (\Throwable $throwable) {
            $this->logger->error('failed to recreate organizations cache: '.$throwable->getMessage(), [$throwable]);
            throw $throwable;
        } finally {
            $connection->executeStatement("TRUNCATE TABLE $organizationNamesStagingTable");
            $connection->executeStatement("TRUNCATE TABLE $organizationsStagingTable");
        }
    }

    /**
     * @param array $options Available options:
     *                       * 'lang' ('de' or 'en')
     *                       * LocalData::INCLUDE_PARAMETER_NAME
     *
     * @throws ApiError
     */
    public function getOrganizationById(string $identifier, array $options = []): Organization
    {
        $options = $this->handleNewRequest($options);
        try {
            $filter = FilterTreeBuilder::create()
                ->equals('identifier', $identifier)
                ->createFilter();
        } catch (FilterException $filterException) {
            $this->logger->error('failed to build filter for person identifier query: '.$filterException->getMessage(), [$filterException]);
            throw new \RuntimeException('failed to build filter for person identifier query');
        }
        $organizations = $this->getOrganizationsInternal(1, 2, $filter, $options);

        $numOrganizations = count($organizations);
        if ($numOrganizations === 0) {
            throw ApiError::withDetails(Response::HTTP_NOT_FOUND, sprintf("organization with identifier '%s' could not be found!", $identifier));
        } elseif ($numOrganizations > 1) {
            throw new \RuntimeException(sprintf("multiple organizations found for identifier '%s'", $identifier));
        }

        return $organizations[0];
    }

    /**
     * @param array $options Available options:
     *                       * Locale::LANGUAGE_OPTION (language in ISO 639‑1 format)
     *                       * 'identifiers' The list of organizations to return
     *                       * Organization::SEARCH_PARAMETER_NAME (partial, case-insensitive text search on 'name' attribute)
     *                       * LocalData::INCLUDE_PARAMETER_NAME
     *                       * LocalData::QUERY_PARAMETER_NAME
     *
     * @return Organization[]
     *
     * @throws ApiError
     */
    public function getOrganizations(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): array
    {
        $options = $this->handleNewRequest($options);

        return $this->getOrganizationsInternal($currentPageNumber, $maxNumItemsPerPage, null, $options);
    }

    /**
     * @return OrganizationAndExtraData[]
     */
    public function getChildOrganizations(string $parentIdentifier, array $options = []): array
    {
        try {
            $filter = FilterTreeBuilder::create()
                ->equals(OrganizationEventSubscriber::PARENT_UID_SOURCE_ATTRIBUTE, $parentIdentifier)
                ->createFilter();
        } catch (FilterException $filterException) {
            throw new \RuntimeException('failed to build filter for child organizations query:'.
                $filterException->getMessage(), previous: $filterException);
        }

        return $this->getOrganizationsFromCache(1, 1000, $filter, $options);
    }

    /**
     * Gets all organizations of the current result set from the API and caches them locally, so that not every organization
     * has to be requested individually on setting local data attributes.
     */
    public function getAndCacheCurrentResultOrganizationsFromApi(): void
    {
        try {
            $currentOrganizationOffset = 0;
            while ($currentOrganizationOffset < count($this->currentResultOrganizationUids)) {
                $queryParameters = [
                    OrganizationApi::INCLUDE_CONTACT_INFO_QUERY_PARAMETER => 'true',
                    OrganizationApi::UID_QUERY_PARAMETER => array_slice(
                        $this->currentResultOrganizationUids,
                        $currentOrganizationOffset,
                        self::MAX_NUM_ORGANIZATION_UIDS_PER_REQUEST),
                ];

                try {
                    foreach ($this->getOrganizationApi()->getOrganizationsOffsetBased($queryParameters,
                        $currentOrganizationOffset, self::MAX_NUM_ORGANIZATION_UIDS_PER_REQUEST) as $organizationResource) {
                        $this->organizationResourcesRequestCache[$organizationResource->getUid()] = $organizationResource;
                    }
                    $currentOrganizationOffset += self::MAX_NUM_ORGANIZATION_UIDS_PER_REQUEST;
                } catch (ApiException $apiException) {
                    self::dispatchException($apiException);
                }
            }
        } finally {
            $this->currentResultOrganizationUids = [];
        }
    }

    public function getOrganizationFromApiCached(string $identifier): OrganizationResource
    {
        if (null === ($organizationResource = $this->organizationResourcesRequestCache[$identifier] ?? null)) {
            try {
                $organizationResource = $this->getOrganizationApi()->getOrganizationByIdentifier($identifier, [
                    OrganizationApi::INCLUDE_CONTACT_INFO_QUERY_PARAMETER => 'true']);
                $this->organizationResourcesRequestCache[$identifier] = $organizationResource;
            } catch (ApiException $apiException) {
                self::dispatchException($apiException, $identifier);
            }
        }

        return $organizationResource;
    }

    private function getOrganizationApi(): OrganizationApi
    {
        if ($this->organizationApi === null) {
            $this->organizationApi = new OrganizationApi(
                new Connection(
                    $this->config['base_url'],
                    $this->config['client_id'],
                    $this->config['client_secret']
                )
            );
            $this->organizationApi->setLogger($this->logger);
            $this->organizationApi->setClientHandler($this->clientHandler);
        }

        return $this->organizationApi;
    }

    private function onRebuildingResourceCacheCallback(): void
    {
        $this->eventDispatcher->dispatch(new RebuildingOrganizationCacheEvent($this));
    }

    /**
     * @return OrganizationAndExtraData[]
     */
    private function getOrganizationsFromCache(
        int $currentPageNumber, int $maxNumItemsPerPage, ?Filter $filter, array $options = []): array
    {
        $CACHED_ORGANIZATION_ENTITY_ALIAS = 'o';
        $CACHED_ORGANIZATION_NAME_ENTITY_ALIAS = 'o_n';

        try {
            $combinedFilter = FilterTreeBuilder::create()->createFilter();
            if ($filter) {
                $combinedFilter->combineWith($filter);
            }
            if ($searchTerm = $options[Organization::SEARCH_PARAMETER_NAME] ?? null) {
                $combinedFilter->combineWith(
                    FilterTreeBuilder::create()
                        ->iContains($CACHED_ORGANIZATION_NAME_ENTITY_ALIAS.'.'.CachedOrganizationName::NAME,
                            $searchTerm)
                        ->equals($CACHED_ORGANIZATION_NAME_ENTITY_ALIAS.'.'.CachedOrganizationName::LANGUAGE_TAG,
                            Options::getLanguage($options))
                        ->createFilter());
            }
            if ($filterFromOptions = Options::getFilter($options)) {
                $combinedFilter->combineWith($filterFromOptions);
            }

            $queryBuilder = $this->entityManager->createQueryBuilder();
            $queryBuilder
                ->select($CACHED_ORGANIZATION_ENTITY_ALIAS)
                ->from(CachedOrganization::class, $CACHED_ORGANIZATION_ENTITY_ALIAS)
                ->innerJoin(CachedOrganizationName::class, $CACHED_ORGANIZATION_NAME_ENTITY_ALIAS, Join::WITH,
                    $CACHED_ORGANIZATION_ENTITY_ALIAS.'.'.CachedOrganization::UID." = $CACHED_ORGANIZATION_NAME_ENTITY_ALIAS.organization");

            if (false === $combinedFilter->isEmpty()) {
                $pathMappingOrg = array_map(
                    function (string $columnName) use ($CACHED_ORGANIZATION_ENTITY_ALIAS): string {
                        return $CACHED_ORGANIZATION_ENTITY_ALIAS.'.'.$columnName;
                    }, CachedOrganization::BASE_ENTITY_ATTRIBUTE_MAPPING);

                foreach (array_keys(CachedOrganization::LOCAL_DATA_SOURCE_ATTRIBUTES) as $attributeName) {
                    $pathMappingOrg[$attributeName] = $CACHED_ORGANIZATION_ENTITY_ALIAS.'.'.$attributeName;
                }

                $pathMappingOrgName = array_map(
                    function (string $columnName) use ($CACHED_ORGANIZATION_NAME_ENTITY_ALIAS): string {
                        return $CACHED_ORGANIZATION_NAME_ENTITY_ALIAS.'.'.$columnName;
                    }, CachedOrganizationName::BASE_ENTITY_ATTRIBUTE_MAPPING);

                FilterTools::mapConditionPaths($combinedFilter, array_merge($pathMappingOrg, $pathMappingOrgName));
                QueryHelper::addFilter($queryBuilder, $combinedFilter);
            }

            $paginator = new Paginator($queryBuilder->getQuery());
            $paginator->getQuery()
                ->setFirstResult(Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage))
                ->setMaxResults($maxNumItemsPerPage);

            $organizationAndExtraDataPage = [];
            /** @var CachedOrganization $cachedOrganization */
            foreach ($paginator as $cachedOrganization) {
                $organizationAndExtraDataPage[] =
                    self::createOrganizationAndExtraDataFromCachedOrganization($cachedOrganization, $options);
            }

            return $organizationAndExtraDataPage;
        } catch (\Throwable $exception) {
            $this->logger->error('failed to get organizations from cache: '.$exception->getMessage(), [$exception]);
            throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, 'failed to get organizations');
        }
    }

    private function postProcessOrganization(OrganizationAndExtraData $organizationAndExtraData, array $options): Organization
    {
        $postEvent = new OrganizationPostEvent(
            $organizationAndExtraData->getOrganization(), $organizationAndExtraData->getExtraData(), $options);
        $this->localDataEventDispatcher->dispatch($postEvent);

        return $organizationAndExtraData->getOrganization();
    }

    private function handleNewRequest(array $options): array
    {
        $this->localDataEventDispatcher->onNewOperation($options);

        $preEvent = new OrganizationPreEvent($options);
        $this->localDataEventDispatcher->dispatch($preEvent);

        return $preEvent->getOptions();
    }

    private static function createOrganizationAndExtraDataFromCachedOrganization(CachedOrganization $cachedOrganization, array $options): OrganizationAndExtraData
    {
        $organization = new Organization();
        $organization->setIdentifier($cachedOrganization->getUid());
        /** @var CachedOrganizationName $cachedOrganizationName */
        foreach ($cachedOrganization->getNames() as $cachedOrganizationName) {
            if ($cachedOrganizationName->getLanguageTag() === Options::getLanguage($options)) {
                $organization->setName($cachedOrganizationName->getName());
            }
        }

        return new OrganizationAndExtraData($organization, [
            OrganizationEventSubscriber::UID_SOURCE_ATTRIBUTE => $cachedOrganization->getUid(),
            OrganizationEventSubscriber::CODE_SOURCE_ATTRIBUTE => $cachedOrganization->getCode(),
            OrganizationEventSubscriber::GROUP_KEY_SOURCE_ATTRIBUTE => $cachedOrganization->getGroupKey(),
            OrganizationEventSubscriber::PARENT_UID_SOURCE_ATTRIBUTE => $cachedOrganization->getParentUid(),
            OrganizationEventSubscriber::TYPE_UID_SOURCE_ATTRIBUTE => $cachedOrganization->getTypeUid(),
        ]);
    }

    private static function createCachedOrganizationStagingFromOrganizationResource(
        OrganizationResource $organizationResource): CachedOrganizationStaging
    {
        $cachedOrganization = new CachedOrganizationStaging();
        $cachedOrganization->setUid($organizationResource->getUid());
        $cachedOrganization->setCode($organizationResource->getCode());
        $cachedOrganization->setGroupKey($organizationResource->getGroupKey());
        $cachedOrganization->setParentUid($organizationResource->getParentUid());
        $cachedOrganization->setTypeUid($organizationResource->getTypeUid());
        if ($organizationResource->getUid() === '123') {
            dump($organizationResource->getResourceData());
        }

        foreach ($organizationResource->getName() as $languageTag => $name) {
            $cachedOrganizationName = new CachedOrganizationNameStaging();
            $cachedOrganizationName->setLanguageTag($languageTag);
            $cachedOrganizationName->setName($name);
            $cachedOrganizationName->setOrganization($cachedOrganization);
            $cachedOrganization->getNames()->add($cachedOrganizationName);
        }

        return $cachedOrganization;
    }

    /**
     * NOTE: Campusonline returns '401 unauthorized' for some resources that are not found. So we can't
     * safely return '404' in all cases because '401' is also returned by CO if e.g. the token is not valid.
     *
     * @throws ApiError
     * @throws ApiException
     */
    private static function dispatchException(ApiException $apiException, ?string $identifier = null): ApiError
    {
        if ($apiException->isHttpResponseCode()) {
            if ($apiException->getCode() === Response::HTTP_NOT_FOUND && $identifier !== null) {
                return new ApiError(Response::HTTP_NOT_FOUND, sprintf("Id '%s' could not be found!", $identifier));
            }
            if ($apiException->getCode() >= 500) {
                return new ApiError(Response::HTTP_BAD_GATEWAY, 'failed to get organizations from Campusonline');
            }
        }

        return new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'failed to get organization(s): '.$apiException->getMessage());
    }

    /**
     * @return Organization[]
     */
    private function getOrganizationsInternal(int $currentPageNumber, int $maxNumItemsPerPage, ?Filter $filter, array $options): array
    {
        $organizationAndExtraDataPage = $this->getOrganizationsFromCache($currentPageNumber, $maxNumItemsPerPage, $filter, $options);

        $this->currentResultOrganizationUids = array_map(
            fn (OrganizationAndExtraData $organizationAndExtraData) => $organizationAndExtraData->getOrganization()->getIdentifier(),
            $organizationAndExtraDataPage);

        return array_map(
            fn (OrganizationAndExtraData $organizationAndExtraData) => $this->postProcessOrganization($organizationAndExtraData, $options),
            $organizationAndExtraDataPage);
    }
}
