<?php

declare(strict_types=1);

namespace App\McpInstancesConfiguration\Infrastructure;

use App\McpInstancesConfiguration\Domain\Dto\EndpointConfig;
use App\McpInstancesConfiguration\Domain\Dto\EndpointHealthConfig;
use App\McpInstancesConfiguration\Domain\Dto\EndpointHealthHttpConfig;
use App\McpInstancesConfiguration\Domain\Dto\InstanceDockerConfig;
use App\McpInstancesConfiguration\Domain\Dto\InstanceTypeConfig;
use App\McpInstancesConfiguration\Domain\Dto\McpInstanceTypesConfig;
use App\McpInstancesConfiguration\Domain\Exception\InvalidInstanceTypesConfigException;
use App\McpInstancesManagement\Domain\Enum\InstanceType;
use Symfony\Component\Yaml\Yaml;
use ValueError;

final class YamlInstanceTypesConfigProvider implements InstanceTypesConfigProviderInterface
{
    public function __construct(private readonly string $configFilePath)
    {
    }

    public function getConfig(): McpInstanceTypesConfig
    {
        if (!is_file($this->configFilePath) || !is_readable($this->configFilePath)) {
            throw new InvalidInstanceTypesConfigException('Config file not readable: ' . $this->configFilePath);
        }

        $parsed = Yaml::parseFile($this->configFilePath);
        if (!is_array($parsed) || !array_key_exists('mcp_instance_types', $parsed) || !is_array($parsed['mcp_instance_types'])) {
            throw new InvalidInstanceTypesConfigException('Root key mcp_instance_types missing or invalid');
        }

        $types = [];
        foreach ($parsed['mcp_instance_types'] as $typeKey => $typeData) {
            if (!is_string($typeKey) || $typeKey === '') {
                throw new InvalidInstanceTypesConfigException('Invalid type key');
            }

            try {
                InstanceType::from($typeKey);
            } catch (ValueError) {
                throw new InvalidInstanceTypesConfigException('Unknown instance type enum: ' . $typeKey);
            }

            if (!is_array($typeData)) {
                throw new InvalidInstanceTypesConfigException('Type data must be an object for ' . $typeKey);
            }

            $displayNameRaw = $typeData['display_name'] ?? null;
            if (!is_string($displayNameRaw) || $displayNameRaw === '') {
                throw new InvalidInstanceTypesConfigException('display_name is required for ' . $typeKey);
            }
            $displayName = $displayNameRaw;

            $descriptionRaw = $typeData['description'] ?? null;
            if (!is_string($descriptionRaw) || $descriptionRaw === '') {
                throw new InvalidInstanceTypesConfigException('description is required for ' . $typeKey);
            }
            $description = $descriptionRaw;

            $docker = $typeData['docker'] ?? [];
            if (!is_array($docker)) {
                throw new InvalidInstanceTypesConfigException('docker must be an object when provided for ' . $typeKey);
            }
            $env = [];
            if (array_key_exists('env', $docker)) {
                if (!is_array($docker['env'])) {
                    throw new InvalidInstanceTypesConfigException('docker.env must be a map for ' . $typeKey);
                }
                foreach ($docker['env'] as $ek => $ev) {
                    if (!is_string($ek) || !is_string($ev)) {
                        throw new InvalidInstanceTypesConfigException('docker.env entries must be strings for ' . $typeKey);
                    }
                    $env[$ek] = $ev;
                }
            }
            $image        = is_string($docker['image'] ?? null) ? (string) $docker['image'] : '';
            $dockerConfig = new InstanceDockerConfig($image, $env);

            // endpoints
            if (!array_key_exists('endpoints', $typeData) || !is_array($typeData['endpoints'])) {
                throw new InvalidInstanceTypesConfigException('endpoints is required for ' . $typeKey);
            }

            $endpointMap = [];
            foreach ($typeData['endpoints'] as $endpointId => $endpointData) {
                if (!is_string($endpointId) || $endpointId === '') {
                    throw new InvalidInstanceTypesConfigException('endpoint id must be a non-empty string for ' . $typeKey);
                }
                if (!is_array($endpointData)) {
                    throw new InvalidInstanceTypesConfigException('endpoint data must be an object for ' . $endpointId);
                }

                $port = $endpointData['port'] ?? null;
                if (!is_int($port) || $port <= 0) {
                    throw new InvalidInstanceTypesConfigException('endpoint.port must be positive int for ' . $endpointId);
                }
                $auth = null;
                if (array_key_exists('auth', $endpointData)) {
                    $authVal = $endpointData['auth'];
                    if ($authVal !== null && !is_string($authVal)) {
                        throw new InvalidInstanceTypesConfigException('endpoint.auth must be string or null for ' . $endpointId);
                    }
                    $auth = $authVal;
                }

                $externalPathsRaw = $endpointData['external_paths'] ?? [];
                if (!is_array($externalPathsRaw)) {
                    throw new InvalidInstanceTypesConfigException('endpoint.external_paths must be array for ' . $endpointId);
                }
                $externalPaths = [];
                foreach ($externalPathsRaw as $p) {
                    if (!is_string($p) || $p === '' || $p[0] !== '/') {
                        throw new InvalidInstanceTypesConfigException('endpoint.external_paths entries must be absolute paths for ' . $endpointId);
                    }
                    $externalPaths[] = $p;
                }

                $healthConfig = null;
                if (array_key_exists('health', $endpointData)) {
                    if (!is_array($endpointData['health'])) {
                        throw new InvalidInstanceTypesConfigException('endpoint.health must be object for ' . $endpointId);
                    }
                    $httpDef = $endpointData['health']['http'] ?? null;
                    $httpCfg = null;
                    if ($httpDef !== null) {
                        if (!is_array($httpDef)) {
                            throw new InvalidInstanceTypesConfigException('endpoint.health.http must be object for ' . $endpointId);
                        }

                        $path = $httpDef['path'] ?? null;
                        if (!is_string($path) || $path === '' || $path[0] !== '/') {
                            throw new InvalidInstanceTypesConfigException('endpoint.health.http.path must be absolute path for ' . $endpointId);
                        }

                        $acceptRaw = $httpDef['accept_status_lt'] ?? 500;
                        if (!is_int($acceptRaw)) {
                            throw new InvalidInstanceTypesConfigException(
                                'endpoint.health.http.accept_status_lt must be an integer for ' . $endpointId
                            );
                        }
                        $accept = $acceptRaw;
                        if ($accept < 100 || $accept > 599) {
                            throw new InvalidInstanceTypesConfigException(
                                'endpoint.health.http.accept_status_lt must be between 100 and 599 for ' . $endpointId
                            );
                        }
                        $httpCfg = new EndpointHealthHttpConfig($path, $accept);
                    }
                    $healthConfig = new EndpointHealthConfig($httpCfg);
                }

                $endpointMap[$endpointId] = new EndpointConfig(
                    $port,
                    $auth,
                    $externalPaths,
                    $healthConfig
                );
            }

            // mcp endpoint presence is required
            if (!array_key_exists('mcp', $endpointMap)) {
                throw new InvalidInstanceTypesConfigException('Each type must define an "mcp" endpoint: ' . $typeKey);
            }

            $types[$typeKey] = new InstanceTypeConfig(
                $displayName,
                $description,
                $dockerConfig,
                $endpointMap
            );
        }

        return new McpInstanceTypesConfig($types);
    }
}
