<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

trait CachedOrganizationTrait
{
    public const UID = 'uid';
    public const CODE = 'code';
    public const PARENT_UID = 'parentUid';
    public const GROUP_KEY = 'groupKey';
    public const TYPE_UID = 'typeUid';
    public const ADDRESS_STREET = 'addressStreet';
    public const ADDRESS_CITY = 'addressCity';
    public const ADDRESS_POSTAL_CODE = 'addressPostalCode';
    public const ADDRESS_COUNTRY = 'addressCountry';

    #[ORM\Id]
    #[ORM\Column(name: self::UID, type: 'string', length: 16)]
    private ?string $uid = null;

    #[ORM\Column(name: self::CODE, type: 'string', length: 16, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(name: self::PARENT_UID, type: 'string', length: 16, nullable: true)]
    private ?string $parentUid = null;

    #[ORM\Column(name: self::GROUP_KEY, type: 'string', length: 16, nullable: true)]
    private ?string $groupKey = null;

    #[ORM\Column(name: self::TYPE_UID, type: 'string', length: 16, nullable: true)]
    private ?string $typeUid = null;

    #[ORM\Column(name: self::ADDRESS_STREET, type: 'string', length: 256, nullable: true)]
    private ?string $addressStreet = null;

    #[ORM\Column(name: self::ADDRESS_CITY, type: 'string', length: 128, nullable: true)]
    private ?string $addressCity = null;

    #[ORM\Column(name: self::ADDRESS_POSTAL_CODE, type: 'string', length: 16, nullable: true)]
    private ?string $addressPostalCode = null;

    #[ORM\Column(name: self::ADDRESS_COUNTRY, type: 'string', length: 128, nullable: true)]
    private ?string $addressCountry = null;

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function setUid(?string $uid): void
    {
        $this->uid = $uid;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getParentUid(): ?string
    {
        return $this->parentUid;
    }

    public function setParentUid(?string $parentUid): void
    {
        $this->parentUid = $parentUid;
    }

    public function getGroupKey(): ?string
    {
        return $this->groupKey;
    }

    public function setGroupKey(?string $groupKey): void
    {
        $this->groupKey = $groupKey;
    }

    public function getTypeUid(): ?string
    {
        return $this->typeUid;
    }

    public function setTypeUid(?string $typeUid): void
    {
        $this->typeUid = $typeUid;
    }

    public function getAddressStreet(): ?string
    {
        return $this->addressStreet;
    }

    public function setAddressStreet(?string $addressStreet): void
    {
        $this->addressStreet = $addressStreet;
    }

    public function getAddressCity(): ?string
    {
        return $this->addressCity;
    }

    public function setAddressCity(?string $addressCity): void
    {
        $this->addressCity = $addressCity;
    }

    public function getAddressPostalCode(): ?string
    {
        return $this->addressPostalCode;
    }

    public function setAddressPostalCode(?string $addressPostalCode): void
    {
        $this->addressPostalCode = $addressPostalCode;
    }

    public function getAddressCountry(): ?string
    {
        return $this->addressCountry;
    }

    public function setAddressCountry(?string $addressCountry): void
    {
        $this->addressCountry = $addressCountry;
    }
}
