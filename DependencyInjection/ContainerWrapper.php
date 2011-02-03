<?php

namespace Liip\ViewBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a wrapper around a Container instance to provide instances for specific services only
 * 
 * @author Lukas Kahwe Smith <smith@pooteeweet.org>
 */
class ContainerWrapper extends ContainerAware implements ContainerInterface
{
    protected $serviceIds;
    protected $container;

    /**
     * Constructor.
     *
     * @param array $serviceIds A list of service ids
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function __construct(array $serviceIds, ContainerInterface $container = null)
    {
        foreach ($serviceIds as $serviceId => $mappedServiceId) {
            if ($mappedServiceId === true) {
                $serviceIds[$serviceId] = $serviceId;
            }
        }

        $this->serviceIds = $serviceIds;

        $this->setContainer($container);
    }

    /**
     * @inheritDoc
     */
    public function getParameterBag()
    {
        throw new \LogicException('Parameters are not supported by the service container wrapper.');
    }

    /**
     * @inheritDoc
     */
    public function getParameter($name)
    {
        throw new \LogicException('Parameters are not supported by the service container wrapper.');
    }

    /**
     * @inheritDoc
     */
    public function hasParameter($name)
    {
        throw new \LogicException('Parameters are not supported by the service container wrapper.');
    }

    /**
     * @inheritDoc
     */
    public function setParameter($name, $value)
    {
        throw new \LogicException('Parameters are not supported by the service container wrapper.');
    }

    /**
     * @inheritDoc
     */
    public function set($id, $service, $scope = self::SCOPE_CONTAINER)
    {
        if (self::SCOPE_PROTOTYPE === $scope) {
            throw new \InvalidArgumentException('You cannot set services of scope "prototype".');
        }

        $id = strtolower($id);

        if (empty($this->serviceIds[$id])) {
            throw new \InvalidArgumentException(sprintf('Only services "%s" supported: %s', implode(', ', $this->serviceIds), $id));
        }

        return $this->container->set($this->serviceIds[$id], $service, $scope);
    }

    /**
     * @inheritDoc
     */
    public function has($id)
    {
        if (empty($this->serviceIds[$id])) {
            return false;
        }

        return $this->container->has($this->serviceIds[$id]);
    }

    /**
     * @inheritDoc
     */
    public function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE)
    {
        if (empty($this->serviceIds[$id])) {
            if ($invalidBehavior) {
                throw new \InvalidArgumentException(sprintf('Only services "%s" supported: %s', implode(', ', $this->serviceIds), $id));
            }

            return;
        }

        return $this->container->get($this->serviceIds[$id], $invalidBehavior);
    }

    /**
     * @inheritDoc
     */
    public function getServiceIds()
    {
        return $this->serviceIds;
    }

    /**
     * @inheritDoc
     */
    public function enterScope($name)
    {
        return $this->container->enterScope($name);
    }

    /**
     * @inheritDoc
     */
    public function leaveScope($name)
    {
        return $this->container->leaveScope($name);
    }

    /**
     * @inheritDoc
     */
    public function addScope($name, $parentScope = self::SCOPE_CONTAINER)
    {
        return $this->container->addScope($name, $parentScope);
    }

    /**
     * @inheritDoc
     */
    public function hasScope($name)
    {
        return $this->container->hasScope($name);
    }

    /**
     * @inheritDoc
     */
    public function isScopeActive($name)
    {
        return $this->container->isScopeActive($name);
    }
}
