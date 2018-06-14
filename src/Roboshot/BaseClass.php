<?php

namespace Roboshot;

use Facebook\WebDriver\Chrome\ChromeDriver;
use Monolog\Logger;

class BaseClass
{

    /**
     * @var ChromeDriver
     */
    protected $driver;

    /**
     * @var string
     */
    protected $domain;

    /**
     * @var Logger
     */
    protected $log;

    /**
     * @param ChromeDriver $driver
     */
    function setDriver(ChromeDriver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @return ChromeDriver
     */
    function getDriver()
    {
        return $this->driver;
    }

    /**
     * @param string $domain
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * @return string
     */
    function getDomain()
    {
        return $this->domain;
    }

    /**
     * @return Logger
     */
    public function getLogger() {
        return $this->log;
    }

    /**
     * @param Logger $log
     * @return BaseClass
     */
    public function setLogger($log) {
        $this->log = $log;

        return $this;
    }

    /**
     * Return [$dir, $filename] of the current ($domain, $path)
     * e.g.
     * ('http://www.igis.govt.nz', '/about-us/our-people') => ['www.igis.govt.nz', 'about-us|our-people']
     *
     * @param $domain
     * @param $path
     * @return array
     */
    public function getDirAndFilename($domain, $path)
    {
        $parts = parse_url($domain . $path);
        $dir = $parts['host'];
        $dir = ltrim($dir, '/');
        $filename = $parts['path'];
        $filename = str_replace('/', '|', $filename);
        $filename = trim($filename, '|');
        $filename = str_replace(array(' ', '%20', '+'), '-', $filename);
        $filename = trim($filename, '-');
        $filename = str_replace('?Locale=en_NZ', '', $filename); // admin
        $filename = $filename ?: 'index';
        $filename = "$filename.png";
        return [$dir, $filename];
    }
}