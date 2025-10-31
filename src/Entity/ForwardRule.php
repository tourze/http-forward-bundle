<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Repository\ForwardRuleRepository;

#[ORM\Entity(repositoryClass: ForwardRuleRepository::class)]
#[ORM\Table(name: 'http_forward_rule', options: ['comment' => 'HTTP转发规则表'])]
#[ORM\Index(name: 'http_forward_rule_idx_enabled_priority', columns: ['enabled', 'priority'])]
class ForwardRule implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '规则ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '规则名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name = '';

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '源路径模式'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $sourcePath = '';

    /**
     * @var Collection<int, Backend>
     */
    #[ORM\ManyToMany(targetEntity: Backend::class, inversedBy: 'forwardRules', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'forward_rule_backends')]
    #[ORM\OrderBy(value: ['weight' => 'DESC', 'id' => 'ASC'])]
    private Collection $backends;

    #[ORM\Column(type: Types::STRING, length: 50, options: ['comment' => '负载均衡策略'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['round_robin', 'random', 'ip_hash', 'weighted_round_robin', 'least_connections'])]
    private string $loadBalanceStrategy = 'round_robin';

    /**
     * @var array<string>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '允许的HTTP方法'])]
    #[Assert\NotBlank]
    #[Assert\Type(type: 'array')]
    #[Assert\All(constraints: [
        new Assert\Choice(choices: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS']),
    ])]
    private array $httpMethods = ['GET'];

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\NotNull]
    private bool $enabled = true;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '优先级'])]
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 9999)]
    private int $priority = 100;

    /**
     * @var array<array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '中间件配置'])]
    #[Assert\Type(type: 'array')]
    private array $middlewares = [];

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否移除前缀'])]
    #[Assert\NotNull]
    private bool $stripPrefix = true;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '超时时间(秒)'])]
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 300)]
    private int $timeout = 30;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '重试次数'])]
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 10)]
    private int $retryCount = 0;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '重试间隔(毫秒)'])]
    #[Assert\NotNull]
    #[Assert\Range(min: 100, max: 60000)]
    private int $retryInterval = 1000;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true, options: ['comment' => '降级类型'])]
    #[Assert\Length(max: 50)]
    #[Assert\Choice(choices: [null, 'STATIC', 'BACKUP'])]
    private ?string $fallbackType = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '降级配置'])]
    #[Assert\Type(type: 'array')]
    private ?array $fallbackConfig = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用流式传输'])]
    #[Assert\NotNull]
    private bool $streamEnabled = false;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '缓冲区大小(字节)'])]
    #[Assert\NotNull]
    #[Assert\Range(min: 0, max: 65536)]
    private int $bufferSize = 8192;

    public function __construct()
    {
        $this->backends = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getSourcePath(): string
    {
        return $this->sourcePath;
    }

    public function setSourcePath(string $sourcePath): void
    {
        $this->sourcePath = $sourcePath;
    }

    /**
     * @return Collection<int, Backend>
     */
    public function getBackends(): Collection
    {
        return $this->backends;
    }

    public function addBackend(Backend $backend): void
    {
        if (!$this->backends->contains($backend)) {
            $this->backends->add($backend);
            $backend->addForwardRule($this);
        }
    }

    public function removeBackend(Backend $backend): void
    {
        if ($this->backends->removeElement($backend)) {
            $backend->removeForwardRule($this);
        }
    }

    public function getLoadBalanceStrategy(): string
    {
        return $this->loadBalanceStrategy;
    }

    public function setLoadBalanceStrategy(string $loadBalanceStrategy): void
    {
        $this->loadBalanceStrategy = $loadBalanceStrategy;
    }

    /**
     * @return array<string>
     */
    public function getHttpMethods(): array
    {
        // 确保返回的数组只包含有效的字符串HTTP方法

        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
        $filtered = [];

        foreach ($this->httpMethods as $method) {
            // 只接受在有效列表中的方法
            if (in_array($method, $validMethods, true)) {
                $filtered[] = $method;
            }
        }

        // 如果过滤后没有有效方法，返回默认值
        return [] === $filtered ? ['GET'] : array_values(array_unique($filtered));
    }

    /**
     * @param array<string> $httpMethods
     */
    public function setHttpMethods(array $httpMethods): void
    {
        // 验证HTTP方法
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
        $filtered = array_filter($httpMethods, static function (string $method) use ($validMethods): bool {
            return in_array($method, $validMethods, true);
        });

        // 确保至少有一个有效方法
        $this->httpMethods = [] === $filtered ? ['GET'] : array_values($filtered);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * @param array<array<string, mixed>> $middlewares
     */
    public function setMiddlewares(array $middlewares): void
    {
        $this->middlewares = $middlewares;
    }

    /**
     * 获取中间件的JSON字符串表示（用于EasyAdmin表单）
     */
    public function getMiddlewaresJson(): string
    {
        if ([] === $this->middlewares) {
            return '[]';
        }

        $encoded = json_encode($this->middlewares, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return false === $encoded ? '[]' : $encoded;
    }

    /**
     * 设置中间件的JSON字符串（用于EasyAdmin表单）
     */
    public function setMiddlewaresJson(string $json): void
    {
        $json = trim($json);
        if ('' === $json || '{}' === $json || '[]' === $json) {
            $this->middlewares = [];

            return;
        }

        $decoded = json_decode($json, true);
        if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
            // 使用现有的标准化方法确保类型安全
            $this->middlewares = $this->normalizeMiddlewaresFormat($decoded);
        }
    }

    public function isStripPrefix(): bool
    {
        return $this->stripPrefix;
    }

    public function setStripPrefix(bool $stripPrefix): void
    {
        $this->stripPrefix = $stripPrefix;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): void
    {
        $this->retryCount = $retryCount;
    }

    public function getRetryInterval(): int
    {
        return $this->retryInterval;
    }

    public function setRetryInterval(int $retryInterval): void
    {
        $this->retryInterval = $retryInterval;
    }

    public function getFallbackType(): ?string
    {
        return $this->fallbackType;
    }

    public function setFallbackType(?string $fallbackType): void
    {
        $this->fallbackType = $fallbackType;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFallbackConfig(): ?array
    {
        return $this->fallbackConfig;
    }

    /**
     * @param array<string, mixed>|null $fallbackConfig
     */
    public function setFallbackConfig(?array $fallbackConfig): void
    {
        $this->fallbackConfig = $fallbackConfig;
    }

    public function isStreamEnabled(): bool
    {
        return $this->streamEnabled;
    }

    public function setStreamEnabled(bool $streamEnabled): void
    {
        $this->streamEnabled = $streamEnabled;
    }

    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    public function setBufferSize(int $bufferSize): void
    {
        $this->bufferSize = $bufferSize;
    }

    public function __toString(): string
    {
        $backendCount = $this->backends->count();
        $firstBackend = $this->backends->first();
        $backendInfo = 1 === $backendCount && $firstBackend instanceof Backend
            ? $firstBackend->getUrl()
            : sprintf('%d backends [%s]', $backendCount, $this->loadBalanceStrategy);

        return sprintf(
            'Rule#%d %s: %s -> %s',
            $this->id ?? 0,
            $this->name,
            $this->sourcePath,
            $backendInfo
        );
    }

    public function normalizeHttpMethods(): void
    {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
        $normalized = [];

        foreach ($this->httpMethods as $method) {
            // 只接受在有效列表中的方法
            if (in_array($method, $validMethods, true)) {
                $normalized[] = $method;
            }
        }

        // 如果过滤后没有有效方法，设置默认值
        $this->httpMethods = [] === $normalized ? ['GET'] : array_values(array_unique($normalized));
    }

    /**
     * 标准化中间件数据格式
     *
     * @param array<mixed> $middlewares
     * @return array<array<string, mixed>>
     */
    private function normalizeMiddlewaresFormat(array $middlewares): array
    {
        if ([] === $middlewares) {
            return [];
        }

        $firstItem = reset($middlewares);
        if (is_array($firstItem) && isset($firstItem['name'])) {
            return $this->normalizeNewFormat($middlewares);
        }

        return $this->normalizeOldFormat($middlewares);
    }

    /**
     * 标准化新格式中间件数据
     *
     * @param array<mixed> $middlewares
     * @return array<array<string, mixed>>
     */
    private function normalizeNewFormat(array $middlewares): array
    {
        $normalized = [];
        foreach ($middlewares as $middleware) {
            if (is_array($middleware) && isset($middleware['name'])) {
                $normalized[] = [
                    'name' => is_string($middleware['name']) ? $middleware['name'] : '',
                    'config' => isset($middleware['config']) && is_array($middleware['config']) ? $middleware['config'] : [],
                ];
            }
        }

        return $normalized;
    }

    /**
     * 标准化旧格式中间件数据
     *
     * @param array<mixed> $middlewares
     * @return array<array<string, mixed>>
     */
    private function normalizeOldFormat(array $middlewares): array
    {
        $normalized = [];
        foreach ($middlewares as $name => $config) {
            if (is_string($name) && is_array($config)) {
                $normalized[] = [
                    'name' => $name,
                    'config' => $config,
                ];
            }
        }

        return $normalized;
    }

    /**
     * @return Backend[]
     */
    public function getEnabledBackends(): array
    {
        return $this->backends->filter(fn (Backend $backend) => $backend->isEnabled())->getValues();
    }

    /**
     * @return Backend[]
     */
    public function getHealthyBackends(): array
    {
        return $this->backends->filter(fn (Backend $backend) => $backend->isEnabled() && BackendStatus::ACTIVE === $backend->getStatus())->getValues();
    }

    public function hasBackends(): bool
    {
        return !$this->backends->isEmpty();
    }

    public function hasHealthyBackends(): bool
    {
        return count($this->getHealthyBackends()) > 0;
    }

    /**
     * 虚拟字段用于EasyAdmin显示，实际格式化在控制器的formatValue中处理
     */
    public function getBackendsSummary(): string
    {
        return '';
    }
}
