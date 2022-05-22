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

use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Schema;
use cebe\openapi\spec\Tag;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use JetBrains\PhpStorm\Pure;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\Parameter as PhpParameter;
use Nette\PhpGenerator\PhpFile;
use Psr\Http\Message\ResponseInterface;
use Waffler\OpenGen\StringHelper;
use Waffler\Waffler\Attributes\Auth\Basic;
use Waffler\Waffler\Attributes\Auth\Bearer;
use Waffler\Waffler\Attributes\Auth\Digest;
use Waffler\Waffler\Attributes\Request\Body;
use Waffler\Waffler\Attributes\Request\FormParam;
use Waffler\Waffler\Attributes\Request\HeaderParam;
use Waffler\Waffler\Attributes\Request\Json;
use Waffler\Waffler\Attributes\Request\PathParam;
use Waffler\Waffler\Attributes\Request\Produces;
use Waffler\Waffler\Attributes\Request\QueryParam;
use Waffler\Waffler\Attributes\Verbs\Delete;
use Waffler\Waffler\Attributes\Verbs\Get;
use Waffler\Waffler\Attributes\Verbs\Head;
use Waffler\Waffler\Attributes\Verbs\Options;
use Waffler\Waffler\Attributes\Verbs\Patch;
use Waffler\Waffler\Attributes\Verbs\Post;
use Waffler\Waffler\Attributes\Verbs\Put;

use function Waffler\Waffler\arrayWrap;

/**
 * Adapter for the OpenAPI specification.
 *
 * Supported specification version: 3.0.x
 */
class OpenApiV3Adapter implements SpecificationAdapterInterface
{
    /**
     * @var array<class-string>
     */
    private array $uses = [];

    /**
     * @param string                              $namespace
     * @param string                              $interfaceSuffix
     * @param array<string, string|array<string>> $ignoreParameters
     * @param array<string>                       $ignoreMethods
     * @param string|null                         $removeMethodPrefix
     */
    public function __construct(
        private string $namespace = '',
        private string $interfaceSuffix = 'ClientInterface',
        private array $ignoreParameters = [],
        private array $ignoreMethods = [],
        private ?string $removeMethodPrefix = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function buildInterfaces(OpenApi $specification): array
    {
        $classes = [];

        foreach ($this->getTagsFromSpecification($specification) as $tag) {
            $interfaceName = StringHelper::studly($tag->name).($this->interfaceSuffix);
            $classes[$interfaceName] = $this->createPhpFile(
                $specification,
                $tag,
                $this->namespace,
                $interfaceName
            );
        }

        return $classes;
    }

    protected function getTagsFromSpecification(OpenApi $openApi): array
    {
        $tags = $openApi->tags;

        if (!empty($tags)) {
            return $tags;
        }

        // If there's no tags, we'll try to get the tags from the paths.
        foreach ($openApi->paths as $pathItem) {
            foreach ($pathItem->getOperations() as $operation) {
                $tags = [...$tags, ...$operation->tags];
            }
        }

        // Finally we'll transform the string tags into an array of openapi tag objects with a generic description.
        return array_map(fn(string $tag) => new Tag([
            'name' => $tag,
            'description' => 'No description available.'.PHP_EOL,
        ]), array_unique($tags));
    }

    /**
     * @throws \Exception
     */
    protected function createPhpFile(
        OpenApi $specification,
        Tag $tag,
        string $namespace,
        string $interfaceName
    ): PhpFile {
        $phpFile = new PhpFile();
        $phpNamespace = $phpFile->addNamespace($namespace);
        $class = $phpNamespace->addInterface($interfaceName);
        $phpFile->addComment(self::PHP_FILE_COMMENT);

        if ($tag->description) {
            $class->addComment($tag->description.PHP_EOL.PHP_EOL);
        }
        if ($tag->externalDocs) {
            $class->addComment("@see {$tag->externalDocs->url} {$tag->externalDocs->description}");
        }
        if ($specification->externalDocs) {
            $class->addComment("@see {$specification->externalDocs->url} {$specification->externalDocs->description}");
        }
        foreach ($specification->paths as $url => $pathItem) {
            foreach ($pathItem->getOperations() as $verbName => $pathOperation) {
                if (!isset($pathOperation->tags[0])) {
                    throw new Exception("Path operation with no tags are not allowed.");
                } elseif (!$pathOperation->operationId) {
                    throw new Exception("Could not generate method for [$verbName] $url. Reason: Missing operationId.");
                } elseif (
                    in_array($pathOperation->operationId, $this->ignoreMethods, true)
                    || $pathOperation->tags[0] !== $tag->name
                ) {
                    continue;
                }

                $this->addMethod(
                    $class,
                    (string) $url,
                    $this->getVerbAttribute($verbName),
                    $pathItem,
                    $pathOperation,
                    $specification
                );
            }
        }
        foreach ($this->uses as $use) {
            $phpNamespace->addUse($use);
        }
        $this->uses = [];
        return $phpFile;
    }

    /**
     * @param \Nette\PhpGenerator\ClassType                            $class
     * @param string                                                   $url
     * @param class-string<\Waffler\Waffler\Attributes\Contracts\Verb> $verbName
     * @param PathItem                                                 $pathItem
     * @param \cebe\openapi\spec\Operation                             $pathOperation
     * @param \cebe\openapi\spec\OpenApi                               $openApi
     *
     * @return void
     * @throws \cebe\openapi\exceptions\TypeErrorException
     * @throws \Exception
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    protected function addMethod(
        ClassType $class,
        string $url,
        string $verbName,
        PathItem $pathItem,
        Operation $pathOperation,
        OpenApi $openApi,
    ): void {
        $methodName = StringHelper::camelCase($this->removePathOperationIdPrefix($pathOperation->operationId));
        $method = $class->addMethod($methodName);
        if ($pathItem->description) {
            $method->addComment($pathOperation->description);
        }
        if ($pathOperation->description) {
            $method->addComment($pathOperation->description);
        }
        $this->addUse($verbName);
        $method->addAttribute($verbName, [$url]);

        $this->addRequestBody($method, $pathOperation);

        $this->addAuthorizationParameters($method, $pathOperation, $openApi);

        foreach ($pathOperation->parameters as $parameter) {
            if (!$this->mustIncludeParameter($parameter->in, $parameter->name)) {
                continue;
            }
            $phpParameter = $this->addParameter($method, $parameter);
            $this->annotateParameter($parameter, $phpParameter);
        }
        $this->orderParametersByRequirement($method);

        if ($pathOperation->externalDocs) {
            $method->addComment("@see {$pathOperation->externalDocs->url} {$pathOperation->externalDocs->description}");
        }
        if ($pathOperation->deprecated) {
            $method->addComment("@deprecated This method is deprecated.");
        }

        $this->addReturnType($method, $pathOperation);

        $method->addComment("@throws \\".ClientException::class);
        $method->addComment("@throws \\".ServerException::class);
        $method->addComment("@throws \\".ConnectException::class);
        $method->addComment("@throws \\".TooManyRedirectsException::class);
    }

    protected function removePathOperationIdPrefix(string $pathOperationId): string
    {
        $methodPrefixRegex = $this->removeMethodPrefix;
        if (!$methodPrefixRegex) {
            return $pathOperationId;
        }

        if (
            str_starts_with($methodPrefixRegex, '/')
            && str_ends_with($methodPrefixRegex, '/')
            && strlen($methodPrefixRegex) > 2
        ) {
            return (string) preg_replace($methodPrefixRegex, '', $pathOperationId);
        }

        return str_replace($methodPrefixRegex, '', $pathOperationId);
    }

    /**
     * @param class-string $object
     *
     * @return void
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    protected function addUse(string $object): void
    {
        $this->uses[] = $object;
    }

    // helpers

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    protected function addRequestBody(Method $method, Operation $operation): void
    {
        if (!$operation->requestBody) {
            return;
        }

        $mimeTypes = array_keys($operation->requestBody->content);
        $hasMimeTypes = count($mimeTypes) !== 0;
        if (!$hasMimeTypes) {
            return;
        }

        $contentType = $operation->requestBody->content[$mimeTypes[0]];
        $contentSchema = $contentType->schema;
        $parameter = new Parameter([
            'name' => 'requestBody',
            'schema' => new Schema([
                'type' => $contentSchema->type,
                'description' => $contentSchema->description,
                'nullable' => $contentSchema->nullable,
                'required' => $contentSchema->required
            ])
        ]);
        $phpParameter = $this->addParameter($method, $parameter);

        if ($mimeTypes[0] === 'application/json') {
            $this->addUse(Json::class);
            $phpParameter->addAttribute(Json::class);
        } else {
            $this->addUse(Body::class);
            $phpParameter->addAttribute(Body::class, [$mimeTypes[0]]);
        }
    }

    protected function addParameter(
        Method $method,
        Parameter $parameter
    ): PhpParameter {
        $paramName = StringHelper::camelCase($parameter->name);
        $phpParameter = $method->addParameter($paramName);
        $parameterSchema = $parameter->schema;
        $paramType = $this->getParameterType($parameterSchema?->type);
        $allowNull = !is_null($parameterSchema)
            && !$parameterSchema->required
            && $this->allowsNullForType($parameterSchema?->type);
        $defaultValue = $parameterSchema?->default;
        if ($allowNull) {
            $paramType = "null|$paramType";
            $phpParameter->setDefaultValue($defaultValue);
        } elseif (!is_null($defaultValue)) {
            $phpParameter->setDefaultValue($defaultValue);
        }

        $method->addComment("@param $paramType \$$paramName {$parameter?->description}");
        $phpParameter->setType($paramType);
        return $phpParameter;
    }

    protected function getParameterType(?string $type): string
    {
        return (string) match (is_string($type) ? strtolower($type) : $type) {
            'integer', 'number', 'numeric' => 'int',
            'object', 'json', 'array' => 'array',
            'apikey', 'basic', 'file', null => 'string',
            'boolean' => 'bool',
            default => $type
        };
    }

    protected function allowsNullForType(?string $typeName): bool
    {
        if (in_array($typeName, ['apiKey', 'basic', 'oauth2', 'oauth'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param \Nette\PhpGenerator\Method   $method
     * @param \cebe\openapi\spec\Operation $pathOperation
     * @param \cebe\openapi\spec\OpenApi   $openApi
     *
     * @return void
     * @throws \cebe\openapi\exceptions\TypeErrorException
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    protected function addAuthorizationParameters(Method $method, Operation $pathOperation, OpenApi $openApi): void
    {
        $globalRequirements = $openApi->security;
        $operationRequirements = $pathOperation->security;
        $allRequirements = array_merge((array) $operationRequirements, (array) $globalRequirements);

        foreach ($allRequirements as $securityRequirement) {
            $requirements = json_decode(json_encode($securityRequirement->getSerializableData()), true);

            foreach (array_keys($requirements) as $name) {
                $this->addSecurityRequirementParameter($method, $openApi, $name);
            }
        }
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     * @throws \Exception
     */
    protected function addSecurityRequirementParameter(Method $method, OpenApi $openApi, string $securityName): void
    {
        $securityRequirement = $openApi->components->securitySchemes[$securityName];

        $secReqPlace = $securityRequirement->in ?? 'header';
        $param = new Parameter([
            'name' => $securityRequirement->name,
            'schema' => new Schema([
                'type' => in_array($secReqPlace, ['header', 'query'], true) ? 'string' : 'mixed',
                'required' => true
            ]),
            'in' => $secReqPlace,
            'description' => $securityRequirement->description ?? 'Authorization'
        ]);

        if (!$this->mustIncludeParameter($secReqPlace, $param->name)) {
            return;
        }

        $phpParam = $this->addParameter($method, $param);
        if ($param->in === 'query') {
            $phpParam->addAttribute(QueryParam::class, [$param->name]);
        } elseif ($param->in === 'header') {
            $attribute = match ($securityRequirement->scheme) {
                'bearer' => Bearer::class,
                'basic' => Basic::class,
                'digest' => Digest::class,
                default => HeaderParam::class
            };
            $this->addUse($attribute);
            if ($attribute === HeaderParam::class) {
                $phpParam->addAttribute($attribute, [$param->name]);
            } else {
                $phpParam->addAttribute($attribute);
            }
        } else {
            throw new Exception("Unsupported security scheme.");
        }
    }

    protected function mustIncludeParameter(string $in, int|string|array $search): bool
    {
        $search = arrayWrap($search);

        if (!isset($this->ignoreParameters[$in])) {
            return true;
        }

        $options = arrayWrap($this->ignoreParameters[$in]);

        foreach ($search as $searchedValue) {
            if (in_array($searchedValue, $options, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws \Exception
     */
    protected function annotateParameter(
        Parameter $parameter,
        PhpParameter $phpParameter,
    ): void {
        switch ($parameter->in) {
            case 'query':
            {
                $this->addUse(QueryParam::class);
                $phpParameter->addAttribute(QueryParam::class, [$parameter->name]);
                break;
            }
            case 'header':
            {
                $this->addUse(HeaderParam::class);
                $phpParameter->addAttribute(HeaderParam::class, [$parameter->name]);
                break;
            }
            case 'path':
            {
                $this->addUse(PathParam::class);
                $phpParameter->addAttribute(PathParam::class, [$parameter->name]);
                break;
            }
            case 'formData':
            {
                $this->addUse(FormParam::class);
                $phpParameter->addAttribute(FormParam::class, [$parameter->name]);
                break;
            }
            default:
            {
                throw new Exception("Unknown parameter position \"$parameter->in\"");
            }
        }
    }

    private function orderParametersByRequirement(Method $method): void
    {
        $parameters = $method->getParameters();
        uasort(
            $parameters,
            function (PhpParameter $first, PhpParameter $second): int {
                if (!$first->hasDefaultValue() && !$second->hasDefaultValue()) {
                    return 0;
                } elseif ($first->hasDefaultValue() && !$second->hasDefaultValue()) {
                    return 1;
                } else {
                    return -1;
                }
            }
        );
        $method->setParameters($parameters);
    }

    protected function addReturnType(Method $method, Operation $pathOperation): void
    {
        foreach ($pathOperation->responses ?? [] as $statusCode => $response) {
            $statusCode = (int) $statusCode;

            if ($statusCode >= 400) {
                continue;
            }

            foreach ($response->content as $mimeType => $mediaType) {
                if (
                    !str_contains($mimeType, 'json')
                    && !in_array(($mediaType->schema->type ?? null), ['array', 'object'], true)
                ) {
                    continue;
                }
                $this->addUse(Produces::class);
                $method->addAttribute(Produces::class, [$mimeType]);
                $method->setReturnType('array');
                $method->addComment("@return array $response->description");
                return;
            }
        }

        $this->addUse(ResponseInterface::class);
        $method->setReturnType(ResponseInterface::class);
        $method->addComment("@return \Psr\Http\Message\ResponseInterface");
    }

    /**
     * @param string $verb
     *
     * @return class-string<\Waffler\Waffler\Attributes\Contracts\Verb>
     * @throws \Exception
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    protected function getVerbAttribute(string $verb): string
    {
        return match ($verb) {
            'get' => Get::class,
            'post' => Post::class,
            'put' => Put::class,
            'patch' => Patch::class,
            'delete' => Delete::class,
            'head' => Head::class,
            'options' => Options::class,
            default => throw new Exception("Unknown operation type \"$verb\"")
        };
    }

    /**
     * @param \cebe\openapi\spec\Operation $operation
     * @param string|array<string>         $mediaTypes
     *
     * @return bool
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    #[Pure]
    protected function operationHasAnyMediaTypes(Operation $operation, string|array $mediaTypes): bool
    {
        $mediaTypes = arrayWrap($mediaTypes);

        $operationKeys = array_keys($operation->requestBody?->content ?? []);

        foreach ($mediaTypes as $mediaType) {
            if (in_array($mediaType, $operationKeys, true)) {
                return true;
            }
        }

        return false;
    }
}
