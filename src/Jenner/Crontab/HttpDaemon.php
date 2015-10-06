<?php
/**
 * Created by PhpStorm.
 * User: Jenner
 * Date: 2015/10/6
 * Time: 11:34
 */

namespace Jenner\Crontab;

use GuzzleHttp\Psr7\Response;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use React\EventLoop\Factory;
use React\Http\Request;
use React\Socket\Server;

class HttpDaemon extends Daemon
{
    const LOG_FILE = '/var/log/php_crontab.log';

    /**
     * @param $missions array
     * @param $logfile string
     */
    public function __construct($missions, $logfile = null)
    {
        $logger = new Logger("php_crontab");
        if (!empty($logfile)) {
            $logger->pushHandler(new StreamHandler($logfile));
        } else {
            $logger->pushHandler(new StreamHandler(self::LOG_FILE));
        }
        $this->logger = $logger;

        parent::__construct($missions, $logger);
    }

    /**
     * start crontab and loop
     */
    public function start()
    {
        $this->logger->info("crontab start");

        $loop = Factory::create();

        // add periodic timer
        $loop->addPeriodicTimer(60, function () {
            $pid = pcntl_fork();
            if ($pid > 0) {
                return;
            } elseif ($pid == 0) {
                $crontab = $this->createCrontab();
                $crontab->start(time());
                exit();
            } else {
                $this->logger->error("could not fork");
                exit();
            }
        });

        // recover the sub processes
        $loop->addPeriodicTimer(60, function () {
            while (($pid = pcntl_waitpid(0, $status, WNOHANG)) > 0) {
                $message = "process exit. pid:" . $pid . ". exit code:" . $status;
                $this->logger->info($message);
            }
        });

        $socket = new Server($loop);

        $http = new \React\Http\Server($socket);
        $http->on('request', function (Request $request, Response $response) {
            $response->writeHead(200, array('Content-Type' => 'text/plain'));
            $response->end("Hello World!\n");
        });

        $socket->listen(1337);

        $loop->run();
    }
}