<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

trait CachedOrganizationNameTrait
{
    public const ORGANIZATION_UID = 'organizationUid';
    public const LANGUAGE_TAG = 'languageTag';
    public const NAME = 'name';

    public const BASE_ENTITY_ATTRIBUTE_MAPPING = [
        'name' => self::NAME,
    ];
    #[ORM\Id]
    #[ORM\Column(name: self::LANGUAGE_TAG, type: 'string', length: 2)]
    private ?string $languageTag = null;

    #[ORM\Column(name: self::NAME, type: 'string', length: 255)]
    private ?string $name = null;

    public function getLanguageTag(): ?string
    {
        return $this->languageTag;
    }

    public function setLanguageTag(?string $languageTag): void
    {
        $this->languageTag = $languageTag;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
