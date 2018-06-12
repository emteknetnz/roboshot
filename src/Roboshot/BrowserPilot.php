<?php

namespace Roboshot;

use Facebook\WebDriver\Chrome\ChromeDriver;
use Facebook\WebDriver\WebDriverDimension;

/**
 * Browser related functions
 *
 * @package Roboshot
 */
class BrowserPilot extends BaseClass
{
    /**
     * Creates a ChromeDriver
     *
     * @return ChromeDriver
     */
    function createDriver()
    {
        $this->driver = ChromeDriver::start();
        $dim = new WebDriverDimension(1440, 900);
        $this->driver->manage()->window()->setSize($dim);
        return $this->driver;
    }

    /**
     * Go chrome to go to an absolute path on the current domain
     *
     * @param $path
     */
    function get($path)
    {
        Logger::get()->info('Navigating to ' . $this->domain . $path);

        $this->driver->get($this->domain . $path);
        $this->waitUntilPageLoaded();
    }

    /**
     * Wait for all resources to load, including xhr which is used in the CMS
     */
    function waitUntilPageLoaded()
    {
        sleep(1);
        $resourceCount = -1;
        for ($i = 0; $i < 30; $i++) {
            $newResourceCount = $this->executeJS("return window.performance.getEntriesByType('resource').length;");
            if ($newResourceCount != $resourceCount) {
                sleep(1);
                $resourceCount = $newResourceCount;
                continue;
            }
            $cmsSpinnerExists = $this->executeJS("return document.querySelector('.cms-content-loading-spinner') ? 1 : 0;");
            if ($cmsSpinnerExists) {
                sleep(1);
                continue;
            }
            $resourcesLoaded = $this->executeJS(<<<EOT
                try {
                    var resources = window.performance.getEntriesByType('resource');
                    for (var i = 0; i < resources.length; i++) {
                        var resource = resources[i];
                        if (!resource.responseEnd) {
                            return 0;
                        }
                    }
                } catch (e) {
                    return 0;
                }
                return 1;
EOT
            );
            sleep(1);
            if ($resourcesLoaded) {
                return;
            }
        }

        Logger::get()->warn("Page did not fully load after 30 seconds");
    }

    /**
     * Execute javascript
     *
     * @param $js
     * @return mixed
     */
    function executeJS($js)
    {
        $js = trim(preg_replace('%[\r\n]%', ' ', $js));
        $js = str_replace('  ', ' ', $js);
        return $this->driver->executeScript(<<<EOT
            return (function() {
                try {
                    $js
                    return 1;
                } catch (e) {
                    return "Javascript exception: " + e.name + ' - ' + e.message;
                }
            })();
EOT
        );
    }

    /**
     * Close the browser
     */
    function close()
    {
        Logger::get()->debug('Closing the browser');
        $this->driver->close();
    }

}