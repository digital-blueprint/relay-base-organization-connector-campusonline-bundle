<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\LegacyOrganizationApi;
use Symfony\Contracts\EventDispatcher\Event;

class RebuildingOrganizationCacheEvent extends Event
{
    public function __construct(
        private readonly LegacyOrganizationApi $organizationApi)
    {
    }

    public function getOrganizationApi(): LegacyOrganizationApi
    {
        return $this->organizationApi;
    }
}
