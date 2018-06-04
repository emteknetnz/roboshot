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
        log("Taking screenshot of {$this->domain}{$path}");
        list($dir, $filename) = $this->getDirAndFilename($this->domain, $path);
        $pilot = $this->browserPilot;

        // create screenshot directory
        if (!file_exists("screenshots/$dir")) {
            mkdir("screenshots/$dir");
        }

        // for admin urls, suffix number for duplicate filenames
        // using _ as part of suffix instead of - because /admin/pages/edit/show/1 filename is admin-pages-edit-show-1
        $c = 1;
        while (file_exists("screenshots/$dir/$filename")) {
            $filename = preg_replace('%_[0-9]+\.png$%', '.png', $filename);
            $filename = preg_replace('%\.png$%', "_$c.png", $filename);
            $c++;
        }

        // scroll to top of page
        $pilot->executeJS("window.scrollTo(0, 0);");

        // for /admin, take screenshot of above the fold content only
        if (!$takeFullPageScreenshot) {
            $this->driver->takeScreenshot("screenshots/$dir/$filename");
            return;
        }

        // get document and viewport heights
        $documentHeight = $pilot->executeJS("return document.documentElement.scrollHeight;");
        $viewportHeight = $pilot->executeJS("return window.innerHeight;");
        debug("documentHeight = $documentHeight");
        debug("viewportHeight = $viewportHeight");

        // take initial screenshot
        $this->driver->takeScreenshot("screenshots/$dir/temp-$filename");
        $tempFile = imagecreatefrompng("screenshots/$dir/temp-$filename");

        // get image dimensions
        $imageWidth = imagesx($tempFile);
        $imageHeight = imagesy($tempFile);
        debug("imageWidth = $imageWidth");
        debug("imageHeight = $imageHeight");

        // this is 0.5 (2x pixels) on ios chrome retina e.g
        // $viewportHeight = 619
        // $imageHeight = 1238
        $imageRatio = $imageHeight / $viewportHeight;
        debug("imageRatio = $imageRatio");

        if ($imageRatio != 1) {
            $tmp = imagescale($tempFile, floor($imageWidth / 2));
            imagedestroy($tempFile);
            $tempFile = $tmp;
            $imageWidth = imagesx($tempFile);
            $imageHeight = imagesy($tempFile);
            debug("new imageWidth = $imageWidth");
            debug("new imageHeight = $imageHeight");
        }

        if ($viewportHeight >= $documentHeight) {
            // ^ document fits in viewport

            imagepng($tempFile, "screenshots/$dir/$filename");

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
                debug("scrollTop = $scrollTop");

                $scrollAmount = $scrollTop - $prevScrollTop;
                debug("scrollAmount = $scrollAmount");

                if ($scrollAmount == 0) {
                    debug("No more scrolling possible, breaking");
                    break;
                }

                // use so that viewportImage has a unique filename, ensure there's no race conditions
                // with the unlink() function and subsequent new screenshots being taken
                $cc = str_pad($c, 2, '0', STR_PAD_LEFT);

                // create a new viewport image
                debug("Taking viewport screenshot to add to composite image");
                $this->driver->takeScreenshot("screenshots/$dir/view-$cc-$filename");
                $viewportImage = imagecreatefrompng("screenshots/$dir/view-$cc-$filename");

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
                debug("viewportImageOffsetY = $viewportImageOffsetY");

                // copy the viewport image on to the composite image
                imagecopy($compositeImage, $viewportImage, 0, $c * $imageHeight, 0, $viewportImageOffsetY, $imageWidth, $imageHeight);

                // delete viewport image
                imagedestroy($viewportImage);
                unlink("screenshots/$dir/view-$cc-$filename");

                // over 10k break - over this will causes memory issues for 3x montage image
                if ($scrollTop > 10000) {
                    log("scrollTop > 10000, breaking");
                    break;
                }

                // safety break
                if (++$c > 20) {
                    log("c > 20, safety breaking");
                    break;
                }

                $prevScrollTop = $scrollTop;
                debug("prevScrollTop = $prevScrollTop");
            }

            // write composite image to disk
            imagepng($compositeImage, "screenshots/$dir/$filename");
            imagedestroy($compositeImage);
        }

        // delete temp file
        imagedestroy($tempFile);
        unlink("screenshots/$dir/temp-$filename");
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

        $baselinePath = getcwd() . "/screenshots/$baselineDir/$filename";
        $branchPath = getcwd() . "/screenshots/$branchDir/$filename";

        log("Comparing images:");
        log($baselinePath);
        log($branchPath);

        $resultsDir = getcwd() . "/screenshots/results/$branchDir";

        // TODO: if one of images is missing, create a blank jpg
        // can happen on weird timing edge cases, and annoying cos breaks otherwise good run

        // TODO: use imagecreatefrompng() ?
        $baselineImage = @imagecreatefromstring(file_get_contents($baselinePath));
        $branchImage = @imagecreatefromstring(file_get_contents($branchPath));

        // check if we were given garbage
        if (!$baselineImage) {
            log("$baselinePath is not a valid image");
            die;
        }
        if (!$branchImage) {
            log("$branchPath is not a valid image");
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
        log(number_format(100 * $percDiff, 2) . "% different");

        $percDiffFourDP = preg_replace('%[01]\.([0-9]{4})%', '$1', number_format($percDiff, 4));

        $diffFilename = preg_replace('%^(.+?)\.png%', "diff-$1-$percDiffFourDP.png", $filename);

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

        $montagePath = str_replace('diff-', 'montage-', $diffPath);

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
        if (!file_exists(getcwd() . "/screenshots/$dir")) {
            return;
        }
        $baselineFiles = scandir(getcwd() . "/screenshots/$dir");
        foreach($baselineFiles as $filename) {
            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) != 'png') {
                continue;
            }
            unlink(getcwd() . "/screenshots/$dir/$filename");
        }
        // TODO: move this somewhere a little more sane
        // need to do this because when doing branch domain after baseline domain post admin resize
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

        if (!preg_match('%\.(test|vagrant|local)%', $driver->getCurrentURL())) {
            echo "Can only screenshot local admin\n";
            return;
        }

        // login to admin
        // currently will only work on local dev
        if (preg_match('%/Security/login%', $driver->getCurrentURL())) {
            $driver->findElement(WebDriverBy::id('MemberLoginForm_LoginForm_Email'))->sendKeys('admin');
            $driver->findElement(WebDriverBy::id('MemberLoginForm_LoginForm_Password'))->sendKeys('password');
            $driver->findElement(WebDriverBy::id('MemberLoginForm_LoginForm_action_dologin'))->click();
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
                $this->browserPilot->executeJS(<<<EOT
                    document.getElementById('$id').querySelector('a').click();
EOT
                );
                $this->browserPilot->waitUntilPageLoaded();
                $this->takeScreenshot(false);
            }
        }

        if (preg_match('%/admin/pages/edit/show/[0-9]+%', $path)) {

            // screenshot first tab
            // TODO: normalise URL segment domains
            $this->browserPilot->waitUntilPageLoaded();
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
        echo "id:$id\n";
        if (!$id || array_key_exists($id, $gridFieldAssoc)) {
            return;
        }
        if ($id == 'Form_EditForm_DependentPages') {
            return;
        }
        $selector = '#Root div[style="display: block;"] .ss-gridfield-table .ss-gridfield-item td';
        $hasAtLeastOneRow = $this->browserPilot->executeJS("return document.querySelector('$selector') ? 1 : 0;");
        echo "hasAtLeastOneRow:$hasAtLeastOneRow\n";
        if (!$hasAtLeastOneRow) {
            return;
        }
        $this->browserPilot->executeJS("document.querySelector('$selector').click();");
        $this->browserPilot->waitUntilPageLoaded();
        $this->takeScreenshot(false);

        // click back link
        $this->browserPilot->executeJS("document.querySelector('.backlink').click();");
        $this->browserPilot->waitUntilPageLoaded();

        // update assoc
        $gridFieldAssoc[$id] = true;
    }

}