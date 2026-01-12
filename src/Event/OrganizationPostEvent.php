<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Apis\OrganizationApiInterface;
use Dbp\Relay\CoreBundle\LocalData\LocalDataAwareInterface;
use Dbp\Relay\CoreBundle\LocalData\LocalDataPostEvent;

class OrganizationPostEvent extends LocalDataPostEvent
{
    public function __construct(LocalDataAwareInterface $entity, array $sourceData,
        private readonly OrganizationApiInterface $organizationApi)
    {
        parent::__construct($entity, $sourceData);
    }

    public function getOrganizationApi(): OrganizationApiInterface
    {
        return $this->organizationApi;
    }
}
