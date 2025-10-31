<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Builtin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;

class HeaderTransformMiddleware extends AbstractMiddleware
{
    protected int $priority = 90;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        $add = $this->ensureStringArray($config['add'] ?? []);
        $remove = $this->ensureStringList($config['remove'] ?? []);
        $rename = $this->ensureStringArray($config['rename'] ?? []);

        foreach ($add as $header => $value) {
            $request->headers->set($header, $value);
        }

        foreach ($remove as $header) {
            $request->headers->remove($header);
        }

        foreach ($rename as $oldHeader => $newHeader) {
            if ($request->headers->has($oldHeader)) {
                $value = $request->headers->get($oldHeader);
                $request->headers->set($newHeader, $value ?? '');
                $request->headers->remove($oldHeader);
            }
        }

        return $request;
    }

    public function processResponse(Response $response, array $config = []): Response
    {
        $addResponse = $this->ensureStringArray($config['add_response'] ?? []);
        $removeResponse = $this->ensureStringList($config['remove_response'] ?? []);
        $renameResponse = $this->ensureStringArray($config['rename_response'] ?? []);

        foreach ($addResponse as $header => $value) {
            $response->headers->set($header, $value);
        }

        foreach ($removeResponse as $header) {
            $response->headers->remove($header);
        }

        foreach ($renameResponse as $oldHeader => $newHeader) {
            if ($response->headers->has($oldHeader)) {
                $value = $response->headers->get($oldHeader);
                $response->headers->set($newHeader, $value ?? '');
                $response->headers->remove($oldHeader);
            }
        }

        return $response;
    }

    /**
     * @param mixed $value
     * @return array<string, string>
     */
    private function ensureStringArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $val) {
            $stringKey = is_string($key) ? $key : '';
            $stringVal = is_string($val) ? $val : '';
            if ('' !== $stringKey) {
                $result[$stringKey] = $stringVal;
            }
        }

        return $result;
    }

    /**
     * @param mixed $value
     * @return array<string>
     */
    private function ensureStringList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map(static fn ($v): string => is_string($v) ? $v : '', $value);
    }

    public static function getServiceAlias(): string
    {
        return 'header_transform';
    }
}
