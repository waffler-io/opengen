<?php

/*
 * This file is part of Waffler.
 *
 * (c) Erick Johnson Almeida de Menezes <erickmenezes.dev@gmail.com>
 *
 * This source file is subject to the MIT licence that is bundled
 * with this source code in the file LICENCE.
 */

namespace Waffler\OpenGen\SpecificationTypes\OpenApi\V3;

use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Schema;
use cebe\openapi\spec\Tag;
use Exception;
use JetBrains\PhpStorm\Pure;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\Parameter as PhpParameter;
use Nette\PhpGenerator\PhpFile;
use Psr\Http\Message\ResponseInterface;
use Waffler\Attributes\Auth\Basic;
use Waffler\Attributes\Auth\Bearer;
use Waffler\Attributes\Auth\Digest;
use Waffler\Attributes\Request\Body;
use Waffler\Attributes\Request\Consumes;
use Waffler\Attributes\Request\FormParam;
use Waffler\Attributes\Request\HeaderParam;
use Waffler\Attributes\Request\Json;
use Waffler\Attributes\Request\PathParam;
use Waffler\Attributes\Request\Produces;
use Waffler\Attributes\Request\QueryParam;
use Waffler\Attributes\Verbs\Delete;
use Waffler\Attributes\Verbs\Get;
use Waffler\Attributes\Verbs\Head;
use Waffler\Attributes\Verbs\Options;
use Waffler\Attributes\Verbs\Patch;
use Waffler\Attributes\Verbs\Post;
use Waffler\Attributes\Verbs\Put;
use Waffler\OpenGen\StringHelper;

use function Waffler\arrayWrap;

class InterfaceBuilder implements \Waffler\OpenGen\Contracts\InterfaceBuilder
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        protected array $options = [],
        protected array $uses = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    public function buildInterfaces(OpenApi $specification): array
    {
        $classes = [];

        foreach ($specification->tags as $tag) {
            $interfaceName = StringHelper::studly($tag->name).($this->options['interface_suffix'] ?? 'ClientInterface');
            $classes[$interfaceName] = $this->createPhpFile(
                $specification,
                $tag,
                $this->options['namespace'],
                $interfaceName
            );
        }

        return $classes;
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
            $class->addComment($tag->description.PHP_EOL);
        }
        if ($tag->externalDocs) {
            $class->addComment("@see {$tag->externalDocs->url} {$tag->externalDocs->description}");
        }
        if ($specification->externalDocs) {
            $class->addComment("@see {$specification->externalDocs->url} {$specification->externalDocs->description}");
        }
        $methodsToIgnore = arrayWrap($this->options['ignore']['methods'] ?? []);
        foreach ($specification->paths as $url => $pathItem) {
            foreach ($pathItem->getOperations() as $verbName => $pathOperation) {
                if (!isset($pathOperation->tags[0])) {
                    throw new Exception("Path operation with no tags are not allowed.");
                } elseif (!$pathOperation->operationId) {
                    throw new Exception("Could not generate method for [$verbName] $url. Reason: Missing operationId.");
                } elseif (
                    in_array($pathOperation->operationId, $methodsToIgnore, true)
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
        return $phpFile;
    }

    /**
     * @param \Nette\PhpGenerator\ClassType                    $class
     * @param string                                           $url
     * @param class-string<\Waffler\Attributes\Contracts\Verb> $verbName
     * @param mixed                                            $pathItem
     * @param \cebe\openapi\spec\Operation                     $pathOperation
     * @param \cebe\openapi\spec\OpenApi                       $openApi
     *
     * @return void
     * @throws \cebe\openapi\exceptions\TypeErrorException
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
            $this->annotateParameter(
                $parameter,
                $phpParameter,
            );
        }
        $this->orderParametersByRequirement($method);

        if ($pathOperation->externalDocs) {
            $method->addComment("@see {$pathOperation->externalDocs->url} {$pathOperation->externalDocs->description}");
        }

        $this->addReturnType($method, $pathOperation);
    }

    /**
     * @throws \cebe\openapi\exceptions\TypeErrorException
     */
    protected function addRequestBody(Method $method, Operation $operation): void
    {
        if (!$operation->requestBody) {
            return;
        }

        //@phpstan-ignore-next-line
        $mimeTypes = array_keys($operation->requestBody->content);
        $hasMimeTypes = count($mimeTypes) !== 0;
        if (!$hasMimeTypes) {
            return;
        }

        $this->addUse(Consumes::class);
        $method->addAttribute(Consumes::class, [$mimeTypes[0]]);
        $contentType = $operation->requestBody->content[$mimeTypes[0]]; //@phpstan-ignore-line
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
            $phpParameter->addAttribute(Body::class);
        }
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
                    && !in_array(($mediaType->schema->type ?? null), ['array', 'object'], true) //@phpstan-ignore-line
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

    // helpers

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

    /**
     * @param string $verb
     *
     * @return class-string<\Waffler\Attributes\Contracts\Verb>
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
     * @param string                       $in
     * @param int|string|array<string|int> $search
     *
     * @return bool
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */

    #[Pure]
    protected function mustIncludeParameter(string $in, int|string|array $search): bool
    {
        $search = arrayWrap($search);
        $options = arrayWrap($this->options['ignore']['parameters'][$in] ?? []);

        foreach ($search as $searchedValue) {
            if (in_array($searchedValue, $options, true)) {
                return false;
            }
        }

        return true;
    }

    protected function allowsNullForType(?string $typeName): bool
    {
        if (in_array($typeName, ['apiKey', 'basic', 'oauth2', 'oauth'], true)) {
            return false;
        }

        return true;
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

    protected function removePathOperationIdPrefix(string $pathOperationId): string
    {
        $methodPrefixRegex = $this->options['remove_method_prefix'] ?? false;
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

    protected function addParameter(
        Method $method,
        Parameter $parameter
    ): PhpParameter {
        $paramName = StringHelper::camelCase($parameter->name);
        $phpParameter = $method->addParameter($paramName);
        $paramType = $this->getParameterType($parameter->schema->type);
        $allowNull = !$parameter->schema->required && $this->allowsNullForType($parameter->schema->type);
        $defaultValue = $parameter->schema->default;
        if ($allowNull) {
            $paramType = "null|$paramType";
            $phpParameter->setDefaultValue($defaultValue);
        } elseif (!is_null($defaultValue)) {
            $phpParameter->setDefaultValue($defaultValue);
        }

        $method->addComment("@param $paramType \$$paramName $parameter->description");
        $phpParameter->setType($paramType);
        return $phpParameter;
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
        $globalRequirements = $openApi->security ?? [];
        $operationRequirements = $pathOperation->security ?? [];

        foreach ([...$operationRequirements, ...$globalRequirements] as $securityRequirement) {
            $requirements = json_decode(json_encode($securityRequirement->getSerializableData()), true);

            foreach ($requirements as $name => $scopes) {
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
        $securityRequirement = $openApi->components->securitySchemes[$securityName]; //@phpstan-ignore-line

        $secReqPlace = $securityRequirement->in ?? 'header'; //@phpstan-ignore-line
        $param = new Parameter([
            'name' => $securityRequirement->name, //@phpstan-ignore-line
            'schema' => new Schema([
                'type' => in_array($secReqPlace, ['header', 'query'], true) ? 'string' : 'mixed',
                'required' => true
            ]),
            'in' => $secReqPlace,
            'description' => $securityRequirement->description ?? 'Authorization' //@phpstan-ignore-line
        ]);

        $phpParam = $this->addParameter($method, $param);
        if ($param->in === 'query') {
            $phpParam->addAttribute(QueryParam::class, [$param->name]);
        } elseif ($param->in === 'header') {
            $attribute = match ($securityRequirement->scheme) { //@phpstan-ignore-line
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
}
