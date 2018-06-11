<?php

namespace Roboshot;

use Monolog;

/**
 * A centralized logging class for your logging needs
 * @package Roboshot
 */
class Logger {
    /**
     * @var Monolog\Logger
     */
    protected static $logger;

    public function __construct()
    {
        $this->createLogger();
    }

    /**
     * Returns the current instance of the Monolog logfer
     * @returns Monolog\Logger
     */
    public static function get()
    {
        if (!self::$logger) {
            self::createLogger();
        }

        return self::$logger;
    }

    /**
     * Creates a Monolog instance
     */
    public static function createLogger() {
        self::$logger = new Monolog\Logger(__NAMESPACE__);
    }
}