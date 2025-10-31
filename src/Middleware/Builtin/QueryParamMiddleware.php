<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Builtin;

use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;

class QueryParamMiddleware extends AbstractMiddleware
{
    protected int $priority = 80;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        $queryParams = $this->prepareQueryParamConfigs($config);
        /** @var array<string, mixed> $queryAll */
        $queryAll = $request->query->all();
        $query = $this->processQueryParams($queryAll, $queryParams);
        $request->query->replace($query);

        return $request;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{add: array<mixed, mixed>, remove: array<mixed>, override: array<mixed, mixed>}
     */
    private function prepareQueryParamConfigs(array $config): array
    {
        return [
            'add' => is_array($config['add'] ?? null) ? $config['add'] : [],
            'remove' => is_array($config['remove'] ?? null) ? $config['remove'] : [],
            'override' => is_array($config['override'] ?? null) ? $config['override'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @param array{add: array<mixed, mixed>, remove: array<mixed>, override: array<mixed, mixed>} $queryParams
     * @return array<string, mixed>
     */
    private function processQueryParams(array $query, array $queryParams): array
    {
        $query = $this->addQueryParams($query, $queryParams['add']);
        $query = $this->removeQueryParams($query, $queryParams['remove']);

        return $this->overrideQueryParams($query, $queryParams['override']);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<mixed, mixed> $add
     * @return array<string, mixed>
     */
    private function addQueryParams(array $query, array $add): array
    {
        foreach ($add as $param => $value) {
            if ($this->isValidParamKey($param)) {
                $stringParam = $this->convertToStringKey($param);
                if (!isset($query[$stringParam])) {
                    $query[$stringParam] = $value;
                }
            }
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<mixed> $remove
     * @return array<string, mixed>
     */
    private function removeQueryParams(array $query, array $remove): array
    {
        foreach ($remove as $param) {
            if (!$this->isValidParamKey($param)) {
                continue;
            }

            $stringParam = $this->convertToStringKey($param);
            unset($query[$stringParam]);
        }

        return $query;
    }

    /**
     * 将mixed类型的键转换为字符串
     *
     * @param mixed $key
     * @return string
     */
    private function convertToStringKey(mixed $key): string
    {
        if (is_string($key)) {
            return $key;
        }

        if (is_int($key)) {
            return (string) $key;
        }

        // 这里不应该到达，因为isValidParamKey已经验证过
        return '';
    }

    /**
     * @param array<string, mixed> $query
     * @param array<mixed, mixed> $override
     * @return array<string, mixed>
     */
    private function overrideQueryParams(array $query, array $override): array
    {
        foreach ($override as $param => $value) {
            if ($this->isValidParamKey($param)) {
                $stringParam = $this->convertToStringKey($param);
                $query[$stringParam] = $value;
            }
        }

        return $query;
    }

    private function isValidParamKey(mixed $param): bool
    {
        return is_string($param) || is_int($param);
    }

    public static function getServiceAlias(): string
    {
        return 'query_param';
    }
}
