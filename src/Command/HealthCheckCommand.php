<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Repository\BackendRepository;
use Tourze\HttpForwardBundle\Service\HealthCheckService;
use Tourze\Symfony\CronJob\Attribute\AsCronTask;

#[AsCommand(
    name: 'http-forward:health-check',
    description: '检查所有后端服务器的健康状态'
)]
#[AsCronTask(expression: '*/3 * * * *')]
class HealthCheckCommand extends Command
{
    public function __construct(
        private readonly BackendRepository $backendRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HealthCheckService $healthCheckService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('检查所有后端服务器的健康状态')
            ->setHelp('此命令检查所有启用的后端服务器的健康状态并更新数据库记录')
            ->addOption(
                'timeout',
                't',
                InputOption::VALUE_OPTIONAL,
                '健康检查超时时间（秒）',
                3
            )
            ->addOption(
                'backend-id',
                'b',
                InputOption::VALUE_OPTIONAL,
                '只检查指定ID的后端服务器'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                '试运行模式，不更新数据库'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeoutOption = $input->getOption('timeout');
        $timeout = is_numeric($timeoutOption) ? (int) $timeoutOption : $this->healthCheckService->getDefaultTimeout();
        $backendId = $input->getOption('backend-id');
        $dryRunOption = $input->getOption('dry-run');
        $dryRun = is_bool($dryRunOption) ? $dryRunOption : false;

        $io->title('后端服务器健康检查');

        // 处理单个后端检查
        if (null !== $backendId && is_numeric($backendId)) {
            return $this->checkSingleBackend((int) $backendId, $timeout, $dryRun, $io);
        }

        // 批量检查所有后端
        return $this->checkAllBackends($timeout, $dryRun, $io);
    }

    private function checkSingleBackend(int $backendId, int $timeout, bool $dryRun, SymfonyStyle $io): int
    {
        $backend = $this->backendRepository->find($backendId);
        if (null === $backend) {
            $io->error("未找到ID为 {$backendId} 的后端服务器");

            return Command::FAILURE;
        }

        $io->writeln(sprintf('正在检查后端服务器: %s (%s)', $backend->getName(), $backend->getUrl()));

        if ($dryRun) {
            $isHealthy = $this->healthCheckService->checkBackendHealth($backend, $timeout);
            $io->writeln(sprintf(
                '试运行结果: %s %s',
                $isHealthy ? '✅' : '❌',
                $backend->getName()
            ));
        } else {
            try {
                $isHealthy = $this->healthCheckService->checkAndUpdateBackend($backend, $timeout);
                $this->entityManager->flush();

                $io->writeln(sprintf(
                    '检查完成: %s %s',
                    $isHealthy ? '✅' : '❌',
                    $backend->getName()
                ));
            } catch (\Exception $e) {
                $io->error(sprintf('检查异常: %s - %s', $backend->getName(), $e->getMessage()));

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    private function checkAllBackends(int $timeout, bool $dryRun, SymfonyStyle $io): int
    {
        $backends = $this->backendRepository->findBackendsForHealthCheck();

        if (0 === count($backends)) {
            $io->warning('没有找到需要健康检查的后端服务器');

            return Command::SUCCESS;
        }

        $io->progressStart(count($backends));

        if ($dryRun) {
            $results = ['healthy' => 0, 'unhealthy' => 0, 'errors' => []];
            foreach ($backends as $backend) {
                $io->progressAdvance();
                $isHealthy = $this->healthCheckService->checkBackendHealth($backend, $timeout);
                if ($isHealthy) {
                    ++$results['healthy'];
                } else {
                    ++$results['unhealthy'];
                    $results['errors'][] = sprintf('后端 "%s" 健康检查失败', $backend->getName());
                }
            }
        } else {
            $results = $this->healthCheckService->checkMultipleBackends($backends, $timeout);
            $this->entityManager->flush();
        }

        $io->progressFinish();
        $this->displayResults($results, $backends, $dryRun, $io);

        return Command::SUCCESS;
    }

    /**
     * @param Backend[] $backends
     * @param array{healthy: int, unhealthy: int, errors: string[]} $results
     */
    private function displayResults(array $results, array $backends, bool $dryRun, SymfonyStyle $io): void
    {
        $io->success('健康检查完成');

        $io->table(
            ['统计', '数量'],
            [
                ['健康的后端', $results['healthy']],
                ['不健康的后端', $results['unhealthy']],
                ['总计', count($backends)],
            ]
        );

        if (count($results['errors']) > 0) {
            $io->section('错误详情：');
            foreach ($results['errors'] as $error) {
                $io->writeln("  • {$error}");
            }
        }

        if (true === $dryRun) {
            $io->note('试运行模式：未更新数据库');
        }
    }
}
