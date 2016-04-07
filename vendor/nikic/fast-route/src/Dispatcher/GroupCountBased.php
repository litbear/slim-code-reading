<?php

namespace FastRoute\Dispatcher;

class GroupCountBased extends RegexBasedAbstract {
    /**
     * 实例化调度器，注入来自数据生成器的数据
     * 
     * @param Array $data RouteCollector::getData()方法的结果
     * 最终来自于DataGenerator::getData()
     */
    public function __construct($data) {
        list($this->staticRouteMap, $this->variableRouteData) = $data;
    }

    protected function dispatchVariableRoute($routeData, $uri) {
        foreach ($routeData as $data) {
            if (!preg_match($data['regex'], $uri, $matches)) {
                continue;
            }

            list($handler, $varNames) = $data['routeMap'][count($matches)];

            $vars = [];
            $i = 0;
            // 为每个占位符分配变量
            foreach ($varNames as $varName) {
                $vars[$varName] = $matches[++$i];
            }
            return [self::FOUND, $handler, $vars];
        }

        return [self::NOT_FOUND];
    }
}
