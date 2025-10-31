<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

use Symfony\Component\HttpFoundation\Request;

final class HeaderBuilder
{
    /**
     * @return array<string, string>
     */
    public function buildForwardHeaders(Request $request): array
    {
        return $this->buildForwardHeadersFromArray($request->headers->all());
    }

    /**
     * @param array<string, list<string|null>|string|array<string>> $headerArray
     * @return array<string, string>
     */
    public function buildForwardHeadersFromArray(array $headerArray): array
    {
        $headers = [];

        foreach ($headerArray as $key => $values) {
            if (!$this->shouldSkipHeader($key)) {
                $headers[$key] = $this->normalizeHeaderValue($values);
            }
        }

        // 不请求压缩内容，便于存储和处理
        $headers['Accept-Encoding'] = 'identity';

        return $headers;
    }

    private function shouldSkipHeader(string $key): bool
    {
        $skipHeaders = ['host', 'content-length', 'accept-encoding'];

        return in_array(strtolower($key), $skipHeaders, true);
    }

    /**
     * @param list<string|null>|string|array<string> $values
     */
    private function normalizeHeaderValue($values): string
    {
        if (is_array($values)) {
            $firstValue = reset($values);

            // reset() on list<string|null> returns string|null|false
            return is_string($firstValue) ? $firstValue : '';
        }

        // $values is string here (from type narrowing)
        return $values;
    }
}
