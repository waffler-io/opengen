<?php

/*
 * This file is part of Waffler.
 *
 * (c) Erick Johnson Almeida de Menezes <erickmenezes.dev@gmail.com>
 *
 * This source file is subject to the MIT licence that is bundled
 * with this source code in the file LICENCE.
 */

namespace Waffler\OpenGen\Pipeline\Stages;

use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Reference;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use JetBrains\PhpStorm\Pure;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;
use Psr\Http\Message\ResponseInterface;
use Waffler\Attributes\Request\Body;
use Waffler\Attributes\Request\FormParam;
use Waffler\Attributes\Request\HeaderParam;
use Waffler\Attributes\Request\Json;
use Waffler\Attributes\Request\PathParam;
use Waffler\Attributes\Request\QueryParam;
use Waffler\Attributes\Verbs\Delete;
use Waffler\Attributes\Verbs\Get;
use Waffler\Attributes\Verbs\Head;
use Waffler\Attributes\Verbs\Options;
use Waffler\Attributes\Verbs\Patch;
use Waffler\Attributes\Verbs\Post;
use Waffler\Attributes\Verbs\Put;
use Waffler\Pipeline\Contracts\StageInterface;

use function Waffler\arrayWrap;

/**
 * Class GenerateWafflerInterfacesForEachArrayKey.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
class GenerateWafflerInterfacesForEachArrayKey implements StageInterface
{
    private const PHP_FILE_COMMENT = <<<EOL
This file is auto generated by Waffler OpenAPI Code generator package.

Do not modify unless you know exactly what you are doing.
EOL;

    /**
     * @param string                $namespace
     * @param array<string, string> $options
     */
    public function __construct(
        private string $namespace,
        private array $options
    ) {
    }

    /**
     * @param \cebe\openapi\spec\OpenApi $value
     *
     * @return array<int, string>
     * @throws \Exception
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function handle(mixed $value): array
    {
        $classes = [];

        foreach ($value->tags as $tag) {
            $phpFile = new PhpFile();
            $phpFile->addComment(self::PHP_FILE_COMMENT);
            $phpNamespace = $phpFile->addNamespace($this->namespace);

            $interfaceName = $this->convertTagToInterfaceName($tag->name);
            $class = $phpNamespace->addInterface($interfaceName);
            if ($tag->description) {
                $class->addComment($tag->description);
            }
            if ($tag->externalDocs) {
                $class->addComment("@see {$tag->externalDocs->url} {$tag->externalDocs->description}");
            }

            foreach ($value->paths as $url => $pathItem) {
                foreach ($pathItem->getOperations() as $verbName => $pathOperation) {
                    if (!isset($pathOperation->tags[0])) {
                        throw new Exception("Path operation with no tags are not allowed.");
                    }

                    if ($pathOperation->tags[0] !== $tag->name) {
                        continue;
                    }

                    $this->addMethod(
                        $phpNamespace,
                        $class,
                        (string) $url,
                        (string) $verbName,
                        $pathItem,
                        $pathOperation
                    );
                }
            }

            $classes[$interfaceName] = (new PsrPrinter())->printFile($phpFile);
        }

        return $classes;
    }

    /**
     * @throws \Exception
     */
    private function addMethod(
        PhpNamespace $phpNamespace,
        ClassType $class,
        string $url,
        string $verbName,
        PathItem $pathItem,
        Operation $pathOperation
    ): void {
        if (! $pathOperation->operationId) {
            throw new Exception("Could not generate method for [$verbName] $url. Reason: Missing operationId.");
        }

        $method = $class->addMethod($this->toFunctionName($this->removePathOperationIdPrefix($pathOperation->operationId)));
        $method->addComment(($pathOperation->description ?: 'No description available.')."\n");
        $method->setPublic();
        if ($pathItem->description) {
            $method->addComment($pathItem->description);
        }
        $verbAttribute = match ($verbName) {
            'get' => Get::class,
            'post' => Post::class,
            'put' => Put::class,
            'patch' => Patch::class,
            'delete' => Delete::class,
            'head' => Head::class,
            'options' => Options::class,
            default => throw new Exception("Unknown operation type \"$verbName\"")
        };
        $phpNamespace->addUse($verbAttribute);
        $method->addAttribute($verbAttribute, [$url]);

        $this->addAuthorizationParameters($pathOperation, $phpNamespace, $method);

        foreach ($pathOperation->parameters as $parameter) {
            $parameter = $parameter instanceof Reference ? $parameter->resolve() : $parameter;
            $this->addParameter(
                $phpNamespace,
                $method,
                $pathOperation,
                //@phpstan-ignore-next-line
                $parameter,
            );
        }

        $this->addRequestBodyParameter($pathOperation, $phpNamespace, $method);

        // @phpstan-ignore-next-line
        $returns200Json = isset($pathOperation->responses['200']->content['application/json']);

        if ($returns200Json || $this->hasJsonInMimeTypes($pathOperation->getSerializableData()?->produces ?? [])) {
            $method->setReturnType('array');
            $method->addComment("\n@return array");
        } else {
            $phpNamespace->addUse(ResponseInterface::class);
            $method->setReturnType(ResponseInterface::class);
            $method->addComment("\n@return \\".ResponseInterface::class);
        }
        $method->addComment('@throws \\'.ClientException::class.' Exception when a client error is encountered (4xx codes).');
        $method->addComment('@throws \\'.ServerException::class.' Exception when a server error is encountered (5xx codes).');
        $method->addComment('@throws \\'.ConnectException::class.' Exception thrown when a connection cannot be established.');

        if ($pathOperation->deprecated) {
            $method->addComment("@deprecated This method is deprecated and will be removed soon.");
        }
    }

    /**
     * @throws \Exception
     */
    private function addParameter(
        PhpNamespace $phpNamespace,
        Method $method,
        Operation $pathOperation,
        Parameter $parameter
    ): void {
        if (!$this->mustIncludeParameter($parameter->in, $parameter->name)) {
            return;
        }

        $parameterName = $this->toFunctionName($parameter->name);
        if (array_key_exists($parameterName, $method->getParameters())) {
            return;
        }
        $phpParameter = $method->addParameter($parameterName);

        try {
            $parameterType = $parameter->schema?->type ?? $parameter->type ?? null;
        } catch (Exception) {
            $parameterType = null;
        }

        $allowsNullForType = $this->allowsNullForType($parameterType);

        if (!$parameter->required && $allowsNullForType) {
            $phpParameter->setNullable();
        }

        switch ($parameter->in) {
            case 'body':
            {
                if ($this->bodyMustBeJsonType($parameterType, $pathOperation)) {
                    $phpNamespace->addUse(Json::class);
                    $phpParameter->addAttribute(Json::class);
                    $phpParameter->setType('array');
                    $parameterType = 'array';
                } else {
                    $phpNamespace->addUse(Body::class);
                    $phpParameter->addAttribute(Body::class);
                    $phpParameter->setType('string');
                    $parameterType = 'string';
                }
                break;
            }
            case 'query':
            {
                $phpNamespace->addUse(QueryParam::class);
                $phpParameter->addAttribute(QueryParam::class, [$parameter->name]);
                $phpParameter->setType($parameterType = $this->getParameterType($parameterType));
                break;
            }
            case 'header':
            {
                $phpNamespace->addUse(HeaderParam::class);
                $phpParameter->addAttribute(HeaderParam::class, [$parameter->name]);
                $phpParameter->setType($parameterType = $this->getParameterType($parameterType));
                break;
            }
            case 'path':
            {
                $phpNamespace->addUse(PathParam::class);
                $phpParameter->addAttribute(PathParam::class, [$parameter->name]);
                $phpParameter->setType($parameterType = $this->getParameterType($parameterType));
                break;
            }
            case 'formData':
            {
                $phpNamespace->addUse(FormParam::class);
                $phpParameter->addAttribute(FormParam::class, [$parameter->name]);
                $phpParameter->setType($parameterType = $this->getParameterType($parameterType));
                $parameterType = 'string';
                break;
            }
            default:
            {
                throw new Exception("Unknown parameter position \"$parameter->in\"");
            }
        }
        $notRequiredParamType = !$parameter->required && $allowsNullForType
            ? 'null|'
            : '';
        $method->addComment("@param $notRequiredParamType$parameterType \$$parameterName $parameter->description");
    }

    private function convertTagToInterfaceName(string $tag): string
    {
        return ucfirst($this->toFunctionName($tag)).'ClientInterface';
    }

    private function toFunctionName(string $name): string
    {
        $pieces = explode(' ', str_replace(['-', '_', '/', '\\'], ' ', $name));

        $newPieces = [];
        foreach ($pieces as $piece) {
            $newPieces[] = str_replace([',', ';', '#', '@', '$'], '', ucfirst($piece));
        }

        return lcfirst(implode('', $newPieces));
    }

    private function getParameterType(?string $type): string
    {
        return (string) match (is_string($type) ? strtolower($type) : $type) {
            'integer', 'number', 'numeric' => 'int',
            'object', 'json', 'array' => 'array',
            'apikey', 'basic', 'file', null => 'string',
            'boolean' => 'bool',
            default => $type
        };
    }

    /**
     * @param \cebe\openapi\spec\Operation     $pathOperation
     * @param \Nette\PhpGenerator\PhpNamespace $phpNamespace
     * @param \Nette\PhpGenerator\Method       $method
     *
     * @return void
     * @throws \cebe\openapi\exceptions\TypeErrorException
     * @throws \Exception
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    private function addAuthorizationParameters(
        Operation $pathOperation,
        PhpNamespace $phpNamespace,
        Method $method
    ): void {
        foreach ($pathOperation->security ?? [] as $securityRequirement) {
            $securityRequirementList = json_decode(json_encode($securityRequirement->getSerializableData()), true);

            /** @var \cebe\openapi\spec\OpenApi $baseDocument */
            $baseDocument = $securityRequirement->getBaseDocument();
            foreach ($baseDocument->securityDefinitions ?? [] as $securityDefinitionName => $securityDefinition) {
                if (in_array(
                    $securityDefinition['type'],
                    ['oauth', 'oauth2'],
                    true
                ) || !isset($securityRequirementList[$securityDefinitionName])) {
                    continue;
                }
                $this->addParameter(
                    $phpNamespace,
                    $method,
                    $pathOperation,
                    new Parameter($securityDefinition)
                );
            }
        }
    }

    private function allowsNullForType(?string $typeName): bool
    {
        if (in_array($typeName, ['apiKey', 'basic', 'oauth2', 'oauth'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param mixed                        $parameterType
     * @param \cebe\openapi\spec\Operation $pathOperation
     *
     * @return bool
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    private function bodyMustBeJsonType(mixed $parameterType, Operation $pathOperation): bool
    {
        return in_array($parameterType, ['array', 'object'], true)
            || $this->hasJsonInMimeTypes($pathOperation->getSerializableData()?->consumes ?? []);
    }

    /**
     * @param array<string> $mimes
     *
     * @return bool
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    private function hasJsonInMimeTypes(array $mimes): bool
    {
        foreach ($mimes as $mime) {
            if (str_contains(strtolower($mime), 'json')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $in
     * @param int|string|array<string|int> $search
     *
     * @return bool
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    #[Pure]
    private function mustIncludeParameter(string $in, int|string|array $search): bool
    {
        $search = arrayWrap($search);
        $options = arrayWrap($this->options['ignore']['parameters'][$in] ?? []); //@phpstan-ignore-line

        foreach ($search as $searchedValue) {
            if (in_array($searchedValue, $options, true)) {
                return false;
            }
        }

        return true;
    }

    private function removePathOperationIdPrefix(string $pathOperationId): string
    {
        $methodPrefixRegex = $this->options['remove_method_prefix'] ?? false;
        if (! $methodPrefixRegex) {
            return $pathOperationId;
        }

        if (
            str_starts_with($methodPrefixRegex, '/')
            && str_ends_with($methodPrefixRegex, '/')
            && strlen($methodPrefixRegex) > 2
        ) {
            return (string)preg_replace($methodPrefixRegex, '', $pathOperationId);
        }

        return str_replace($methodPrefixRegex, '', $pathOperationId);
    }

    /**
     * @param \cebe\openapi\spec\Operation     $pathOperation
     * @param \Nette\PhpGenerator\PhpNamespace $phpNamespace
     * @param \Nette\PhpGenerator\Method       $method
     *
     * @return void
     * @throws \cebe\openapi\exceptions\TypeErrorException
     * @throws \Exception
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    private function addRequestBodyParameter(Operation $pathOperation, PhpNamespace $phpNamespace, Method $method): void
    {
        if (!$requestBody = $pathOperation->requestBody) {
            return;
        }

        //@phpstan-ignore-next-line
        if ($requestBody?->content['application/json'] ?? false) {
            $parameter = new Parameter([
                'name' => 'requestBody',
                'in' => 'body',
                'type' => 'object',
                'description' => $pathOperation->requestBody->description, //@phpstan-ignore-line
                'required' => $pathOperation->requestBody->required //@phpstan-ignore-line
            ]);
        } else {
            $parameter = new Parameter([
                'name' => 'requestBody',
                'in' => 'body',
                'type' => 'string',
                'description' => $pathOperation->requestBody->description, //@phpstan-ignore-line
                'required' => $pathOperation->requestBody->required //@phpstan-ignore-line
            ]);
        }

        $this->addParameter(
            $phpNamespace,
            $method,
            $pathOperation,
            $parameter
        );
    }
}
