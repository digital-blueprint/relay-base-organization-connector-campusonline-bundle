<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service;

use Dbp\CampusonlineApi\Helpers\FullPaginator as CoFullPaginator;
use Dbp\CampusonlineApi\LegacyWebService\ApiException;
use Dbp\CampusonlineApi\LegacyWebService\Organization\OrganizationUnitData;
use Dbp\CampusonlineApi\LegacyWebService\ResourceData;
use Dbp\Relay\BaseOrganizationBundle\API\OrganizationProviderInterface;
use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPostEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPreEvent;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\LocalData\LocalDataAwareEventDispatcher;
use Dbp\Relay\CoreBundle\Pagination\FullPaginator;
use Dbp\Relay\CoreBundle\Pagination\Pagination;
use Dbp\Relay\CoreBundle\Pagination\Paginator;
use Dbp\Relay\CoreBundle\Pagination\PartialPaginator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

class OrganizationProvider implements OrganizationProviderInterface
{
    /** @var OrganizationApi */
    private $orgApi;

    /** @var LocalDataAwareEventDispatcher */
    private $eventDispatcher;

    public function __construct(OrganizationApi $orgApi, EventDispatcherInterface $eventDispatcher)
    {
        $this->orgApi = $orgApi;
        $this->eventDispatcher = new LocalDataAwareEventDispatcher(Organization::class, $eventDispatcher);
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
        $this->eventDispatcher->onNewOperation($options);

        $organizationUnitData = null;
        try {
            $organizationUnitData = $this->orgApi->getOrganizationById($identifier, $options);
        } catch (ApiException $e) {
            self::dispatchException($e, $identifier);
        }

        return self::createOrganizationFromOrganizationUnitData($organizationUnitData);
    }

    /**
     * @param array $options Available options:
     *                       * string 'lang' ('de' or 'en')
     *                       * array 'identifiers' The list of organizations to return
     *                       * string Organization::SEARCH_PARAMETER_NAME (partial, case-insensitive text search on 'name' attribute)
     *                       * string LocalData::INCLUDE_PARAMETER_NAME
     *                       * string LocalData::QUERY_PARAMETER_NAME
     *
     * @throws ApiError
     */
    public function getOrganizations(array $options = []): Paginator
    {
        $this->eventDispatcher->onNewOperation($options);

        $preEvent = new OrganizationPreEvent();
        $this->eventDispatcher->dispatch($preEvent, OrganizationPreEvent::NAME);
        $options = array_merge($options, $preEvent->getQueryParameters());

        $this->addFilterOptions($options);

        $organizations = [];
        try {
            $paginator = $this->orgApi->getOrganizations($options);
            foreach ($paginator->getItems() as $organizationUnitData) {
                $organizations[] = self::createOrganizationFromOrganizationUnitData($organizationUnitData);
            }
        } catch (ApiException $e) {
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }

        if (Pagination::isPartialPagination($options)) {
            return new PartialPaginator($organizations, $paginator->getCurrentPageNumber(), $paginator->getMaxNumItemsPerPage());
        } else {
            if ($paginator instanceof CoFullPaginator) {
                return new FullPaginator($organizations, $paginator->getCurrentPageNumber(), $paginator->getMaxNumItemsPerPage(), $paginator->getTotalNumItems());
            } else {
                throw new ApiError(500, 'camppusonline api returned invalid paginator');
            }
        }
    }

    private function createOrganizationFromOrganizationUnitData(OrganizationUnitData $organizationUnitData): Organization
    {
        $organization = new Organization();
        $organization->setIdentifier($organizationUnitData->getIdentifier());
        $organization->setName($organizationUnitData->getName());

        $postEvent = new OrganizationPostEvent($organization, $organizationUnitData);
        $this->eventDispatcher->dispatch($postEvent, OrganizationPostEvent::NAME);

        return $postEvent->getEntity();
    }

    private function addFilterOptions(array &$options)
    {
        if (($searchParameter = $options[Organization::SEARCH_PARAMETER_NAME] ?? null) && $searchParameter !== '') {
            $options[ResourceData::NAME_SEARCH_FILTER_NAME] = $searchParameter;
        }
    }

    /**
     * NOTE: Comfortonline returns '401 unauthorized' for some ressources that are not found. So we can't
     * safely return '404' in all cases because '401' is also returned by CO if e.g. the token is not valid.
     *
     * @throws ApiError
     */
    private static function dispatchException(ApiException $e, string $identifier)
    {
        if ($e->isHttpResponseCode()) {
            switch ($e->getCode()) {
                case Response::HTTP_NOT_FOUND:
                    throw new ApiError(Response::HTTP_NOT_FOUND, sprintf("Id '%s' could not be found!", $identifier));
                    break;
                case Response::HTTP_UNAUTHORIZED:
                    throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, sprintf("Id '%s' could not be found or access denied!", $identifier));
                    break;
                default:
                    break;
            }
        }
        throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
    }
}
