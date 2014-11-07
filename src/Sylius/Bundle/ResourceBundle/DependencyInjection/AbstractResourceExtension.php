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

use Sylius\Bundle\ResourceBundle\DependencyInjection\Driver\DatabaseDriverFactory;
use Sylius\Component\Resource\Exception\Driver\InvalidDriverException;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Base extension.
 *
 * @author Paweł Jędrzejewski <pjedrzejewski@sylius.pl>
 */
abstract class AbstractResourceExtension extends Extension
{
    const CONFIGURE_LOADER     = 1;
    const CONFIGURE_DATABASE   = 2;
    const CONFIGURE_PARAMETERS = 4;
    const CONFIGURE_VALIDATORS = 8;
    const CONFIGURE_FORMS      = 16;

    protected $applicationName = 'sylius';
    protected $configDirectory = '/../Resources/config';
    protected $configFiles = array(
        'services',
    );

    const DEFAULT_KEY = 'default';

    /**
     * {@inheritdoc}
     */
    public function load(array $config, ContainerBuilder $container)
    {
        $this->configure($config, new Configuration(), $container);
    }

    /**
     * @param array                  $config
     * @param ConfigurationInterface $configuration
     * @param ContainerBuilder       $container
     * @param integer                $configure
     *
     * @return array
     */
    public function configure(
        array $config,
        ConfigurationInterface $configuration,
        ContainerBuilder $container,
        $configure = self::CONFIGURE_LOADER
    ) {
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, $config);

        $config = $this->process($config, $container);

        $loader = new XmlFileLoader($container, new FileLocator($this->getConfigurationDirectory()));

        $this->loadConfigurationFile($this->configFiles, $loader);

        if ($configure & self::CONFIGURE_DATABASE) {
            $this->loadDatabaseDriver($config, $loader, $container);
        }

        $classes = isset($config['classes']) ? $config['classes'] : array();

        if ($configure & self::CONFIGURE_PARAMETERS) {
            $this->mapClassParameters($classes, $container);
        }

        if ($configure & self::CONFIGURE_VALIDATORS) {
            $this->mapValidationGroupParameters($config['validation_groups'], $container);
        }

        if ($configure & self::CONFIGURE_FORMS) {
            $this->registerFormTypes($config, $container);
        }

        if ($container->hasParameter('sylius.config.classes')) {
            $classes = array_merge($classes, $container->getParameter('sylius.config.classes'));
        }

        $container->setParameter('sylius.config.classes', $classes);

        return array($config, $loader);
    }

    /**
     * Remap class parameters.
     *
     * @param array            $classes
     * @param ContainerBuilder $container
     */
    protected function mapClassParameters(array $classes, ContainerBuilder $container)
    {
        foreach ($classes as $model => $serviceClasses) {
            foreach ($serviceClasses as $service => $class) {
                if (!is_array($class)) {
                    $class = array(self::DEFAULT_KEY => $class);
                }
                foreach ($class as $suffix => $subClass) {
                    $container->setParameter(
                        sprintf(
                            '%s.%s.%s%s.class',
                            $this->applicationName,
                            in_array($service, array('form', 'choice_form')) ? 'form.type' : $service,
                            $model,
                            $suffix === self::DEFAULT_KEY 
                                ? ($service === 'choice_form' ? '_choice' : '')
                                : sprintf('_%s', $suffix)
                        ),
                        $subClass
                    );
                }
            }
        }
    }

    /**
     * Register resource form types
     *
     * @param array            $config
     * @param ContainerBuilder $container
     */
    protected function registerFormTypes(array $config, ContainerBuilder $container)
    {
        foreach ($config['classes'] as $model => $serviceClasses) {
            // registering resource form types
            if (isset($serviceClasses['form'])) {
                if (!is_array($serviceClasses['form'])) {
                    $this->createResourceFormDefinition($container, $model, $serviceClasses['form']);
                } else {
                    foreach ($serviceClasses['form'] as $name => $class) {
                        $this->createResourceFormDefinition(
                            $container,
                            $model.($name === self::DEFAULT_KEY ? '' : sprintf('_%s', $name)),
                            $class
                        );
                    }
                }
            }

            // registering resource choice form types
            if (!empty($serviceClasses['choice_form'])) {
                $name = sprintf('%s_%s_choice', $this->applicationName, $model);
                $definition = new Definition($serviceClasses['choice_form']);
                $definition
                    ->setArguments(array(
                        new Parameter(sprintf('%s.model.%s.class', $this->applicationName, $model)),
                        new Parameter(sprintf('%s.driver', $this->getAlias())),
                        $name
                    ))
                    ->addTag('form.type', array('alias' => $name))
                ;

                $container->setDefinition(
                    sprintf('%s.form.type.%s_choice', $this->applicationName, $model),
                    $definition
                );
            }

            // registering resource filter form types... coming soon
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param string           $name  Form name
     * @param string           $class Form type class
     */
    protected function createResourceFormDefinition(ContainerBuilder $container, $name, $class)
    {
        $definition = new Definition($class);
        $definition
            ->setArguments(array(
                new Parameter(sprintf('%s.model.%s.class', $this->applicationName, $name)),
                new Parameter(sprintf('%s.validation_group.%s', $this->applicationName, $name))
            ))
            ->addTag('form.type',
                array(
                    'alias' => sprintf('%s_%s', $this->applicationName, $name)
                ))
        ;
        $container->setDefinition(sprintf('%s.form.type.%s', $this->applicationName, $name), $definition);
    }

    /**
     * Remap validation group parameters.
     *
     * @param array            $validationGroups
     * @param ContainerBuilder $container
     */
    protected function mapValidationGroupParameters(array $validationGroups, ContainerBuilder $container)
    {
        foreach ($validationGroups as $model => $groups) {
            $container->setParameter(sprintf('%s.validation_group.%s', $this->applicationName, $model), $groups);
        }
    }

    /**
     * Load bundle driver.
     *
     * @param array                 $config
     * @param XmlFileLoader         $loader
     * @param null|ContainerBuilder $container
     *
     * @throws InvalidDriverException
     */
    protected function loadDatabaseDriver(array $config, XmlFileLoader $loader, ContainerBuilder $container)
    {
        $bundle = str_replace(array('Extension', 'DependencyInjection\\'), array('Bundle', ''), get_class($this));
        $driver = $config['driver'];
        $manager = isset($config['object_manager']) ? $config['object_manager'] : 'default';

        if (!in_array($driver, call_user_func(array($bundle, 'getSupportedDrivers')))) {
            throw new InvalidDriverException($driver, basename($bundle));
        }

        $this->loadConfigurationFile(array(sprintf('driver/%s', $driver)), $loader);

        $container->setParameter(sprintf('%s.driver', $this->getAlias()), $driver);
        $container->setParameter(sprintf('%s.driver.%s', $this->getAlias(), $driver), true);
        $container->setParameter(sprintf('%s.object_manager', $this->getAlias()), $manager);

        foreach ($config['classes'] as $model => $classes) {
            if (array_key_exists('model', $classes)) {
                DatabaseDriverFactory::get(
                    $driver,
                    $container,
                    $this->applicationName,
                    $model,
                    isset($config['object_manager']) ? $config['object_manager'] : 'default',
                    isset($config['templates'][$model]) ? $config['templates'][$model] : null
                )->load($classes);
            }
        }
    }

    /**
     * @param array         $config
     * @param XmlFileLoader $loader
     */
    protected function loadConfigurationFile(array $config, XmlFileLoader $loader)
    {
        foreach ($config as $filename) {
            if (file_exists($file = sprintf('%s/%s.xml', $this->getConfigurationDirectory(), $filename))) {
                $loader->load($file);
            }
        }
    }

    /**
     * Get the configuration directory
     *
     * @return string
     * @throws \RuntimeException
     */
    protected function getConfigurationDirectory()
    {
        $reflector = new \ReflectionClass($this);
        $fileName = $reflector->getFileName();

        if (!is_dir($directory = dirname($fileName) . $this->configDirectory)) {
            throw new \RuntimeException(sprintf('The configuration directory "%s" does not exists.', $directory));
        }

        return $directory;
    }

    /**
     * In case any extra processing is needed.
     *
     * @param array            $config
     * @param ContainerBuilder $container
     *
     * @return array
     */
    protected function process(array $config, ContainerBuilder $container)
    {
        // Override if needed.
        return $config;
    }
}
