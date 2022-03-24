<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Tests;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use Dbp\CampusonlineApi\LegacyWebService\ApiException;
use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\Service\OrganizationApi;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class OrganizationTest extends ApiTestCase
{
    private $api;

    protected function setUp(): void
    {
        parent::setUp();

        $this->api = new OrganizationApi();
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
        $org = $this->api->getOrganizationById('2322');
        $this->assertSame('2322', $org->getIdentifier());
        $this->assertSame('Institute of Fundamentals and Theory in Electrical  Engineering', $org->getName());
    }

    public function testGetOrganizationByIdNoPermission()
    {
        $this->mockResponses([
            new Response(403, [], 'error'),
        ]);
        $this->expectException(ApiException::class);
        $this->api->getOrganizationById('2234');
    }

    public function testGetOrganizations()
    {
        $this->mockResponses([
            new Response(200, ['Content-Type' => 'text/xml;charset=utf-8'], file_get_contents(__DIR__.'/co_orgunit_response_nested.xml')),
        ]);
        $result = $this->api->getOrganizations();
        $this->assertCount(3, $result);
        $this->assertSame('2391', $result[0]->getIdentifier());
        $this->assertSame('18454', $result[1]->getIdentifier());
        $this->assertSame('18452', $result[2]->getIdentifier());
    }
}
