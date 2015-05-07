<?php
/**
 * This file is part of the BEAR.Package package
 *
 * @license http://opensource.org/licenses/MIT MIT
 */
namespace BEAR\Package\Provide\Router;

use Aura\Cli\CliFactory;
use Aura\Cli\Context\OptionFactory;
use Aura\Cli\Status;
use Aura\Cli\Stdio;
use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Package\Annotation\StdIn;
use BEAR\Sunday\Extension\Router\RouterInterface;
use Ray\Di\Di\Inject;
use Ray\Di\Di\Named;

class CliRouter implements RouterInterface
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var AbstractAppMeta
     */
    private $appMeta;

    /**
     * @var \LogicException
     */
    private $exception;

    /**
     * @var Stdio
     */
    private $stdIo;

    private $stdIn;

    /**
     * @param AbstractAppMeta $appMeta
     *
     * @Inject
     */
    public function setAppMeta(AbstractAppMeta $appMeta)
    {
        $this->appMeta = $appMeta;
        ini_set('error_log', $appMeta->logDir . '/console.log');
    }

    /**
     * @Inject
     * @StdIn
     */
    public function setStdIn($stdIn)
    {
        $this->stdIn = $stdIn;
    }
    /**
     * @param RouterInterface $router
     *
     * @Inject
     * @Named("original")
     */
    public function __construct(RouterInterface $router, \LogicException $exception = null, Stdio $stdIo = null)
    {
        $this->router = $router;
        $this->exception = $exception;
        $this->stdIo = $stdIo ?: (new CliFactory)->newStdio();
    }

    /**
     * {@inheritdoc}
     */
    public function match(array $globals, array $server)
    {
        if ($globals['argc'] !== 3) {
            $this->error(basename($globals['argv'][0]));
            $this->exitProgram(Status::USAGE);
        };
        list(, $method, $uri) = $globals['argv'];
        $parsedUrl = parse_url($uri);
        $query = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }
        $globals = [
            '_GET' => $query,
            '_POST' => $query
        ];
        $server = [
            'REQUEST_METHOD' => strtoupper($method),
            'REQUEST_URI' => $parsedUrl['path']
        ];
        $this->setQuery($method, $query, $globals, $server);

        return $this->router->match($globals, $server);
    }

    /**
     * Set user input query to $globals or &$server
     *
     * @param string $method
     * @param array  $query
     * @param array  $globals
     * @param array  $server
     */
    private function setQuery($method, array $query, array &$globals, array &$server)
    {
        if ($method === 'get') {
            $globals['_GET'] = $query;
        }
        if ($method === 'post') {
            $globals['_POST'] = $query;
        }
        if ($method === 'put' || $method === 'patch' || $method === 'delete') {
            $server[HttpMethodParams::CONTENT_TYPE] = HttpMethodParams::FORM_URL_ENCODE;
            file_put_contents($this->stdIn, http_build_query($query));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generate($name, $data)
    {
        return $this->router->generate($name, $data);
    }

    /**
     * @param string $command
     */
    private function error($command)
    {
        $help = new CliRouterHelp(new OptionFactory);
        $this->stdIo->outln($help->getHelp($command));
    }

    /**
     * @param int $status
     *
     * @SuppressWarnings(PHPMD)
     */
    private function exitProgram($status)
    {
        if ($this->exception) {
            throw $this->exception;
        }
        // @codeCoverageIgnoreStart
        exit($status);
        // @codeCoverageIgnoreEnd
    }
}
