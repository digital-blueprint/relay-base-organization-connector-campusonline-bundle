<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPostEvent;
use Dbp\Relay\CoreBundle\LocalData\AbstractLocalDataPostEventSubscriber;

class OrganizationPostEventSubscriber extends AbstractLocalDataPostEventSubscriber
{
    public static function getSubscribedEventName(): string
    {
        return OrganizationPostEvent::class;
    }
}
