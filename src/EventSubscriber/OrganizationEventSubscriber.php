<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber;

use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPostEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Event\OrganizationPreEvent;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationAndExtraData;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Dbp\Relay\CoreBundle\LocalData\AbstractLocalDataEventSubscriber;
use Dbp\Relay\CoreBundle\LocalData\LocalDataPostEvent;
use Dbp\Relay\CoreBundle\Rest\Options;

class OrganizationEventSubscriber extends AbstractLocalDataEventSubscriber
{
    public const CHILD_IDS_SOURCE_ATTRIBUTE = 'childIds';
    public const TYPE_NAME_SOURCE_DATA_ATTRIBUTE = 'typeName';
    public const DESCRIPTION_SOURCE_DATA_ATTRIBUTE = 'description';
    public const CONTACTS_SOURCE_DATA_ATTRIBUTE = 'contacts';

    // auto-mappable attributes:
    public const UID_SOURCE_ATTRIBUTE = 'uid';
    public const CODE_SOURCE_ATTRIBUTE = 'code';
    public const GROUP_KEY_SOURCE_ATTRIBUTE = 'groupKey';
    public const PARENT_UID_SOURCE_ATTRIBUTE = 'parentUid';
    public const TYPE_UID_SOURCE_ATTRIBUTE = 'typeUid';

    protected static function getSubscribedEventNames(): array
    {
        return [
            OrganizationPreEvent::class,
            OrganizationPostEvent::class,
        ];
    }

    public function __construct(private readonly OrganizationProvider $organizationProvider)
    {
    }

    protected function getAttributeValue(LocalDataPostEvent $postEvent, array $attributeMapEntry): mixed
    {
        $options = $postEvent->getOptions();
        $organization = $postEvent->getEntity();
        assert($organization instanceof Organization);

        switch ($attributeMapEntry[self::SOURCE_ATTRIBUTE_KEY]) {
            case self::CHILD_IDS_SOURCE_ATTRIBUTE:
                return array_map(
                    fn (OrganizationAndExtraData $childOrganizationAndExtraData) => $childOrganizationAndExtraData->getOrganization()->getIdentifier(),
                    $this->organizationProvider->getChildOrganizations($organization->getIdentifier(), $options),
                );

            case self::TYPE_NAME_SOURCE_DATA_ATTRIBUTE:
                $this->organizationProvider->getAndCacheCurrentResultOrganizationsFromApi();

                return $this->organizationProvider->getOrganizationFromApiCached($organization->getIdentifier())
                    ->getTypeName()[Options::getLanguage($options) ?? OrganizationProvider::DEFAULT_LANGUAGE] ?? null;

            case self::DESCRIPTION_SOURCE_DATA_ATTRIBUTE:
                $this->organizationProvider->getAndCacheCurrentResultOrganizationsFromApi();

                return $this->organizationProvider->getOrganizationFromApiCached($organization->getIdentifier())
                    ->getShortDescription()[Options::getLanguage($options) ?? OrganizationProvider::DEFAULT_LANGUAGE] ?? null;

            case self::CONTACTS_SOURCE_DATA_ATTRIBUTE:
                $this->organizationProvider->getAndCacheCurrentResultOrganizationsFromApi();
                $organizationResource = $this->organizationProvider->getOrganizationFromApiCached($organization->getIdentifier());
                $contacts = [];
                for ($contactInfoIndex = 0; $contactInfoIndex < $organizationResource->getNumberOfContactInfos(); ++$contactInfoIndex) {
                    $contacts[] = [
                        'name' => $organizationResource->getContactInfoKey($contactInfoIndex),
                        'email' => $organizationResource->getContactInfoEmail($contactInfoIndex),
                        'webPage' => $organizationResource->getContactInfoWebPageHref($contactInfoIndex),
                        'phoneNumber' => $organizationResource->getContactInfoTel($contactInfoIndex),
                        'officeHours' => $organizationResource->getContactInfoSecretariatInformation($contactInfoIndex),
                        'address' => [
                            'street' => $organizationResource->getContactInfoAddressStreet($contactInfoIndex),
                            'postalCode' => $organizationResource->getContactInfoAddressPostalCode($contactInfoIndex),
                            'city' => $organizationResource->getContactInfoAddressCity($contactInfoIndex),
                            'country' => $organizationResource->getContactInfoAddressCountry($contactInfoIndex),
                        ],
                    ];
                }

                return $contacts;
        }

        return parent::getAttributeValue($postEvent, $attributeMapEntry);
    }
}
