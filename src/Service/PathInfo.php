<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Service;

final class PathInfo
{
    public function __construct(
        private readonly string $originalPath,
    ) {
    }

    public function getOriginalPath(): string
    {
        return $this->originalPath;
    }

    public function stripPrefix(string $sourcePath): string
    {
        if (str_starts_with($sourcePath, '^')) {
            return $this->stripRegexPrefix($sourcePath);
        }

        return $this->stripStringPrefix($sourcePath);
    }

    /**
     * @return array<string, string>
     */
    public function extractParameters(string $sourcePath): array
    {
        if (0 === preg_match_all('/{(\w+)}/', $sourcePath, $matches)) {
            return [];
        }

        $pattern = $this->buildRegexPattern($sourcePath, $matches[1]);

        if (0 === preg_match('#^' . $pattern . '$#', $this->originalPath, $values)) {
            return [];
        }

        return $this->extractNamedGroups($matches[1], $values);
    }

    private function stripRegexPrefix(string $pattern): string
    {
        $regex = '/' . $pattern . '/';

        return preg_replace($regex, '', $this->originalPath, 1) ?? $this->originalPath;
    }

    private function stripStringPrefix(string $prefix): string
    {
        return substr($this->originalPath, strlen($prefix));
    }

    /**
     * @param array<string> $paramNames
     */
    private function buildRegexPattern(string $sourcePath, array $paramNames): string
    {
        $pattern = $sourcePath;
        foreach ($paramNames as $param) {
            $pattern = str_replace(
                '{' . $param . '}',
                '(?P<' . $param . '>[^/]+)',
                $pattern
            );
        }

        return $pattern;
    }

    /**
     * @param array<string> $paramNames
     * @param array<int|string, string> $matches
     * @return array<string, string>
     */
    private function extractNamedGroups(array $paramNames, array $matches): array
    {
        $parameters = [];
        foreach ($paramNames as $param) {
            if (isset($matches[$param])) {
                $parameters[$param] = $matches[$param];
            }
        }

        return $parameters;
    }
}
