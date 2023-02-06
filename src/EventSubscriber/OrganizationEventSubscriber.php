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

    protected function onPreEvent(LocalDataPreEvent $preEvent, array $mappedQueryParameters)
    {
        $options = $preEvent->getOptions();
        foreach ($mappedQueryParameters as $sourceParameterName => $parameterValue) {
            ResourceApi::addFilter($options, $sourceParameterName, Filters::CONTAINS_CI_OPERATOR, $parameterValue);
        }
        $preEvent->setOptions($options);
    }
}
