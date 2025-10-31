<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Entity\ForwardLog;
use Tourze\HttpForwardBundle\Entity\ForwardRule;
use Tourze\HttpForwardBundle\Repository\ForwardLogRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @template-extends AbstractRepositoryTestCase<ForwardLog>
 * @internal
 */
#[CoversClass(ForwardLogRepository::class)]
#[RunTestsInSeparateProcesses]
final class ForwardLogRepositoryTest extends AbstractRepositoryTestCase
{
    private ?ForwardLogRepository $repository = null;

    protected function getRepository(): ForwardLogRepository
    {
        if (null === $this->repository) {
            $repository = self::getContainer()->get(ForwardLogRepository::class);
            $this->assertInstanceOf(ForwardLogRepository::class, $repository);
            $this->repository = $repository;
        }

        return $this->repository;
    }

    protected function getRepositoryClass(): string
    {
        return ForwardLogRepository::class;
    }

    protected function getEntityClass(): string
    {
        return ForwardLog::class;
    }

    protected function onSetUp(): void
    {
        // 初始化测试环境
    }

    protected function createNewEntity(): ForwardLog
    {
        $log = new ForwardLog();
        $log->setMethod('GET');
        $log->setPath('/test');
        $log->setTargetUrl('https://example.com/test');
        $log->setResponseStatus(200);
        $log->setDurationMs(100);

        return $log;
    }

    public function testFindRecentLogs(): void
    {
        $manager = self::getEntityManager();

        for ($i = 1; $i <= 5; ++$i) {
            $log = new ForwardLog();
            $log->setMethod('GET');
            $log->setPath('/test/' . $i);
            $log->setTargetUrl('https://example.com/test/' . $i);
            $log->setResponseStatus(200);
            $log->setDurationMs(100 * $i);
            $manager->persist($log);
        }

        $manager->flush();

        $recentLogs = $this->getRepository()->findRecentLogs(3);

        $this->assertCount(3, $recentLogs);
    }

    public function testFindByRule(): void
    {
        $manager = self::getEntityManager();

        // 创建Backend实体
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://example.com');
        $backend->setWeight(1);
        $backend->setEnabled(true);
        $manager->persist($backend);

        $rule = new ForwardRule();
        $rule->setName('Test Rule');
        $rule->setSourcePath('/test/*');
        $rule->addBackend($backend);
        $rule->setHttpMethods(['GET']);
        $rule->setEnabled(true);
        $rule->setPriority(100);
        $manager->persist($rule);

        $log1 = new ForwardLog();
        $log1->setRule($rule);
        $log1->setMethod('GET');
        $log1->setPath('/test/1');
        $log1->setTargetUrl('https://example.com/test/1');
        $log1->setResponseStatus(200);
        $log1->setDurationMs(100);
        $manager->persist($log1);

        $log2 = new ForwardLog();
        $log2->setMethod('GET');
        $log2->setPath('/other/path');
        $log2->setTargetUrl('https://example.com/other');
        $log2->setResponseStatus(200);
        $log2->setDurationMs(200);
        $manager->persist($log2);

        $manager->flush();

        $ruleLogs = $this->getRepository()->findByRule($rule);

        $this->assertCount(1, $ruleLogs);
        $this->assertEquals('/test/1', $ruleLogs[0]->getPath());
    }

    public function testFindErrorLogs(): void
    {
        $manager = self::getEntityManager();

        // 清理现有的日志数据
        foreach ($this->getRepository()->findAll() as $log) {
            $manager->remove($log);
        }
        $manager->flush();

        $successLog = new ForwardLog();
        $successLog->setMethod('GET');
        $successLog->setPath('/success');
        $successLog->setTargetUrl('https://example.com/success');
        $successLog->setResponseStatus(200);
        $successLog->setDurationMs(100);
        $manager->persist($successLog);

        $errorLog = new ForwardLog();
        $errorLog->setMethod('GET');
        $errorLog->setPath('/error');
        $errorLog->setTargetUrl('https://example.com/error');
        $errorLog->setResponseStatus(500);
        $errorLog->setDurationMs(200);
        $errorLog->setErrorMessage('Internal server error');
        $manager->persist($errorLog);

        $manager->flush();

        $errorLogs = $this->getRepository()->findErrorLogs();

        $this->assertCount(1, $errorLogs);
        $this->assertEquals('/error', $errorLogs[0]->getPath());
    }

    public function testGetStatsByRule(): void
    {
        $manager = self::getEntityManager();

        // 创建Backend实体
        $backend = new Backend();
        $backend->setName('Stats Backend');
        $backend->setUrl('https://example.com');
        $backend->setWeight(1);
        $backend->setEnabled(true);
        $manager->persist($backend);

        $rule = new ForwardRule();
        $rule->setName('Stats Rule');
        $rule->setSourcePath('/stats/*');
        $rule->addBackend($backend);
        $rule->setHttpMethods(['GET']);
        $rule->setEnabled(true);
        $rule->setPriority(100);
        $manager->persist($rule);

        $log1 = new ForwardLog();
        $log1->setRule($rule);
        $log1->setMethod('GET');
        $log1->setPath('/stats/1');
        $log1->setTargetUrl('https://example.com/stats/1');
        $log1->setResponseStatus(200);
        $log1->setDurationMs(100);
        $log1->setRetryCountUsed(0);
        $log1->setFallbackUsed(false);
        $manager->persist($log1);

        $log2 = new ForwardLog();
        $log2->setRule($rule);
        $log2->setMethod('GET');
        $log2->setPath('/stats/2');
        $log2->setTargetUrl('https://example.com/stats/2');
        $log2->setResponseStatus(500);
        $log2->setDurationMs(200);
        $log2->setRetryCountUsed(2);
        $log2->setFallbackUsed(true);
        $manager->persist($log2);

        $manager->flush();

        $stats = $this->getRepository()->getStatsByRule($rule);

        $this->assertEquals(2, $stats['totalRequests']);
        $this->assertEquals(150, $stats['avgDuration']);
        $this->assertEquals(1, $stats['successCount']);
        $this->assertEquals(1, $stats['errorCount']);
        $this->assertEquals(1, $stats['fallbackCount']);
        $this->assertEquals(2, $stats['totalRetries']);
    }

    public function testCleanOldLogs(): void
    {
        $manager = self::getEntityManager();

        $oldLog = new ForwardLog();
        $oldLog->setMethod('GET');
        $oldLog->setPath('/old');
        $oldLog->setTargetUrl('https://example.com/old');
        $oldLog->setResponseStatus(200);
        $oldLog->setDurationMs(100);
        $manager->persist($oldLog);

        $newLog = new ForwardLog();
        $newLog->setMethod('GET');
        $newLog->setPath('/new');
        $newLog->setTargetUrl('https://example.com/new');
        $newLog->setResponseStatus(200);
        $newLog->setDurationMs(100);
        $manager->persist($newLog);

        $manager->flush();

        $oldLog->setRequestTime(new \DateTimeImmutable('-7 days'));
        $manager->flush();

        $oldLogId = $oldLog->getId();
        $newLogId = $newLog->getId();

        $deletedCount = $this->getRepository()->cleanOldLogs(new \DateTimeImmutable('-1 day'));

        $this->assertEquals(1, $deletedCount);

        // 清理实体管理器缓存以强制从数据库重新加载
        $manager->clear();

        $this->assertNull($this->getRepository()->find($oldLogId));
        $this->assertNotNull($this->getRepository()->find($newLogId));
    }

    public function testRemove(): void
    {
        $manager = self::getEntityManager();

        $log = new ForwardLog();
        $log->setMethod('GET');
        $log->setPath('/remove');
        $log->setTargetUrl('https://example.com/remove');
        $log->setResponseStatus(200);
        $log->setDurationMs(100);
        $manager->persist($log);
        $manager->flush();

        $id = $log->getId();
        $this->assertNotNull($this->getRepository()->find($id));

        $this->getRepository()->remove($log, true);

        $this->assertNull($this->getRepository()->find($id));
    }

    public function testUpdate(): void
    {
        $manager = self::getEntityManager();

        $log = new ForwardLog();
        $log->setMethod('GET');
        $log->setPath('/update');
        $log->setTargetUrl('https://example.com/update');
        $log->setResponseStatus(200);
        $log->setDurationMs(100);
        $manager->persist($log);
        $manager->flush();

        $originalId = $log->getId();

        // 修改实体属性
        $log->setResponseStatus(404);
        $log->setDurationMs(250);
        $log->setErrorMessage('Not found');

        // 测试update方法不立即flush - 更改仍在内存中但未持久化
        $this->getRepository()->update($log, false);

        // 重新加载同一个实体，应该看到未持久化的状态
        $manager->refresh($log); // 从数据库重新加载，丢弃内存中的更改
        $this->assertEquals(200, $log->getResponseStatus());
        $this->assertEquals(100, $log->getDurationMs());

        // 重新修改并测试立即flush
        $log->setResponseStatus(404);
        $log->setDurationMs(250);
        $log->setErrorMessage('Not found');

        $this->getRepository()->update($log, true);

        // 重新加载验证更改已持久化
        $manager->refresh($log);
        $this->assertEquals(404, $log->getResponseStatus());
        $this->assertEquals(250, $log->getDurationMs());
        $this->assertEquals('Not found', $log->getErrorMessage());
    }

    public function testUpdateWithFlush(): void
    {
        $manager = self::getEntityManager();

        $log = new ForwardLog();
        $log->setMethod('POST');
        $log->setPath('/update-flush');
        $log->setTargetUrl('https://example.com/update-flush');
        $log->setResponseStatus(201);
        $log->setDurationMs(150);
        $manager->persist($log);
        $manager->flush();

        // 修改实体属性
        $log->setResponseStatus(500);
        $log->setDurationMs(300);
        $log->setErrorMessage('Internal server error');

        // 测试update方法立即flush
        $this->getRepository()->update($log, true);

        // 清除缓存并重新加载
        $manager->clear();

        $updatedLog = $this->getRepository()->find($log->getId());
        $this->assertNotNull($updatedLog, 'Updated log should be found');
        $this->assertEquals(500, $updatedLog->getResponseStatus());
        $this->assertEquals(300, $updatedLog->getDurationMs());
        $this->assertEquals('Internal server error', $updatedLog->getErrorMessage());
    }
}
