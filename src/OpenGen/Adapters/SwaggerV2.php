<?php

/*
 * This file is part of Waffler.
 *
 * (c) Erick Johnson Almeida de Menezes <erickmenezes.dev@gmail.com>
 *
 * This source file is subject to the MIT licence that is bundled
 * with this source code in the file LICENCE.
 */

namespace Waffler\OpenGen\Adapters;

use cebe\openapi\spec\Components;
use cebe\openapi\spec\MediaType;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Response;
use cebe\openapi\spec\Responses;
use cebe\openapi\spec\Schema;
use cebe\openapi\spec\SecurityScheme;
use cebe\openapi\spec\Tag;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;

/**
 * Adapter for Swagger specification file.
 *
 * Supported specification version: 2.0
 */
class SwaggerV2 extends OpenApiV3
{
    protected function createPhpFile(
        OpenApi $specification,
        Tag $tag,
        string $namespace,
        string $interfaceName
    ): PhpFile {
        $this->adpatSecurityDefinitionsToOpenApiV3($specification);
        $this->adaptSwaggerBodyParameterToOpenApiV3RequestBody($specification);
        $this->adaptParametersToOpenApiV3Format($specification);
        $this->adaptReturnValueToOpenApiV3Format($specification);

        return parent::createPhpFile(
            $specification,
            $tag,
            $namespace,
            $interfaceName,
        );
    }

    private function adpatSecurityDefinitionsToOpenApiV3(OpenApi $specification): void
    {
        $specification->components ??= new Components([]);
        $securitySchemes = [];
        $securityDefinitions = isset($specification->securityDefinitions)
            ? json_decode(json_encode($specification->securityDefinitions), true)
            : [];

        foreach ($securityDefinitions as $name => $definition) {
            if (!isset($definition['type']) || $definition['type'] === 'oauth2') {
                continue;
            }

            $securitySchemes[$name] = new SecurityScheme([
                'type' => $definition['type'] ?? null,
                'in' => $definition['in'] ?? 'header',
                'name' => $definition['name'] ?? null,
                'description' => $definition['description'] ?? null
            ]);
        }
        $specification->components->securitySchemes = $securitySchemes;
    }

    private function adaptSwaggerBodyParameterToOpenApiV3RequestBody(OpenApi $specification): void
    {
        foreach ($specification->paths as $path) {
            foreach ($path->getOperations() as $operation) {
                foreach ($operation->parameters as $parameter) {
                    if ($parameter->in !== 'body') {
                        continue;
                    }

                    $mediaTypes = $operation->consumes ?? [];
                    $parameter->schema = is_array($parameter->schema)
                        ? new Schema($parameter->schema)
                        : $parameter->schema;

                    $bodyData = [
                        'schema' => $parameter->schema,
                        'required' => $parameter->required,
                    ];

                    if (
                        empty($mediaTypes)
                        && in_array($parameter->schema->type, ['array', 'object'], true)
                    ) {
                        $mediaTypes[] = 'application/json';
                    }

                    $types = [];

                    foreach ($mediaTypes as $mediaType) {
                        $types[$mediaType] = new MediaType($bodyData);
                    }

                    $operation->requestBody = new RequestBody([
                        'content' => $types,
                        'required' => $parameter->required,
                        'description' => $parameter->description,
                    ]);

                    return;
                }
            }
        }
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    private function adaptParametersToOpenApiV3Format(OpenApi $specification): void
    {
        foreach ($specification->paths as $path) {
            foreach ($path->getOperations() as $operation) {
                $newParameters = [];
                foreach ($operation->parameters as $parameter) {
                    $parameter = $parameter->getSerializableData();
                    $schema = new Schema([
                        'type' => $parameter->type ?? $parameter?->schema?->type,
                        'required' => $parameter->required ?? false,
                        'description' => $parameter->description ?? null
                    ]);
                    if (!$schema->required) {
                        $schema->default = $parameter->default ?? $parameter->schema->default ?? null;
                    }
                    $newParameters[] = new Parameter([
                        'name' => $parameter->name,
                        'in' => $parameter->in,
                        'required' => $parameter->required ?? false,
                        'deprecated' => $parameter->deprecated ?? false,
                        'schema' => $schema,
                        'description' => $parameter->description ?? null,
                    ]);
                }
                $operation->parameters = $newParameters;
            }
        }
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    private function adaptReturnValueToOpenApiV3Format(OpenApi $specification): void
    {
        foreach ($specification->paths as $path) {
            foreach ($path->getOperations() as $operation) {
                $newResponses = [];
                $produces = $operation->getSerializableData()->produces ?? [];

                foreach ($operation->responses as $statusCode => $response) {
                    $content = [];
                    foreach ($produces as $mimeType) {
                        $content[$mimeType] = new MediaType([
                            'schema' => new Schema([
                                'type' => $response->schema->type ?? $response->type ?? null,
                            ])
                        ]);
                    }
                    $newResponses[$statusCode] = new Response([
                        'description' => $response->description,
                        'content' => $content
                    ]);
                }

                $operation->responses = new Responses($newResponses);
            }
        }
    }

    protected function mustIncludeParameter(string $in, array|int|string $search): bool
    {
        if ($in === 'body') {
            return false;
        }

        return parent::mustIncludeParameter($in, $search);
    }

    protected function addSecurityRequirementParameter(Method $method, OpenApi $openApi, string $securityName): void
    {
        if (!isset($openApi->components->securitySchemes[$securityName])) {
            return;
        }

        parent::addSecurityRequirementParameter($method, $openApi, $securityName);
    }
}
