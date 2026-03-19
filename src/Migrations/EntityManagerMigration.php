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

    protected function recreateCacheTables(): void
    {
        $organizationsTable = CachedOrganization::TABLE_NAME;
        $organizationNamesTable = CachedOrganizationName::TABLE_NAME;
        $organizationsStagingTable = CachedOrganizationStaging::TABLE_NAME;
        $organizationNamesStagingTable = CachedOrganizationNameStaging::TABLE_NAME;

        $this->addSql("DROP TABLE IF EXISTS $organizationNamesTable");
        $this->addSql("DROP TABLE IF EXISTS $organizationNamesStagingTable");
        $this->addSql("DROP TABLE IF EXISTS $organizationsStagingTable");
        $this->addSql("DROP TABLE IF EXISTS $organizationsTable");

        $entityManager = $this->getEntityManager();
        $metaData = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);

        foreach ($schemaTool->getCreateSchemaSql($metaData) as $sql) {
            $this->addSql($sql);
        }
    }
}
