<?php

namespace Rollerworks\DBBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
	/**
	 * {@inheritDoc}
	 */
	public function getConfigTreeBuilder()
	{
		$treeBuilder = new TreeBuilder();
		$rootNode    = $treeBuilder->root('rollerworks_db');

		$rootNode
			->children()
				->arrayNode('user_exception_listener')
					->addDefaultsIfNotSet()
					->fixXmlConfig('check_class_in')
					->children()

						->arrayNode( 'check_class_in' )
							->addDefaultsIfNotSet()
							->defaultValue(array('PDOException', 'Doctrine\DBAL\Driver\OCI8\OCI8Exception'))
							->prototype('scalar')->end()
						->end()

						->scalarNode('check_prefix')->defaultValue('app-exception: ')->end()
					->end()
				->end()
			->end()
		->end() ;

		return $treeBuilder;
	}
}

