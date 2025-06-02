<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationApi;
use Dbp\Relay\CoreBundle\LocalData\LocalDataAwareInterface;
use Dbp\Relay\CoreBundle\LocalData\LocalDataPostEvent;

class OrganizationPostEvent extends LocalDataPostEvent
{
    public function __construct(LocalDataAwareInterface $entity, array $sourceData,
        private readonly OrganizationApi $organizationApi)
    {
        parent::__construct($entity, $sourceData);
    }

    public function getOrganizationApi(): OrganizationApi
    {
        return $this->organizationApi;
    }
}
