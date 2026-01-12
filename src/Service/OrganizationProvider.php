<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service;

use Dbp\CampusonlineApi\LegacyWebService\ApiException;
use Dbp\Relay\BaseOrganizationBundle\API\OrganizationProviderInterface;
use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Apis\LegacyOrganizationApi;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Apis\OrganizationAndExtraData;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Apis\OrganizationApiInterface;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Apis\PublicRestOrganizationApi;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection\Configuration;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPostEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPreEvent;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\LocalData\LocalDataEventDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

class OrganizationProvider implements OrganizationProviderInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ?OrganizationApiInterface $organizationApi = null;
    private LocalDataEventDispatcher $localDataEventDispatcher;
    private array $config = [];
    private ?object $clientHandler = null;
    private ?CacheItemPoolInterface $cachePool = null;
    private int $cacheTTL = 0;

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

    public function setCache(?CacheItemPoolInterface $cachePool, int $ttl): void
    {
        $this->cachePool = $cachePool;
        $this->cacheTTL = $ttl;
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
        if ($this->organizationApi !== null) {
            $this->organizationApi->setClientHandler($handler);
        }
    }

    /**
     * @throws ApiException
     */
    public function checkConnection(): void
    {
        $this->getOrganizationApi()->checkConnection();
    }

    public function recreateOrganizationsCache(): void
    {
        $this->getOrganizationApi()->recreateOrganizationsCache();
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
            $organizationAndExtraData = $this->getOrganizationApi()->getOrganizationById($identifier, $options);
        } catch (ApiException $apiException) {
            throw self::dispatchException($apiException, $identifier);
        }

        return $this->postProcessOrganization($organizationAndExtraData);
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

        $organizations = [];
        try {
            foreach ($this->getOrganizationApi()->getOrganizations($currentPageNumber, $maxNumItemsPerPage, $options) as $organizationAndExtraData) {
                $organizations[] = $this->postProcessOrganization($organizationAndExtraData);
            }
        } catch (ApiException $apiException) {
            throw self::dispatchException($apiException);
        }

        return $organizations;
    }

    private function getOrganizationApi(): OrganizationApiInterface
    {
        if ($this->organizationApi === null) {
            if ($this->config[Configuration::LEGACY_NODE] ?? true) {
                $this->organizationApi = new LegacyOrganizationApi($this->eventDispatcher,
                    $this->config, $this->cachePool, $this->cacheTTL, $this->logger);
            } else {
                $this->organizationApi = new PublicRestOrganizationApi($this->entityManager, $this->config, $this->logger);
            }
            if ($this->clientHandler !== null) {
                $this->organizationApi->setClientHandler($this->clientHandler);
            }
        }

        return $this->organizationApi;
    }

    private function postProcessOrganization(OrganizationAndExtraData $organizationAndExtraData): Organization
    {
        $postEvent = new OrganizationPostEvent(
            $organizationAndExtraData->getOrganization(), $organizationAndExtraData->getExtraData(), $this->getOrganizationApi());
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
}
