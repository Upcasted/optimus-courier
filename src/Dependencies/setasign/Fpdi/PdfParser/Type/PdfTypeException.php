<?php

/**
 * This file is part of FPDI
 *
 * @package   OptimusCourier\Dependencies\setasign\Fpdi
 * @copyright Copyright (c) 2020 Setasign GmbH & Co. KG (https://www.setasign.com)
 * @license   http://opensource.org/licenses/mit-license The MIT License
 */

namespace OptimusCourier\Dependencies\setasign\Fpdi\PdfParser\Type;

use OptimusCourier\Dependencies\setasign\Fpdi\PdfParser\PdfParserException;

/**
 * Exception class for pdf type classes
 */
class PdfTypeException extends PdfParserException
{
    /**
     * @var int
     */
    const NO_NEWLINE_AFTER_STREAM_KEYWORD = 0x0601;
}
