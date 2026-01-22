<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Apis\LegacyOrganizationApi;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Apis\OrganizationApiInterface;
use Symfony\Contracts\EventDispatcher\Event;

class RebuildingOrganizationCacheEvent extends Event
{
    public function __construct(
        private readonly OrganizationApiInterface $organizationApi)
    {
    }

    public function getOrganizationApi(): OrganizationApiInterface
    {
        return $this->organizationApi;
    }
}
