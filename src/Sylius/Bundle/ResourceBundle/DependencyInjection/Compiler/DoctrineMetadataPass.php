<?php
namespace Sylius\Bundle\ResourceBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Aleksey Bannov <Aleksey.Bannov@noveogroup.com>
 */
class DoctrineMetadataPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->hasDefinition('doctrine')) {
            $container->getDefinition('sylius.event_subscriber.load_orm_metadata')
                      ->setPublic(true)
                      ->addTag('doctrine.event_subscriber', array('priority' => -100));
        }

        if ($container->hasDefinition('doctrine_mongodb')) {
            $container->getDefinition('sylius.event_subscriber.load_odm_metadata')
                      ->setPublic(true)
                      ->addTag('doctrine_mongodb.odm.event_subscriber', array('priority' => -100));
        }

        if ($container->hasDefinition('doctrine_phpcr')) {
        }
    }
}
