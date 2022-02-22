<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service;

use Dbp\Relay\BaseOrganizationBundle\API\OrganizationProviderInterface;
use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\BasePersonBundle\Entity\Person;
use Dbp\Relay\CoreBundle\Exception\ApiError;

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
        try {
            $org = $this->orgApi->getOrganizationById($identifier, $options);
        } catch (\Exception $e) {
            throw new ApiError($e->getCode(), $e->getMessage());
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
        return [];
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
        } catch (\Exception $e) {
            throw new ApiError($e->getCode(), $e->getMessage());
        }
    }
}
