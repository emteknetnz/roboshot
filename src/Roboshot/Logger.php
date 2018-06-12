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

        // Output our logs to the console
        $handler = new Monolog\Handler\StreamHandler('php://stdout', LOG_LEVEL);

        // custom format - remove namespace
        $format = "[%datetime%] %level_name%: %message% %context% %extra%\n";

        // remove empty content/extra [] [] from output
        $formatter = new Monolog\Formatter\LineFormatter($format, null, false, true);
        $handler->setFormatter($formatter);

        self::$logger->pushHandler($handler);
    }
}