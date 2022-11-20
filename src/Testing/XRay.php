<?php

namespace Bead\Testing;

use BadMethodCallException;
use LogicException;
use ReflectionException;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;

/**
 * Make visible the inner workings of an object.
 *
 * Provides an easy-to-use interface to use reflection to access the inner implementation details of objects. Use the
 * XRay object like you would the original, including its protected and private members. Static members are not
 * supported - use StaticXRay for those.
 *
 * WARNING implementation details are private for a reason. You should strive never to use this class. If you do need to
 * use it, if it's for anything other than testing you're probably using it incorrectly.
 */
class XRay
{
    /** @var ReflectionObject The ReflectionObject for the object being examined. */
    private ReflectionObject $m_subjectReflector;

    /** @var object The subject of the x-ray. */
    private object $m_subject;

    /** @var string[] Cache of resolved public methods. */
    private array $m_publicMethods = [];

    /** @var ReflectionMethod[] Cache of the resolved ReflectionMethod instances for inaccessible methods. */
    private array $m_xRayedMethods = [];

    /** @var string[] Cache of methods that cannot be resolved. */
    private array $m_unresolvedMethods = [];

    /** @var string[] Cache of resolved public properties. */
    private array $m_publicProperties = [];

    /** @var ReflectionProperty[] Cache of the resolved ReflectionProperty instances for inaccessible properties. */
    private array $m_xRayedProperties = [];

    /** @var string[] Cache of properties that cannot be resolved. */
    private array $m_unresolvedProperties = [];

    /**
     * Initialise a new x-ray for an object.
     *
     * @param object $object The object to x-ray.
     */
    public function __construct(object $object)
    {
        $this->m_subject = $object;
        $this->m_subjectReflector = new ReflectionObject($object);
    }

    /**
     * Helper to resolve a named method for the xray.
     *
     * @param string $method The method name to resolve.
     */
    protected function resolveMethod(string $method): void
    {
        if (in_array($method, $this->m_publicMethods) || in_array($method, $this->m_unresolvedMethods) || isset($this->m_xRayedMethods[$method])) {
            return;
        }

        try {
            $reflector = $this->m_subjectReflector->getMethod($method);
        } catch (ReflectionException $err) {
            $reflector = null;
        }

        if (!isset($reflector) || $reflector->isStatic()) {
            $this->m_unresolvedMethods[] = $method;
            return;
        }

        if ($reflector->isPublic()) {
            $this->m_publicMethods[] = $method;
            return;
        }

        $reflector->setAccessible(true);
        $this->m_xRayedMethods[$method] = $reflector;
    }

    /**
     * Helper to resolve a named property for the x-ray.
     *
     * @param string $property The property name to resolve.
     */
    protected function resolveProperty(string $property): void
    {
        if (in_array($property, $this->m_publicProperties) || in_array($property, $this->m_unresolvedProperties) || isset($this->m_xRayedProperties[$property])) {
            return;
        }

        try {
            $reflector = $this->m_subjectReflector->getProperty($property);
        } catch (ReflectionException $err) {
            $reflector = null;
        }

        if (!isset($reflector) || $reflector->isStatic()) {
            $this->m_unresolvedProperties[] = $property;
            return;
        }

        if ($reflector->isPublic()) {
            $this->m_publicProperties[] = $property;
            return;
        }

        $reflector->setAccessible(true);
        $this->m_xRayedProperties[$property] = $reflector;
    }

    /**
     * Fetch the object being x-rayed.
     *
     * @return object The subject of the x-ray.
     */
    public function subject(): object
    {
        return $this->m_subject;
    }

    /**
     * Check whether a named method is public.
     *
     * @param string $method The method name. It is case-sensitive.
     *
     * @return bool `true` if the method exists on the x-rayed object, is public and is not static, `false` otherwise.
     */
    public function isPublicMethod(string $method): bool
    {
        $this->resolveMethod($method);

        return in_array($method, $this->m_publicMethods);
    }

    /**
     * Check whether a named method has been made accessible by the XRay.
     *
     * @param string $method The method name. It is case-sensitive.
     *
     * @return bool `true` if the method exists and the XRay has made it visible, `false` if it doesn't exist, is
     * static, or is a public method.
     */
    public function isXRayedMethod(string $method): bool
    {
        $this->resolveMethod($method);

        return isset($this->m_xRayedMethods[$method]);
    }

    /**
     * Check whether a named property is public.
     *
     * @param string $property The property name. It is case-sensitive.
     *
     * @return bool `true` if the property exists on the x-rayed object, is public and is not static, `false` otherwise.
     */
    public function isPublicProperty(string $property): bool
    {
        $this->resolveProperty($property);

        return in_array($property, $this->m_publicProperties);
    }

    /**
     * Check whether a named property has been made accessible by the XRay.
     *
     * @param string $property The property name. It is case-sensitive.
     *
     * @return bool `true` if the property exists and the XRay has made it visible, `false` if it doesn't exist or is
     * a public property.
     */
    public function isXRayedProperty(string $property): bool
    {
        $this->resolveProperty($property);

        return isset($this->m_xRayedProperties[$property]);
    }

    /**
     * Invoke a method of the x-rayed object.
     *
     * @param string $method The method name. It is case-sensitive.
     * @param array $args The arguments to pass to the method.
     *
     * @return mixed The return value of the method call.
     * @throws BadMethodCallException if the named method does not exist or is static.
     */
    public function __call(string $method, array $args)
    {
        if ($this->isPublicMethod($method)) {
            return $this->m_subject->$method(...$args);
        } else if ($this->isXRayedMethod($method)) {
            try {
                return $this->m_xRayedMethods[$method]->invoke($this->subject(), ...$args);
            } catch (ReflectionException $err) {
                throw new BadMethodCallException("Method '{$method}' could not be invoked on instance of class '{$this->className()}'.", 0, $err);
            }
        } else if (method_exists($this->m_subject, "__call")) {
            return $this->m_subject->__call($method, $args);
        }

        throw new BadMethodCallException("Method '{$method}' does not exist on object of class '{$this->m_subjectReflector->getName()}'.");
    }

    /**
     * Get the value of a property of the x-rayed object.
     *
     * @param string $property The property name. It is case-sensitive.
     *
     * @return mixed The value of the subject's property.
     * @throws LogicException if the named property does not exist or is static.
     */
    public function __get(string $property)
    {
        if ($this->isPublicProperty($property)) {
            return $this->m_subject->$property;
        } else if ($this->isXRayedProperty($property)) {
            return $this->m_xRayedProperties[$property]->getValue($this->subject());
        } else if (method_exists($this->m_subject, "__get")) {
            return $this->m_subject->__get($property);
        }

        throw new LogicException("Property '{$property}' does not exist on object of class '{$this->m_subjectReflector->getName()}'.");
    }

    /**
     * Set the value of a property of the x-rayed object.
     *
     * @param string $property The property name. It is case-sensitive.
     * @param mixed $value The value to set for the property.
     *
     * @throws LogicException if the named property does not exist or is static.
     */
    public function __set(string $property, $value)
    {
        if ($this->isPublicProperty($property)) {
            $this->m_subject->$property = $value;
            return;
        } else if ($this->isXRayedProperty($property)) {
            $this->m_xRayedProperties[$property]->setValue($this->subject(), $value);
            return;
        } else if (method_exists($this->m_subject, "__set")) {
            $this->m_subject->__set($property, $value);
            return;
        }

        throw new LogicException("Property '{$property}' does not exist on object of class '{$this->m_subjectReflector->getName()}'.");
    }
}
