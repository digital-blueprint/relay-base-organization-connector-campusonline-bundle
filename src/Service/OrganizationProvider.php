<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service;

use Dbp\CampusonlineApi\LegacyWebService\ApiException;
use Dbp\Relay\BaseOrganizationBundle\API\OrganizationProviderInterface;
use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\BasePersonBundle\Entity\Person;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Symfony\Component\HttpFoundation\Response;

class OrganizationProvider implements OrganizationProviderInterface
{
    /*
     * @var OrganizationApi
     */
    private $orgApi;

    public function __construct(OrganizationApi $orgApi)
    {
        $this->orgApi = $orgApi;
    }

    /**
     * @throws ApiError
     */
    public function getOrganizationById(string $identifier, array $options = []): ?Organization
    {
        $org = null;

        try {
            $org = $this->orgApi->getOrganizationById($identifier, $options);
        } catch (ApiException $e) {
            self::dispatchException($e, $identifier);
        }

        return $org;
    }

    /**
     * currently not supported by the Campusonline API.
     *
     * @return Organization[]
     *
     * @throws ApiError
     */
    public function getOrganizationsByPerson(Person $person, string $context, array $options = []): array
    {
        throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, 'query not available');
    }

    /**
     * @return Organization[]
     *
     * @throws ApiError
     */
    public function getOrganizations(array $options = []): array
    {
        try {
            return $this->orgApi->getOrganizations($options);
        } catch (ApiException $e) {
            throw new ApiError(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }
    }

    /**
     * NOTE: Comfortonline returns '401 unauthorized' for some ressources that are not found. So we can't
     * safely return '404' in all cases because '401' is also returned by CO if e.g. the token is not valid.
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
