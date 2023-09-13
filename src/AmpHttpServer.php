<?php

declare(strict_types=1);

namespace MicroPHP\Amp;

use Amp\ByteStream;
use Amp\CompositeException;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Socket\SocketException;

use function Amp\trapSignal;

use MicroPHP\Framework\Http\Contract\HttpServerInterface;
use MicroPHP\Framework\Http\ServerConfig;
use MicroPHP\Framework\Http\ServerRequest;
use MicroPHP\Framework\Http\Traits\HttpServerTrait;
use MicroPHP\Framework\Router\Router;
use Monolog\Level;
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
        $serverConfig = new ServerConfig();
        $logHandler = new StreamHandler(ByteStream\getStdout());
        $logHandler->pushProcessor(new PsrLogMessageProcessor());
        $logHandler->setFormatter(new ConsoleFormatter());
        $logHandler->setLevel(Level::Info);

        $logger = new Logger('server');
        $logger->pushHandler($logHandler);

        $requestHandler = new class($router) implements RequestHandler
        {
            private Router $router;

            use HttpServerTrait;

            public function __construct(Router $router)
            {
                $this->router = $router;
            }

            public function handleRequest(Request $request): Response
            {
                $psr7Request = ServerRequest::fromAmp($request);
                $psr7Response = $this->routeDispatch($this->router, $psr7Request);

                return new Response(
                    status: $psr7Response->getStatusCode(),
                    headers: $psr7Response->getHeaders(),
                    body: $psr7Response->getBody()->getContents()
                );
            }
        };

        $errorHandler = new DefaultErrorHandler();

        $server = SocketHttpServer::createForDirectAccess($logger);
        $server->expose($serverConfig->getUri());
        $server->start($requestHandler, $errorHandler);

        // Serve requests until SIGINT or SIGTERM is received by the process.
        trapSignal([SIGINT, SIGTERM]);

        $server->stop();
    }
}
