<?php

declare(strict_types=1);

namespace App\Tests\Unit\McpInstances\Facade;

use App\Account\Facade\Dto\AccountCoreInfoDto;
use App\DockerManagement\Facade\DockerManagementFacadeInterface;
use App\McpInstances\Domain\Entity\McpInstance;
use App\McpInstances\Domain\Service\McpInstancesDomainService;
use App\McpInstances\Facade\McpInstancesFacade;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class McpInstancesFacadeTest extends TestCase
{
    public function testGetMcpInstanceInfosForAccountDelegatesToRepo(): void
    {
        $em          = $this->createMock(EntityManagerInterface::class);
        $repo        = $this->createMock(EntityRepository::class);
        $docker      = $this->createMock(DockerManagementFacadeInterface::class);
        $domain      = new McpInstancesDomainService($em, $docker);
        $accountInfo = new AccountCoreInfoDto('acc-id');

        $em->method('getRepository')->willReturn($repo);
        $inst = new McpInstance('acc-id', 1280, 720, 24, 'v', 'b');
        $repo->method('findBy')->with(['accountCoreId' => 'acc-id'])->willReturn([$inst]);

        $facade = new McpInstancesFacade($domain, $em, $docker);
        $infos  = $facade->getMcpInstanceInfosForAccount($accountInfo);

        $this->assertCount(1, $infos);
        $this->assertSame($inst->getVncPassword(), $infos[0]->vncPassword);
    }
}
