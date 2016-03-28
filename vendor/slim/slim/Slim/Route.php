<?php
/**
 * Slim Framework (http://slimframework.com)
 *
 * @link      https://github.com/slimphp/Slim
 * @copyright Copyright (c) 2011-2016 Josh Lockhart
 * @license   https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */
namespace Slim;

use Exception;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Handlers\Strategies\RequestResponse;
use Slim\Interfaces\InvocationStrategyInterface;
use Slim\Interfaces\RouteInterface;

/**
 * Route
 * 路由规则
 */
class Route extends Routable implements RouteInterface
{
    use MiddlewareAwareTrait;

    /**
     * HTTP methods supported by this route
     * 本规则支持的HTTP方法
     *
     * @var string[]
     */
    protected $methods = [];

    /**
     * Route identifier
     * 路由规则识别ID
     *
     * @var string
     */
    protected $identifier;

    /**
     * Route name
     * 路由规则名称
     *
     * @var null|string
     */
    protected $name;

    /**
     * Parent route groups
     * 父规则组
     *
     * @var RouteGroup[]
     */
    protected $groups;

    private $finalized = false;

    /**
     * Output buffering mode
     * 输出缓冲模式
     *
     * One of: false, 'prepend' or 'append'
     * 关闭或者预加或者附加
     *
     * @var boolean|string
     */
    protected $outputBuffering = 'append';

    /**
     * Route parameters
     * 路由规则参数
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * Create new route
     * 创建新的路由规则
     *
     * @param string[]     $methods The route HTTP methods
     * @param string       $pattern The route pattern
     * @param callable     $callable The route callable
     * @param int          $identifier The route identifier
     * @param RouteGroup[] $groups The parent route groups
     */
    public function __construct($methods, $pattern, $callable, $groups = [], $identifier = 0)
    {
        $this->methods  = $methods;
        $this->pattern  = $pattern;
        $this->callable = $callable;
        $this->groups   = $groups;
        $this->identifier = 'route' . $identifier;
    }

    /**
     * Finalize the route in preparation for dispatching
     * 准备分配，最后确定路由规则
     */
    public function finalize()
    {
        if ($this->finalized) {
            return;
        }

        $groupMiddleware = [];
        foreach ($this->getGroups() as $group) {
            $groupMiddleware = array_merge($group->getMiddleware(), $groupMiddleware);
        }

        $this->middleware = array_merge($this->middleware, $groupMiddleware);

        foreach ($this->getMiddleware() as $middleware) {
            $this->addMiddleware($middleware);
        }

        $this->finalized = true;
    }

    /**
     * Get route callable
     *
     * @return callable
     */
    public function getCallable()
    {
        return $this->callable;
    }

    /**
     * Get route methods
     *
     * @return string[]
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * Get parent route groups
     *
     * @return RouteGroup[]
     */
    public function getGroups()
    {
        return $this->groups;
    }

    /**
     * Get route name
     *
     * @return null|string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get route identifier
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Get output buffering mode
     *
     * @return boolean|string
     */
    public function getOutputBuffering()
    {
        return $this->outputBuffering;
    }

    /**
     * Set output buffering mode
     * 设置输出缓冲区类型
     *
     * One of: false, 'prepend' or 'append'
     *
     * @param boolean|string $mode
     *
     * @throws InvalidArgumentException If an unknown buffering mode is specified
     */
    public function setOutputBuffering($mode)
    {
        if (!in_array($mode, [false, 'prepend', 'append'], true)) {
            throw new InvalidArgumentException('Unknown output buffering mode');
        }
        $this->outputBuffering = $mode;
    }

    /**
     * Set route name
     *
     * @param string $name
     *
     * @return self
     *
     * @throws InvalidArgumentException if the route name is not a string
     */
    public function setName($name)
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('Route name must be a string');
        }
        $this->name = $name;
        return $this;
    }

    /**
     * Set a route argument
     *
     * @param string $name
     * @param string $value
     *
     * @return self
     */
    public function setArgument($name, $value)
    {
        $this->arguments[$name] = $value;
        return $this;
    }

    /**
     * Replace route arguments
     *
     * @param array $arguments
     *
     * @return self
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * Retrieve route arguments
     *
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Retrieve a specific route argument
     *
     * @param string $name
     * @param mixed $default
     *
     * @return mixed
     */
    public function getArgument($name, $default = null)
    {
        if (array_key_exists($name, $this->arguments)) {
            return $this->arguments[$name];
        }
        return $default;
    }

    /********************************************************************************
     * Route Runner
     *******************************************************************************/

    /**
     * Prepare the route for use
     * 准备路由规则以使用
     *
     * @param ServerRequestInterface $request
     * @param array $arguments
     */
    public function prepare(ServerRequestInterface $request, array $arguments)
    {
        // Add the arguments
        foreach ($arguments as $k => $v) {
            $this->setArgument($k, $v);
        }
    }

    /**
     * Run route
     * 执行路由规则
     *
     * This method traverses the middleware stack, including the route's callable
     * and captures the resultant HTTP response object. It then sends the response
     * back to the Application.
     * 本方法需要依次执行中间件组成的栈，捕获路由规则并且合成响应对象。而后将合成后
     * 的响应对象交给应用
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface      $response
     *
     * @return ResponseInterface
     */
    public function run(ServerRequestInterface $request, ResponseInterface $response)
    {
        // Finalise route now that we are about to run it
        // 完成路由并运行
        $this->finalize();

        // Traverse middleware stack and fetch updated response
        // 依次调用中间件
        return $this->callMiddlewareStack($request, $response);
    }

    /**
     * Dispatch route callable against current Request and Response objects
     *
     * This method invokes the route object's callable. If middleware is
     * registered for the route, each callable middleware is invoked in
     * the order specified.
     *
     * @param ServerRequestInterface $request  The current Request object
     * @param ResponseInterface      $response The current Response object
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Exception  if the route callable throws an exception
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->callable = $this->resolveCallable($this->callable);

        /** @var InvocationStrategyInterface $handler */
        $handler = isset($this->container) ? $this->container->get('foundHandler') : new RequestResponse();

        // invoke route callable
        if ($this->outputBuffering === false) {
            $newResponse = $handler($this->callable, $request, $response, $this->arguments);
        } else {
            try {
                ob_start();
                $newResponse = $handler($this->callable, $request, $response, $this->arguments);
                $output = ob_get_clean();
            } catch (Exception $e) {
                ob_end_clean();
                throw $e;
            }
        }

        if ($newResponse instanceof ResponseInterface) {
            // if route callback returns a ResponseInterface, then use it
            $response = $newResponse;
        } elseif (is_string($newResponse)) {
            // if route callback returns a string, then append it to the response
            if ($response->getBody()->isWritable()) {
                $response->getBody()->write($newResponse);
            }
        }

        if (!empty($output) && $response->getBody()->isWritable()) {
            if ($this->outputBuffering === 'prepend') {
                // prepend output buffer content
                $body = new Http\Body(fopen('php://temp', 'r+'));
                $body->write($output . $response->getBody());
                $response = $response->withBody($body);
            } elseif ($this->outputBuffering === 'append') {
                // append output buffer content
                $response->getBody()->write($output);
            }
        }

        return $response;
    }
}
