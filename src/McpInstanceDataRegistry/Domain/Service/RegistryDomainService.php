<?php

declare(strict_types=1);

namespace App\McpInstanceDataRegistry\Domain\Service;

use App\McpInstanceDataRegistry\Domain\Entity\RegistryEntry;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class RegistryDomainService implements RegistryDomainServiceInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger
    ) {
    }

    public function getValue(string $instanceId, string $key): ?string
    {
        $repo  = $this->entityManager->getRepository(RegistryEntry::class);
        $entry = $repo->findOneBy([
            'instanceId'  => $instanceId,
            'registryKey' => $key
        ]);

        if (!$entry) {
            $this->logger->debug('[RegistryDomainService] Key not found', [
                'instanceId' => $instanceId,
                'key'        => $key
            ]);

            return null;
        }

        return $entry->getRegistryValue();
    }

    public function setValue(string $instanceId, string $key, string $value): void
    {
        $repo  = $this->entityManager->getRepository(RegistryEntry::class);
        $entry = $repo->findOneBy([
            'instanceId'  => $instanceId,
            'registryKey' => $key
        ]);

        if ($entry) {
            $entry->setRegistryValue($value);
            $this->logger->info('[RegistryDomainService] Updated existing entry', [
                'instanceId' => $instanceId,
                'key'        => $key
            ]);
        } else {
            $entry = new RegistryEntry($instanceId, $key, $value);
            $this->entityManager->persist($entry);
            $this->logger->info('[RegistryDomainService] Created new entry', [
                'instanceId' => $instanceId,
                'key'        => $key
            ]);
        }

        $this->entityManager->flush();
    }

    public function deleteValue(string $instanceId, string $key): bool
    {
        $repo  = $this->entityManager->getRepository(RegistryEntry::class);
        $entry = $repo->findOneBy([
            'instanceId'  => $instanceId,
            'registryKey' => $key
        ]);

        if (!$entry) {
            return false;
        }

        $this->entityManager->remove($entry);
        $this->entityManager->flush();

        $this->logger->info('[RegistryDomainService] Deleted entry', [
            'instanceId' => $instanceId,
            'key'        => $key
        ]);

        return true;
    }

    public function getAllValues(string $instanceId): array
    {
        $repo    = $this->entityManager->getRepository(RegistryEntry::class);
        $entries = $repo->findBy(['instanceId' => $instanceId]);

        $result = [];
        foreach ($entries as $entry) {
            $result[$entry->getRegistryKey()] = $entry->getRegistryValue();
        }

        return $result;
    }

    public function deleteAllValues(string $instanceId): int
    {
        $repo    = $this->entityManager->getRepository(RegistryEntry::class);
        $entries = $repo->findBy(['instanceId' => $instanceId]);

        $count = count($entries);
        foreach ($entries as $entry) {
            $this->entityManager->remove($entry);
        }

        if ($count > 0) {
            $this->entityManager->flush();
            $this->logger->info('[RegistryDomainService] Deleted all entries for instance', [
                'instanceId' => $instanceId,
                'count'      => $count
            ]);
        }

        return $count;
    }
}
