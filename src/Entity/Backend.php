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
use Tourze\HttpForwardBundle\Repository\BackendRepository;

#[ORM\Entity(repositoryClass: BackendRepository::class)]
#[ORM\Table(name: 'http_forward_backend', options: ['comment' => 'HTTP转发后端服务器表'])]
#[ORM\Index(name: 'http_forward_backend_idx_enabled_status', columns: ['enabled', 'status'])]
class Backend implements \Stringable
{
    use TimestampableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '后端ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '后端名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::STRING, length: 500, options: ['comment' => '后端基础URL'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    #[Assert\Url]
    private string $url = '';

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '权重(1-100)'])]
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 100)]
    private int $weight = 1;

    #[IndexColumn]
    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\NotNull]
    private bool $enabled = true;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 50, enumType: BackendStatus::class, options: ['comment' => '后端状态'])]
    #[Assert\NotNull]
    #[Assert\Choice(choices: [BackendStatus::ACTIVE, BackendStatus::INACTIVE, BackendStatus::UNHEALTHY], message: 'Invalid backend status')]
    private BackendStatus $status = BackendStatus::ACTIVE;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '超时时间(秒)'])]
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 300)]
    private int $timeout = 30;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '最大并发连接数'])]
    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: 1000)]
    private int $maxConnections = 100;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '健康检查路径'])]
    #[Assert\Length(max: 255)]
    private ?string $healthCheckPath = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后健康检查时间'])]
    #[Assert\Type(type: '\DateTimeImmutable')]
    private ?\DateTimeImmutable $lastHealthCheck = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '最后健康检查状态'])]
    #[Assert\Type(type: 'bool')]
    private ?bool $lastHealthStatus = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ['comment' => '平均响应时间(毫秒)'])]
    #[Assert\PositiveOrZero]
    private ?float $avgResponseTime = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['comment' => '元数据信息'])]
    #[Assert\Type(type: 'array')]
    private array $metadata = [];

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '描述信息'])]
    #[Assert\Length(max: 65535)]
    private ?string $description = null;

    /**
     * @var Collection<int, ForwardRule>
     */
    #[ORM\ManyToMany(targetEntity: ForwardRule::class, mappedBy: 'backends')]
    private Collection $forwardRules;

    public function __construct()
    {
        $this->forwardRules = new ArrayCollection();
    }

    public function __toString(): string
    {
        return '' !== $this->name ? $this->name : 'Backend #' . $this->id;
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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): void
    {
        $this->weight = $weight;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getStatus(): BackendStatus
    {
        return $this->status;
    }

    public function setStatus(BackendStatus $status): void
    {
        $this->status = $status;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getMaxConnections(): int
    {
        return $this->maxConnections;
    }

    public function setMaxConnections(int $maxConnections): void
    {
        $this->maxConnections = $maxConnections;
    }

    public function getHealthCheckPath(): ?string
    {
        return $this->healthCheckPath;
    }

    public function setHealthCheckPath(?string $healthCheckPath): void
    {
        $this->healthCheckPath = $healthCheckPath;
    }

    public function getLastHealthCheck(): ?\DateTimeImmutable
    {
        return $this->lastHealthCheck;
    }

    public function setLastHealthCheck(?\DateTimeImmutable $lastHealthCheck): void
    {
        $this->lastHealthCheck = $lastHealthCheck;
    }

    public function getLastHealthStatus(): ?bool
    {
        return $this->lastHealthStatus;
    }

    public function setLastHealthStatus(?bool $lastHealthStatus): void
    {
        $this->lastHealthStatus = $lastHealthStatus;
    }

    public function getAvgResponseTime(): ?float
    {
        return $this->avgResponseTime;
    }

    public function setAvgResponseTime(?float $avgResponseTime): void
    {
        $this->avgResponseTime = $avgResponseTime;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return Collection<int, ForwardRule>
     */
    public function getForwardRules(): Collection
    {
        return $this->forwardRules;
    }

    public function addForwardRule(ForwardRule $forwardRule): void
    {
        if (!$this->forwardRules->contains($forwardRule)) {
            $this->forwardRules->add($forwardRule);
            $forwardRule->addBackend($this);
        }
    }

    public function removeForwardRule(ForwardRule $forwardRule): void
    {
        if ($this->forwardRules->removeElement($forwardRule)) {
            $forwardRule->removeBackend($this);
        }
    }
}
