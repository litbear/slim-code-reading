<?php

namespace FastRoute;

if (!function_exists('FastRoute\simpleDispatcher')) {
    /**
     * 简单路由调度器
     * 
     * @param callable $routeDefinitionCallback
     * @param array $options
     *
     * @return Dispatcher
     */
    function simpleDispatcher(callable $routeDefinitionCallback, array $options = []) {
        $options += [
            // 路由解析器
            'routeParser' => 'FastRoute\\RouteParser\\Std',
            // 数据创建器
            'dataGenerator' => 'FastRoute\\DataGenerator\\GroupCountBased',
            // 调度器
            'dispatcher' => 'FastRoute\\Dispatcher\\GroupCountBased',
            // 路由规则收集器
            'routeCollector' => 'FastRoute\\RouteCollector',
        ];

        /** @var RouteCollector $routeCollector */
        // 实例化路由规则收集器
        $routeCollector = new $options['routeCollector'](
            new $options['routeParser'], new $options['dataGenerator']
        );
        // 将规则收集器传入回调函数
        $routeDefinitionCallback($routeCollector);

        // 返回实例化后的
        return new $options['dispatcher']($routeCollector->getData());
    }

    /**
     * 缓存调度器
     * 
     * @param callable $routeDefinitionCallback
     * @param array $options
     *
     * @return Dispatcher
     */
    function cachedDispatcher(callable $routeDefinitionCallback, array $options = []) {
        $options += [
            'routeParser' => 'FastRoute\\RouteParser\\Std',
            'dataGenerator' => 'FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher' => 'FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => 'FastRoute\\RouteCollector',
            'cacheDisabled' => false,
        ];

        if (!isset($options['cacheFile'])) {
            throw new \LogicException('Must specify "cacheFile" option');
        }

        if (!$options['cacheDisabled'] && file_exists($options['cacheFile'])) {
            $dispatchData = require $options['cacheFile'];
            if (!is_array($dispatchData)) {
                throw new \RuntimeException('Invalid cache file "' . $options['cacheFile'] . '"');
            }
            return new $options['dispatcher']($dispatchData);
        }

        $routeCollector = new $options['routeCollector'](
            new $options['routeParser'], new $options['dataGenerator']
        );
        $routeDefinitionCallback($routeCollector);

        /** @var RouteCollector $routeCollector */
        $dispatchData = $routeCollector->getData();
        file_put_contents(
            $options['cacheFile'],
            '<?php return ' . var_export($dispatchData, true) . ';'
        );

        return new $options['dispatcher']($dispatchData);
    }
}
