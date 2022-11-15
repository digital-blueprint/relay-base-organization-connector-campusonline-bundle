<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Tests;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationApi;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\EventDispatcher\EventDispatcher;

class OrganizationTest extends ApiTestCase
{
    /** @var OrganizationApi */
    private $api;

    /** @var OrganizationProvider */
    private $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new OrganizationApi();
        $this->provider = new OrganizationProvider($this->api, new EventDispatcher());
        $this->mockResponses([]);
    }

    private function mockResponses(array $responses)
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $this->api->setClientHandler($stack);
    }

    public function testGetOrganizationById()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response.xml')),
        ]);
        $org = $this->provider->getOrganizationById('2322');
        $this->assertSame('2322', $org->getIdentifier());
        $this->assertSame('Institute of Fundamentals and Theory in Electrical  Engineering', $org->getName());
    }

    public function testGetOrganizationByIdNotFound()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response.xml')),
        ]);

        try {
            $this->provider->getOrganizationById('---');
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
            $this->provider->getOrganizationById('2234');
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

        $organizations = $this->provider->getOrganizations(1, 10);
        $this->assertCount(3, $organizations);

        $this->assertSame('2391', $organizations[0]->getIdentifier());
        $this->assertSame('18454', $organizations[1]->getIdentifier());
        $this->assertSame('18452', $organizations[2]->getIdentifier());
    }

    public function testGetOrganizationsPartialPagination()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response_nested.xml')),
        ]);

        $organizations = $this->provider->getOrganizations(3, 1);
        $this->assertCount(1, $organizations);

        // assert is third item:
        $this->assertSame('18452', $organizations[0]->getIdentifier());
    }
}
