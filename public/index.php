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

require __DIR__ . '/../vendor/autoload.php';

use nohn\Watermeter\Cache;
use nohn\Watermeter\Reader;

$watermeterCache = new Cache();
$lastValue = $watermeterCache->getValue();
$lastValueTimestamp = $watermeterCache->getLastUpdate();

$darkMode = isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'enabled';

if (isset($_GET['debug'])) {
    $debug = true;
    echo '<link rel="stylesheet" href="styles.css">';
    echo '<body class="' . ($darkMode ? 'dark-gray' : '') . '">';
    echo '<button id="toggleBtn">' . ($darkMode ? 'Light Mode' : 'Dark Mode') . '</button>';
    echo '<button type="button" onclick="window.location.href=\'configure.php\'">Go to configure.php</button><br><br>';
    echo '<script>
            const btn = document.getElementById("toggleBtn");
            btn.addEventListener("click", () => {
                document.body.classList.toggle("dark-gray");
                if (document.body.classList.contains("dark-gray")) {
                    btn.textContent = "Light Mode";
                    document.cookie = "darkMode=enabled; path=/; max-age=31536000"; // 1 year
                } else {
                    btn.textContent = "Dark Mode";
                    document.cookie = "darkMode=disabled; path=/; max-age=31536000";
                }
            });</script>';
} else {
    $debug = false;
}

$watermeterReader = new Reader($debug);
$watermeterReader->writeSourceImage('watermeter.jpg');

$lastPreDecimalPlaces = (int)$lastValue;

$now = time();

try {
    $logGaugeImages = array();
    $readout = $watermeterReader->getReadout();
    $offset = $watermeterReader->getOffset();
    $value = $readout+$offset;
    $returnData = array();
    if ($watermeterReader->hasErrors()) {
        $returnData['readout'] = $lastValue;
        $returnData['offset'] = $offset;
        $returnData['value'] = $value;
        $returnData['status'] = 'error';
        $returnData['errors'] = $watermeterReader->getErrors();
        $returnData['exception'] = false;
        $returnData['lastUpdated'] = $lastValueTimestamp;
    } else {
        $returnData['readout'] = $readout;
        $returnData['offset'] = $offset;
        $returnData['value'] = $value;
        $returnData['status'] = 'success';
        $returnData['errors'] = false;
        $returnData['exception'] = false;
        $returnData['lastUpdated'] = $now;
        file_put_contents(__DIR__ . '/../src/config/lastValue.txt', $readout);
    }
    if ($debug) {
        echo "<br><br>";
        echo "<br> ------ Begin Debug output from index.php------ <br>";
        echo "<br>Status: ";
        echo $returnData['status'] . "<br>";
        echo "<br>Input image:<br>";
        echo '<table><tr><td>';
        $watermeterReader->writeDebugImage(__DIR__ . '/../public/tmp/input_debug.jpg');
        echo '<img id="myImage" src="tmp/input_debug.jpg" />';
        echo '<div id="coords">Hover over the image to see coordinates</div>';
        echo '
            <script>
            const img = document.getElementById("myImage");
            const coords = document.getElementById("coords");
            img.addEventListener("mousemove", function(event) {
                // Get bounding box of the image
                const rect = img.getBoundingClientRect();
                // Calculate mouse position relative to the image
                const x = Math.floor(event.clientX - rect.left);
                const y = Math.floor(event.clientY - rect.top);
                coords.textContent = `X: ${x}, Y: ${y}`;
            });
            img.addEventListener("mouseleave", function() {
                coords.textContent = "Hover over the image to see coordinates";
            });
            </script>
            ';
        echo '</td></tr></table>';
        echo "<br>";
        echo "hasErrors: " . $watermeterReader->hasErrors() . "\n<br>";
        echo "<br>";
        echo "getErrors result:";
        echo "<br>";
        echo "-----------------------------<br>";
        echo '<table><tr><td>';
        echo "<pre>";
        var_dump($watermeterReader->getErrors());
        echo "</pre>";
        echo "</td></tr></table>";
        echo "-----------------------------<br>";
        echo "<br>";
        echo ' 
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const el = document.getElementById("localTime");
                    const ts = parseInt(el.dataset.ts, 10) * 1000; // JS expects milliseconds
                    const date = new Date(ts);
                    // Format in users local timezone
                    // Why "en-CA"? The Canadian locale outputs dates in YYYY-MM-DD order (ISO-like)
                    el.textContent = date.toLocaleString("en-CA", {
                        year: "numeric",
                        month: "2-digit",
                        day: "2-digit",
                        hour: "2-digit",
                        minute: "2-digit",
                        second: "2-digit",
                        hour12: false
                    });
                    // Examples: "2025-12-09, 14:38:00"
                });
            </script>
            ';
        echo "lastValueTimestamp: $lastValueTimestamp <br>" ;
        echo "lastValueTimestamp: " . '<span id="localTime" data-ts="' . $lastValueTimestamp . '"></span><br>';
        echo "lastValue: $lastValue\n<br>";
        echo "readout: $readout\n<br>";
        echo "value: $value\n<br>";
        echo "<br>-----end of debug output-----<br><br>";
    }
    if (isset($_GET['json'])) {
        header("Content-Type: application/json");
        echo json_encode($returnData);
    } else {
        echo $returnData['value'];
    }
} catch (Exception $e) {
    $returnData = array(
        'value' => $lastValue,
        'status' => 'exception',
        'errors' => false,
        'exception' => $e->__toString(),
        'lastUpdated' => $lastValueTimestamp,
    );
    if (isset($_GET['json'])) {
        header("Content-Type: application/json");
        echo json_encode($returnData);
    } else {
        echo $returnData['value'];
    }
}