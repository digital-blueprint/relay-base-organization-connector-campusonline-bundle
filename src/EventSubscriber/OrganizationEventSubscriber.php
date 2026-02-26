<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber;

use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPostEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPreEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Dbp\Relay\CoreBundle\LocalData\AbstractLocalDataEventSubscriber;
use Dbp\Relay\CoreBundle\LocalData\LocalDataPostEvent;

class OrganizationEventSubscriber extends AbstractLocalDataEventSubscriber
{
    public const CHILD_IDS_SOURCE_ATTRIBUTE = 'childIds';

    // auto-mappable attributes:
    public const UID_SOURCE_ATTRIBUTE = 'uid';
    public const CODE_SOURCE_ATTRIBUTE = 'code';
    public const GROUP_KEY_SOURCE_ATTRIBUTE = 'groupKey';
    public const PARENT_UID_SOURCE_ATTRIBUTE = 'parentUid';
    public const TYPE_UID_SOURCE_ATTRIBUTE = 'typeUid';

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
        if ($postEvent->isLocalDataAttributeRequested(self::CHILD_IDS_SOURCE_ATTRIBUTE)) {
            $organization = $postEvent->getEntity();
            assert($organization instanceof Organization);

            $childIds = [];
            foreach ($this->organizationProvider->getChildOrganizations($organization->getIdentifier()) as $childOrganizationAndExtraData) {
                $childIds[] = $childOrganizationAndExtraData->getOrganization()->getIdentifier();
            }
            $postEvent->setLocalDataAttribute(self::CHILD_IDS_SOURCE_ATTRIBUTE, $childIds);
        }
    }
}
