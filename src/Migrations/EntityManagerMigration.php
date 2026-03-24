<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Migrations;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection\DbpRelayBaseOrganizationConnectorCampusonlineExtension;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationName;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationNameStaging;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationStaging;
use Dbp\Relay\CoreBundle\Doctrine\AbstractEntityManagerMigration;
use Doctrine\ORM\Tools\SchemaTool;

abstract class EntityManagerMigration extends AbstractEntityManagerMigration
{
    protected function getEntityManagerId(): string
    {
        return DbpRelayBaseOrganizationConnectorCampusonlineExtension::ENTITY_MANAGER_ID;
    }
}
