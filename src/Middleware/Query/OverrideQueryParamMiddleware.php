<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Query;

use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;
use Tourze\HttpForwardBundle\Middleware\ConfigurableMiddlewareInterface;

class OverrideQueryParamMiddleware extends AbstractMiddleware implements ConfigurableMiddlewareInterface
{
    protected int $priority = 80;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        $params = $config['params'] ?? $config;

        if (!is_array($params) || [] === $params) {
            return $request;
        }

        $query = $request->query->all();

        foreach ($params as $param => $value) {
            $query[$param] = $value;
        }

        $request->query->replace($query);

        return $request;
    }

    public static function getServiceAlias(): string
    {
        return 'override_query_param';
    }

    public static function getConfigTemplate(): array
    {
        return [
            'label' => '覆盖查询参数',
            'description' => '覆盖或添加URL查询参数',
            'priority' => 80,
            'fields' => [
                'params' => [
                    'type' => 'collection',
                    'label' => '查询参数',
                    'default' => [],
                    'required' => true,
                ],
            ],
        ];
    }
}
