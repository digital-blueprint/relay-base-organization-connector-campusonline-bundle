<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service;

use Dbp\CampusonlineApi\Helpers\Filters;
use Dbp\CampusonlineApi\LegacyWebService\ApiException;
use Dbp\CampusonlineApi\LegacyWebService\Organization\OrganizationUnitData;
use Dbp\CampusonlineApi\LegacyWebService\ResourceApi;
use Dbp\Relay\BaseOrganizationBundle\API\OrganizationProviderInterface;
use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPostEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPreEvent;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\LocalData\LocalDataEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

class OrganizationProvider implements OrganizationProviderInterface
{
    private OrganizationApi $orgApi;
    private LocalDataEventDispatcher $eventDispatcher;

    public function __construct(OrganizationApi $orgApi, EventDispatcherInterface $eventDispatcher)
    {
        $this->orgApi = $orgApi;
        $this->eventDispatcher = new LocalDataEventDispatcher(Organization::class, $eventDispatcher);
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

        $organizationUnitData = null;
        try {
            $organizationUnitData = $this->orgApi->getOrganizationById($identifier, $options);
        } catch (ApiException $apiException) {
            self::dispatchException($apiException, $identifier);
        }

        return self::createOrganizationFromOrganizationUnitData($organizationUnitData);
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
        $this->addFilterOptions($options);

        $organizations = [];
        try {
            foreach ($this->orgApi->getOrganizations($currentPageNumber, $maxNumItemsPerPage, $options) as $organizationUnitData) {
                $organizations[] = self::createOrganizationFromOrganizationUnitData($organizationUnitData);
            }
        } catch (ApiException $apiException) {
            self::dispatchException($apiException);
        }

        return $organizations;
    }

    private function createOrganizationFromOrganizationUnitData(OrganizationUnitData $organizationUnitData): Organization
    {
        $organization = new Organization();
        $organization->setIdentifier($organizationUnitData->getIdentifier());
        $organization->setName($organizationUnitData->getName());

        $postEvent = new OrganizationPostEvent($organization, $organizationUnitData->getData(), $this->orgApi);
        $this->eventDispatcher->dispatch($postEvent);

        return $organization;
    }

    private function addFilterOptions(array &$options): void
    {
        if (($searchParameter = $options[Organization::SEARCH_PARAMETER_NAME] ?? null) && $searchParameter !== '') {
            unset($options[Organization::SEARCH_PARAMETER_NAME]);
            ResourceApi::addFilter($options, OrganizationUnitData::NAME_ATTRIBUTE, Filters::CONTAINS_CI_OPERATOR, $searchParameter);
        }
    }

    private function handleNewRequest(array $options): array
    {
        $this->eventDispatcher->onNewOperation($options);

        $preEvent = new OrganizationPreEvent($options);
        $this->eventDispatcher->dispatch($preEvent);

        return $preEvent->getOptions();
    }

    /**
     * NOTE: Campusonline returns '401 unauthorized' for some resources that are not found. So we can't
     * safely return '404' in all cases because '401' is also returned by CO if e.g. the token is not valid.
     *
     * @throws ApiError
     * @throws ApiException
     */
    private static function dispatchException(ApiException $apiException, ?string $identifier = null): void
    {
        if ($apiException->isHttpResponseCode()) {
            switch ($apiException->getCode()) {
                case Response::HTTP_NOT_FOUND:
                    if ($identifier !== null) {
                        throw new ApiError(Response::HTTP_NOT_FOUND, sprintf("Id '%s' could not be found!", $identifier));
                    }
                    break;
                case Response::HTTP_UNAUTHORIZED:
                    throw new ApiError(Response::HTTP_UNAUTHORIZED, sprintf("Id '%s' could not be found or access denied!", $identifier));
            }
            if ($apiException->getCode() >= 500) {
                throw new ApiError(Response::HTTP_BAD_GATEWAY, 'failed to get organizations from Campusonline');
            }
        }
        throw $apiException;
    }
}
