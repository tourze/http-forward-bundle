<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\AccessKeyBundle\Entity\AccessKey;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\HttpForwardBundle\Enum\ForwardLogStatus;
use Tourze\HttpForwardBundle\Repository\ForwardLogRepository;

#[ORM\Entity(repositoryClass: ForwardLogRepository::class)]
#[ORM\Table(name: 'http_forward_log', options: ['comment' => 'HTTP转发请求日志表'])]
class ForwardLog implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['comment' => '日志ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ForwardRule::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ForwardRule $rule = null;

    #[IndexColumn]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '请求时间'])]
    #[Assert\NotNull]
    private \DateTimeImmutable $requestTime;

    #[ORM\Column(type: Types::STRING, length: 10, options: ['comment' => 'HTTP方法'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    #[Assert\Choice(choices: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'])]
    private string $method = '';

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '请求路径'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private string $path = '';

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '目标URL'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    #[Assert\Url]
    private string $targetUrl = '';

    /**
     * @var array<string, string|array<string>>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '原始请求头'])]
    #[Assert\Type(type: 'array')]
    private ?array $originalRequestHeaders = null;

    /**
     * @var array<string, string|array<string>>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '处理后请求头'])]
    #[Assert\Type(type: 'array')]
    private ?array $processedRequestHeaders = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '请求体'])]
    #[Assert\Length(max: 65535)]
    private ?string $requestBody = null;

    #[IndexColumn]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '响应状态码'])]
    #[Assert\Range(min: 0, max: 999)]
    private int $responseStatus = 0;

    /**
     * @var array<string, string|array<string>>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '响应头'])]
    #[Assert\Type(type: 'array')]
    private ?array $responseHeaders = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '响应体'])]
    #[Assert\Length(max: 65535)]
    private ?string $responseBody = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '响应时间(毫秒)'])]
    #[Assert\PositiveOrZero]
    private int $durationMs = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '使用的重试次数'])]
    #[Assert\PositiveOrZero]
    private int $retryCountUsed = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否使用了降级'])]
    #[Assert\NotNull]
    private bool $fallbackUsed = false;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '错误信息'])]
    #[Assert\Length(max: 65535)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true, options: ['comment' => '客户端IP'])]
    #[Assert\Length(max: 45)]
    #[Assert\Ip]
    private ?string $clientIp = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '用户代理'])]
    #[Assert\Length(max: 500)]
    private ?string $userAgent = null;

    #[ORM\ManyToOne(targetEntity: AccessKey::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AccessKey $accessKey = null;

    #[ORM\Column(type: Types::STRING, enumType: ForwardLogStatus::class, options: ['default' => 'pending', 'comment' => '转发状态'])]
    #[Assert\Choice(callback: [ForwardLogStatus::class, 'cases'])]
    private ForwardLogStatus $status = ForwardLogStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '请求发送时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $sendTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '首字节时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $firstByteTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '完成时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $completeTime = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '网络延迟(毫秒)'])]
    #[Assert\PositiveOrZero]
    private ?int $latencyMs = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '下载耗时(毫秒)'])]
    #[Assert\PositiveOrZero]
    private ?int $downloadMs = null;

    #[ORM\ManyToOne(targetEntity: Backend::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Backend $backend = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '后端名称快照'])]
    #[Assert\Length(max: 255)]
    private ?string $backendName = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true, options: ['comment' => '后端URL快照'])]
    #[Assert\Length(max: 500)]
    private ?string $backendUrl = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '负载均衡策略'])]
    #[Assert\Length(max: 50)]
    private ?string $loadBalanceStrategy = null;

    /**
     * @var array<array<string, mixed>>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '转发时可用的后端列表'])]
    #[Assert\Type(type: 'array')]
    private ?array $availableBackends = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '规则名称快照'])]
    #[Assert\Length(max: 255)]
    private ?string $ruleName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '规则源路径快照'])]
    #[Assert\Length(max: 255)]
    private ?string $ruleSourcePath = null;

    /**
     * @var array<array<string, mixed>>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '使用的中间件配置快照'])]
    #[Assert\Type(type: 'array')]
    private ?array $ruleMiddlewares = null;

    /**
     * @var array<array<string, mixed>>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '重试详情'])]
    #[Assert\Type(type: 'array')]
    private ?array $retryDetails = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '降级详情'])]
    #[Assert\Type(type: 'array')]
    private ?array $fallbackDetails = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '后端响应时间(毫秒)'])]
    #[Assert\PositiveOrZero]
    private ?int $backendResponseTime = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '总处理时间(毫秒)'])]
    #[Assert\PositiveOrZero]
    private ?int $totalProcessingTime = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['comment' => '分布式追踪ID'])]
    #[Assert\Length(max: 64)]
    private ?string $traceId = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['comment' => 'Span ID'])]
    #[Assert\Length(max: 64)]
    private ?string $spanId = null;

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true, options: ['comment' => '请求ID'])]
    #[Assert\Length(max: 64)]
    private ?string $requestId = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '上游连接时间(毫秒)'])]
    #[Assert\PositiveOrZero]
    private ?int $upstreamConnectTime = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '上游响应头时间(毫秒)'])]
    #[Assert\PositiveOrZero]
    private ?int $upstreamHeaderTime = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '上游响应时间(毫秒)'])]
    #[Assert\PositiveOrZero]
    private ?int $upstreamResponseTime = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '请求大小(字节)'])]
    #[Assert\PositiveOrZero]
    private ?int $requestSize = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '响应大小(字节)'])]
    #[Assert\PositiveOrZero]
    private ?int $responseSize = null;

    public function __construct()
    {
        $this->requestTime = new \DateTimeImmutable();
        $this->status = ForwardLogStatus::PENDING;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * 仅供Doctrine ORM内部使用
     *
     * @internal
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getRule(): ?ForwardRule
    {
        return $this->rule;
    }

    public function setRule(?ForwardRule $rule): void
    {
        $this->rule = $rule;
    }

    public function getRequestTime(): \DateTimeImmutable
    {
        return $this->requestTime;
    }

    public function setRequestTime(\DateTimeImmutable $requestTime): void
    {
        $this->requestTime = $requestTime;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(string $targetUrl): void
    {
        $this->targetUrl = $targetUrl;
    }

    /**
     * @return array<string, string|array<string>>|null
     */
    public function getOriginalRequestHeaders(): ?array
    {
        return $this->originalRequestHeaders;
    }

    /**
     * @param array<string, string|array<string>>|null $originalRequestHeaders
     */
    public function setOriginalRequestHeaders(?array $originalRequestHeaders): void
    {
        $this->originalRequestHeaders = $originalRequestHeaders;
    }

    /**
     * @return array<string, string|array<string>>|null
     */
    public function getProcessedRequestHeaders(): ?array
    {
        return $this->processedRequestHeaders;
    }

    /**
     * @param array<string, string|array<string>>|null $processedRequestHeaders
     */
    public function setProcessedRequestHeaders(?array $processedRequestHeaders): void
    {
        $this->processedRequestHeaders = $processedRequestHeaders;
    }

    public function getRequestBody(): ?string
    {
        return $this->requestBody;
    }

    public function setRequestBody(?string $requestBody): void
    {
        $this->requestBody = $requestBody;
    }

    public function getResponseStatus(): int
    {
        return $this->responseStatus;
    }

    public function setResponseStatus(int $responseStatus): void
    {
        $this->responseStatus = $responseStatus;
    }

    /**
     * @return array<string, string|array<string>>|null
     */
    public function getResponseHeaders(): ?array
    {
        return $this->responseHeaders;
    }

    /**
     * @param array<string, string|array<string>>|null $responseHeaders
     */
    public function setResponseHeaders(?array $responseHeaders): void
    {
        $this->responseHeaders = $responseHeaders;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function setResponseBody(?string $responseBody): void
    {
        $this->responseBody = $responseBody;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function setDurationMs(int $durationMs): void
    {
        $this->durationMs = $durationMs;
    }

    public function getRetryCountUsed(): int
    {
        return $this->retryCountUsed;
    }

    public function setRetryCountUsed(int $retryCountUsed): void
    {
        $this->retryCountUsed = $retryCountUsed;
    }

    public function isFallbackUsed(): bool
    {
        return $this->fallbackUsed;
    }

    public function setFallbackUsed(bool $fallbackUsed): void
    {
        $this->fallbackUsed = $fallbackUsed;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    public function setClientIp(?string $clientIp): void
    {
        $this->clientIp = $clientIp;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getAccessKey(): ?AccessKey
    {
        return $this->accessKey;
    }

    public function setAccessKey(?AccessKey $accessKey): void
    {
        $this->accessKey = $accessKey;
    }

    public function getStatus(): ForwardLogStatus
    {
        return $this->status;
    }

    public function setStatus(ForwardLogStatus $status): void
    {
        $this->status = $status;
    }

    public function getSendTime(): ?\DateTimeImmutable
    {
        return $this->sendTime;
    }

    public function setSendTime(?\DateTimeImmutable $sendTime): void
    {
        $this->sendTime = $sendTime;
    }

    public function getFirstByteTime(): ?\DateTimeImmutable
    {
        return $this->firstByteTime;
    }

    public function setFirstByteTime(?\DateTimeImmutable $firstByteTime): void
    {
        $this->firstByteTime = $firstByteTime;

        if (null !== $this->sendTime && null !== $firstByteTime) {
            $sendMicroTime = (float) $this->sendTime->format('U.u');
            $firstByteMicroTime = (float) $firstByteTime->format('U.u');
            $this->latencyMs = (int) round(($firstByteMicroTime - $sendMicroTime) * 1000);
        }
    }

    public function getCompleteTime(): ?\DateTimeImmutable
    {
        return $this->completeTime;
    }

    public function setCompleteTime(?\DateTimeImmutable $completeTime): void
    {
        $this->completeTime = $completeTime;

        if (null !== $this->firstByteTime && null !== $completeTime) {
            $firstByteMicroTime = (float) $this->firstByteTime->format('U.u');
            $completeMicroTime = (float) $completeTime->format('U.u');
            $this->downloadMs = (int) round(($completeMicroTime - $firstByteMicroTime) * 1000);
        }

        if (null !== $this->sendTime && null !== $completeTime) {
            $sendMicroTime = (float) $this->sendTime->format('U.u');
            $completeMicroTime = (float) $completeTime->format('U.u');
            $this->durationMs = (int) round(($completeMicroTime - $sendMicroTime) * 1000);
        }
    }

    public function getLatencyMs(): ?int
    {
        return $this->latencyMs;
    }

    public function setLatencyMs(?int $latencyMs): void
    {
        $this->latencyMs = $latencyMs;
    }

    public function getDownloadMs(): ?int
    {
        return $this->downloadMs;
    }

    public function setDownloadMs(?int $downloadMs): void
    {
        $this->downloadMs = $downloadMs;
    }

    public function getBackend(): ?Backend
    {
        return $this->backend;
    }

    public function setBackend(?Backend $backend): void
    {
        $this->backend = $backend;
    }

    public function getBackendName(): ?string
    {
        return $this->backendName;
    }

    public function setBackendName(?string $backendName): void
    {
        $this->backendName = $backendName;
    }

    public function getBackendUrl(): ?string
    {
        return $this->backendUrl;
    }

    public function setBackendUrl(?string $backendUrl): void
    {
        $this->backendUrl = $backendUrl;
    }

    public function getLoadBalanceStrategy(): ?string
    {
        return $this->loadBalanceStrategy;
    }

    public function setLoadBalanceStrategy(?string $loadBalanceStrategy): void
    {
        $this->loadBalanceStrategy = $loadBalanceStrategy;
    }

    /**
     * @return array<array<string, mixed>>|null
     */
    public function getAvailableBackends(): ?array
    {
        return $this->availableBackends;
    }

    /**
     * @param array<array<string, mixed>>|null $availableBackends
     */
    public function setAvailableBackends(?array $availableBackends): void
    {
        $this->availableBackends = $availableBackends;
    }

    public function getRuleName(): ?string
    {
        return $this->ruleName;
    }

    public function setRuleName(?string $ruleName): void
    {
        $this->ruleName = $ruleName;
    }

    public function getRuleSourcePath(): ?string
    {
        return $this->ruleSourcePath;
    }

    public function setRuleSourcePath(?string $ruleSourcePath): void
    {
        $this->ruleSourcePath = $ruleSourcePath;
    }

    /**
     * @return array<array<string, mixed>>|null
     */
    public function getRuleMiddlewares(): ?array
    {
        return $this->ruleMiddlewares;
    }

    /**
     * @param array<array<string, mixed>>|null $ruleMiddlewares
     */
    public function setRuleMiddlewares(?array $ruleMiddlewares): void
    {
        $this->ruleMiddlewares = $ruleMiddlewares;
    }

    /**
     * @return array<array<string, mixed>>|null
     */
    public function getRetryDetails(): ?array
    {
        return $this->retryDetails;
    }

    /**
     * @param array<array<string, mixed>>|null $retryDetails
     */
    public function setRetryDetails(?array $retryDetails): void
    {
        $this->retryDetails = $retryDetails;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFallbackDetails(): ?array
    {
        return $this->fallbackDetails;
    }

    /**
     * @param array<string, mixed>|null $fallbackDetails
     */
    public function setFallbackDetails(?array $fallbackDetails): void
    {
        $this->fallbackDetails = $fallbackDetails;
    }

    public function getBackendResponseTime(): ?int
    {
        return $this->backendResponseTime;
    }

    public function setBackendResponseTime(?int $backendResponseTime): void
    {
        $this->backendResponseTime = $backendResponseTime;
    }

    public function getTotalProcessingTime(): ?int
    {
        return $this->totalProcessingTime;
    }

    public function setTotalProcessingTime(?int $totalProcessingTime): void
    {
        $this->totalProcessingTime = $totalProcessingTime;
    }

    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    public function setTraceId(?string $traceId): void
    {
        $this->traceId = $traceId;
    }

    public function getSpanId(): ?string
    {
        return $this->spanId;
    }

    public function setSpanId(?string $spanId): void
    {
        $this->spanId = $spanId;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(?string $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function getUpstreamConnectTime(): ?int
    {
        return $this->upstreamConnectTime;
    }

    public function setUpstreamConnectTime(?int $upstreamConnectTime): void
    {
        $this->upstreamConnectTime = $upstreamConnectTime;
    }

    public function getUpstreamHeaderTime(): ?int
    {
        return $this->upstreamHeaderTime;
    }

    public function setUpstreamHeaderTime(?int $upstreamHeaderTime): void
    {
        $this->upstreamHeaderTime = $upstreamHeaderTime;
    }

    public function getUpstreamResponseTime(): ?int
    {
        return $this->upstreamResponseTime;
    }

    public function setUpstreamResponseTime(?int $upstreamResponseTime): void
    {
        $this->upstreamResponseTime = $upstreamResponseTime;
    }

    public function getRequestSize(): ?int
    {
        return $this->requestSize;
    }

    public function setRequestSize(?int $requestSize): void
    {
        $this->requestSize = $requestSize;
    }

    public function getResponseSize(): ?int
    {
        return $this->responseSize;
    }

    public function setResponseSize(?int $responseSize): void
    {
        $this->responseSize = $responseSize;
    }

    public function __toString(): string
    {
        return sprintf(
            'Log#%d %s %s -> %s [%d] %dms (%s)',
            $this->id ?? 0,
            $this->method,
            $this->path,
            $this->targetUrl,
            $this->responseStatus,
            $this->durationMs,
            $this->status->getLabel()
        );
    }
}
