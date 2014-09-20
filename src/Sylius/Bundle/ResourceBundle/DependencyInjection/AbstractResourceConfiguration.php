<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\ResourceBundle\DependencyInjection;

use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Aleksey Bannov <a.s.bannov@gmail.com>
 */
abstract class AbstractResourceConfiguration implements ConfigurationInterface
{
    /**
     * @param ArrayNodeDefinition $node
     * @param string              $driver
     * @param string              $objectManager
     * @param string             $template
     * @param array               $validationGroups
     *
     * @return AbstractResourceConfiguration
     */
    protected function addDefaults(
        ArrayNodeDefinition $node,
        $driver = null,
        $objectManager = null,
        array $validationGroups = array()
    ) {
        $node->append($this->createDriverNode($driver));
        $node->append($this->createObjectManagerNode($objectManager));
        $node->append($this->createTemplatesNode());

        $this->addValidationGroupsSection($node, $validationGroups);

        return $this;
    }


    protected function createResourcesSection(array $resources = array())
    {
        $builder = new TreeBuilder();
        $node = $builder->root('classes');
        $node
            ->addDefaultsIfNotSet()
            ;
        $resourceNodes = $node->children();
        foreach ($resources as $resource => $defaults){
            $resourceNode = $resourceNodes
                ->arrayNode($resource)
                ->addDefaultsIfNotSet()
                //->append($this->createDriverNode(null))
                //->append($this->createObjectManagerNode(null))
                /*->append($this->createValidationGroupNode(
                    isset($defaults['validation_group'])
                        ? $defaults['validation_group']
                        : array()
                ))
                ->append($this->createTemplateNode(
                    isset($defaults['templates']) ? $defaults['templates'] : null
                ))*/
            ;
                $this->addClassesSection($resourceNode, $defaults);
            $resourceNode->end();

            //$this->addDefaults($resourceNode);
            //$resourceNode;
        }
        return $node;
    }

    /**
     * @param array $defaults
     *
     * @return ArrayNodeDefinition
     */
    protected function addClassesSection(ArrayNodeDefinition $node, array $defaults = array())
    {
        //$builder = new TreeBuilder();
        //$node = $builder->root('classes');
        $node
            //->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('model')
                        ->cannotBeEmpty()
                        ->defaultValue(isset($defaults['model']) ? $defaults['model'] : null)
                    ->end()
                    ->scalarNode('controller')
                        ->defaultValue(
                            isset($defaults['controller'])
                                ? $defaults['controller']
                                : '%sylius.default.controller.class%'
                        )
                    ->end()
                    ->scalarNode('repository')
                        ->defaultValue(isset($defaults['repository']) ? $defaults['repository'] : null)
                    ->end()
                    ->scalarNode('interface')
                        ->defaultValue(isset($defaults['interface']) ? $defaults['interface'] : null)
                    ->end()
                    ->append($this->createFormsNode(isset($defaults['form']) ? $defaults['form'] : null))
                    ->append($this->createFormsNode(
                        isset($defaults['choice_form']) ? $defaults['choice_form'] : null,
                        'choice_form'
                    ))
                ->end()
           // ->end()
        ;
        return $node;
    }

    /**
     * @param string $default
     *
     * @return ScalarNodeDefinition
     */
    protected function createDriverNode($default = null)
    {
        $builder = new TreeBuilder();
        $node = $builder->root('driver', 'enum');

        if ($default){
            $node->defaultValue($default);
        }
        $node
            ->values(array(
                    SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
                    SyliusResourceBundle::DRIVER_DOCTRINE_MONGODB_ODM,
                    SyliusResourceBundle::DRIVER_DOCTRINE_PHPCR_ODM,
                ))
            ->cannotBeEmpty()
            ->info(sprintf(
                'Database driver (%s, %s or %s)',
                SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
                SyliusResourceBundle::DRIVER_DOCTRINE_MONGODB_ODM,
                SyliusResourceBundle::DRIVER_DOCTRINE_PHPCR_ODM
            ))
            ->end()
        ;

        return $node;
    }

    /**
     * @param string $default
     *
     * @return ScalarNodeDefinition
     */
    protected function createObjectManagerNode($default = 'default')
    {
        $builder = new TreeBuilder();
        $node = $builder->root('object_manager', 'scalar');

        if ($default){
            $node->defaultValue($default);
        }
        $node
            ->cannotBeEmpty()
            ->info('Name of object Manager')
            ->end();

        return $node;
    }

    /**
     * @param string $default
     *
     * @return ScalarNodeDefinition
     */
    protected function createTemplateNode($default = null)
    {
        $builder = new TreeBuilder();
        $node = $builder->root('templates', 'scalar');

        if ($default){
            $node->defaultValue($default);
        }
        $node
            ->info('Template namespace used by each resource')
            ->cannotBeEmpty()
        ->end();

        return $node;
    }

    /**
     * @return ArrayNodeDefinition
     */
    protected function createTemplatesNode()
    {
        $builder = new TreeBuilder();
        $node = $builder->root('templates');
        $node
            ->useAttributeAsKey('name')
                ->prototype('scalar')->end()
            ->end();

        return $node;
    }

    /**
     * @param array  $default
     *
     * @return ArrayNodeDefinition
     */
    protected function createValidationGroupNode(array $default = array())
    {
        $builder = new TreeBuilder();
        $node = $builder->root('validation_group');
        $node
            ->info('Validation groups used by the form component')
            ->prototype('scalar')->defaultValue($default)->end()
        ;

        return $node;
    }

    protected function addValidationGroupsSection(ArrayNodeDefinition $node, array $validationGroups = array())
    {
        $child = $node
            ->children()
                ->arrayNode('validation_groups')
                    ->addDefaultsIfNotSet()
                    ->children();
                        foreach ($validationGroups as $name=>$groups){
                            $child
                                ->arrayNode($name)
                                ->prototype('scalar')->end()
                                ->defaultValue($groups)
                                ->end();
                        }
                        $child
                    ->end()
                ->end()
            ->end();
    }


    protected function addTemplatesSection(ArrayNodeDefinition $node)
    {
        $node
            ->children()
                ->arrayNode('templates')
                    ->useAttributeAsKey('name')
                    ->prototype('scalar')
                ->end()
            ->end();
    }

    protected function createFormsNode($classes, $name = 'form')
    {
        $builder = new TreeBuilder();
        $node = $builder->root($name);

        $node
            ->info('')
            ->useAttributeAsKey('name')
            ->prototype('scalar')->end()
            ->beforeNormalization()
                ->ifString()
                ->then(function ($v) {
                        return array('default' => $v);
                    })
            ->end()
        ;
        if (!empty($classes)){
            if (!is_array($classes)) {
                $classes = ['default' => $classes];
            }
            $node->defaultValue($classes);
        }

        return $node;
    }
}
