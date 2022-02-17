<?php

declare(strict_types=1);

namespace Dbp\Relay\BaseOrganizationConnectorCampusonlineBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_base_organization_connector_campusonline');
        $treeBuilder->getRootNode()
            ->children()
            ->arrayNode('campus_online')
            ->children()
            ->scalarNode('api_url')->end()
            ->scalarNode('api_token')->end()
            ->scalarNode('org_root_id')->end()
            ->end()
            ->end();

        return $treeBuilder;
    }
}
