<?php

namespace Roboshot;

/**
 * Computes the difference between two files
 * @package Roboshot
 */
class SourceDiff extends BaseClass
{
    /**
     * Creates a diff file
     * @param $filename
     * @param $baselineDomain
     * @param $branchDomain
     */
    public function createDiff($filename, $baselineDomain, $branchDomain) {
        if(\function_exists('xdiff_file_diff')) {
            list($baselineDir) = $this->getDirAndFilename($baselineDomain, '/');
            list($branchDir) = $this->getDirAndFilename($branchDomain, '/');

            $baselinePath = ROOT_DIR."/screenshots/$baselineDir/$filename";
            $branchPath = ROOT_DIR."/screenshots/$branchDir/$filename";
            $filename = \str_replace('/', '', $filename);

            $diffFolder = ROOT_DIR. "/screenshots/results/$branchDir/diffs/";

            if (!\file_exists($diffFolder)) {
                \mkdir($diffFolder);
            }

            $fileName = $diffFolder.str_replace('.png', '', $filename).".diff";

            // Perform the file diff
            \xdiff_file_diff($baselinePath, $branchPath, $fileName);
        } else {
            Logger::get()->debug('xdiff_file_diff not present, skipping the file diff');
        }
    }
}