<?php

namespace BeadTests\Framework\Constraints;

use Bead\Testing\XRay;
use InvalidArgumentException;
use PHPUnit\Framework\Constraint\Constraint;

class AttributeIsInt extends Constraint
{
    /**
     * Check that an attribute of an object is an int.
     *
     * @param array $other the object whose attribute needs to be tested as the first element and the name of the
     * attribute as the second.
     *
     * @return bool `true` if the attribute is an int, `false` if not.
     */
    public function matches($other): bool
    {
        [$object, $attr] = $other;

        if (!is_object($object)) {
            throw new InvalidArgumentException("the 'object' to match was not an object type");
        }

        if (!is_string($attr)) {
            throw new InvalidArgumentException("the name of the attribute to match was not a string");
        }

        if (empty($attr)) {
            throw new InvalidArgumentException("cannot test for an empty attribute name");
        }

        $xray = new XRay($object);
        return is_int($xray->$attr);
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
