<?php

namespace FastRoute;

interface RouteParser {
    /**
     * Parses a route string into multiple route data arrays.
     * 将路由规则字符串 解析为多个路由数据数组
     *
     * The expected output is defined using an example:
     * 期望输出由下文示例定义
     *
     * For the route string "/fixedRoutePart/{varName}[/moreFixed/{varName2:\d+}]", if {varName} is interpreted as
     * a placeholder and [...] is interpreted as an optional route part, the expected result is:
     * 对应路由字符串"/fixedRoutePart/{varName}[/moreFixed/{varName2:\d+}]"，如果将{varName}看作占位符，并且将
     * [...]看作规则的可选项，则期望输出结果为：
     *
     * [
     *     // first route: without optional part
     *     // 第一条路由规则：不带可选项 
     *     [
     *         "/fixedRoutePart/",
     *         ["varName", "[^/]+"],
     *     ],
     *     // second route: with optional part
     *     // 第二条路由规则：带可选项 
     *     [
     *         "/fixedRoutePart/",
     *         ["varName", "[^/]+"],
     *         "/moreFixed/",
     *         ["varName2", [0-9]+"],
     *     ],
     * ]
     *
     * Here one route string was converted into two route data arrays.
     * 在这种情况下，路由规则字符串被转换为两个路由数据数组
     *
     * @param string $route Route string to parse
     * 
     * @return mixed[][] Array of route data arrays
     */
    public function parse($route);
}
