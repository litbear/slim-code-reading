<?php

namespace FastRoute\DataGenerator;

class GroupCountBased extends RegexBasedAbstract {
    protected function getApproxChunkSize() {
        return 10;
    }

    /**
     * 处理给定的$chunks中的每一个元素
     * 
     * @param type $regexToRoutesMap
     * @return type
     */
    protected function processChunk($regexToRoutesMap) {
        $routeMap = [];
        $regexes = [];
        $numGroups = 0;
        foreach ($regexToRoutesMap as $regex => $route) {
            $numVariables = count($route->variables);
            $numGroups = max($numGroups, $numVariables);

            $regexes[] = $regex . str_repeat('()', $numGroups - $numVariables);
            $routeMap[$numGroups + 1] = [$route->handler, $route->variables];

            ++$numGroups;
        }

        $regex = '~^(?|' . implode('|', $regexes) . ')$~';
        return ['regex' => $regex, 'routeMap' => $routeMap];
    }
}

