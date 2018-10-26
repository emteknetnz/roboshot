<?php

/* TODO:
- normalise urlsegments on dependent pages page tab
- use | in filenames for admin urls
- show the ?path thing in the modal cation id="modal-path" new element
- checkbox in js modal default true that left/right arrows go to next non-0000 no diff image
- make the results template non ugly
- (remove this) deletePreviousImages() - move resize 1440 - done there because when doing branch domain after baseline domain post admin resize

*/

require_once 'vendor/autoload.php';
require_once '_config.php';

use Facebook\WebDriver\Chrome\ChromeDriverService;
use Monolog\Handler\StreamHandler;

# === SCRIPT ============
ini_set('memory_limit', '2048M');
$var = ChromeDriverService::CHROME_DRIVER_EXE_PROPERTY;
if (isset($_SERVER['OS']) && $_SERVER['OS'] == 'Windows_NT') {
    putenv("$var=webdriver/win32/chromedriver.exe");
} else {
    putenv("$var=webdriver/mac64/chromedriver 3");
}

// create directories
if (!file_exists(ROOT_DIR."/screenshots")) {
    mkdir(ROOT_DIR."/screenshots");
}
if (!file_exists(ROOT_DIR."/screenshots/results")) {
    mkdir(ROOT_DIR."/screenshots/results");
}

$baselineDomain = rtrim(BASELINE_DOMAIN, '/');
$branchDomain = rtrim(BRANCH_DOMAIN, '/');
$screenshotBaseline = SCREENSHOT_BASELINE;
$screenshotBranch = SCREENSHOT_BRANCH;
$paths = unserialize(PATHS);
$createResults = CREATE_RESULTS;

// create objects
$browserPilot = new Roboshot\BrowserPilot();
$imageMaker = new Roboshot\ImageMaker();
$resultsMaker = new Roboshot\ResultsMaker();

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
                // disabling this feature because performance in admin is really awful so takes forever,
                // it sometimes crashes, makes far too many screenshots, and make the whole experience of using
                // roboshot horrible, unlikely to get an user uptake on tool if the whole thing is painful
                // $imageMaker->screenshotAdmin($path);
                continue;
            } else {
                // this is useful and fun mode, so keep this :-)
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
