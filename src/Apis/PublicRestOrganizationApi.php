<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Apis;

use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;

class PublicRestOrganizationApi implements OrganizationApiInterface
{
    public function checkConnection(): void
    {
        // TODO: Implement checkConnection() method.
    }

    public function getOrganizationById(string $identifier, array $options = []): OrganizationAndExtraData
    {
        return new OrganizationAndExtraData(new Organization(), []);
    }

    public function getOrganizations(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): iterable
    {
        return [];
    }

    public function setClientHandler(?object $handler): void
    {
        // TODO: Implement setClientHandler() method.
    }

    public function recreateOrganizationsCache(): void
    {
        // TODO: Implement recreateOrganizationsCache() method.
    }
}
