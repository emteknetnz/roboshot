<?php

/* TODO:
- normalise urlsegments on dependent pages page tab
- use | in filenames for admin urls
- show the ?path thing in the modal cation id="modal-path" new element
- checkbox in js modal default true that left/right arrows go to next non-0000 no diff image
- make the results template non ugly
- deletePreviousImages() - move resize 1440 - done there because when doing branch domain after baseline domain post admin resize

*/

namespace Roboshot;

require_once __DIR__ . '/vendor/autoload.php';

use Facebook\WebDriver\Chrome\ChromeDriverService;

require_once 'BaseClass.php';
require_once 'BrowserPilot.php';
require_once 'ImageMaker.php';
require_once 'ResultsMaker.php';

# === SCRIPT ============
ini_set('memory_limit', '1024M');
$var = ChromeDriverService::CHROME_DRIVER_EXE_PROPERTY;
if (isset($_SERVER['OS']) && $_SERVER['OS'] == 'Windows_NT') {
    putenv("$var=webdriver/win32/chromedriver.exe");
} else {
    putenv("$var=webdriver/mac64/chromedriver 3");
}

// logging functions
function log($str, $trace = null) {
    if (!$trace) {
        $trace = debug_backtrace()[0];
    }
    $line = $trace['line'];
    preg_match('%/([A-Z\.a-z]+)$%', $trace['file'], $match);
    $file = $match[1];
    $fileline = "$file:$line";
    $fileline = str_pad($fileline, 20, ' ', STR_PAD_RIGHT);
    echo "$fileline -- $str\n";
}

function debug($str) {
    if (SHOW_DEBUG) {
        log($str, debug_backtrace()[0]);
    }
}

// create directories
if (!file_exists("screenshots")) {
    mkdir("screenshots");
}
if (!file_exists("screenshots/results")) {
    mkdir("screenshots/results");
}

// read config
require_once '_config.php';
$baselineDomain = rtrim(BASELINE_DOMAIN, '/');
$branchDomain = rtrim(BRANCH_DOMAIN, '/');
$screenshotBaseline = SCREENSHOT_BASELINE;
$screenshotBranch = SCREENSHOT_BRANCH;
$paths = unserialize(PATHS);
$createResults = CREATE_RESULTS;

// create objects
$browserPilot = new BrowserPilot();
$imageMaker = new ImageMaker();
$resultsMaker = new ResultsMaker();

// take screenshots
if ($screenshotBaseline || $screenshotBranch) {
    $driver = $browserPilot->createDriver();
    $imageMaker->setDriver($driver);
    $imageMaker->setBrowserPilot($browserPilot);
    foreach ([$baselineDomain, $branchDomain] as $domain) {
        if (!$screenshotBaseline && $domain == $baselineDomain) {
            continue;
        }
        if (!$screenshotBranch && $domain == $branchDomain) {
            continue;
        }
        $browserPilot->setDomain($domain);
        $imageMaker->setDomain($domain);
        $imageMaker->deletePreviousImages();
        foreach ($paths as $path) {
            if (preg_match('%^/admin%', $path)) {
                $imageMaker->screenshotAdmin($path);
            } else {
                $browserPilot->get($path);
                $imageMaker->takeScreenshot();
            }
        }
    }
    $browserPilot->close();
}

// compare images
if ($createResults) {
    $resultsMaker->createResults($baselineDomain, $branchDomain);
}
