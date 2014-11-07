<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\ResourceBundle\Form\Type;

use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Component\Resource\Exception\Driver\UnknownDriverException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Extending Doctrine document/entity/phpcr_document choice form types
 *
 * @author Aleksey Bannov <a.s.bannov@gmail.com>
 */
class ResourceChoiceType extends AbstractType
{
    /**
     * @var string
     */
    protected $className;

    /**
     * @var string
     */
    protected $parent;

    /**
     * Form name.
     *
     * @var string
     */
    protected $name;

    /**
     * @param string $className
     * @param string $driver
     * @param string $name
     *
     * @throws UnknownDriverException
     */
    public function __construct($className, $driver, $name)
    {
        $this->className = $className;
        $this->name = $name;

        switch ($driver) {
            case SyliusResourceBundle::DRIVER_DOCTRINE_MONGODB_ODM:
                $this->parent = 'document';
                break;
            case SyliusResourceBundle::DRIVER_DOCTRINE_ORM:
                $this->parent = 'entity';
                break;
            case SyliusResourceBundle::DRIVER_DOCTRINE_PHPCR_ODM:
                $this->parent = 'phpcr_document';
                break;
            default:
                throw new UnknownDriverException($driver);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $className = $this->className;
        $resolver
            ->setDefaults(array(
                'class' => null,
            ))
            ->setNormalizers(array(
                'class' => function () use ($className) {
                    return $className;
                },
            ))
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }
}
