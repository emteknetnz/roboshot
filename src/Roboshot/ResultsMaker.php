<?php

namespace Roboshot;

/**
 * HTML result related functions
 *
 * @package Roboshot
 */
class ResultsMaker extends BaseClass
{
    /**
     * Create a results.html file
     *
     * @param $baselineDomain
     * @param $branchDomain
     */
    public function createResults($baselineDomain, $branchDomain)
    {
        $resultsDir = $this->createResultsDir($branchDomain);
        list($baselineDir) = $this->getDirAndFilename($baselineDomain, '/');
        $baselineFiles = scandir(ROOT_DIR . "/screenshots/$baselineDir");
        $imageHtmls = [];

        foreach($baselineFiles as $filename) {
            switch(strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
                case 'png':
                    $imageHtmls[] = $this->handleImageResult($filename, $baselineDomain, $branchDomain);
                    break;

                case 'html':
                    $this->handleHTMLResult($filename, $baselineDomain, $branchDomain);
                    break;
            }
        }

        // reverse sort array so most different diff's are first
        rsort($imageHtmls);
        $html = file_get_contents('templates/results.html');
        $html = str_replace('%images%', implode("\n", $imageHtmls), $html);
        file_put_contents("$resultsDir/results.html", $html);
        copy('templates/styles.css', "$resultsDir/styles.css");
        copy('templates/script.js', "$resultsDir/script.js");
    }

    protected function handleHTMLResult($filename, $baselineDomain, $branchDomain) {
        $sourceDiff = new SourceDiff();
        $sourceDiff->createDiff($filename, $baselineDomain, $branchDomain);
    }

    protected function handleImageResult($filename, $baselineDomain, $branchDomain) {
        $imageMaker = new ImageMaker();

        $path = '/' . str_replace('|', '/', $filename);
        $path = str_replace('.png', '', $path);
        $montageFilename = $imageMaker->createDiffAndMontageImages($filename, $baselineDomain, $branchDomain);
        $thumbFilename = $imageMaker->getThumbPath($montageFilename);
        $imageHtml = file_get_contents('templates/screenshot.html');
        $imageHtml = str_replace('%src%', $montageFilename, $imageHtml);
        $imageHtml = str_replace('%thumbsrc%', $thumbFilename, $imageHtml);
        $imageHtml = str_replace('%filename%', $montageFilename, $imageHtml);
        $imageHtml = str_replace('%path%', $path, $imageHtml);

        return $imageHtml;
    }

    /**
     * Create the results directory if it doesn't yet exists
     * Delete any old results
     *
     * @param string $branchDomain
     * @return string
     */
    protected function createResultsDir($branchDomain)
    {
        list($branchDir) = $this->getDirAndFilename($branchDomain, '/');
        $resultsDir = ROOT_DIR . "/screenshots/results/$branchDir";
        if (!file_exists($resultsDir)) {
            mkdir($resultsDir);
        }
        $files = glob("$resultsDir/*");
        foreach($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return $resultsDir;
    }
}