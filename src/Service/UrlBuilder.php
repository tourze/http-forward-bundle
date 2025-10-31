<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardRule;

final class UrlBuilder
{
    public function __construct(
        private readonly LoadBalanceService $loadBalanceService,
    ) {
    }

    public function build(Request $request, ForwardRule $rule): string
    {
        // 使用负载均衡服务选择后端
        $backend = $this->loadBalanceService->selectBackend($rule, $request);

        return $this->buildWithBackend($request, $rule, $backend);
    }

    public function buildWithBackend(Request $request, ForwardRule $rule, Backend $backend): string
    {
        $pathInfo = new PathInfo($request->getPathInfo());

        $targetUrl = $this->buildTargetUrlWithParameters($pathInfo, $rule, $backend);
        $processedPath = $this->processPath($pathInfo, $rule);
        $finalUrl = $this->shouldUseParametrizedUrl($rule)
            ? $targetUrl
            : $this->appendPath($backend->getUrl(), $processedPath);

        return $this->appendQueryString($finalUrl, $request->getQueryString());
    }

    private function processPath(PathInfo $pathInfo, ForwardRule $rule): string
    {
        if (!$rule->isStripPrefix()) {
            return $pathInfo->getOriginalPath();
        }

        return $pathInfo->stripPrefix($rule->getSourcePath());
    }

    private function appendPath(string $targetUrl, string $path): string
    {
        return rtrim($targetUrl, '/') . '/' . ltrim($path, '/');
    }

    private function buildTargetUrlWithParameters(PathInfo $pathInfo, ForwardRule $rule, Backend $backend): string
    {
        $parameters = $pathInfo->extractParameters($rule->getSourcePath());
        $targetUrl = $backend->getUrl();

        foreach ($parameters as $name => $value) {
            $targetUrl = str_replace('{' . $name . '}', $value, $targetUrl);
        }

        return $targetUrl;
    }

    private function shouldUseParametrizedUrl(ForwardRule $rule): bool
    {
        return str_contains($rule->getSourcePath(), '{');
    }

    private function appendQueryString(string $url, ?string $queryString): string
    {
        if (null === $queryString || '' === $queryString) {
            return $url;
        }

        return $url . '?' . $queryString;
    }
}
