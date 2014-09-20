<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\ResourceBundle\Tests\DependencyInjection;

use ReflectionMethod;
use Sylius\Bundle\ResourceBundle\DependencyInjection\AbstractResourceConfiguration;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

/**
 * @author Aleksey Bannov <a.s.bannov@gmail.com>
 */
class AbstractResourceConfigurationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Processor
     */
    protected $processor;

    /**
     * @var AbstractResourceConfiguration
     */
    protected $configuration;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->processor = new Processor();

        $this->configuration = new ConcreteResourceConfiguration();

    }

    public function testEmpty()
    {
        $configuration = $this->processor->processConfiguration($this->configuration, array(
            'sylius' => array(
                'driver' => 'doctrine/orm',
                //'classes' => array(),
            )
        ))
        ;

        // $reflectionMethod = new \ReflectionMethod('HelloWorld', 'sayHelloTo');
        //echo $reflectionMethod->invoke(new ConcreteResourceConfiguration(), 'Mike');

        $this->assertEquals(array(
            'driver' => 'doctrine/orm',
            //'classes' => array(),
        ), $configuration)
        ;
    }

    public function testCreateClassesSection()
    {
        $this->markTestSkipped();
        $config = $this->processor
            ->process(
                $this->buildNode(
                    $this->invokeProtectedMethod('createClassesSection')
                ),
                array(
                    'sylius' => array(
                        'classes' => array(
                            'model' => 'Sylius\Model'
                        ),
                    )
                )
            )
        ;

        $this->assertEquals(array(
            'classes' => array(
                'model'      => 'Sylius\Model',
                'controller' => '%sylius.default.controller.class%'
            ),
        ), $config);
    }

    public function testCreateFormsNodeForSingleForm()
    {
        $config = $this->processor
            ->process(
                $this->buildNode(
                    $this->invokeProtectedMethod('createFormsNode', array(array()))
                ),
                array(
                    'sylius' => array(
                        'form' => 'Sylius\FormType',
                    )
                )
            )
        ;

        $this->assertEquals(array(
            'form' => array(
                'default' => 'Sylius\FormType',
            ),
        ), $config);

        $config = $this->processor
            ->process(
                $this->buildNode(
                    $this->invokeProtectedMethod('createFormsNode', array('Sylius\FormType'))
                ),
                array(
                    'sylius' => array(
                    )
                )
            )
        ;

        $this->assertEquals(array(
            'form' => array(
                'default' => 'Sylius\FormType',
            ),
        ), $config);
    }


    public function testCreateFormsNodeForManyForms()
    {
        $config = $this->processor
            ->process(
                $this->buildNode(
                    $this->invokeProtectedMethod('createFormsNode', array(
                        array(
                            'default'     => 'Sylius\FormType',
                            'second_form' => 'Sylius\SecondFormType',
                        )
                    ))
                ),
                array(
                    'sylius' => array()
                )
            )
        ;

        $this->assertEquals(array(
            'form' => array(
                'default' => 'Sylius\FormType',
                'second_form' => 'Sylius\SecondFormType',
            ),
        ), $config);

    }

    public function testCreateObjectManagerNode()
    {
        $config = $this->processor
            ->process(
                $this->buildNode(
                    $this->invokeProtectedMethod('createObjectManagerNode', array('default'))
                ),
                array(
                    'sylius' => array(
                    )
                )
            )
        ;

        $this->assertEquals(array(
            'object_manager' => 'default',
        ), $config);
    }

    public function testCreateDriverNodeWithDefaultValue()
    {
        $config = $this->processor
            ->process(
                $this->buildNode(
                    $this->invokeProtectedMethod('createDriverNode', array('doctrine/orm'))
                ),
                array(
                    'sylius' => array(
                    )
                )
            )
        ;

        $this->assertEquals(array(
            'driver' => 'doctrine/orm',
        ), $config);
    }

    public function testCreateDriverNodeWithoutDefaultValue()
    {
        $config = $this->processor
            ->process(
                $this->buildNode(
                    $this->invokeProtectedMethod('createDriverNode')
                ),
                array(
                    'sylius' => array(
                        'driver' => 'doctrine/orm',
                    )
                )
            )
        ;

        $this->assertEquals(array(
            'driver' => 'doctrine/orm',
        ), $config);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testExceptionOnInvalidDriver()
    {
        $this->processor
            ->process(
                $this->buildNode(
                    $this->invokeProtectedMethod('createDriverNode')
                ),
                array(
                    'sylius' => array(
                        'driver' => 'unknown_driver'
                    )
                )
            )
        ;
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testDriverShouldBeDefined()
    {
        $this->processor
            ->process(
                $this->buildNode(
                    $this->invokeProtectedMethod('createDriverNode')
                ),
                array(
                    'sylius' => array(
                        'driver' => ''
                    )
                )
            )
        ;
    }

    /**
     * @param NodeDefinition $definition
     *
     * @return \Symfony\Component\Config\Definition\Builder\NodeInterface|\Symfony\Component\Config\Definition\NodeInterface
     */
    protected function buildNode(NodeDefinition $definition)
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('sylius');

        $rootNode->append($definition);

        return $treeBuilder->buildTree();
    }

    /**
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    protected function invokeProtectedMethod($method, array $args = array())
    {
        $reflectionMethod = new ReflectionMethod(
            'Sylius\Bundle\ResourceBundle\Tests\DependencyInjection\ConcreteResourceConfiguration',
            $method
        );
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($this->configuration, $args);
    }
}

class ConcreteResourceConfiguration extends AbstractResourceConfiguration
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('sylius');

        $this
            ->addDefaults($rootNode, null, null, null, array(
                'product' => array('sylius'),
                'product_prototype' => array('sylius'),
            ))
        ;

        $rootNode
            ->append($this->createResourcesSection(array(
                    'product'           => array(
                        'classes' => array(
                            'model' => 'Sylius\Product',
                            'form'  => 'Sylius\ProductType',
                        )
                    ),
                    'product_prototype' => array(
                        'classes' => array(
                            'model'      => 'Sylius\Prototype',
                            'controller' => 'Sylius\Bundle\ProductBundle\Controller\PrototypeController',
                            'form'       => 'Sylius\Prototype',
                        )
                    ),
                ))
            );

        return $treeBuilder;
    }
}