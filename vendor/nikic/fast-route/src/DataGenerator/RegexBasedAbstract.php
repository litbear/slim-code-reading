<?php

namespace FastRoute\DataGenerator;

use FastRoute\DataGenerator;
use FastRoute\BadRouteException;
use FastRoute\Route;

/**
 * 基于正则表达式的数据生成器接口
 */
abstract class RegexBasedAbstract implements DataGenerator {
    /**
     * 储存静态路由数据
     * @var Array $staticRoutes
     */
    protected $staticRoutes = [];
    protected $methodToRegexToRoutesMap = [];

    /**
     * 获取大概的分块大小
     */
    protected abstract function getApproxChunkSize();
    /**
     * 处理分块
     */
    protected abstract function processChunk($regexToRoutesMap);

    /**
     * 将路由规则加入数据生成器
     * 
     * @param type $httpMethod
     * @param type $routeData
     * @param type $handler
     */
    public function addRoute($httpMethod, $routeData, $handler) {
        if ($this->isStaticRoute($routeData)) {
            $this->addStaticRoute($httpMethod, $routeData, $handler);
        } else {
            $this->addVariableRoute($httpMethod, $routeData, $handler);
        }
    }

    public function getData() {
        if (empty($this->methodToRegexToRoutesMap)) {
            return [$this->staticRoutes, []];
        }

        return [$this->staticRoutes, $this->generateVariableRouteData()];
    }

    private function generateVariableRouteData() {
        $data = [];
        foreach ($this->methodToRegexToRoutesMap as $method => $regexToRoutesMap) {
            $chunkSize = $this->computeChunkSize(count($regexToRoutesMap));
            $chunks = array_chunk($regexToRoutesMap, $chunkSize, true);
            $data[$method] =  array_map([$this, 'processChunk'], $chunks);
        }
        return $data;
    }

    private function computeChunkSize($count) {
        $numParts = max(1, round($count / $this->getApproxChunkSize()));
        return ceil($count / $numParts);
    }

    /**
     * 判断路由规则是否是静态的
     * @param type $routeData
     * @return type
     */
    private function isStaticRoute($routeData) {
        // 路由数据的元素个数为1 且 路由数据的唯一元素直是字符串类型
        return count($routeData) == 1 && is_string($routeData[0]);
    }

    /**
     * 加入静态路由规则
     * 
     * @param type $httpMethod
     * @param type $routeData
     * @param type $handler
     * @throws BadRouteException
     */
    private function addStaticRoute($httpMethod, $routeData, $handler) {
        // 取出路由数据的唯一元素值，类型为字符串
        $routeStr = $routeData[0];

        // 如发现已经注册过了，则抛出异常
        if (isset($this->staticRoutes[$httpMethod][$routeStr])) {
            throw new BadRouteException(sprintf(
                'Cannot register two routes matching "%s" for method "%s"',
                $routeStr, $httpMethod
            ));
        }

        if (isset($this->methodToRegexToRoutesMap[$httpMethod])) {
            foreach ($this->methodToRegexToRoutesMap[$httpMethod] as $route) {
                if ($route->matches($routeStr)) {
                    throw new BadRouteException(sprintf(
                        'Static route "%s" is shadowed by previously defined variable route "%s" for method "%s"',
                        $routeStr, $route->regex, $httpMethod
                    ));
                }
            }
        }

        $this->staticRoutes[$httpMethod][$routeStr] = $handler;
    }

    private function addVariableRoute($httpMethod, $routeData, $handler) {
        list($regex, $variables) = $this->buildRegexForRoute($routeData);

        if (isset($this->methodToRegexToRoutesMap[$httpMethod][$regex])) {
            throw new BadRouteException(sprintf(
                'Cannot register two routes matching "%s" for method "%s"',
                $regex, $httpMethod
            ));
        }

        $this->methodToRegexToRoutesMap[$httpMethod][$regex] = new Route(
            $httpMethod, $handler, $regex, $variables
        );
    }

    private function buildRegexForRoute($routeData) {
        $regex = '';
        $variables = [];
        foreach ($routeData as $part) {
            if (is_string($part)) {
                $regex .= preg_quote($part, '~');
                continue;
            }

            list($varName, $regexPart) = $part;

            if (isset($variables[$varName])) {
                throw new BadRouteException(sprintf(
                    'Cannot use the same placeholder "%s" twice', $varName
                ));
            }

            if ($this->regexHasCapturingGroups($regexPart)) {
                throw new BadRouteException(sprintf(
                    'Regex "%s" for parameter "%s" contains a capturing group',
                    $regexPart, $varName
                ));
            }

            $variables[$varName] = $varName;
            $regex .= '(' . $regexPart . ')';
        }

        return [$regex, $variables];
    }

    private function regexHasCapturingGroups($regex) {
        if (false === strpos($regex, '(')) {
            // Needs to have at least a ( to contain a capturing group
            return false;
        }

        // Semi-accurate detection for capturing groups
        return preg_match(
            '~
                (?:
                    \(\?\(
                  | \[ [^\]\\\\]* (?: \\\\ . [^\]\\\\]* )* \]
                  | \\\\ .
                ) (*SKIP)(*FAIL) |
                \(
                (?!
                    \? (?! <(?![!=]) | P< | \' )
                  | \*
                )
            ~x',
            $regex
        );
    }
}
