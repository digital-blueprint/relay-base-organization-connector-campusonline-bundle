<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection;

use Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\EventSubscriber\OrganizationPostEventSubscriber;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const CAMPUS_ONLINE_NODE = 'campus_online';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_base_organization_connector_campusonline');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode(self::CAMPUS_ONLINE_NODE)
                    ->children()
                        ->scalarNode('api_url')->end()
                        ->scalarNode('api_token')->end()
                        ->scalarNode('org_root_id')->end()
                    ->end()
                ->end()
                ->append(OrganizationPostEventSubscriber::getLocalDataMappingConfigNodeDefinition())
            ->end();

        return $treeBuilder;
    }
}
