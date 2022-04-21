<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event;

use Dbp\CampusonlineApi\LegacyWebService\Organization\OrganizationUnitData;
use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\CoreBundle\LocalData\LocalDataAwareEvent;

class OrganizationPostEvent extends LocalDataAwareEvent
{
    public const NAME = 'dbp.relay.relay_base_organization_connector_campusonline.organization_provider.post';

    /** @var OrganizationUnitData */
    private $organizationUnitData;

    /** @var Organization */
    private $organization;

    public function __construct(Organization $organization, OrganizationUnitData $organizationUnitData)
    {
        parent::__construct($organization);

        $this->organization = $organization;
        $this->organizationUnitData = $organizationUnitData;
    }

    public function getSourceData(): OrganizationUnitData
    {
        return $this->organizationUnitData;
    }

    public function getEntity(): Organization
    {
        return $this->organization;
    }
}
