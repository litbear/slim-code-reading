<?php

namespace FastRoute;

interface Dispatcher {
    const NOT_FOUND = 0;
    const FOUND = 1;
    const METHOD_NOT_ALLOWED = 2;

    /**
     * Dispatches against the provided HTTP method verb and URI.
     * 调度器类——通过给定的HTTP方法和URI进行调度
     *
     * Returns array with one of the following formats:
     * 返回的数组格式为以下其中之一
     *
     *     # 未找到匹配规则
     *     [self::NOT_FOUND]
     *     # 找到规则，但请求方法不匹配
     *     [self::METHOD_NOT_ALLOWED, ['GET', 'OTHER_ALLOWED_METHODS']]
     *     # 找到符合的规则
     *     [self::FOUND, $handler, ['varName' => 'value', ...]]
     *
     * @param string $httpMethod
     * @param string $uri
     *
     * @return array
     */
    public function dispatch($httpMethod, $uri);
}
