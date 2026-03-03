<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Entity]
class CachedOrganizationName
{
    use CachedOrganizationNameTrait;

    public const TABLE_NAME = 'organization_names';

    #[ORM\Id]
    #[ORM\JoinColumn(name: self::ORGANIZATION_UID, referencedColumnName: 'uid', onDelete: 'CASCADE')]
    #[ORM\ManyToOne(targetEntity: CachedOrganization::class, inversedBy: 'names')]
    private ?CachedOrganization $organization = null;

    public function getOrganization(): ?CachedOrganization
    {
        return $this->organization;
    }

    public function setOrganization(?CachedOrganization $organization): void
    {
        $this->organization = $organization;
    }
}
