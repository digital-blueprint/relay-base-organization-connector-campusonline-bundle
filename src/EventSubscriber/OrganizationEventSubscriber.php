<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber;

use Dbp\CampusonlineApi\Helpers\Filters;
use Dbp\CampusonlineApi\LegacyWebService\ResourceApi;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPostEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPreEvent;
use Dbp\Relay\CoreBundle\LocalData\AbstractLocalDataEventSubscriber;
use Dbp\Relay\CoreBundle\LocalData\LocalDataPreEvent;

class OrganizationEventSubscriber extends AbstractLocalDataEventSubscriber
{
    protected static function getSubscribedEventNames(): array
    {
        return [
            OrganizationPreEvent::class,
            OrganizationPostEvent::class,
            ];
    }

    protected function onPreEvent(LocalDataPreEvent $preEvent, array $localQueryAttributes)
    {
        $options = $preEvent->getOptions();
        foreach ($localQueryAttributes as $localQueryAttribute) {
            ResourceApi::addFilter($options, $localQueryAttribute[parent::LOCAL_QUERY_PARAMETER_SOURCE_ATTRIBUTE_KEY],
                Filters::CONTAINS_CI_OPERATOR, $localQueryAttribute[parent::LOCAL_QUERY_PARAMETER_VALUE_KEY]);
        }
        $preEvent->setOptions($options);
    }
}
