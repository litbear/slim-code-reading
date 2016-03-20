<?php
/**
 * Slim Framework (http://slimframework.com)
 *
 * @link      https://github.com/slimphp/Slim
 * @copyright Copyright (c) 2011-2016 Josh Lockhart
 * @license   https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */
namespace Slim;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Pimple\Container as PimpleContainer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\ContainerValueNotFoundException;
use Slim\Handlers\Error;
use Slim\Handlers\NotFound;
use Slim\Handlers\NotAllowed;
use Slim\Handlers\Strategies\RequestResponse;
use Slim\Http\Environment;
use Slim\Http\Headers;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Interfaces\Http\EnvironmentInterface;
use Slim\Interfaces\InvocationStrategyInterface;
use Slim\Interfaces\RouterInterface;

/**
 * Slim's default DI container is Pimple.
 * Slim默认的依赖注入容器，继承自 Pimple
 *
 * Slim\App expects a container that implements Interop\Container\ContainerInterface
 * with these service keys configured and ready for use:
 * Slim\App 类需要一个实现了 Interop\Container\ContainerInterface 接口的容器，
 * 默认配置并提供了以下几种服务的键：
 *
 *  - settings: an array or instance of \ArrayAccess
 *    数组或\ArrayAccess的实例
 *  - environment: an instance of \Slim\Interfaces\Http\EnvironmentInterface
 *    \Slim\Interfaces\Http\EnvironmentInterface 接口的实例
 *  - request: an instance of \Psr\Http\Message\ServerRequestInterface
 *  - response: an instance of \Psr\Http\Message\ResponseInterface
 *  - router: an instance of \Slim\Interfaces\RouterInterface
 *  - foundHandler: an instance of \Slim\Interfaces\InvocationStrategyInterface
 *  - errorHandler: a callable with the signature: function($request, $response, $exception)
 *    制订了函数签名的回调
 *  - notFoundHandler: a callable with the signature: function($request, $response)
 *  - notAllowedHandler: a callable with the signature: function($request, $response, $allowedHttpMethods)
 *  - callableResolver: an instance of callableResolver
 *
 * @property-read array settings
 * @property-read \Slim\Interfaces\Http\EnvironmentInterface environment
 * @property-read \Psr\Http\Message\ServerRequestInterface request
 * @property-read \Psr\Http\Message\ResponseInterface response
 * @property-read \Slim\Interfaces\RouterInterface router
 * @property-read \Slim\Interfaces\InvocationStrategyInterface foundHandler
 * @property-read callable errorHandler
 * @property-read callable notFoundHandler
 * @property-read callable notAllowedHandler
 * @property-read \Slim\Interfaces\CallableResolverInterface callableResolver
 */
class Container extends PimpleContainer implements ContainerInterface
{
    /**
     * Default settings
     * 默认设置
     *
     * @var array
     */
    private $defaultSettings = [
        'httpVersion' => '1.1',
        'responseChunkSize' => 4096,
        'outputBuffering' => 'append',
        'determineRouteBeforeAppMiddleware' => false,
        'displayErrorDetails' => false,
    ];

    /**
     * Create new container
     * 创建新的容器
     *
     * @param array $values The parameters or objects.
     */
    public function __construct(array $values = [])
    {
        parent::__construct($values);

        $userSettings = isset($values['settings']) ? $values['settings'] : [];
        $this->registerDefaultServices($userSettings);
    }

    /**
     * This function registers the default services that Slim needs to work.
     * 本方法注册默认服务，以及做一些框架必要的工作
     *
     * All services are shared - that is, they are registered such that the
     * same instance is returned on subsequent calls.
     * 所有的服务都是分享的，因此，一次注册，多次调用返回的都是一个实例
     *
     * @param array $userSettings Associative array of application settings
     *
     * @return void
     */
    private function registerDefaultServices($userSettings)
    {
        $defaultSettings = $this->defaultSettings;

        /**
         * This service MUST return an array or an
         * instance of \ArrayAccess.
         * 本服务必须返回数组或\ArrayAccess实例
         * 匿名函数使用传入配置覆盖合并了默认配置
         *
         * @return array|\ArrayAccess
         */
        $this['settings'] = function () use ($userSettings, $defaultSettings) {
            return new Collection(array_merge($defaultSettings, $userSettings));
        };

        if (!isset($this['environment'])) {
            /**
             * This service MUST return a shared instance
             * of \Slim\Interfaces\Http\EnvironmentInterface.
             * 单例 指定类实例
             *
             * @return EnvironmentInterface
             */
            $this['environment'] = function () {
                return new Environment($_SERVER);
            };
        }

        if (!isset($this['request'])) {
            /**
             * PSR-7 Request object
             * PSR-7标准的请求对象
             *
             * @param Container $c
             *
             * @return ServerRequestInterface
             */
            $this['request'] = function ($c) {
                return Request::createFromEnvironment($c->get('environment'));
            };
        }

        if (!isset($this['response'])) {
            /**
             * PSR-7 Response object
             * PSR-7标准的响应对象
             *
             * @param Container $c
             *
             * @return ResponseInterface
             */
            $this['response'] = function ($c) {
                $headers = new Headers(['Content-Type' => 'text/html; charset=UTF-8']);
                $response = new Response(200, $headers);

                return $response->withProtocolVersion($c->get('settings')['httpVersion']);
            };
        }

        if (!isset($this['router'])) {
            /**
             * This service MUST return a SHARED instance
             * of \Slim\Interfaces\RouterInterface.
             * 单例，指定接口实例
             *
             * @return RouterInterface
             */
            $this['router'] = function () {
                return new Router;
            };
        }

        if (!isset($this['foundHandler'])) {
            /**
             * This service MUST return a SHARED instance
             * of \Slim\Interfaces\InvocationStrategyInterface.
             * 单例，指定接口实例
             *
             * @return InvocationStrategyInterface
             */
            $this['foundHandler'] = function () {
                return new RequestResponse;
            };
        }

        if (!isset($this['errorHandler'])) {
            /**
             * This service MUST return a callable
             * that accepts three arguments:
             * 接受以下三个参数的回调
             *
             * 1. Instance of \Psr\Http\Message\ServerRequestInterface
             * 2. Instance of \Psr\Http\Message\ResponseInterface
             * 3. Instance of \Exception
             *
             * The callable MUST return an instance of
             * \Psr\Http\Message\ResponseInterface.
             * 指定接口实例
             *
             * @param Container $c
             *
             * @return callable
             */
            $this['errorHandler'] = function ($c) {
                return new Error($c->get('settings')['displayErrorDetails']);
            };
        }

        if (!isset($this['notFoundHandler'])) {
            /**
             * This service MUST return a callable
             * that accepts two arguments:
             * 必须是包含以下两个参数的回调
             *
             * 1. Instance of \Psr\Http\Message\ServerRequestInterface
             * 2. Instance of \Psr\Http\Message\ResponseInterface
             *
             * The callable MUST return an instance of
             * \Psr\Http\Message\ResponseInterface.
             * 指定接口实例
             *
             * @return callable
             */
            $this['notFoundHandler'] = function () {
                return new NotFound;
            };
        }

        if (!isset($this['notAllowedHandler'])) {
            /**
             * This service MUST return a callable
             * that accepts three arguments:
             * 必须是接受以下三种参数的回调
             *
             * 1. Instance of \Psr\Http\Message\ServerRequestInterface
             * 2. Instance of \Psr\Http\Message\ResponseInterface
             * 3. Array of allowed HTTP methods
             *
             * The callable MUST return an instance of
             * \Psr\Http\Message\ResponseInterface.
             * 指定接口的实例
             *
             * @return callable
             */
            $this['notAllowedHandler'] = function () {
                return new NotAllowed;
            };
        }

        if (!isset($this['callableResolver'])) {
            /**
             * Instance of \Slim\Interfaces\CallableResolverInterface
             * 指定接口的实例
             *
             * @param Container $c
             *
             * @return CallableResolverInterface
             */
            $this['callableResolver'] = function ($c) {
                return new CallableResolver($c);
            };
        }
    }

    /********************************************************************************
     * Methods to satisfy Interop\Container\ContainerInterface
     *******************************************************************************/

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws ContainerValueNotFoundException  No entry was found for this identifier.
     * @throws ContainerException               Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public function get($id)
    {
        if (!$this->offsetExists($id)) {
            throw new ContainerValueNotFoundException(sprintf('Identifier "%s" is not defined.', $id));
        }
        return $this->offsetGet($id);
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return boolean
     */
    public function has($id)
    {
        return $this->offsetExists($id);
    }


    /********************************************************************************
     * Magic methods for convenience
     *******************************************************************************/

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __isset($name)
    {
        return $this->has($name);
    }
}
