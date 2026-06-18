<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Dbp\Relay\BaseOrganizationBundle\Entity\Organization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\TestUtils\TestOrganizationProvider;
use Dbp\Relay\CoreBundle\Rest\Options;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class OrganizationProviderTest extends ApiTestCase
{
    private const ORGANIZATION_CODE_ATTRIBUTE_NAME = TestOrganizationProvider::ORGANIZATION_CODE_ATTRIBUTE_NAME;
    private const TYPE_ATTRIBUTE_NAME = TestOrganizationProvider::TYPE_ATTRIBUTE_NAME;
    private const TYPE_NAME_ATTRIBUTE_NAME = TestOrganizationProvider::TYPE_NAME_ATTRIBUTE_NAME;

    private ?TestOrganizationProvider $testOrganizationProvider = null;

    /**
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->testOrganizationProvider = TestOrganizationProvider::createTestOrganizationProvider(
            self::bootKernel()->getContainer()
        );
    }

    public function testCampusonlineApiTokenCacheIsReused(): void
    {
        $cachePool = new ArrayAdapter();

        TestOrganizationProvider::createTestOrganizationProvider(
            self::bootKernel()->getContainer(),
            campusonlineApiCacheItemPool: $cachePool
        );

        $organizationProvider = TestOrganizationProvider::createTestOrganizationProvider(
            self::bootKernel()->getContainer(),
            campusonlineApiCacheItemPool: $cachePool,
            mockAuthServerResponses: false
        );

        $organization = $organizationProvider->getOrganizationById('37');

        $this->assertSame('37', $organization->getIdentifier());
    }

    public function testCustomTestOrganizationResources(): void
    {
        $this->testOrganizationProvider = TestOrganizationProvider::createTestOrganizationProvider(
            self::bootKernel()->getContainer(),
            testOrganizationResources: [
                [
                    'uid' => '123',
                    'code' => '321',
                    'groupKey' => 'OE',
                    'name' => [
                        'value' => [
                            'de' => 'Institut für Irgendwas',
                            'en' => 'Institute of Something',
                        ],
                    ],
                    'parentUid' => '12',
                    'type' => [
                        'name' => [
                            'value' => [
                                'de' => 'Institut',
                                'en' => 'Institute',
                            ],
                        ],
                        'uid' => '691',
                    ],
                ],
            ]
        );

        $options = [];
        Options::requestLocalDataAttributes($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME]);
        $organization = $this->testOrganizationProvider->getOrganizationById('123', $options);
        $this->assertSame('123', $organization->getIdentifier());
        $this->assertSame('Institut für Irgendwas', $organization->getName());
        $this->assertSame('321', $organization->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
    }

    public function testGetOrganizationById()
    {
        $org = $this->testOrganizationProvider->getOrganizationById('37');
        $this->assertSame('37', $org->getIdentifier());
        $this->assertSame('Technische Universität Graz', $org->getName()); // default language is german
        $this->assertNull($org->getLocalData());
    }

    public function testGetOrganizationByIdEn()
    {
        $options = [];
        Options::setLanguage($options, 'en');
        $org = $this->testOrganizationProvider->getOrganizationById('37', $options);
        $this->assertSame('37', $org->getIdentifier());
        $this->assertSame('Graz University of Technology', $org->getName());
        $this->assertNull($org->getLocalData());
    }

    public function testGetOrganizationByIdDe()
    {
        $options = [];
        Options::setLanguage($options, 'de');
        $org = $this->testOrganizationProvider->getOrganizationById('37', $options);
        $this->assertSame('37', $org->getIdentifier());
        $this->assertSame('Technische Universität Graz', $org->getName());
        $this->assertNull($org->getLocalData());
    }

    public function testGetOrganizationByIdWithLocalData()
    {
        $options = [];
        Options::setLanguage($options, 'en');
        Options::requestLocalDataAttributes($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME, self::TYPE_ATTRIBUTE_NAME]);
        $org = $this->testOrganizationProvider->getOrganizationById('21', $options);
        $this->assertSame('21', $org->getIdentifier());
        $this->assertSame('Faculty of Mechanical Engineering and Economic Sciences', $org->getName());
        $this->assertSame('3000', $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
        $this->assertSame('680', $org->getLocalDataValue(self::TYPE_ATTRIBUTE_NAME));
    }

    public function testGetOrganizationByIdWithLocalDataWithApiRequestEn()
    {
        $this->testOrganizationProvider->mockApiResponse();

        $options = [];
        Options::setLanguage($options, 'en');
        Options::requestLocalDataAttributes($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME, self::TYPE_NAME_ATTRIBUTE_NAME]);
        $org = $this->testOrganizationProvider->getOrganizationById('21', $options);
        $this->assertSame('21', $org->getIdentifier());
        $this->assertSame('Faculty of Mechanical Engineering and Economic Sciences', $org->getName());
        $this->assertSame('3000', $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
        $this->assertSame('Faculty', $org->getLocalDataValue(self::TYPE_NAME_ATTRIBUTE_NAME));
    }

    public function testGetOrganizationByIdWithLocalDataWithApiRequestDe()
    {
        $this->testOrganizationProvider->mockApiResponse();

        $options = [];
        Options::setLanguage($options, 'de');
        Options::requestLocalDataAttributes($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME, self::TYPE_NAME_ATTRIBUTE_NAME]);
        $org = $this->testOrganizationProvider->getOrganizationById('21', $options);
        $this->assertSame('21', $org->getIdentifier());
        $this->assertSame('Fakultät für Maschinenbau und Wirtschaftswissenschaften', $org->getName());
        $this->assertSame('3000', $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
        $this->assertSame('Fakultät', $org->getLocalDataValue(self::TYPE_NAME_ATTRIBUTE_NAME));
    }

    public function testGetOrganizations()
    {
        $organizations = $this->testOrganizationProvider->getOrganizations(1, 30);
        $this->assertCount(3, $organizations);
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '37'
                && $org->getName() === 'Technische Universität Graz' // default language is german
                && $org->getLocalData() === null));
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '22'
                && $org->getName() === 'Institut für Verfahrenstechnik'
                && $org->getLocalData() === null));
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '21'
                && $org->getName() === 'Fakultät für Maschinenbau und Wirtschaftswissenschaften'
                && $org->getLocalData() === null));
    }

    public function testGetOrganizationsEn()
    {
        $options = [];
        Options::setLanguage($options, 'en');
        $organizations = $this->testOrganizationProvider->getOrganizations(1, 30, $options);
        $this->assertCount(3, $organizations);
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '37'
                && $org->getName() === 'Graz University of Technology'
                && $org->getLocalData() === null));
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '22'
                && $org->getName() === 'Institute of Chemical and Process Engineering'
                && $org->getLocalData() === null));
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '21'
                && $org->getName() === 'Faculty of Mechanical Engineering and Economic Sciences'
                && $org->getLocalData() === null));
    }

    public function testGetOrganizationsDe()
    {
        $options = [];
        Options::setLanguage($options, 'de');
        $organizations = $this->testOrganizationProvider->getOrganizations(1, 30, $options);
        $this->assertCount(3, $organizations);
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '37'
                && $org->getName() === 'Technische Universität Graz'
                && $org->getLocalData() === null));
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '22'
                && $org->getName() === 'Institut für Verfahrenstechnik'
                && $org->getLocalData() === null));
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '21'
                && $org->getName() === 'Fakultät für Maschinenbau und Wirtschaftswissenschaften'
                && $org->getLocalData() === null));
    }

    public function testGetOrganizationsWithSearchParameter()
    {
        // default language is german
        $options = [];
        $options[Organization::SEARCH_PARAMETER_NAME] = 'fakultät';
        $organizations = $this->testOrganizationProvider->getOrganizations(1, 30, $options);
        $this->assertCount(1, $organizations);
        $this->assertSame('21', $organizations[0]->getIdentifier());
        $this->assertSame('Fakultät für Maschinenbau und Wirtschaftswissenschaften', $organizations[0]->getName());

        $options = [];
        $options[Organization::SEARCH_PARAMETER_NAME] = 'faculty';
        $organizations = $this->testOrganizationProvider->getOrganizations(1, 30, $options);
        $this->assertCount(0, $organizations);
    }

    public function testGetOrganizationsWithMultitermSearchParameter()
    {
        // default language is german
        $options = [];
        $options[Organization::SEARCH_PARAMETER_NAME] = 'fak electro';
        $organizations = $this->testOrganizationProvider->getOrganizations(1, 30, $options);
        $this->assertCount(0, $organizations);

        $options = [];
        $options[Organization::SEARCH_PARAMETER_NAME] = 'fak masch';
        $organizations = $this->testOrganizationProvider->getOrganizations(1, 30, $options);
        $this->assertCount(1, $organizations);
        $this->assertSame('21', $organizations[0]->getIdentifier());
        $this->assertSame('Fakultät für Maschinenbau und Wirtschaftswissenschaften', $organizations[0]->getName());
    }

    public function testGetOrganizationsWithSearchParameterDe()
    {
        $options = [];
        Options::setLanguage($options, 'de');
        $options[Organization::SEARCH_PARAMETER_NAME] = 'fakultät';
        $organizations = $this->testOrganizationProvider->getOrganizations(1, 30, $options);
        $this->assertCount(1, $organizations);
        $this->assertSame('21', $organizations[0]->getIdentifier());
        $this->assertSame('Fakultät für Maschinenbau und Wirtschaftswissenschaften', $organizations[0]->getName());
    }

    public function testGetOrganizationsWithSearchParameterEn()
    {
        $options = [];
        Options::setLanguage($options, 'en');
        $options[Organization::SEARCH_PARAMETER_NAME] = 'faculty';
        $organizations = $this->testOrganizationProvider->getOrganizations(1, 30, $options);
        $this->assertCount(1, $organizations);
        $this->assertSame('21', $organizations[0]->getIdentifier());
        $this->assertSame('Faculty of Mechanical Engineering and Economic Sciences', $organizations[0]->getName());
    }

    public function testGetOrganizationsPagination()
    {
        $options = [];
        Options::setLanguage($options, 'en');
        $organizationPage1 = $this->testOrganizationProvider->getOrganizations(1, 2, $options);
        $this->assertCount(2, $organizationPage1);
        $organizationPage2 = $this->testOrganizationProvider->getOrganizations(2, 2, $options);
        $this->assertCount(1, $organizationPage2);
        $organizations = array_merge($organizationPage1, $organizationPage2);
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '37'
                && $org->getName() === 'Graz University of Technology'
                && $org->getLocalData() === null));
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '22'
                && $org->getName() === 'Institute of Chemical and Process Engineering'
                && $org->getLocalData() === null));
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '21'
                && $org->getName() === 'Faculty of Mechanical Engineering and Economic Sciences'
                && $org->getLocalData() === null));
    }

    public function testGetOrganizationsLocalData()
    {
        $options = [];
        Options::setLanguage($options, 'en');
        Options::requestLocalDataAttributes($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME, self::TYPE_ATTRIBUTE_NAME]);
        $organizations = $this->testOrganizationProvider->getOrganizations(1, 30, $options);
        $this->assertCount(3, $organizations);
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '37'
                && $org->getName() === 'Graz University of Technology'
                && $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME) === '90000'
                && $org->getLocalDataValue(self::TYPE_ATTRIBUTE_NAME) === '672'));
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '22'
                && $org->getName() === 'Institute of Chemical and Process Engineering'
                && $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME) === '3510'
                && $org->getLocalDataValue(self::TYPE_ATTRIBUTE_NAME) === '691'));
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '21'
                && $org->getName() === 'Faculty of Mechanical Engineering and Economic Sciences'
                && $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME) === '3000'
                && $org->getLocalDataValue(self::TYPE_ATTRIBUTE_NAME) === '680'));
    }
}
