<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPostEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPreEvent;
use Dbp\Relay\CoreBundle\LocalData\AbstractLocalDataEventSubscriber;

class OrganizationEventSubscriber extends AbstractLocalDataEventSubscriber
{
    protected static function getSubscribedEventNames(): array
    {
        return [
            OrganizationPreEvent::class,
            OrganizationPostEvent::class,
            ];
    }
}
