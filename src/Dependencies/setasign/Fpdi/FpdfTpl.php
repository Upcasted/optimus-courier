<?php

/**
 * This file is part of FPDI
 *
 * @package   OptimusCourier\Dependencies\setasign\Fpdi
 * @copyright Copyright (c) 2020 Setasign GmbH & Co. KG (https://www.setasign.com)
 * @license   http://opensource.org/licenses/mit-license The MIT License
 */

namespace OptimusCourier\Dependencies\setasign\Fpdi;

/**
 * Class FpdfTpl
 *
 * This class adds a templating feature to OptimusCourier_FPDF.
 */
class FpdfTpl extends \OptimusCourier_FPDF
{
    use FpdfTplTrait;
}
