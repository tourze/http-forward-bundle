<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Request;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Repository\ForwardRuleRepository;

#[Autoconfigure(public: true)]
readonly class RuleMatcher
{
    public function __construct(
        private ForwardRuleRepository $ruleRepository,
    ) {
    }

    public function match(Request $request): ?ForwardRule
    {
        $rules = $this->ruleRepository->findEnabledRulesOrderedByPriority();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        foreach ($rules as $rule) {
            if ($this->matchRule($rule, $path, $method, $request)) {
                return $rule;
            }
        }

        return null;
    }

    private function matchRule(ForwardRule $rule, string $path, string $method, Request $request): bool
    {
        if (!in_array($method, $rule->getHttpMethods(), true)) {
            return false;
        }

        if (!$this->matchPath($rule->getSourcePath(), $path)) {
            return false;
        }

        return true;
    }

    private function matchPath(string $pattern, string $path): bool
    {
        if (str_starts_with($pattern, '^')) {
            return (bool) preg_match('/' . $pattern . '/', $path);
        }

        if (str_contains($pattern, '{')) {
            $regexPattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
            $regexPattern = '#^' . $regexPattern . '$#';

            return (bool) preg_match($regexPattern, $path);
        }

        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim($pattern, '*');

            return str_starts_with($path, $prefix);
        }

        return $pattern === $path || str_starts_with($path, $pattern);
    }

    /**
     * @param string $sourcePath
     * @param string $actualPath
     * @return array<string, string>
     */
    public function extractParameters(string $sourcePath, string $actualPath): array
    {
        if (!str_contains($sourcePath, '{')) {
            return [];
        }

        $regexPattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $sourcePath);
        $regexPattern = '#^' . $regexPattern . '$#';

        if (preg_match($regexPattern, $actualPath, $matches) > 0) {
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return $params;
        }

        return [];
    }
}
