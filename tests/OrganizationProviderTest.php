<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection\Configuration;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection\DbpRelayBaseOrganizationConnectorCampusonlineExtension;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationName;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationNameStaging;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationStaging;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber\OrganizationEventSubscriber;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\CoreBundle\TestUtils\TestEntityManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

class OrganizationProviderTest extends ApiTestCase
{
    private const ORGANIZATION_CODE_ATTRIBUTE_NAME = 'code';
    private const TYPE_ATTRIBUTE_NAME = 'type';
    private const TYPE_NAME_ATTRIBUTE_NAME = 'typeName';

    private ?OrganizationProvider $organizationProvider = null;
    private ?EntityManagerInterface $entityManager = null;
    private ?OrganizationEventSubscriber $organizationEventSubscriber = null;

    private static function createEventSubscriberConfig(bool $publicRest): array
    {
        $config = [];
        $config['local_data_mapping'] = [
            [
                'local_data_attribute' => self::ORGANIZATION_CODE_ATTRIBUTE_NAME,
                'source_attribute' => OrganizationEventSubscriber::CODE_SOURCE_ATTRIBUTE,
                'default_value' => '',
            ],
            [
                'local_data_attribute' => self::TYPE_ATTRIBUTE_NAME,
                'source_attribute' => OrganizationEventSubscriber::TYPE_UID_SOURCE_ATTRIBUTE,
                'default_value' => '',
            ],
            [
                'local_data_attribute' => self::TYPE_NAME_ATTRIBUTE_NAME,
                'source_attribute' => OrganizationEventSubscriber::TYPE_NAME_SOURCE_DATA_ATTRIBUTE,
                'default_value' => '',
            ],
        ];

        return $config;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::bootKernel()->getContainer();

        $eventDispatcher = new EventDispatcher();
        $this->entityManager = TestEntityManager::setUpEntityManager($container,
            DbpRelayBaseOrganizationConnectorCampusonlineExtension::ENTITY_MANAGER_ID);

        $this->organizationProvider = new OrganizationProvider($this->entityManager, $eventDispatcher);
        $this->organizationProvider->setLogger(new NullLogger());
        $this->organizationProvider->setConfig($this->getPublicRestApiConfig());
        $this->organizationProvider->reset();

        $this->organizationEventSubscriber = new OrganizationEventSubscriber($this->organizationProvider);
        $eventDispatcher->addSubscriber($this->organizationEventSubscriber);

        $this->organizationEventSubscriber->setConfig(self::createEventSubscriberConfig(true));

        $this->recreateOrganizationCache();
    }

    private function getPublicRestApiConfig(): array
    {
        $config = [];
        $config[Configuration::CAMPUS_ONLINE_NODE] = [
            'legacy' => false,
            'base_url' => 'https://campusonline.at/campusonline/ws/public/rest/',
            'client_id' => 'client',
            'client_secret' => 'secret',
        ];

        return $config;
    }

    private function mockResponses(): void
    {
        $responses = [...self::createMockAuthServerResponses(),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                file_get_contents(__DIR__.'/public_rest_org_api_response.json')),
        ];

        $stack = HandlerStack::create(new MockHandler($responses));
        $this->organizationProvider->setClientHandler($stack);
    }

    private static function createMockAuthServerResponses(): array
    {
        return [
            new Response(200, ['Content-Type' => 'application/json;charset=utf-8'], '{"authServerUrl": "https://auth-server.net/"}'),
            new Response(200, ['Content-Type' => 'application/json;charset=utf-8'], '{"token_endpoint": "https://token-endpoint.net/"}'),
            new Response(200, ['Content-Type' => 'application/json;charset=utf-8'], '{"access_token": "token", "expires_in": 3600, "token_type": "Bearer"}'),
        ];
    }

    public function testGetOrganizationByIdEn()
    {
        $options = [];
        Options::setLanguage($options, 'en');
        $org = $this->organizationProvider->getOrganizationById('37', $options);
        $this->assertSame('37', $org->getIdentifier());
        $this->assertSame('Graz University of Technology', $org->getName());
        $this->assertNull($org->getLocalData());
    }

    public function testGetOrganizationByIdDe()
    {
        $options = [];
        Options::setLanguage($options, 'de');
        $org = $this->organizationProvider->getOrganizationById('37', $options);
        $this->assertSame('37', $org->getIdentifier());
        $this->assertSame('Technische Universität Graz', $org->getName());
        $this->assertNull($org->getLocalData());
    }

    public function testGetOrganizationByIdWithLocalData()
    {
        $options = [];
        Options::setLanguage($options, 'en');
        Options::requestLocalDataAttributes($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME, self::TYPE_ATTRIBUTE_NAME]);
        $org = $this->organizationProvider->getOrganizationById('21', $options);
        $this->assertSame('21', $org->getIdentifier());
        $this->assertSame('Faculty of Mechanical Engineering and Economic Sciences', $org->getName());
        $this->assertSame('3000', $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
        $this->assertSame('680', $org->getLocalDataValue(self::TYPE_ATTRIBUTE_NAME));
    }

    public function testGetOrganizationByIdWithLocalDataWithApiRequestEn()
    {
        $this->mockResponses();

        $options = [];
        Options::setLanguage($options, 'en');
        Options::requestLocalDataAttributes($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME, self::TYPE_NAME_ATTRIBUTE_NAME]);
        $org = $this->organizationProvider->getOrganizationById('21', $options);
        $this->assertSame('21', $org->getIdentifier());
        $this->assertSame('Faculty of Mechanical Engineering and Economic Sciences', $org->getName());
        $this->assertSame('3000', $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
        $this->assertSame('Faculty', $org->getLocalDataValue(self::TYPE_NAME_ATTRIBUTE_NAME));
    }

    public function testGetOrganizationByIdWithLocalDataWithApiRequestDe()
    {
        $this->mockResponses();

        $options = [];
        Options::setLanguage($options, 'de');
        Options::requestLocalDataAttributes($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME, self::TYPE_NAME_ATTRIBUTE_NAME]);
        $org = $this->organizationProvider->getOrganizationById('21', $options);
        $this->assertSame('21', $org->getIdentifier());
        $this->assertSame('Fakultät für Maschinenbau und Wirtschaftswissenschaften', $org->getName());
        $this->assertSame('3000', $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
        $this->assertSame('Fakultät', $org->getLocalDataValue(self::TYPE_NAME_ATTRIBUTE_NAME));
    }

    public function testGetOrganizationsEn()
    {
        $options = [];
        Options::setLanguage($options, 'en');
        $organizations = $this->organizationProvider->getOrganizations(1, 30, $options);
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
        $organizations = $this->organizationProvider->getOrganizations(1, 30, $options);
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

    public function testGetOrganizationsPagination()
    {
        $options = [];
        Options::setLanguage($options, 'en');
        $organizationPage1 = $this->organizationProvider->getOrganizations(1, 2, $options);
        $this->assertCount(2, $organizationPage1);
        $organizationPage2 = $this->organizationProvider->getOrganizations(2, 2, $options);
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
        $organizations = $this->organizationProvider->getOrganizations(1, 30, $options);
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

    private function recreateOrganizationCache(): void
    {
        $this->mockResponses();
        try {
            // this is expected to fail, since sqlite does not support some operations
            $this->organizationProvider->recreateOrganizationsCache();
        } catch (\Throwable) {
            $organizationsLiveTable = CachedOrganization::TABLE_NAME;
            $organizationsStagingTable = CachedOrganizationStaging::TABLE_NAME;
            $organizationsTempTable = 'organizations_old';
            $organizationNamesLiveTable = CachedOrganizationName::TABLE_NAME;
            $organizationNamesStagingTable = CachedOrganizationNameStaging::TABLE_NAME;
            $organizationNamesTempTable = 'organization_names_old';
            $connection = $this->entityManager->getConnection();
            $connection->executeStatement("ALTER TABLE $organizationsLiveTable RENAME TO $organizationsTempTable;");
            $connection->executeStatement("ALTER TABLE $organizationsStagingTable RENAME TO $organizationsLiveTable;");
            $connection->executeStatement("ALTER TABLE $organizationsTempTable RENAME TO $organizationsStagingTable;");
            $connection->executeStatement("ALTER TABLE $organizationNamesLiveTable RENAME TO $organizationNamesTempTable;");
            $connection->executeStatement("ALTER TABLE $organizationNamesStagingTable RENAME TO $organizationNamesLiveTable;");
            $connection->executeStatement("ALTER TABLE $organizationNamesTempTable RENAME TO $organizationNamesStagingTable;");
        } finally {
            $this->organizationProvider->reset(); // ensure new api connection is created on subsequent requests
        }
    }
}
