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
    /**
     * 储存带占位符的动态路由数据
     * @var Array $methodToRegexToRoutesMap
     */
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
        // 静态路由的路由数据是具有一个元素，且元素值为字符串的数组
        if ($this->isStaticRoute($routeData)) {
            $this->addStaticRoute($httpMethod, $routeData, $handler);
        } else {
            $this->addVariableRoute($httpMethod, $routeData, $handler);
        }
    }

    /**
     * 获取全部路由数据
     * 
     * @return Array
     */
    public function getData() {
        // 如果占位符路由为空
        if (empty($this->methodToRegexToRoutesMap)) {
            return [$this->staticRoutes, []];
        }

        return [$this->staticRoutes, $this->generateVariableRouteData()];
    }

    /**
     * 生成占位符(动态)路由数据
     * 
     * @return Array
     */
    private function generateVariableRouteData() {
        $data = [];
        foreach ($this->methodToRegexToRoutesMap as $method => $regexToRoutesMap) {
            $chunkSize = $this->computeChunkSize(count($regexToRoutesMap));
            // 第三个参数为true，索引计数不间断
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
        // 路由数据的元素个数为1 且 路由数据的唯一元素值是字符串类型
        return count($routeData) == 1 && is_string($routeData[0]);
    }

    /**
     * 加入静态路由规则，即没有占位符的
     * 
     * @param String $httpMethod HTTP方法
     * @param Array $routeData 静态规则路由数据，
     * 本参数只有一个元素且元素值类型为字符串
     * @param Mixed $handler PHP回调
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

    /**
     * 添加可变路由规则，即有占位符的
     * 
     * @param String $httpMethod HTTP方法
     * @param Array $routeData 动态路由规则，数组
     * @param Mixed $handler PHP回调
     * @throws BadRouteException
     */
    private function addVariableRoute($httpMethod, $routeData, $handler) {
        // 为路由规则构建正则表达式
        list($regex, $variables) = $this->buildRegexForRoute($routeData);

        // 冲突会触发异常
        if (isset($this->methodToRegexToRoutesMap[$httpMethod][$regex])) {
            throw new BadRouteException(sprintf(
                'Cannot register two routes matching "%s" for method "%s"',
                $regex, $httpMethod
            ));
        }

        // 使用Route实例包裹
        $this->methodToRegexToRoutesMap[$httpMethod][$regex] = new Route(
            $httpMethod, $handler, $regex, $variables
        );
    }

    /**
     * 为路由规则构建正则表达式
     * 
     * @param type $routeData
     * @return type
     * @throws BadRouteException
     */
    private function buildRegexForRoute($routeData) {
        $regex = '';
        $variables = [];
        // 便利路由规则数组
        foreach ($routeData as $part) {
            // 如果是字符串，说明是路由文本
            if (is_string($part)) {
                $regex .= preg_quote($part, '~');
                continue;
            }

            // 否则，说明是变量映射 ：
            // 变量名=>变量必须匹配的正则表达式
            list($varName, $regexPart) = $part;

            // 同一组中，如果同名变量占位符会引发异常
            if (isset($variables[$varName])) {
                throw new BadRouteException(sprintf(
                    'Cannot use the same placeholder "%s" twice', $varName
                ));
            }

            // 判断正则表达式是否含有捕获组，
            // 有捕获组会引发异常
            if ($this->regexHasCapturingGroups($regexPart)) {
                throw new BadRouteException(sprintf(
                    'Regex "%s" for parameter "%s" contains a capturing group',
                    $regexPart, $varName
                ));
            }

            // 组成变量名 => 变量名 形式的数组
            $variables[$varName] = $varName;
            // 将路由文本中的占位符拼接为捕获组
            // /users/aaa/bbb/ccc_(bar)&&&_(123)
            $regex .= '(' . $regexPart . ')';
        }

        return [$regex, $variables];
    }

    /**
     * 
     * @param type $regex
     * @return int $count 匹配次数
     */
    private function regexHasCapturingGroups($regex) {
        // 先看有没有括号，如果没有括号，
        // 当然不含捕获组，提高效率
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
