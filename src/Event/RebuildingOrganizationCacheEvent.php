<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationApi;
use Symfony\Contracts\EventDispatcher\Event;

class RebuildingOrganizationCacheEvent extends Event
{
    /**
     * @var OrganizationApi
     */
    private $organizationApi;

    public function __construct(OrganizationApi $organizationApi)
    {
        $this->organizationApi = $organizationApi;
    }

    public function getOrganizationApi(): OrganizationApi
    {
        return $this->organizationApi;
    }
}
