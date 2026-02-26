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

    public const BASE_ENTITY_ATTRIBUTE_MAPPING = [
        'identifier' => self::UID,
    ];

    public const LOCAL_DATA_SOURCE_ATTRIBUTES = [
        self::CODE => 'getCode',
        self::PARENT_UID => 'getParentUid',
        self::GROUP_KEY => 'getGroupKey',
        self::TYPE_UID => 'getTypeUid',
    ];

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

    public function getLocalDataSourceAttributeValues(): array
    {
        return array_map(function (string $getterMethod) {
            return $this->$getterMethod();
        }, self::LOCAL_DATA_SOURCE_ATTRIBUTES);
    }
}
