<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service;

use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;

readonly class OrganizationAndExtraData
{
    public function __construct(
        private Organization $organization,
        private array $extraData)
    {
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getExtraData(): array
    {
        return $this->extraData;
    }
}
