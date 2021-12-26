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

    public function __construct(
        private string $namespace
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
            $class->addComment($tag->description);
            if ($tag->externalDocs) {
                $class->addComment("@see {$tag->externalDocs->url} {$tag->externalDocs->description}");
            }

            foreach ($value->paths as $url => $pathItem) {
                foreach ($pathItem->getOperations() as $verbName => $pathOperation) {
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
        $method = $class->addMethod($this->toFunctionName($pathOperation->operationId));
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
                //@phpstan-ignore-next-line
                $parameter,
            );
        }

        if (in_array('application/json', $pathOperation->getSerializableData()?->produces ?? [], true)) {
            $method->setReturnType('array');
            $method->addComment("\n@return array");
        } else {
            $phpNamespace->addUse(ResponseInterface::class);
            $method->setReturnType(ResponseInterface::class);
            $method->addComment("\n@return ".ResponseInterface::class);
        }
    }

    /**
     * @throws \Exception
     */
    private function addParameter(
        PhpNamespace $phpNamespace,
        Method $method,
        Parameter $parameter
    ): void {
        $parameterName = $this->toFunctionName($parameter->name);
        if (array_key_exists($parameterName, $method->getParameters())) {
            return;
        }
        $phpParameter = $method->addParameter($parameterName);

        try {
            $parameterType = $parameter->type;
        } catch (Exception) {
            $parameterType = $parameter->schema->type ?? null;
        }

        $allowsNullForType = $this->allowsNullForType($parameterType);

        if (!$parameter->required && $allowsNullForType) {
            $phpParameter->setNullable();
        }

        switch ($parameter->in) {
            case 'body':
            {
                if (in_array($parameterType, ['array', 'object'], true)) {
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
                $phpParameter->setType('string');
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
        $pieces = explode(' ', str_replace(['-', '_'], ' ', $name));

        $newPieces = [];
        foreach ($pieces as $piece) {
            $newPieces[] = str_replace([',', ';', '#', '@', '$', '/', '\\'], '', ucfirst(strtolower($piece)));
        }

        return lcfirst(implode('', $newPieces));
    }

    private function getParameterType(?string $type): string
    {
        return (string) match (is_string($type) ? strtolower($type) : $type) {
            'integer', 'int', 'number', 'numeric' => 'int',
            'object', 'json', 'array' => 'array',
            'null', null, '' => 'null|string',
            'apikey', 'basic' => 'string',
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

            foreach ($securityRequirement->getBaseDocument()->securityDefinitions as $securityDefinitionName => $securityDefinition) {
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
}
