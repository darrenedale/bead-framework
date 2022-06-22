<?php

namespace Equit\Html\Detail;

use Equit\Html\Element;

/**
* Represents a child item in a GridLayout.
*
* This is a private class and should not be used at all. It is used internally in GridLayout to contain items in
* the layout along with their indices in the grid and the extent of their span. Its internals are not guaranteed to
* remain consistent. If PHP supported nested classes, this class would be a private nested class of GridLayout.
*
* Basically, just don't touch it.
*
* @internal
*
* @class GridLayoutItem
* @author Darren Edale
* @package bead-framework
*/
class GridLayoutItem
{
    /** @var Element|null */
    public ?Element $content   = null;
    public ?int $anchorRow = null;
    public ?int $anchorCol = null;
    public ?int $rowSpan   = 1;
    public ?int $colSpan   = 1;
    public ?int $alignment = 0;

    public function __construct($content, $anchorRow, $anchorCol, $rowSpan, $colSpan) {
        $this->content   = $content;
        $this->anchorRow = $anchorRow;
        $this->anchorCol = $anchorCol;
        $this->rowSpan   = $rowSpan;
        $this->colSpan   = $colSpan;
    }
}
