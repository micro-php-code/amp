<?php
declare(strict_types=1);
namespace MicroPHP\Amp;
use Amp\CompositeException;
use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Socket\SocketException;
use MicroPHP\Framework\Http\Contract\HttpServerInterface;
use MicroPHP\Framework\Router\Router;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Throwable;

class AmpHttpServer implements HttpServerInterface
{
    /**
     * @throws Throwable
     * @throws CompositeException
     * @throws SocketException
     */
    public function run(Router $router): void
    {
        // Note any PSR-3 logger may be used, Monolog is only an example.
        $logHandler = new StreamHandler(getStdout());
        $logHandler->pushProcessor(new PsrLogMessageProcessor());
        $logHandler->setFormatter(new ConsoleFormatter());

        $logger = new Logger('server');
        $logger->pushHandler($logHandler);

        $requestHandler = new class() implements RequestHandler {
            public function handleRequest(Request $request) : Response
            {
                return new Response(
                    status: HttpStatus::OK,
                    headers: ['Content-Type' => 'text/plain'],
                    body: 'Hello, world!',
                );
            }
        };

        $errorHandler = new DefaultErrorHandler();

        $server = SocketHttpServer::createForDirectAccess($logger);
        $server->expose('127.0.0.1:1337');
        $server->start($requestHandler, $errorHandler);

        // Serve requests until SIGINT or SIGTERM is received by the process.
        trapSignal([SIGINT, SIGTERM]);

        $server->stop();
    }

}