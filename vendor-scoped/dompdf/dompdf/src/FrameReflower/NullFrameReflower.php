<?php

/**
 * @package dompdf
 * @link    https://github.com/dompdf/dompdf
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */
namespace OCA\JournalNotes\Vendor\Dompdf\FrameReflower;

use OCA\JournalNotes\Vendor\Dompdf\Frame;
use OCA\JournalNotes\Vendor\Dompdf\FrameDecorator\Block as BlockFrameDecorator;
/**
 * Dummy reflower
 *
 * @package dompdf
 */
class NullFrameReflower extends AbstractFrameReflower
{
    /**
     * NullFrameReflower constructor.
     * @param Frame $frame
     */
    function __construct(Frame $frame)
    {
        parent::__construct($frame);
    }
    /**
     * @param BlockFrameDecorator|null $block
     */
    function reflow(?BlockFrameDecorator $block = null)
    {
        return;
    }
}
