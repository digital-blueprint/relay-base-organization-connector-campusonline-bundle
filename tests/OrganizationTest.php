<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Tests;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationApi;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationProvider;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Pagination\FullPaginator;
use Dbp\Relay\CoreBundle\Pagination\PartialPaginator;
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

        $paginator = $this->provider->getOrganizations();
        $this->assertInstanceOf(FullPaginator::class, $paginator);
        $this->assertSame(3.0, $paginator->getTotalItems());
        $this->assertCount(3, $paginator->getItems());

        $this->assertSame('2391', $paginator->getItems()[0]->getIdentifier());
        $this->assertSame('18454', $paginator->getItems()[1]->getIdentifier());
        $this->assertSame('18452', $paginator->getItems()[2]->getIdentifier());
    }

    public function testGetOrganizationsFullPagination()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response_nested.xml')),
        ]);

        $paginator = $this->provider->getOrganizations(['perPage' => 1, 'page' => 2]);
        $this->assertInstanceOf(FullPaginator::class, $paginator);
        $this->assertSame(3.0, $paginator->getTotalItems());
        $this->assertEquals(1, $paginator->getItemsPerPage());
        $this->assertEquals(2, $paginator->getCurrentPage());
        $this->assertCount(1, $paginator->getItems());

        $this->assertSame('18454', $paginator->getItems()[0]->getIdentifier());
    }

    public function testGetOrganizationsPartialPagination()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response_nested.xml')),
        ]);

        $paginator = $this->provider->getOrganizations(['partialPagination' => true, 'perPage' => 1, 'page' => 3]);
        $this->assertInstanceOf(PartialPaginator::class, $paginator);
        $this->assertEquals(1, $paginator->getItemsPerPage());
        $this->assertEquals(3, $paginator->getCurrentPage());
        $this->assertCount(1, $paginator->getItems());

        $this->assertSame('18452', $paginator->getItems()[0]->getIdentifier());
    }
}
