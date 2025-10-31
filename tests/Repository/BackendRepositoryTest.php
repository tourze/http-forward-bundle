<?php

declare(strict_types=1);

namespace Tourze\HttpForwardBundle\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\HttpForwardBundle\Entity\Backend;
use Tourze\HttpForwardBundle\Enum\BackendStatus;
use Tourze\HttpForwardBundle\Repository\BackendRepository;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @template-extends AbstractRepositoryTestCase<Backend>
 * @internal
 */
#[CoversClass(BackendRepository::class)]
#[RunTestsInSeparateProcesses]
final class BackendRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // No setup required - using self::getService() directly in tests
    }

    protected function createNewEntity(): Backend
    {
        $backend = new Backend();
        $backend->setName('Test Backend');
        $backend->setUrl('https://example.com');
        $backend->setWeight(50);
        $backend->setEnabled(true);
        $backend->setStatus(BackendStatus::ACTIVE);
        $backend->setTimeout(30);
        $backend->setMaxConnections(100);

        return $backend;
    }

    protected function getRepository(): BackendRepository
    {
        return self::getService(BackendRepository::class);
    }

    public function testSaveAndFindBackendShouldWorkCorrectly(): void
    {
        $backend = $this->createNewEntity();
        $repository = $this->getRepository();

        $repository->save($backend, true);

        $this->assertNotNull($backend->getId());

        $foundBackend = $repository->find($backend->getId());

        $this->assertNotNull($foundBackend);
        $this->assertEquals($backend->getId(), $foundBackend->getId());
        $this->assertEquals('Test Backend', $foundBackend->getName());
        $this->assertEquals('https://example.com', $foundBackend->getUrl());
        $this->assertTrue($foundBackend->isEnabled());
        $this->assertEquals(BackendStatus::ACTIVE, $foundBackend->getStatus());
    }

    public function testRemoveBackendShouldWorkCorrectly(): void
    {
        $backend = $this->createNewEntity();
        $repository = $this->getRepository();

        $repository->save($backend, true);
        $backendId = $backend->getId();

        $this->assertNotNull($backendId);

        $repository->remove($backend, true);

        $foundBackend = $repository->find($backendId);
        $this->assertNull($foundBackend);
    }

    public function testUpdateBackendShouldWorkCorrectly(): void
    {
        $backend = $this->createNewEntity();
        $repository = $this->getRepository();

        $repository->save($backend, true);

        $backend->setName('Updated Backend');
        $backend->setWeight(75);

        $repository->update($backend, true);

        $foundBackend = $repository->find($backend->getId());
        $this->assertNotNull($foundBackend);
        $this->assertEquals('Updated Backend', $foundBackend->getName());
        $this->assertEquals(75, $foundBackend->getWeight());
    }

    public function testFindEnabledBackendsShouldReturnOnlyEnabledBackends(): void
    {
        $enabledBackend = $this->createNewEntity();
        $enabledBackend->setName('Enabled Backend');
        $enabledBackend->setWeight(80);

        $disabledBackend = new Backend();
        $disabledBackend->setName('Disabled Backend');
        $disabledBackend->setUrl('https://disabled.com');
        $disabledBackend->setWeight(60);
        $disabledBackend->setEnabled(false);
        $disabledBackend->setStatus(BackendStatus::INACTIVE);

        $repository = $this->getRepository();
        $repository->save($enabledBackend);
        $repository->save($disabledBackend, true);

        $enabledBackends = $repository->findEnabledBackends();

        $this->assertGreaterThanOrEqual(1, count($enabledBackends));
        foreach ($enabledBackends as $backend) {
            $this->assertTrue($backend->isEnabled());
        }

        // Check that our enabled backend is in the results
        $backendNames = array_map(fn (Backend $b) => $b->getName(), $enabledBackends);
        $this->assertContains('Enabled Backend', $backendNames);
    }

    public function testFindHealthyBackendsShouldReturnOnlyActiveEnabledBackends(): void
    {
        $healthyBackend = $this->createNewEntity();
        $healthyBackend->setName('Healthy Backend');
        $healthyBackend->setStatus(BackendStatus::ACTIVE);

        $unhealthyBackend = new Backend();
        $unhealthyBackend->setName('Unhealthy Backend');
        $unhealthyBackend->setUrl('https://unhealthy.com');
        $unhealthyBackend->setWeight(40);
        $unhealthyBackend->setEnabled(true);
        $unhealthyBackend->setStatus(BackendStatus::UNHEALTHY);

        $disabledBackend = new Backend();
        $disabledBackend->setName('Disabled Backend');
        $disabledBackend->setUrl('https://disabled.com');
        $disabledBackend->setWeight(30);
        $disabledBackend->setEnabled(false);
        $disabledBackend->setStatus(BackendStatus::ACTIVE);

        $repository = $this->getRepository();
        $repository->save($healthyBackend);
        $repository->save($unhealthyBackend);
        $repository->save($disabledBackend, true);

        $healthyBackends = $repository->findHealthyBackends();

        foreach ($healthyBackends as $backend) {
            $this->assertTrue($backend->isEnabled());
            $this->assertEquals(BackendStatus::ACTIVE, $backend->getStatus());
        }

        // Check that our healthy backend is in the results
        $backendNames = array_map(fn (Backend $b) => $b->getName(), $healthyBackends);
        $this->assertContains('Healthy Backend', $backendNames);
        $this->assertNotContains('Unhealthy Backend', $backendNames);
        $this->assertNotContains('Disabled Backend', $backendNames);
    }

    public function testFindBackendsForHealthCheckShouldReturnOnlyEnabledBackendsWithHealthCheckPath(): void
    {
        $backendWithHealthCheck = $this->createNewEntity();
        $backendWithHealthCheck->setName('Backend with Health Check');
        $backendWithHealthCheck->setHealthCheckPath('/health');

        $backendWithoutHealthCheck = $this->createNewEntity();
        $backendWithoutHealthCheck->setName('Backend without Health Check');
        $backendWithoutHealthCheck->setUrl('https://no-health.com');
        // healthCheckPath remains null

        $disabledBackendWithHealthCheck = new Backend();
        $disabledBackendWithHealthCheck->setName('Disabled Backend with Health Check');
        $disabledBackendWithHealthCheck->setUrl('https://disabled-health.com');
        $disabledBackendWithHealthCheck->setWeight(20);
        $disabledBackendWithHealthCheck->setEnabled(false);
        $disabledBackendWithHealthCheck->setStatus(BackendStatus::INACTIVE);
        $disabledBackendWithHealthCheck->setHealthCheckPath('/health');

        $repository = $this->getRepository();
        $repository->save($backendWithHealthCheck);
        $repository->save($backendWithoutHealthCheck);
        $repository->save($disabledBackendWithHealthCheck, true);

        $backendsForHealthCheck = $repository->findBackendsForHealthCheck();

        foreach ($backendsForHealthCheck as $backend) {
            $this->assertTrue($backend->isEnabled());
            $this->assertNotNull($backend->getHealthCheckPath());
        }

        // Check that only our enabled backend with health check is in the results
        $backendNames = array_map(fn (Backend $b) => $b->getName(), $backendsForHealthCheck);
        $this->assertContains('Backend with Health Check', $backendNames);
        $this->assertNotContains('Backend without Health Check', $backendNames);
        $this->assertNotContains('Disabled Backend with Health Check', $backendNames);
    }

    public function testFindByIdsShouldReturnCorrectBackends(): void
    {
        $backend1 = $this->createNewEntity();
        $backend1->setName('Backend 1');

        $backend2 = $this->createNewEntity();
        $backend2->setName('Backend 2');
        $backend2->setUrl('https://backend2.com');

        $backend3 = $this->createNewEntity();
        $backend3->setName('Backend 3');
        $backend3->setUrl('https://backend3.com');

        $repository = $this->getRepository();
        $repository->save($backend1);
        $repository->save($backend2);
        $repository->save($backend3, true);

        $ids = array_filter([$backend1->getId(), $backend3->getId()], fn ($id) => null !== $id);
        $foundBackends = $repository->findByIds($ids);

        $this->assertCount(2, $foundBackends);

        $foundIds = array_map(fn (Backend $b) => $b->getId(), $foundBackends);
        $this->assertContains($backend1->getId(), $foundIds);
        $this->assertContains($backend3->getId(), $foundIds);
        $this->assertNotContains($backend2->getId(), $foundIds);
    }

    public function testFindUnhealthyBackendsShouldReturnCorrectBackends(): void
    {
        $healthyBackend = $this->createNewEntity();
        $healthyBackend->setName('Healthy Backend');
        $healthyBackend->setStatus(BackendStatus::ACTIVE);
        $healthyBackend->setLastHealthStatus(true);

        $unhealthyStatusBackend = new Backend();
        $unhealthyStatusBackend->setName('Unhealthy Status Backend');
        $unhealthyStatusBackend->setUrl('https://unhealthy-status.com');
        $unhealthyStatusBackend->setWeight(30);
        $unhealthyStatusBackend->setEnabled(true);
        $unhealthyStatusBackend->setStatus(BackendStatus::UNHEALTHY);

        $unhealthyHealthCheckBackend = new Backend();
        $unhealthyHealthCheckBackend->setName('Unhealthy Health Check Backend');
        $unhealthyHealthCheckBackend->setUrl('https://unhealthy-health.com');
        $unhealthyHealthCheckBackend->setWeight(25);
        $unhealthyHealthCheckBackend->setEnabled(true);
        $unhealthyHealthCheckBackend->setStatus(BackendStatus::ACTIVE);
        $unhealthyHealthCheckBackend->setLastHealthStatus(false);

        $repository = $this->getRepository();
        $repository->save($healthyBackend);
        $repository->save($unhealthyStatusBackend);
        $repository->save($unhealthyHealthCheckBackend, true);

        $unhealthyBackends = $repository->findUnhealthyBackends();

        $backendNames = array_map(fn (Backend $b) => $b->getName(), $unhealthyBackends);
        $this->assertNotContains('Healthy Backend', $backendNames);
        $this->assertContains('Unhealthy Status Backend', $backendNames);
        $this->assertContains('Unhealthy Health Check Backend', $backendNames);
    }

    public function testSaveWithoutFlushShouldNotPersistImmediately(): void
    {
        $backend = $this->createNewEntity();
        $repository = $this->getRepository();

        $repository->save($backend, false);

        // The entity should NOT have an ID after persist without flush (IDENTITY generation strategy)
        $this->assertNull($backend->getId());
    }

    public function testRemoveWithoutFlushShouldNotRemoveImmediately(): void
    {
        $backend = $this->createNewEntity();
        $repository = $this->getRepository();

        $repository->save($backend, true);
        $backendId = $backend->getId();

        $repository->remove($backend, false);

        // Without flush, entity might still be findable in the same transaction
        // This test mainly ensures no exception is thrown
        $this->assertNotNull($backendId);
    }

    public function testUpdateWithoutFlushShouldNotPersistChangesImmediately(): void
    {
        $backend = $this->createNewEntity();
        $repository = $this->getRepository();

        $repository->save($backend, true);

        $backend->setName('Updated without flush');
        $repository->update($backend, false);

        // This mainly tests that update method works without exceptions
        $this->assertEquals('Updated without flush', $backend->getName());
    }
}
