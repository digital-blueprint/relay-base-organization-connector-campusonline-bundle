<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection\Configuration;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection\DbpRelayBaseOrganizationConnectorCampusonlineExtension;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganization;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Entity\CachedOrganizationName;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber\OrganizationEventSubscriber;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\CoreBundle\TestUtils\TestEntityManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;

class OrganizationProviderTest extends ApiTestCase
{
    private const ORGANIZATION_CODE_ATTRIBUTE_NAME = 'code';
    private const CITY_ATTRIBUTE_NAME = 'addressLocality';

    private ?OrganizationProvider $organizationProvider = null;
    private ?EntityManagerInterface $entityManager = null;
    private ?OrganizationEventSubscriber $organizationEventSubscriber = null;

    private static function createEventSubscriberConfig(bool $publicRest): array
    {
        $config = [];
        $config['local_data_mapping'] = [
            [
                'local_data_attribute' => self::ORGANIZATION_CODE_ATTRIBUTE_NAME,
                'source_attribute' => $publicRest ? CachedOrganization::CODE : 'code',
                'default_value' => '',
            ],
            [
                'local_data_attribute' => self::CITY_ATTRIBUTE_NAME,
                'source_attribute' => $publicRest ? CachedOrganization::ADDRESS_CITY : 'addressLocality',
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

        $this->createStagingTables();

        $this->organizationProvider = new OrganizationProvider($this->entityManager, $eventDispatcher);
        $this->organizationProvider->setCache(new ArrayAdapter(), 3600);
        $this->organizationProvider->setLogger(new NullLogger());

        $this->organizationEventSubscriber = new OrganizationEventSubscriber();
        $eventDispatcher->addSubscriber($this->organizationEventSubscriber);
    }

    private function createStagingTables(): void
    {
        $organizationTableName = CachedOrganization::TABLE_NAME;
        $organizationStagingTableName = CachedOrganization::STAGING_TABLE_NAME;

        $this->entityManager->getConnection()->executeStatement(
            "CREATE TABLE $organizationStagingTableName AS SELECT * FROM $organizationTableName");

        $organizationNamesTableName = CachedOrganizationName::TABLE_NAME;
        $organizationNamesStagingTableName = CachedOrganizationName::STAGING_TABLE_NAME;

        $this->entityManager->getConnection()->executeStatement(
            "CREATE TABLE $organizationNamesStagingTableName AS SELECT * FROM $organizationNamesTableName");
    }

    private function setUpLegacyApi(): void
    {
        $this->organizationProvider->setConfig($this->getLegacyApiConfig());
        $this->organizationProvider->reset();
        $this->organizationEventSubscriber->setConfig(self::createEventSubscriberConfig(false));
    }

    private function setUpPublicRestApi(): void
    {
        $this->organizationProvider->setConfig($this->getPublicRestApiConfig());
        $this->organizationProvider->reset();
        $this->organizationEventSubscriber->setConfig(self::createEventSubscriberConfig(true));

        $this->recreateOrganizationCache();
    }

    private function getLegacyApiConfig(): array
    {
        $config = [];
        $config[Configuration::CAMPUS_ONLINE_NODE] = [
            'legacy' => true,
            'org_root_id' => '1',
        ];

        return $config;
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

    private function mockResponses(array $responses): void
    {
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

    public function testGetOrganizationByIdEnPublicRest()
    {
        $this->setUpPublicRestApi();

        $options = [];
        Options::setLanguage($options, 'en');
        $org = $this->organizationProvider->getOrganizationById('37', $options);
        $this->assertSame('37', $org->getIdentifier());
        $this->assertSame('Graz University of Technology', $org->getName());
        $this->assertNull($org->getLocalData());
    }

    public function testGetOrganizationByIdDePublicRest()
    {
        $this->setUpPublicRestApi();

        $options = [];
        Options::setLanguage($options, 'de');
        $org = $this->organizationProvider->getOrganizationById('37', $options);
        $this->assertSame('37', $org->getIdentifier());
        $this->assertSame('Technische Universität Graz', $org->getName());
        $this->assertNull($org->getLocalData());
    }

    public function testGetOrganizationByIdWithLocalDataPublicRest()
    {
        $this->setUpPublicRestApi();

        $options = [];
        Options::setLanguage($options, 'en');
        Options::requestLocalDataAttributes($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME, self::CITY_ATTRIBUTE_NAME]);
        $org = $this->organizationProvider->getOrganizationById('21', $options);
        $this->assertSame('21', $org->getIdentifier());
        $this->assertSame('Faculty of Mechanical Engineering and Economic Sciences', $org->getName());
        $this->assertSame('3000', $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
        $this->assertSame('Graz City', $org->getLocalDataValue(self::CITY_ATTRIBUTE_NAME));
    }

    public function testGetOrganizationsEnPublicRest()
    {
        $this->setUpPublicRestApi();

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

    public function testGetOrganizationsDePublicRest()
    {
        $this->setUpPublicRestApi();

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

    public function testGetOrganizationsPaginationPublicRest()
    {
        $this->setUpPublicRestApi();

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

    public function testGetOrganizationsLocalDataPublicRest()
    {
        $this->setUpPublicRestApi();

        $options = [];
        Options::setLanguage($options, 'en');
        Options::requestLocalDataAttributes($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME, self::CITY_ATTRIBUTE_NAME]);
        $organizations = $this->organizationProvider->getOrganizations(1, 30, $options);
        $this->assertCount(3, $organizations);
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '37'
                && $org->getName() === 'Graz University of Technology'
                && $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME) === '90000'
                && $org->getLocalDataValue(self::CITY_ATTRIBUTE_NAME) === 'Graz'));
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '22'
                && $org->getName() === 'Institute of Chemical and Process Engineering'
                && $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME) === '3510'
                && $org->getLocalDataValue(self::CITY_ATTRIBUTE_NAME) === 'Graz'));
        $this->assertCount(1, array_filter($organizations,
            fn ($org) => $org->getIdentifier() === '21'
                && $org->getName() === 'Faculty of Mechanical Engineering and Economic Sciences'
                && $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME) === '3000'
                && $org->getLocalDataValue(self::CITY_ATTRIBUTE_NAME) === 'Graz City'));
    }

    public function testGetOrganizationByIdLegacy()
    {
        $this->setUpLegacyApi();

        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response.xml')),
        ]);
        $org = $this->organizationProvider->getOrganizationById('2322');
        $this->assertSame('2322', $org->getIdentifier());
        $this->assertSame('Institute of Fundamentals and Theory in Electrical  Engineering', $org->getName());
        $this->assertNull($org->getLocalData());
    }

    public function testGetOrganizationByIdLocalDataLegacy()
    {
        $this->setUpLegacyApi();

        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response.xml')),
        ]);

        $options = [];
        Options::requestLocalDataAttributes($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME]);
        $org = $this->organizationProvider->getOrganizationById('2322', $options);

        $this->assertSame('2322', $org->getIdentifier());
        $this->assertSame('Institute of Fundamentals and Theory in Electrical  Engineering', $org->getName());
        $this->assertSame('4370', $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
    }

    public function testGetOrganizationByIdNotFoundLegacy()
    {
        $this->setUpLegacyApi();

        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response.xml')),
        ]);

        try {
            $this->organizationProvider->getOrganizationById('---');
        } catch (\Throwable $exception) {
            $this->assertInstanceOf(ApiError::class, $exception);
            $this->assertEquals(404, $exception->getStatusCode());
        }
    }

    public function testGetOrganizationByIdNoPermissionLegacy()
    {
        $this->setUpLegacyApi();

        $this->mockResponses([
            new Response(403, [], 'error'),
        ]);

        try {
            $this->organizationProvider->getOrganizationById('2234');
            $this->fail('Expected ApiError exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(\Symfony\Component\HttpFoundation\Response::HTTP_INTERNAL_SERVER_ERROR, $apiError->getStatusCode());
        }
    }

    public function testGetOrganizationsLegacy()
    {
        $this->setUpLegacyApi();

        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response_nested.xml')),
        ]);

        $organizations = $this->organizationProvider->getOrganizations(1, 10);
        $this->assertCount(3, $organizations);

        $this->assertSame('2391', $organizations[0]->getIdentifier());
        $this->assertNull($organizations[0]->getLocalData());
        $this->assertSame('18454', $organizations[1]->getIdentifier());
        $this->assertNull($organizations[1]->getLocalData());
        $this->assertSame('18452', $organizations[2]->getIdentifier());
        $this->assertNull($organizations[2]->getLocalData());
    }

    public function testGetOrganizationsLocalDataLegacy()
    {
        $this->setUpLegacyApi();

        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response_nested.xml')),
        ]);

        $options = [];
        Options::requestLocalDataAttributes($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME]);
        $organizations = $this->organizationProvider->getOrganizations(1, 10, $options);
        $this->assertCount(3, $organizations);

        $this->assertSame('2391', $organizations[0]->getIdentifier());
        $this->assertSame('6350', $organizations[0]->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
        $this->assertSame('18454', $organizations[1]->getIdentifier());
        $this->assertSame('6352', $organizations[1]->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
        $this->assertSame('18452', $organizations[2]->getIdentifier());
        $this->assertSame('6351', $organizations[2]->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
    }

    public function testGetOrganizationsPartialPaginationLegacy()
    {
        $this->setUpLegacyApi();

        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response_nested.xml')),
        ]);

        $organizations = $this->organizationProvider->getOrganizations(3, 1);
        $this->assertCount(1, $organizations);

        // assert is third item:
        $this->assertSame('18452', $organizations[0]->getIdentifier());
    }

    private function recreateOrganizationCache(): void
    {
        $this->mockResponses([...self::createMockAuthServerResponses(),
            new Response(
                200,
                ['Content-Type' => 'application/json'],
                file_get_contents(__DIR__.'/public_rest_org_api_response.json')),
        ]);
        try {
            // this is expected to fail, since sqlite does not support some operations
            $this->organizationProvider->recreateOrganizationsCache();
        } catch (\Throwable) {
            $organizationsLiveTable = CachedOrganization::TABLE_NAME;
            $organizationsStagingTable = CachedOrganization::STAGING_TABLE_NAME;
            $organizationsTempTable = 'organizations_old';
            $organizationNamesLiveTable = CachedOrganizationName::TABLE_NAME;
            $organizationNamesStagingTable = CachedOrganizationName::STAGING_TABLE_NAME;
            $organizationNamesTempTable = 'organization_names_old';
            $connection = $this->entityManager->getConnection();
            $connection->executeStatement("ALTER TABLE $organizationsLiveTable RENAME TO $organizationsTempTable;");
            $connection->executeStatement("ALTER TABLE $organizationsStagingTable RENAME TO $organizationsLiveTable;");
            $connection->executeStatement("ALTER TABLE $organizationsTempTable RENAME TO $organizationsStagingTable;");
            $connection->executeStatement("ALTER TABLE $organizationNamesLiveTable RENAME TO $organizationNamesTempTable;");
            $connection->executeStatement("ALTER TABLE $organizationNamesStagingTable RENAME TO $organizationNamesLiveTable;");
            $connection->executeStatement("ALTER TABLE $organizationNamesTempTable RENAME TO $organizationNamesStagingTable;");
        }
    }
}
