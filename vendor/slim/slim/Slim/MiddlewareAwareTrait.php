<?php
/**
 * Slim Framework (http://slimframework.com)
 *
 * @link      https://github.com/slimphp/Slim
 * @copyright Copyright (c) 2011-2016 Josh Lockhart
 * @license   https://github.com/slimphp/Slim/blob/3.x/LICENSE.md (MIT License)
 */
namespace Slim;

use RuntimeException;
use SplStack;
use SplDoublyLinkedList;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

/**
 * Middleware
 * 中间件
 *
 * This is an internal class that enables concentric middleware layers. This
 * class is an implementation detail and is used only inside of the Slim
 * application; it is not visible to—and should not be used by—end users.
 * 本trait是一个开启同心圆中间件的内部类。本类用以实现同心圆层的细节，并且
 * 应用于slim应用的内部，本类对外不可见也不应该被用户使用
 */
trait MiddlewareAwareTrait
{
    /**
     * Middleware call stack
     *
     * @var  \SplStack
     * @link http://php.net/manual/class.splstack.php
     */
    protected $stack;

    /**
     * Middleware stack lock
     *
     * @var bool
     */
    protected $middlewareLock = false;

    /**
     * Add middleware
     * 添加中间件
     *
     * This method prepends new middleware to the application middleware stack.
     * 
     *
     * @param callable $callable Any callable that accepts three arguments:
     *                           1. A Request object
     *                           2. A Response object
     *                           3. A "next" middleware callable
     * @return static
     *
     * @throws RuntimeException         If middleware is added while the stack is dequeuing
     * @throws UnexpectedValueException If the middleware doesn't return a Psr\Http\Message\ResponseInterface
     */
    protected function addMiddleware(callable $callable)
    {
        if ($this->middlewareLock) {
            throw new RuntimeException('Middleware can’t be added once the stack is dequeuing');
        }

        // 初始化栈
        if (is_null($this->stack)) {
            $this->seedMiddlewareStack();
        }
        // 栈顶，最后放进去的
        $next = $this->stack->top();
        $this->stack[] = function (ServerRequestInterface $req, ResponseInterface $res) use ($callable, $next) {
            $result = call_user_func($callable, $req, $res, $next);
            // 如果返回值不是一个响应对象，则抛出异常
            if ($result instanceof ResponseInterface === false) {
                throw new UnexpectedValueException(
                    'Middleware must return instance of \Psr\Http\Message\ResponseInterface'
                );
            }

            return $result;
        };

        return $this;
    }

    /**
     * Seed middleware stack with first callable
     * 组织中间件堆栈以备首次调用 初始化栈
     *
     * @param callable $kernel The last item to run as middleware
     *
     * @throws RuntimeException if the stack is seeded more than once
     */
    protected function seedMiddlewareStack(callable $kernel = null)
    {
        if (!is_null($this->stack)) {
            throw new RuntimeException('MiddlewareStack can only be seeded once.');
        }
        if ($kernel === null) {
            $kernel = $this;
        }
        $this->stack = new SplStack;
        // 栈风格，后进先出
        $this->stack->setIteratorMode(SplDoublyLinkedList::IT_MODE_LIFO | SplDoublyLinkedList::IT_MODE_KEEP);
        $this->stack[] = $kernel;
    }

    /**
     * Call middleware stack
     * 调用中间件堆栈
     *
     * @param  ServerRequestInterface $req A request object
     * @param  ResponseInterface      $res A response object
     *
     * @return ResponseInterface
     */
    public function callMiddlewareStack(ServerRequestInterface $req, ResponseInterface $res)
    {
        if (is_null($this->stack)) {
            $this->seedMiddlewareStack();
        }
        /** @var callable $start */
        // 从栈的顶部元素，也就是最后放进去的元素开始执行
        $start = $this->stack->top();
        $this->middlewareLock = true;
        $resp = $start($req, $res);
        $this->middlewareLock = false;
        return $resp;
    }
}
