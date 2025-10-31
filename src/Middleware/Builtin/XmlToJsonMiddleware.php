<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Middleware\Builtin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Middleware\AbstractMiddleware;

class XmlToJsonMiddleware extends AbstractMiddleware
{
    protected int $priority = 50;

    public function processRequest(Request $request, ForwardLog $log, array $config = []): Request
    {
        if (!$this->shouldProcess($config, 'request')) {
            return $request;
        }

        $contentType = $request->headers->get('Content-Type', '') ?? '';
        if (!$this->isXmlContentType($contentType)) {
            return $request;
        }

        // getContent() always returns string with no arguments
        $xmlContent = $request->getContent();
        $json = $this->convertXmlToJson($xmlContent);
        if (null === $json) {
            return $request;
        }

        $request = $request->duplicate(null, null, null, null, null, ['_raw_content' => $json]);
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    public function processResponse(Response $response, array $config = []): Response
    {
        if (!$this->shouldProcess($config, 'response')) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '') ?? '';
        if (!$this->isXmlContentType($contentType)) {
            return $response;
        }

        // getContent() always returns string|false
        $xmlContent = $response->getContent();
        if (false === $xmlContent) {
            return $response;
        }
        $json = $this->convertXmlToJson($xmlContent);
        if (null === $json) {
            return $response;
        }

        $response->setContent($json);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    public static function getServiceAlias(): string
    {
        return 'xml_to_json';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function shouldProcess(array $config, string $direction): bool
    {
        $configDirection = $config['direction'] ?? 'both';

        return in_array($configDirection, [$direction, 'both'], true);
    }

    private function isXmlContentType(string $contentType): bool
    {
        return str_contains($contentType, 'application/xml') || str_contains($contentType, 'text/xml');
    }

    private function convertXmlToJson(string $xmlContent): ?string
    {
        if ('' === $xmlContent) {
            return null;
        }

        try {
            $xml = @simplexml_load_string($xmlContent);
            if (false === $xml) {
                return null;
            }

            $json = json_encode($xml);

            return false !== $json ? $json : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
