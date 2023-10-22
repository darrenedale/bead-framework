<?php

namespace BeadTests\Framework\Constraints;

use InvalidArgumentException;
use PHPUnit\Framework\Constraint\Constraint;
use ReflectionException;
use ReflectionProperty;

class AttributeIsInt extends Constraint
{
    /**
     * Check that an attribute of an object is an int.
     *
     * @param array $objectAndAttr the object whose attribute needs to be tested as the first element and the name of
     * the attribute as the second.
     *
     * @return bool `true` if the attribute is an int, `false` if not.
     */
    public function matches($objectAndAttr): bool
    {
        [$object, $attr] = $objectAndAttr;

        if (!is_object($object)) {
            throw new InvalidArgumentException("the 'object' to match was not an object type");
        }

        if (!is_string($attr)) {
            throw new InvalidArgumentException("the name of the attribute to match was not a string");
        }

        if (empty($attr)) {
            throw new InvalidArgumentException("cannot test for an empty attribute name");
        }

        try {
            $refAttr = new ReflectionProperty($object, $attr);
        } catch (ReflectionException $err) {
            throw new InvalidArgumentException("the property {$attr} does not exist in class " . get_class($object), 0, $err);
        }

        $refAttr->setAccessible(true);
        return is_int($refAttr->getValue($object));
    }

    /**
     * Description of the constraint.
     *
     * @return string The description.
     */
    public function toString(): string
    {
        return "attribute is int";
    }
}
