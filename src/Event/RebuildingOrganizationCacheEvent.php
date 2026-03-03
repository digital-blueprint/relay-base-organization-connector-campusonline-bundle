<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Symfony\Contracts\EventDispatcher\Event;

class RebuildingOrganizationCacheEvent extends Event
{
    public function __construct(
        private readonly OrganizationProvider $organizationProvider)
    {
    }

    public function getOrganizationProvider(): OrganizationProvider
    {
        return $this->organizationProvider;
    }
}
