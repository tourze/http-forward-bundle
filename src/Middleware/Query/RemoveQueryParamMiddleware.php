<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Query;

use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;
use Tourze\HttpForwardBundle\Middleware\ConfigurableMiddlewareInterface;

class RemoveQueryParamMiddleware extends AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    protected int $priority = 80;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        $params = $config['params'] ?? $config;

        if (!is_array($params) || [] === $params) {
            return $request;
        }

        $query = $request->query->all();

        foreach ($params as $param) {
            if (is_string($param)) {
                unset($query[$param]);
            }
        }

        $request->query->replace($query);

        return $request;
    }

    public static function getServiceAlias(): string
    {
        return 'remove_query_param';
    }

    public static function getConfigTemplate(): array
    {
        return [
            'label' => '移除查询参数',
            'description' => '从URL中移除指定的查询参数',
            'priority' => 80,
            'fields' => [
                'params' => [
                    'type' => 'array',
                    'label' => '参数名称',
                    'default' => [],
                    'required' => true,
                ],
            ],
        ];
    }
}
