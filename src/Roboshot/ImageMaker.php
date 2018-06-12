<?php

namespace Roboshot;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverDimension;

/**
 * Image related functions
 *
 * @package Roboshot
 */
class ImageMaker extends BaseClass
{
    /**
     * @var BrowserPilot
     */
    protected $browserPilot;

    /**
     * Take a screenshot of the what is current in the browser and save it to disk
     *
     * @param bool $takeFullPageScreenshot
     */
    public function takeScreenshot($takeFullPageScreenshot = true)
    {
        $url = $this->driver->getCurrentURL();
        $path = parse_url($url)['path'];
        Logger::get()->info("Taking screenshot of {$this->domain}{$path}");
        list($dir, $filename) = $this->getDirAndFilename($this->domain, $path);
        $pilot = $this->browserPilot;

        // create screenshot directory
        if (!file_exists(ROOT_DIR."/screenshots/$dir")) {
            mkdir(ROOT_DIR."/screenshots/$dir");
        }

        // for admin urls, suffix number for duplicate filenames
        // using _ as part of suffix instead of - because /admin/pages/edit/show/1 filename is admin-pages-edit-show-1
        $c = 1;
        while (file_exists(ROOT_DIR."/screenshots/$dir/$filename")) {
            $filename = preg_replace('%_[0-9]+\.png$%', '.png', $filename);
            $filename = preg_replace('%\.png$%', "_$c.png", $filename);
            $c++;
        }

        // Save the source code of the current path
        file_put_contents(ROOT_DIR."/screenshots/$dir/$filename.html", $this->driver->getPageSource());

        // scroll to top of page
        $pilot->executeJS("window.scrollTo(0, 0);");

        // for /admin, take screenshot of above the fold content only
        if (!$takeFullPageScreenshot) {
            $this->driver->takeScreenshot(ROOT_DIR."/screenshots/$dir/$filename");
            return;
        }

        // get document and viewport heights
        $documentHeight = $pilot->executeJS("return document.documentElement.scrollHeight;");
        $viewportHeight = $pilot->executeJS("return window.innerHeight;");
        Logger::get()->debug("documentHeight = $documentHeight");
        Logger::get()->debug("viewportHeight = $viewportHeight");

        // take initial screenshot
        $this->driver->takeScreenshot(ROOT_DIR."/screenshots/$dir/temp-$filename");
        $tempFile = imagecreatefrompng(ROOT_DIR."/screenshots/$dir/temp-$filename");

        // get image dimensions
        $imageWidth = imagesx($tempFile);
        $imageHeight = imagesy($tempFile);
        Logger::get()->debug("imageWidth = $imageWidth");
        Logger::get()->debug("imageHeight = $imageHeight");

        // this is 0.5 (2x pixels) on ios chrome retina e.g
        // $viewportHeight = 619
        // $imageHeight = 1238
        $imageRatio = $imageHeight / $viewportHeight;
        Logger::get()->debug("imageRatio = $imageRatio");

        if ($imageRatio != 1) {
            $tmp = imagescale($tempFile, floor($imageWidth / 2));
            imagedestroy($tempFile);
            $tempFile = $tmp;
            $imageWidth = imagesx($tempFile);
            $imageHeight = imagesy($tempFile);
            Logger::get()->debug("new imageWidth = $imageWidth");
            Logger::get()->debug("new imageHeight = $imageHeight");
        }

        if ($viewportHeight >= $documentHeight) {
            // ^ document fits in viewport

            imagepng($tempFile, ROOT_DIR."/screenshots/$dir/$filename");

        } else {
            // ^ take multiple screenshots and join them together

            // create a composite image
            $compositeImage = imagecreatetruecolor($imageWidth, $documentHeight);
            imagecopy($compositeImage, $tempFile, 0, 0, 0, 0, $imageWidth, $imageHeight);

            $c = 1;
            $prevScrollTop = 0;
            while (true) {

                // scroll the browser window
                $pilot->executeJS("window.scrollBy(0, $viewportHeight);");
                $scrollTop = $pilot->executeJS("return document.documentElement.scrollTop");
                Logger::get()->debug("scrollTop = $scrollTop");

                $scrollAmount = $scrollTop - $prevScrollTop;
                Logger::get()->debug("scrollAmount = $scrollAmount");

                if ($scrollAmount == 0) {
                    Logger::get()->debug("No more scrolling possible, breaking");
                    break;
                }

                // use so that viewportImage has a unique filename, ensure there's no race conditions
                // with the unlink() function and subsequent new screenshots being taken
                $cc = str_pad($c, 2, '0', STR_PAD_LEFT);

                // create a new viewport image
                Logger::get()->debug("Taking viewport screenshot to add to composite image");
                $this->driver->takeScreenshot(ROOT_DIR."/screenshots/$dir/view-$cc-$filename");
                $viewportImage = imagecreatefrompng(ROOT_DIR."/screenshots/$dir/view-$cc-$filename");

                if ($imageRatio != 1) {
                    $tmp = imagescale($viewportImage, $imageWidth);
                    imagedestroy($viewportImage);
                    $viewportImage = $tmp;
                }

                // check if the amount of scrolling done was less than the viewport height
                $viewportImageOffsetY = 0;
                if ($scrollTop % $viewportHeight > 0) {

                    // offset top of source image for last image in composite
                    $viewportImageOffsetY = $viewportHeight - $scrollTop % $viewportHeight;
                }
                Logger::get()->debug("viewportImageOffsetY = $viewportImageOffsetY");

                // copy the viewport image on to the composite image
                imagecopy($compositeImage, $viewportImage, 0, $c * $imageHeight, 0, $viewportImageOffsetY, $imageWidth, $imageHeight);

                // delete viewport image
                imagedestroy($viewportImage);
                unlink(ROOT_DIR."/screenshots/$dir/view-$cc-$filename");

                // over 10k break - over this will causes memory issues for 3x montage image
                if ($scrollTop > 10000) {
                    Logger::get()->debug("scrollTop > 10000, breaking");
                    break;
                }

                // safety break
                if (++$c > 20) {
                    Logger::get()->debug("c > 20, safety breaking");
                    break;
                }

                $prevScrollTop = $scrollTop;
                Logger::get()->debug("prevScrollTop = $prevScrollTop");
            }

            // write composite image to disk
            imagepng($compositeImage, ROOT_DIR."/screenshots/$dir/$filename");
            imagedestroy($compositeImage);
        }

        // delete temp file
        imagedestroy($tempFile);
        unlink(ROOT_DIR."/screenshots/$dir/temp-$filename");
    }

    /**
     * @param BrowserPilot $browserPilot
     */
    public function setBrowserPilot(BrowserPilot $browserPilot)
    {
        $this->browserPilot = $browserPilot;
    }

    /**
     * Get the thumb path from an image path
     *
     * @param $srcPath
     * @return string
     */
    public function getThumbPath($srcPath)
    {
        if (strpos($srcPath, '/') === false) {
            return "thumb-$srcPath";
        }
        return preg_replace('@/([^/]+)$@', '/thumb-$1', $srcPath);
    }

    /**
     * Create a thumbnail image of an image
     *
     * @param $srcPath - path of the original image
     * @return string
     */
    public function createThumbnail($srcPath)
    {
        $thumbPath = $this->getThumbPath($srcPath);
        $sourceImage = imagecreatefrompng($srcPath);
        $thumbImage = imagecreatetruecolor(150, 150);

        imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, 150, 150, imagesx($sourceImage), imagesy($sourceImage));
        imagepng($thumbImage, $thumbPath);
        imagedestroy($thumbImage);

        return $thumbPath;
    }

    /**
     * @param $filename
     * @param $baselineDomain
     * @param $branchDomain
     *
     * @return string
     */
    public function createDiffAndMontageImages($filename, $baselineDomain, $branchDomain)
    {
        list($baselineDir) = $this->getDirAndFilename($baselineDomain, '/');
        list($branchDir) = $this->getDirAndFilename($branchDomain, '/');

        $baselinePath = ROOT_DIR . "/screenshots/$baselineDir/$filename";
        $branchPath = ROOT_DIR . "/screenshots/$branchDir/$filename";

        Logger::get()->info("Comparing images $baselinePath and $branchPath");

        $resultsDir = ROOT_DIR . "/screenshots/results/$branchDir";

        // baseline image will always exist because that's the directory we're looping
        // in createResults()
        $baselineImage = imagecreatefrompng($baselinePath);

        if (file_exists($branchPath)) {
            $branchImage = imagecreatefrompng($branchPath);
        } else {
            // create branch image if it doesn't exist as a blank white image
            Logger::get()->warn("$branchPath does not exist, using blank white image instead");
            $branchImage = imagecreatetruecolor(imagesx($baselineImage), imagesy($baselineImage));
            $white = imagecolorallocate($branchImage, 255, 255, 255);
            imagefill($branchImage, 0, 0, $white);
        }

        // verify images are working properly
        if (!$baselineImage) {
            Logger::get()->warn("$baselinePath is not a valid image");
            die;
        }
        if (!$branchImage) {
            Logger::get()->warn("$branchPath is not a valid image");
            die;
        }

        list($diffPath, $diffImage) = $this->createDiffImage($baselineImage, $branchImage, $branchDir, $resultsDir, $filename);

        $montagePath = $this->createMontageImage($baselineImage, $branchImage, $diffImage, $diffPath);

        // free up memory
        imagedestroy($diffImage);
        imagedestroy($baselineImage);
        imagedestroy($branchImage);

        // delete diff image
        unlink($diffPath);

        // createThumbnail() needs to happen outside of createMontageImage() otherwise
        // memory will get exhausted
        $this->createThumbnail($montagePath);

        $montageFilename = str_replace("$resultsDir/", '', $montagePath);

        return $montageFilename;
    }

    /**
     * Create a diff image
     *
     * @param $baselineImage
     * @param $branchImage
     * @param $branchDir
     * @param $resultsDir
     * @param $filename
     * @return array
     */
    public function createDiffImage($baselineImage, $branchImage, $branchDir, $resultsDir, $filename)
    {
        $baselineImageWidth = imagesx($baselineImage);
        $baselineImageHeight = imagesy($baselineImage);

        $diffImage = imagecreatetruecolor($baselineImageWidth, max($baselineImageHeight, imagesy($branchImage)));
        $red = imagecolorallocate($diffImage, 255, 0, 0);
        $white = imagecolorallocate($diffImage, 255, 255, 255);
        imagefill($diffImage, 0, 0, $white);

        $missingPix = ['red' => 255, 'green' => 255, 'blue' => 255, 'alpha' => 0];
        $numberOfDifferentPixels = 0;
        for ($x = 0; $x < $baselineImageWidth; $x++) {
            for ($y = 0; $y < $baselineImageHeight; $y++) {
                $rgb1 = @imagecolorat($baselineImage, $x, $y);
                $pix1 = ($rgb1 !== false) ? imagecolorsforindex($baselineImage, $rgb1) : $missingPix;
                $rgb2 = @imagecolorat($branchImage, $x, $y);
                $pix2 = ($rgb2 !== false) ? imagecolorsforindex($branchImage, $rgb2) : $missingPix;
                if ($this->pixelsAreDifferent($pix1, $pix2)) {
                    imagesetpixel($diffImage, $x, $y, $red);
                    $numberOfDifferentPixels++;
                } else {
                    imagesetpixel($diffImage, $x, $y, $rgb1);
                }
            }
        }

        $total = $baselineImageWidth * $baselineImageHeight;
        $percDiff = $numberOfDifferentPixels / $total;
        Logger::get()->debug(number_format(100 * $percDiff, 2) . "% different");

        $percDiffFourDP = preg_replace('%[01]\.([0-9]{4})%', '$1', number_format($percDiff, 4));

        $diffFilename = preg_replace('%^(.+?)\.png%', "diff-$percDiffFourDP-|$1.png", $filename);

        $diffPath = "$resultsDir/$diffFilename";

        imagepng($diffImage, $diffPath);

        return [$diffPath, $diffImage];
    }

    /**
     * Create a montage image
     *
     * @param $baselineImage
     * @param $branchImage
     * @param $diffImage
     * @param string $diffPath
     *
     * @return string
     */
    public function createMontageImage($baselineImage, $branchImage, $diffImage, $diffPath)
    {
        $baselineWidth = imagesx($baselineImage);
        $baselineHeight = imagesy($baselineImage);

        $branchWidth = imagesx($branchImage);
        $branchHeight = imagesy($branchImage);

        $diffWidth = imagesx($diffImage);
        $diffHeight = imagesy($diffImage);

        $montageWidth = $baselineWidth + $branchWidth + $diffWidth;
        $montageHeight = max($baselineHeight, $branchHeight, $diffHeight);

        $montageImage = imagecreatetruecolor($montageWidth, $montageHeight);

        $white = imagecolorallocate($montageImage, 255, 255, 255);
        imagefill($montageImage, 0, 0, $white);

        imagecopy($montageImage, $baselineImage, 0, 0, 0, 0, $baselineWidth, $baselineHeight);
        imagecopy($montageImage, $branchImage, $baselineWidth, 0, 0, 0, $branchWidth, $branchHeight);
        imagecopy($montageImage, $diffImage, $baselineWidth + $branchWidth, 0, 0, 0, $diffWidth, $diffHeight);

        $montagePath = str_replace('diff-', '', $diffPath);

        imagepng($montageImage, $montagePath);
        imagedestroy($montageImage);

        return $montagePath;
    }

    /**
     * Compare pixels with a degree of tolerances
     *
     * Example of a pixel:
     * [
     *   'red'   => 135,
     *   'green' => 86,
     *   'blue'  => 117,
     *   'alpha' => 0
     * ]
     *
     * @param array $pix1
     * @param array $pix2
     *
     * @return bool
     */
    protected function pixelsAreDifferent(array $pix1, array $pix2)
    {
        foreach (['red', 'green', 'blue'] as $key) {
            if (abs($pix1[$key] - $pix2[$key]) > 5) {
                return true;
            }
        }
        return false;
    }

    /**
     * Delete any previous screenshots for the current domain
     */
    public function deletePreviousImages() {
        list($dir) = $this->getDirAndFilename($this->domain, '/');
        if (!file_exists(ROOT_DIR . "/screenshots/$dir")) {
            return;
        }
        $baselineFiles = scandir(ROOT_DIR . "/screenshots/$dir");
        foreach($baselineFiles as $filename) {
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) != 'png') {
                continue;
            }
            unlink(ROOT_DIR . "/screenshots/$dir/$filename");
        }
        $dim = new WebDriverDimension(1440, 900);
        $this->driver->manage()->window()->setSize($dim);
    }

    /**
     * Handle /admin paths
     *
     * @param $path
     */
    public function screenshotAdmin($path)
    {
        // set screensize so preview does not show
        $dim = new WebDriverDimension(1200, 900);
        $this->driver->manage()->window()->setSize($dim);

        $this->browserPilot->get($path);
        $gridFieldAssoc = [];
        $driver = $this->browserPilot->getDriver();

        $currentURL = $driver->getCurrentURL();

        $rx = '%\.(govt|com|org|mil|nz|uat|prod|cwp)%';
        if (preg_match('%\.(govt|com|org|mil|nz|uat|prod|cwp)%', $currentURL)) {
            Logger::get()->warn("Can only screenshot local admin, $currentURL matched regex $rx");
            return;
        }

        // login to admin
        if (preg_match('%/Security/login%', $currentURL)) {
            $driver->findElement(WebDriverBy::id(LOGIN_USERNAME_ID))->sendKeys(ADMIN_USERNAME);
            $driver->findElement(WebDriverBy::id(LOGIN_PASSWORD_ID))->sendKeys(ADMIN_PASSWORD);
            $driver->findElement(WebDriverBy::id(LOGIN_SUBMIT_ID))->click();
        }

        // clear window to small for preview mode popup
        $this->browserPilot->executeJS(<<<EOT
            var el = document.querySelector('.notice-item-close');
            if (el) {
                el.click();
            }
EOT
        );

        if ($path == '/admin') {

            // screenshot default screen
            $this->browserPilot->waitUntilPageLoaded();
            $this->takeScreenshot(false);

            // get all model admins
            $idsJoined = $this->browserPilot->executeJS(<<<EOT
                var ids = [];
                var lis = document.querySelectorAll('.cms-menu-list li');
                for (var i = 0; i < lis.length; i++) {
                    var li = lis[i];
                    if (li.className.match('current')) {
                        continue;
                    }
                    if (li.id == 'Menu-Help') {
                        continue;
                    }
                    ids.push(li.id);
                }
                return ids.join(';');
EOT
            );
            $ids = explode(';', $idsJoined);

            // navigate to each model admin and take screenshot
            foreach ($ids as $id) {
                Logger::get()->debug("Clicking model admin $id");
                $this->browserPilot->executeJS(<<<EOT
                    document.getElementById('$id').querySelector('a').click();
EOT
                );
                $this->browserPilot->waitUntilPageLoaded();
                $this->takeScreenshot(false);

                // get ids of all model admin tabs
                $tabIDsJoined = $this->browserPilot->executeJS(<<<EOT
                    var ids = [];
                    var selector = '.cms-tabset-nav-primary.ui-tabs-nav a';
                    var links = document.querySelectorAll(selector);
                    for (var i = 0; i < links.length; i++) {
                        var link = links[i];
                        if (link.innerText == 'Main') {
                            continue;
                        }
                        ids.push(link.id);
                    }
                    return ids.join(';');
EOT
                );
                $tabIDs = explode(';', $tabIDsJoined);

                foreach ($tabIDs as $tabID) {
                    $this->browserPilot->executeJS("document.getElementById('$tabID').click();");
                    $this->browserPilot->waitUntilPageLoaded();
                    $this->takeScreenshot(false);
                    $this->screenshotGridfield($gridFieldAssoc);
                }
            }
        }

        if (preg_match('%/admin/pages/edit/show/[0-9]+%', $path)) {

            $this->browserPilot->waitUntilPageLoaded();

            // normalise URL Segment
            $this->browserPilot->executeJS(<<<EOT
                var link = document.querySelector('a.preview');
                if (link) {
                    link.innerHTML = link.innerHTML.replace(/^http(s)?:\/\/[^\/]+\//, 'http://roboshot.nz/');
                }
EOT
            );

            $this->takeScreenshot(false);
            $this->screenshotGridfield($gridFieldAssoc);

            // get ids of all page tabs
            $idsJoined = $this->browserPilot->executeJS(<<<EOT
                var ids = [];
                var links = document.querySelectorAll('#Root .ui-tabs-nav a');
                for (var i = 0; i < links.length; i++) {
                    var link = links[i];
                    if (link.id == 'tab-Root_Main') {
                        continue;
                    }
                    ids.push(link.id);
                }
                return ids.join(';');
EOT
            );
            $ids = explode(';', $idsJoined);

            foreach ($ids as $id) {
                $this->browserPilot->executeJS("document.getElementById('$id').click();");
                $this->browserPilot->waitUntilPageLoaded();
                $this->takeScreenshot(false);
                $this->screenshotGridfield($gridFieldAssoc);
            }
        }
    }

    /**
     * Screenshot the edit form of the first DataObject in a gridfield.
     *
     * @param $gridFieldAssoc - array of all gridfield ids that have previously had screenshots take
     */
    protected function screenshotGridfield(&$gridFieldAssoc)
    {
        $js = <<<EOT
            var table = document.querySelector('#Root div[aria-expanded=true] .ss-gridfield-table');
            return table ? table.parentNode.id : '';
EOT;
        $id = $this->browserPilot->executeJS($js);
        Logger::get()->debug("GridField#ID = $id");
        if (!$id || array_key_exists($id, $gridFieldAssoc)) {
            // shea dawson blocks or dna elemental blocks
            if ($id != 'Form_EditForm_Blocks' && $id != 'Form_EditForm_ElementArea') {
                return;
            }
        }
        if ($id == 'Form_EditForm_DependentPages') {
            return;
        }

        $selector = '#Root .tab[aria-expanded="true"] .ss-gridfield-table .ss-gridfield-item td';
        $hasAtLeastOneRow = $this->browserPilot->executeJS("return document.querySelector('$selector') ? 1 : 0;");
        Logger::get()->debug("hasAtLeastOneRow = $hasAtLeastOneRow");
        if (!$hasAtLeastOneRow) {
            return;
        }

        if ($id == 'Form_EditForm_Blocks' || $id == 'Form_EditForm_ElementArea') {
            // sheadawson blocks or dna elemental blocks
            // this will return Block/BaseElement classes e.g. BasicContent,LocationContent
            $dataClasses = $this->browserPilot->executeJS(<<<EOT
                var dataClasses = [];
                var trs = document.querySelectorAll('#Root .tab[aria-expanded="true"] .ss-gridfield-table .ss-gridfield-item');
                for (var i = 0; i < trs.length; i++) {
                    var tr = trs[i];
                    var dataClass = tr.getAttribute('data-class');
                    dataClasses.push(dataClass);
                }
                return dataClasses.join(',');
EOT
            );
            Logger::get()->debug("dataClasses = $dataClasses");
            foreach (explode(',', $dataClasses) as $dataClass) {
                if (array_key_exists("$id:$dataClass", $gridFieldAssoc)) {
                    continue;
                }
                $sel = "#Root .tab[aria-expanded='true'] .ss-gridfield-table .ss-gridfield-item[data-class='$dataClass']";
                $this->browserPilot->executeJS("document.querySelector(\"$sel\").click();");
                $this->browserPilot->waitUntilPageLoaded();
                $this->takeScreenshot(false);

                // click back link
                $this->browserPilot->executeJS("document.querySelector('.backlink').click();");
                $this->browserPilot->waitUntilPageLoaded();

                $gridFieldAssoc["$id:$dataClass"] = true;
            }
        } else {
            // standard gridfield
            $this->browserPilot->executeJS("document.querySelector('$selector').click();");
            $this->browserPilot->waitUntilPageLoaded();
            $this->takeScreenshot(false);

            // click back link
            $this->browserPilot->executeJS("document.querySelector('.backlink').click();");
            $this->browserPilot->waitUntilPageLoaded();
        }

        // update assoc
        $gridFieldAssoc[$id] = true;
    }

}