<?php

namespace FastRoute;

/**
 * 数据生成器接口
 */
interface DataGenerator {
    /**
     * Adds a route to the data generator. The route data uses the
     * same format that is returned by RouterParser::parser().
     * 将路由规则加入数据生成器，路由规则数据使用由RouterParser::parser()
     * 方法返回的相同格式。
     *
     * The handler doesn't necessarily need to be a callable, it
     * can be arbitrary data that will be returned when the route
     * matches.
     * 处理句柄没必要必须调用，当路由规则匹配时，会反回任意数据。
     *
     * @param string $httpMethod
     * @param array $routeData
     * @param mixed $handler
     */
    public function addRoute($httpMethod, $routeData, $handler);

    /**
     * Returns dispatcher data in some unspecified format, which
     * depends on the used method of dispatch.
     * 以某种未指明的格式返回调度数据，该调度数据依赖于调度方法。
     */
    public function getData();
}
