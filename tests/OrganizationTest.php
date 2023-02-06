<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Tests;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber\OrganizationEventSubscriber;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationApi;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Dbp\Relay\CoreBundle\Authorization\AuthorizationDataMuxer;
use Dbp\Relay\CoreBundle\Authorization\AuthorizationDataProviderProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\LocalData\LocalData;
use Dbp\Relay\CoreBundle\TestUtils\TestUserSession;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\EventDispatcher\EventDispatcher;

class OrganizationTest extends ApiTestCase
{
    private const ORGANIZATION_CODE_ATTRIBUTE_NAME = 'code';
    private const ADDRESS_LOCALITY_ATTRIBUTE_NAME = 'addressLocality';

    /** @var OrganizationApi */
    private $organizationApi;

    /** @var OrganizationProvider */
    private $organizationProvider;

    private static function createConfig(): array
    {
        $config = [];
        $config['local_data_mapping'] = [
            [
                'local_data_attribute' => self::ORGANIZATION_CODE_ATTRIBUTE_NAME,
                'source_attribute' => self::ORGANIZATION_CODE_ATTRIBUTE_NAME,
                'authorization_expression' => 'true',
                'allow_query' => false,
                'default_value' => '',
            ],
            [
                'local_data_attribute' => self::ADDRESS_LOCALITY_ATTRIBUTE_NAME,
                'source_attribute' => self::ADDRESS_LOCALITY_ATTRIBUTE_NAME,
                'authorization_expression' => 'true',
                'allow_query' => true,
                'default_value' => '',
            ],
        ];

        return $config;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $eventDispatcher = new EventDispatcher();
        $localDataEventSubscriber = new OrganizationEventSubscriber();
        $localDataEventSubscriber->_injectServices(new TestUserSession('testuser'), new AuthorizationDataMuxer(new AuthorizationDataProviderProvider([]), new EventDispatcher()));
        $localDataEventSubscriber->setConfig(self::createConfig());
        $eventDispatcher->addSubscriber($localDataEventSubscriber);
        $this->organizationApi = new OrganizationApi();
        $this->organizationProvider = new OrganizationProvider($this->organizationApi, $eventDispatcher);
        $this->mockResponses([]);
    }

    private function mockResponses(array $responses)
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $this->organizationApi->setClientHandler($stack);
    }

    public function testGetOrganizationById()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response.xml')),
        ]);
        $org = $this->organizationProvider->getOrganizationById('2322');
        $this->assertSame('2322', $org->getIdentifier());
        $this->assertSame('Institute of Fundamentals and Theory in Electrical  Engineering', $org->getName());
        $this->assertNull($org->getLocalData());
    }

    public function testGetOrganizationByIdLocalData()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response.xml')),
        ]);

        $options = [];
        LocalData::addIncludeParameter($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME]);
        $org = $this->organizationProvider->getOrganizationById('2322', $options);

        $this->assertSame('2322', $org->getIdentifier());
        $this->assertSame('Institute of Fundamentals and Theory in Electrical  Engineering', $org->getName());
        $this->assertSame('4370', $org->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
    }

    public function testGetOrganizationByIdNotFound()
    {
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

    public function testGetOrganizationByIdNoPermission()
    {
        $this->mockResponses([
            new Response(403, [], 'error'),
        ]);

        try {
            $this->organizationProvider->getOrganizationById('2234');
        } catch (\Throwable $exception) {
            $this->assertInstanceOf(ApiError::class, $exception);
            $this->assertEquals(500, $exception->getStatusCode());
        }
    }

    public function testGetOrganizations()
    {
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

    public function testGetOrganizationsLocalData()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response_nested.xml')),
        ]);

        $options = [];
        LocalData::addIncludeParameter($options, [self::ORGANIZATION_CODE_ATTRIBUTE_NAME]);
        $organizations = $this->organizationProvider->getOrganizations(1, 10, $options);
        $this->assertCount(3, $organizations);

        $this->assertSame('2391', $organizations[0]->getIdentifier());
        $this->assertSame('6350', $organizations[0]->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
        $this->assertSame('18454', $organizations[1]->getIdentifier());
        $this->assertSame('6352', $organizations[1]->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
        $this->assertSame('18452', $organizations[2]->getIdentifier());
        $this->assertSame('6351', $organizations[2]->getLocalDataValue(self::ORGANIZATION_CODE_ATTRIBUTE_NAME));
    }

    public function testGetOrganizationsLocalQuery()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response_nested.xml')),
        ]);

        $options = [];
        LocalData::addIncludeParameter($options, [self::ADDRESS_LOCALITY_ATTRIBUTE_NAME]);
        $options[LocalData::QUERY_PARAMETER_NAME] = self::ADDRESS_LOCALITY_ATTRIBUTE_NAME.':graz';
        $organizations = $this->organizationProvider->getOrganizations(1, 10, $options);
        $this->assertCount(1, $organizations);

        $this->assertSame('2391', $organizations[0]->getIdentifier());
        $this->assertSame('Graz', $organizations[0]->getLocalDataValue(self::ADDRESS_LOCALITY_ATTRIBUTE_NAME));
    }

    public function testGetOrganizationsPartialPagination()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response_nested.xml')),
        ]);

        $organizations = $this->organizationProvider->getOrganizations(3, 1);
        $this->assertCount(1, $organizations);

        // assert is third item:
        $this->assertSame('18452', $organizations[0]->getIdentifier());
    }
}
