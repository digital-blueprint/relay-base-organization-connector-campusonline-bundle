<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event;

use Dbp\Relay\CoreBundle\LocalData\LocalDataPostEvent;

class OrganizationPostEvent extends LocalDataPostEvent
{
    public const NAME = 'dbp.relay.relay_base_organization_connector_campusonline.organization_provider.post';
}
