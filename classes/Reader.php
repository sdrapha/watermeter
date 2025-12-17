<?php
/**
 * Watermeter
 *
 * A tool for reading water meters
 *
 * PHP version 8.1
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Sebastian Nohn <sebastian@nohn.net>
 * @copyright 2022 Sebastian Nohn
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 */

namespace nohn\Watermeter;

use Imagick;
use nohn\AnalogMeterReader\AnalogMeter;
use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\TesseractOcrException;

class Reader extends Watermeter
{
    private $hasErrors = false;

    private $errors = array();

    private $delta = 0;

    public function getValue()
    {
        return (float)($this->getReadout() + $this->getOffset());
    }

    public function getReadout()
    {
        if (isset($this->config['postDecimalDigits']) && !empty($this->config['postDecimalDigits']) &&
            isset($this->config['analogGauges']) && !empty($this->config['analogGauges'])) {
            $value = $this->readDigits() . '.' . $this->readDigits(true) . $this->readGauges();
        } else if (isset($this->config['analogGauges']) && !empty($this->config['analogGauges'])) {
            $value = $this->readDigits() . '.' . $this->readGauges();
        } else if (isset($this->config['postDecimalDigits']) && !empty($this->config['postDecimalDigits'])) {
            $value = $this->readDigits() . '.' . $this->readDigits(true);
        } else {
            $value = $this->readDigits();
        }
        if (
            is_numeric($value) &&
            ($this->lastValue <= $value) &&
            (($value - $this->lastValue) <= abs(floatval($this->config['maxThreshold']) ))
        ) {
            return $value;
        }
        else if (is_numeric($value) &&
            ($this->lastValue >= $value) &&
            (($this->lastValue - $value) <= abs(floatval($this->config['maxThresholdBacktracking']) )))
        {
            return $value;
        } else {
            $this->delta = $value - $this->lastValue;
            $this->errors['getReadout() : is_numeric()'] = is_numeric($value);
            $this->errors['getReadout() : increasing'] = ($this->lastValue <= $value);
            $this->errors['value'] = $value;
            $this->errors['lastValue'] = $this->lastValue;
            $this->errors['delta'] = $value - $this->lastValue;
            $this->errors['acceptable_delta'] = ($this->delta >= 0) && ($this->delta <= $this->config['maxThreshold']);
            $this->errors['maxThreshold'] = $this->config['maxThreshold'];
            $this->errors['acceptable_backtracking_delta'] = ($this->delta <= 0) && ($this->lastValue - $value) <= floatval($this->config['maxThresholdBacktracking']);
            $this->errors['maxThresholdBacktracking'] = $this->config['maxThresholdBacktracking'];
            $this->hasErrors = true;
            return (float)$this->lastValue;
        }
    }

    private function readDigits($post_decimal = false)
    {
        $digitalSourceImage = clone $this->sourceImage;
        $targetImage = new Imagick();

        if ($post_decimal == false) {
            $digits_to_read = $this->config['digitalDigits'];
            $cachePrefix = '';
        } else {
            $digits_to_read = $this->config['postDecimalDigits'];
            $cachePrefix = 'post_decimal';
        }

        $digitscount = count($digits_to_read);
        foreach ($digits_to_read as $digit) {
            $rawDigit = clone $digitalSourceImage;
            if (isset($digit['width']) && $digit['width'] > 0 && isset($digit['height']) && $digit['height'] > 0) {
                $rawDigit->cropImage($digit['width'], $digit['height'], $digit['x'], $digit['y']);
                $targetImage->addImage($rawDigit);
                if ($this->debug) {
                    $this->drawDebugImageDigit($digit, $post_decimal);
                }
            }
        }
        $targetImage->resetIterator();
        $numberDigitalImage = $targetImage->appendImages(false);
        if (isset($this->config['digitDecolorization']) && $this->config['digitDecolorization']) {
            $numberDigitalImage->modulateImage(100, 0, 100);
        }
        if (!isset($this->config['postprocessing']) || (isset($this->config['postprocessing']) && $this->config['postprocessing'])) {
            $numberDigitalImage->enhanceImage();
            $numberDigitalImage->equalizeImage();
        }
        if (isset($this->config['digitalDigitsInversion']) && $this->config['digitalDigitsInversion']) {
            $numberDigitalImage->negateImage(false);
        }
        $numberDigitalImage->setImageFormat("png");
        $numberDigitalImage->borderImage('white', 10, 10);
        try {
            $ocr = new TesseractOCR();
            $ocr->imageData($numberDigitalImage, sizeof($numberDigitalImage));
            // $ocr->allowlist(range('0', '9'));
            //$ocr->allowlist('0123456789oOiIlzZsSBg');
            $ocr->allowlist(['0','1','2','3','4','5','6','7','8','9','o','O','i','I','|','l','z','Z','s','S','b','B','g']);
            $numberOCR = $ocr->run();
        } catch (TesseractOcrException $e) {
            $numberOCR = 0;
            $this->errors[] = $e->getMessage();
        }
        $numberDigital = preg_replace('/\s+/', '', $numberOCR);
        $numberDigital = str_pad($numberDigital, $digitscount, "0", STR_PAD_LEFT);
        // There is TesseractOCR::digits(), but sometimes this will not convert a letter do a similar looking digit but completely ignore it. So we replace o with 0, I with 1 etc.
        $numberDigital = strtr($numberDigital, 'oOiI|lzZsSbBg', '0011112255689');
        // $numberDigital = '00815';
        if ($this->debug) {
            $numberDigitalImage->writeImage('tmp/' . $cachePrefix . '_digital.jpg');
            echo "<em style='color:grey;font-size:85%;'>Debug output from Reader.php readDigits()</em>";
            echo '<br><table border="1"><tr><td>';
            if ($post_decimal == true) { echo '<b>Post decimal:</b><br>'; }
            echo "Raw OCR: $numberOCR<br>";
            echo "Clean OCR: $numberDigital<br>";
            echo '<img alt="Digital Preview" src="tmp/' . $cachePrefix . '_digital.jpg" /><br>';
            echo '</td></tr></table><br>';
        }

        if (is_numeric($numberDigital)) {
            $numberRead = (string)$numberDigital;
        } else {
            # FIXXME
            $numberRead = (string)$this->lastValue;
            if ($this->debug) {
                echo 'Choosing last value ' . $numberRead . '<br>';
            }
            $this->errors['readDigits() : !is_numeric()'] = 'Could not interpret "' . $numberDigital . '". Using last known value ' . (string)$this->lastValue;
            $this->hasErrors = true;
        }
        // Redundant, comment out temporarily:
        // if ($this->debug) {
        //     echo '<table border="1"><tr>';
        //     echo '<td>';
        //     echo "ReadDigits method:<br>";
        //     echo "Digital: $numberRead<br>";
        //     echo 'digital source image<br>';
        //     $digitalSourceImage->writeImage('tmp/input.jpg');
        //     echo '<img src="tmp/input.jpg" />';
        //     echo '<br>number digital image<br>';
        //     $numberDigitalImage->writeImage('tmp/' . $cachePrefix . '_digital.png');
        //     echo '<img src="tmp/' . $cachePrefix . '_digital.png" />';
        //     echo '</td></tr></table>';
        // }
        return $numberRead;
    }

    private function readGauges()
    {
        $decimalPlaces = null;
        foreach ($this->config['analogGauges'] as $gaugeKey => $gauge) {
            $gauge['key'] = $gaugeKey;
            $rawGaugeImage = clone $this->sourceImage;
            $rawGaugeImage->cropImage($gauge['width'], $gauge['height'], $gauge['x'], $gauge['y']);
            $rawGaugeImage->setImagePage(0, 0, 0, 0);
            $amr = new AnalogMeter($rawGaugeImage, 'r');
            $decimalPlaces .= $amr->getValue();
            if ($this->debug) {
                $this->debugGauge($amr, $gauge);
            }
        }
        return $decimalPlaces;
    }

    private function debugGauge($amr, $gauge)
    {
        $this->drawDebugImageGauge($gauge);
        echo "<em style='color:grey;font-size:85%;'>Debug output from Reader.php debugGauge()</em>";
        echo '<br><table><tr><td>';
        echo $amr->getValue(true) . '<br>';
        echo '<img src="tmp/analog_' . $gauge['key'] . '.png" /><br />';
        $debugData = $amr->getDebugData();
        foreach ($debugData as $significance => $step) {
            echo round($significance, 4) . ': ' . $step['xStep'] . 'x' . $step['yStep'] . ' => ' . $step['number'] . '<br>';
        }
        $debugImage = $amr->getDebugImage();
        $debugImage->setImageFormat('png');
        $debugImage->writeImage(__DIR__ . '/../public/tmp/analog_' . $gauge['key'] . '.png');
        echo '</td></tr></table><br>';
    }

    public function getOffset()
    {
        if (isset($this->config['offsetValue'])) {
            return (float)$this->config['offsetValue'];
        } else {
            return 0;
        }
    }

    public function hasErrors()
    {
        return $this->hasErrors;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
