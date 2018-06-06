<?php

// TODO: delete diff? images after results completed
// TODO: normalise URL segment domains
// TODO: checkbox in js modal default true that left/right arrows go to next non-0000 no diff image
// TODO: when clicking on image to open in new tab, suffix ?path=/admin/pages/edit.. etc, from /montage-admin-pages-edit-EditForm-326-fi...
//       ^ may want to investigate switch - to or _ or other so because - can be in a url for frontend pages
//       ^ or, better, get the path into the template as data-path
// TODO: config for admin/password credentials
// TODO: config for ids on security login
// TODO: update (.vagrant/.test) regex to !(.govt|.com|.org)
// TODO: show line number and filename of call in log() and debug()
// TODO: replace imagecreatefromstring() with imagecreatefrompng()
// TODO: createDiffAndMontageImages() - if one of images is missing, create a blank jpg
// TODO: deletePreviousImages() - move resize 1440 - done there because when doing branch domain after baseline domain post admin resize

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
function log($str) {
    echo "$str\n";
}

function debug($str) {
    global $showDebug;
    if ($showDebug) {
        log($str);
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
