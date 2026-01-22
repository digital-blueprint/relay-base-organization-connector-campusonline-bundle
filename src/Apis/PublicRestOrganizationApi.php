<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Apis;

use Dbp\CampusonlineApi\Helpers\ApiException;
use Dbp\CampusonlineApi\PublicRestApi\Connection;
use Dbp\CampusonlineApi\PublicRestApi\Organizations\OrganizationApi;
use Dbp\CampusonlineApi\PublicRestApi\Organizations\OrganizationResource;
use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationName;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\RebuildingOrganizationCacheEvent;
use Dbp\Relay\CoreBundle\Doctrine\QueryHelper;
use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterException;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterTreeBuilder;
use Dbp\Relay\CoreBundle\Rest\Query\Pagination\Pagination;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

class PublicRestOrganizationApi implements OrganizationApiInterface
{
    public const INCLUDE_ORGANIZATION = 0;
    public const IGNORE_ORGANIZATION = 1;
    public const IGNORE_ORGANIZATION_AND_ITS_CHILDREN = 2;

    private OrganizationApi $organizationApi;
    private ?LoggerInterface $logger;
    private ?\Closure $isOrganigramOrganizationCallback = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        array $config,
        ?LoggerInterface $logger = null)
    {
        $this->organizationApi = new OrganizationApi(
            new Connection(
                $config['base_url'],
                $config['client_id'],
                $config['client_secret']
            )
        );

        $this->logger = $logger;
        if ($this->logger !== null) {
            $this->organizationApi->setLogger($logger);
        }
    }

    public function checkConnection(): void
    {
        $this->organizationApi->getOrganizationsCursorBased(maxNumItems: 1);
    }

    public function setClientHandler(?object $handler): void
    {
        $this->organizationApi->setClientHandler($handler);
    }

    public function setIsOrganizationCallback(callable $isOrganizationCallback): void
    {
        $this->isOrganigramOrganizationCallback = $isOrganizationCallback(...);
    }

    /**
     * @throws \Throwable
     * @throws Exception
     */
    public function recreateOrganizationsCache(): void
    {
        $this->onRebuildingResourceCacheCallback();

        $organizationsStagingTable = CachedOrganization::STAGING_TABLE_NAME;
        $uidColumn = CachedOrganization::UID;
        $codeColumn = CachedOrganization::CODE;
        $parentUidColumn = CachedOrganization::PARENT_UID;
        $groupKeyColumn = CachedOrganization::GROUP_KEY;
        $typeUidColumn = CachedOrganization::TYPE_UID;
        $addressStreetColumn = CachedOrganization::ADDRESS_STREET;
        $addressCityColumn = CachedOrganization::ADDRESS_CITY;
        $addressPostalCodeColumn = CachedOrganization::ADDRESS_POSTAL_CODE;
        $addressCountryColumn = CachedOrganization::ADDRESS_COUNTRY;

        $insertIntoOrganizationsStagingSql = <<<STMT
            INSERT INTO $organizationsStagingTable (
                               $uidColumn, $codeColumn, $parentUidColumn, $groupKeyColumn, $typeUidColumn,
                               $addressStreetColumn, $addressCityColumn, $addressPostalCodeColumn, $addressCountryColumn)
            VALUES (:uid, :code, :parentUid, :groupKey, :typeUid, :addressStreet, :addressCity, :addressPostalCode, :addressCountry)
            STMT;

        $organizationNamesStagingTable = CachedOrganizationName::STAGING_TABLE_NAME;
        $organizationUidColumn = CachedOrganizationName::ORGANIZATION_UID;
        $languageTagColumn = CachedOrganizationName::LANGUAGE_TAG;
        $nameColumn = CachedOrganizationName::NAME;

        $insertIntoOrganizationNamesStagingSql = <<<STMT
            INSERT INTO $organizationNamesStagingTable ($organizationUidColumn, $languageTagColumn, $nameColumn)
            VALUES (:organizationUid, :languageTag, :name)
            STMT;

        $connection = $this->entityManager->getConnection();
        try {
            $nextCursor = null;
            $subtreeRootsToDelete = [];
            $organizationsToDelete = [];
            do {
                $organizationsResourcePage = $this->organizationApi->getOrganizationsCursorBased([
                    OrganizationApi::INCLUDE_CONTACT_INFO_QUERY_PARAMETER => 'true',
                    'only_active' => 'true',
                ], $nextCursor, 1000);

                /** @var OrganizationResource $organizationResource */
                foreach ($organizationsResourcePage->getResources() as $organizationResource) {
                    $parentUid = $organizationResource->getParentUid();
                    if ($this->isOrganigramOrganizationCallback !== null) {
                        $result = ($this->isOrganigramOrganizationCallback)($organizationResource);
                        if (array_key_exists(0, $result)) {
                            switch ($result[0]) {
                                case self::IGNORE_ORGANIZATION:
                                    $organizationsToDelete[] = $organizationResource->getUid();
                                    break;
                                case self::IGNORE_ORGANIZATION_AND_ITS_CHILDREN:
                                    $subtreeRootsToDelete[] = $organizationResource->getUid();
                                    break;
                            }
                        }
                        if (array_key_exists(1, $result)) {
                            $parentUid = $result[1];
                        }
                    }
                    $connection->executeStatement($insertIntoOrganizationsStagingSql, [
                        $uidColumn => $organizationResource->getUid(),
                        $codeColumn => $organizationResource->getCode(),
                        $parentUidColumn => $parentUid,
                        $groupKeyColumn => $organizationResource->getGroupKey(),
                        $typeUidColumn => $organizationResource->getTypeUid(),
                        $addressStreetColumn => $organizationResource->getAddressStreet(),
                        $addressCityColumn => $organizationResource->getAddressCity(),
                        $addressPostalCodeColumn => $organizationResource->getAddressPostalCode(),
                        $addressCountryColumn => $organizationResource->getAddressCountry(),
                    ]);

                    foreach ($organizationResource->getName() as $languageTag => $name) {
                        $connection->executeStatement($insertIntoOrganizationNamesStagingSql, [
                            $organizationUidColumn => $organizationResource->getUid(),
                            $languageTagColumn => $languageTag,
                            $nameColumn => $name,
                        ]);
                    }
                }
                $nextCursor = $organizationsResourcePage->getNextCursor();
            } while ($nextCursor !== null);

            if ([] === $subtreeRootsToDelete) {
                try {
                    $foreignKeyConstraint = 'fk_organizations_parent_uid';
                    $connection->executeStatement(<<<STMT
                            ALTER TABLE $organizationsStagingTable
                            ADD CONSTRAINT $foreignKeyConstraint
                            FOREIGN KEY ($parentUidColumn) REFERENCES $organizationsStagingTable($uidColumn)
                            ON DELETE CASCADE;
                        STMT
                    );
                    $inClause = implode(',', array_fill(0, count($subtreeRootsToDelete), '?'));
                    $connection->executeStatement(<<<STMT
                        DELETE FROM $organizationsStagingTable
                        WHERE $uidColumn IN ($inClause);
                    STMT, $subtreeRootsToDelete);
                } catch (\Throwable $throwable) {
                    $this->logger->error('failed to delete organization subtrees: '.$throwable->getMessage(), [$throwable]);
                    throw $throwable;
                } finally {
                    $connection->executeStatement(<<<STMT
                            ALTER TABLE $organizationsStagingTable
                            DROP FOREIGN KEY $foreignKeyConstraint;
                        STMT
                    );
                }
            }
            if ([] !== $organizationsToDelete) {
                try {
                    $inClause = implode(',', array_fill(0, count($organizationsToDelete), '?'));
                    $connection->executeStatement(<<<STMT
                        DELETE FROM $organizationsStagingTable
                        WHERE $uidColumn IN ($inClause);
                    STMT, $organizationsToDelete);
                } catch (\Throwable $throwable) {
                    $this->logger->error('failed to delete organizations: '.$throwable->getMessage(), [$throwable]);
                    throw $throwable;
                }
            }

            $organizationsLiveTable = CachedOrganization::TABLE_NAME;
            $organizationsTempTable = 'organizations_old';
            $organizationNamesLiveTable = CachedOrganizationName::TABLE_NAME;
            $organizationNamesTempTable = 'organization_names_old';

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

    public function getOrganizationById(string $identifier, array $options = []): OrganizationAndExtraData
    {
        $cachedOrganization = $this->entityManager->getRepository(CachedOrganization::class)->find($identifier);
        if ($cachedOrganization === null) {
            throw new ApiException('organization with ID not found: '.$identifier,
                Response::HTTP_NOT_FOUND, true);
        }

        return self::createOrganizationAndExtraDataFromCachedOrganization(
            $cachedOrganization,
            $options);
    }

    /**
     * @return iterable<OrganizationAndExtraData>
     */
    public function getOrganizations(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): iterable
    {
        $CACHED_ORGANIZATION_ENTITY_ALIAS = 'o';
        $CACHED_ORGANIZATION_NAME_ENTITY_ALIAS = 'on';

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select($CACHED_ORGANIZATION_ENTITY_ALIAS)
            ->from(CachedOrganization::class, $CACHED_ORGANIZATION_ENTITY_ALIAS)
            ->innerJoin(CachedOrganizationName::class, $CACHED_ORGANIZATION_NAME_ENTITY_ALIAS, Join::WITH,
                $CACHED_ORGANIZATION_ENTITY_ALIAS.'.'.CachedOrganization::UID." = $CACHED_ORGANIZATION_NAME_ENTITY_ALIAS.organization");

        if ($searchTerm = $options[Organization::SEARCH_PARAMETER_NAME] ?? null) {
            try {
                $filter = FilterTreeBuilder::create()
                    ->iContains($CACHED_ORGANIZATION_NAME_ENTITY_ALIAS.'.'.CachedOrganizationName::NAME,
                        $searchTerm)
                    ->equals($CACHED_ORGANIZATION_NAME_ENTITY_ALIAS.'.'.CachedOrganizationName::LANGUAGE_TAG,
                        Options::getLanguage($options))
                    ->createFilter();
            } catch (FilterException $filterException) {
                $this->logger->error('failed to build filter for organization search: '.$filterException->getMessage(), [$filterException]);
                throw new ApiException('failed to build filter for organization search');
            }

            try {
                QueryHelper::addFilter($queryBuilder, $filter);
            } catch (\Exception $exception) {
                $this->logger->error('failed to apply filter for organization search: '.$exception->getMessage(), [$exception]);
                throw new ApiException('failed to apply filter for organization search');
            }
        }

        $paginator = new Paginator($queryBuilder->getQuery());
        $paginator->getQuery()
            ->setFirstResult(Pagination::getFirstItemIndex($currentPageNumber, $maxNumItemsPerPage))
            ->setMaxResults($maxNumItemsPerPage);

        /** @var CachedOrganization $cachedOrganization */
        foreach ($paginator as $cachedOrganization) {
            yield self::createOrganizationAndExtraDataFromCachedOrganization($cachedOrganization, $options);
        }
    }

    /**
     * @return iterable<OrganizationAndExtraData>
     */
    public function getChildOrganizations(string $parentIdentifier, array $options = []): iterable
    {
        foreach ($this->entityManager->getRepository(CachedOrganization::class)->findBy([
            CachedOrganization::PARENT_UID => $parentIdentifier,
        ]) as $cachedOrganization) {
            yield self::createOrganizationAndExtraDataFromCachedOrganization($cachedOrganization, $options);
        }
    }

    private function onRebuildingResourceCacheCallback(): void
    {
        $this->eventDispatcher->dispatch(new RebuildingOrganizationCacheEvent($this));
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
            CachedOrganization::CODE => $cachedOrganization->getCode(),
            CachedOrganization::GROUP_KEY => $cachedOrganization->getGroupKey(),
            CachedOrganization::PARENT_UID => $cachedOrganization->getParentUid(),
            CachedOrganization::TYPE_UID => $cachedOrganization->getTypeUid(),
            CachedOrganization::ADDRESS_STREET => $cachedOrganization->getAddressStreet(),
            CachedOrganization::ADDRESS_CITY => $cachedOrganization->getAddressCity(),
            CachedOrganization::ADDRESS_POSTAL_CODE => $cachedOrganization->getAddressPostalCode(),
            CachedOrganization::ADDRESS_COUNTRY => $cachedOrganization->getAddressCountry(),
        ]);
    }
}
