<?php

namespace FastRoute;

/**
 * 路由规则收集器接口
 */
class RouteCollector {
    private $routeParser;
    private $dataGenerator;

    /**
     * Constructs a route collector.
     * 实例化路由规则收集器
     *
     * @param RouteParser   $routeParser
     * @param DataGenerator $dataGenerator
     */
    public function __construct(RouteParser $routeParser, DataGenerator $dataGenerator) {
        $this->routeParser = $routeParser;
        $this->dataGenerator = $dataGenerator;
    }

    /**
     * Adds a route to the collection.
     * 将路由规则加入集合
     *
     * The syntax used in the $route string depends on the used route parser.
     * 路由规则的语法取决于使用的路由解析器
     *
     * @param string|string[] $httpMethod
     * @param string $route
     * @param mixed  $handler
     */
    public function addRoute($httpMethod, $route, $handler) {
        $routeDatas = $this->routeParser->parse($route);
        foreach ((array) $httpMethod as $method) {
            foreach ($routeDatas as $routeData) {
                $this->dataGenerator->addRoute($method, $routeData, $handler);
            }
        }
    }

    /**
     * Returns the collected route data, as provided by the data generator.
     * 返回数据生成器提供的，已经收集的路由数据
     *
     * @return array
     */
    public function getData() {
        return $this->dataGenerator->getData();
    }
}
