<?php

/* TODO:

# Backend features
- config for admin/password credentials
- config for ids on security login
- show line number and filename of call in log() and debug()

# Frontend features
- normalise URL segment domains
- put filename to top of screenshot template
- when clicking on image to open in new tab, suffix ?path=/admin/pages/edit.. etc, from /montage-admin-pages-edit-EditForm-326-fi... (get the path into the template as data-path)
- checkbox in js modal default true that left/right arrows go to next non-0000 no diff image
- make the results template non ugly

# Refactoring
- replace imagecreatefromstring() with imagecreatefrompng()
- createDiffAndMontageImages() - if one of images is missing, create a blank jpg
- deletePreviousImages() - move resize 1440 - done there because when doing branch domain after baseline domain post admin resize
- change lol $imageHtmls .= in createResults() to an array push

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
