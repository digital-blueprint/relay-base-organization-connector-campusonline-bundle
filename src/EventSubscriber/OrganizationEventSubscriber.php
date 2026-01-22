<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Apis\PublicRestOrganizationApi;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPostEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPreEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Dbp\Relay\CoreBundle\LocalData\AbstractLocalDataEventSubscriber;
use Dbp\Relay\CoreBundle\LocalData\LocalDataPostEvent;

class OrganizationEventSubscriber extends AbstractLocalDataEventSubscriber
{
    private const CHILD_IDS_LOCAL_DATA_ATTRIBUTE = 'childIds';
    protected static function getSubscribedEventNames(): array
    {
        return [
            OrganizationPreEvent::class,
            OrganizationPostEvent::class,
        ];
    }

    public function __construct(private readonly OrganizationProvider $organizationProvider)
    {
    }

    protected function onPostEvent(LocalDataPostEvent $postEvent, array &$localDataAttributes): void
    {
        parent::onPostEvent($postEvent, $localDataAttributes);

        if ($postEvent->isLocalDataAttributeRequested(self::CHILD_IDS_LOCAL_DATA_ATTRIBUTE)) {
            $organizationApi = $this->organizationProvider->getOrganizationApi();
            if ($organizationApi instanceof PublicRestOrganizationApi) {
                $postEvent->setLocalDataAttribute(self::CHILD_IDS_LOCAL_DATA_ATTRIBUTE,
                array_filter());
            }
        }
    }
}
