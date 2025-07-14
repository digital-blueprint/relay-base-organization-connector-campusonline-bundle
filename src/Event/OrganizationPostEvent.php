<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\LegacyOrganizationApi;
use Dbp\Relay\CoreBundle\LocalData\LocalDataAwareInterface;
use Dbp\Relay\CoreBundle\LocalData\LocalDataPostEvent;

class OrganizationPostEvent extends LocalDataPostEvent
{
    public function __construct(LocalDataAwareInterface $entity, array $sourceData,
        private readonly LegacyOrganizationApi $organizationApi)
    {
        parent::__construct($entity, $sourceData);
    }

    public function getOrganizationApi(): LegacyOrganizationApi
    {
        return $this->organizationApi;
    }
}
