<?php

namespace FastRoute;

interface RouteParser {
    /**
     * Parses a route string into multiple route data arrays.
     * 将路由规则字符串解析为多个路由数据数组
     *
     * The expected output is defined using an example:
     * 本函数的期待输出为：
     *
     * For the route string "/fixedRoutePart/{varName}[/moreFixed/{varName2:\d+}]", if {varName} is interpreted as
     * a placeholder and [...] is interpreted as an optional route part, the expected result is:
     * 对于路由规则字符串"/fixedRoutePart/{varName}[/moreFixed/{varName2:\d+}]"
     * 假如{varName}被看作是占位符，同时[...]被看作是路由规则的可选部分，则期待输出为：
     *
     * [
     *     // first route: without optional part
     *     // 第一路由规则：不含可选部分：
     *     [
     *         "/fixedRoutePart/",
     *         ["varName", "[^/]+"],
     *     ],
     *     // second route: with optional part
     *     // 第二路由规则：包含可选部分
     *     [
     *         "/fixedRoutePart/",
     *         ["varName", "[^/]+"],
     *         "/moreFixed/",
     *         ["varName2", [0-9]+"],
     *     ],
     * ]
     *
     * Here one route string was converted into two route data arrays.
     * 在这里，一条路由规则字符串会被转换为两个路由数据数组
     *
     * @param string $route Route string to parse
     * 
     * @return mixed[][] Array of route data arrays
     */
    public function parse($route);
}
