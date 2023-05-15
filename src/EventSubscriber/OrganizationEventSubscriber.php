<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber;

use Dbp\CampusonlineApi\Helpers\Filters;
use Dbp\CampusonlineApi\LegacyWebService\ResourceApi;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPostEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPreEvent;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\LocalData\AbstractLocalDataEventSubscriber;
use Dbp\Relay\CoreBundle\LocalData\LocalDataPreEvent;
use Dbp\Relay\CoreBundle\Query\LogicalOperator;
use Dbp\Relay\CoreBundle\Query\Operator;
use Symfony\Component\HttpFoundation\Response;

class OrganizationEventSubscriber extends AbstractLocalDataEventSubscriber
{
    protected static function getSubscribedEventNames(): array
    {
        return [
            OrganizationPreEvent::class,
            OrganizationPostEvent::class,
            ];
    }

    /**
     * @throws ApiError
     */
    protected static function toCampusonlineLogicalOperator(string $logicalOperator): string
    {
        switch ($logicalOperator) {
            case LogicalOperator::AND:
                return Filters::LOGICAL_AND_OPERATOR;
            case LogicalOperator::OR:
                return Filters::LOGICAL_OR_OPERATOR;
            default:
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, sprintf('unsupported logical operator "%s"', $logicalOperator));
        }
    }

    /**
     * @trows ApiError
     */
    protected static function toCampusonlineOperator(string $operator): string
    {
        switch ($operator) {
            case Operator::ICONTAINS:
                return Filters::CONTAINS_CI_OPERATOR;
            case Operator::CONTAINS:
                return Filters::CONTAINS_OPERATOR;
            case Operator::IEQUALS:
                return Filters::EQUALS_CI_OPERATOR;
            case Operator::EQUALS:
                return Filters::EQUALS_OPERATOR;
            default:
                throw ApiError::withDetails(Response::HTTP_INTERNAL_SERVER_ERROR, sprintf('unsupported operator "%s"', $operator));
        }
    }

    protected function onPreEvent(LocalDataPreEvent $preEvent, array $localQueryFilters)
    {
        $options = $preEvent->getOptions();
        foreach ($localQueryFilters as $localQueryFilter) {
            ResourceApi::addFilter($options,
                $localQueryFilter->getField(),
                self::toCampusonlineOperator($localQueryFilter->getOperator()),
                $localQueryFilter->getValue(),
                self::toCampusonlineLogicalOperator($localQueryFilter->getLogicalOperator()));
        }
        $preEvent->setOptions($options);
    }
}
