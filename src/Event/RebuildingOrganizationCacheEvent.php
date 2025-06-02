<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationApi;
use Symfony\Contracts\EventDispatcher\Event;

class RebuildingOrganizationCacheEvent extends Event
{
    public function __construct(
        private readonly OrganizationApi $organizationApi)
    {
    }

    public function getOrganizationApi(): OrganizationApi
    {
        return $this->organizationApi;
    }
}
