<?php

/*
 * This file is part of Waffler.
 *
 * (c) Erick Johnson Almeida de Menezes <erickmenezes.dev@gmail.com>
 *
 * This source file is subject to the MIT licence that is bundled
 * with this source code in the file LICENCE.
 */

namespace Waffler\Opengen\Pipeline\Stages;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Waffler\Attributes\Auth\Basic;
use Waffler\Attributes\Request\FormData;
use Waffler\Attributes\Request\HeaderParam;
use Waffler\Attributes\Request\Headers;
use Waffler\Attributes\Request\Json;
use Waffler\Attributes\Request\PathParam;
use Waffler\Attributes\Request\Query;
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
    public function __construct(
        private string $namespace
    ) {
    }

    /**
     * @param array<non-empty-string, array<string, mixed>> $value
     *
     * @return array
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function handle(mixed $value): array
    {
        $classes = [];

        foreach ($value as $tag => $pathItems) {
            $className = $this->convertTagToInterfaceName($tag);

            $phpFile = new PhpFile();
            $phpFile->addComment("This file is auto generated by Waffler OpenAPI Code Generator, do not modify.\n\nIs recommended to add this file/folder to gitignore.");
            $phpNamespace = new PhpNamespace($this->namespace);
            $phpFile->addNamespace($phpNamespace);
            $class = $phpNamespace->addInterface($className);
            foreach ($pathItems as $pathDefinition) {
                /** @var \cebe\openapi\spec\PathItem $path */
                $path = $pathDefinition['path'];

                /** @var \cebe\openapi\spec\Operation $operation */
                $operation = $pathDefinition['operation'];

                $verb = match (true) {
                    !is_null($path->get) => Get::class,
                    !is_null($path->post) => Post::class,
                    !is_null($path->put) => Put::class,
                    !is_null($path->patch) => Patch::class,
                    !is_null($path->delete) => Delete::class,
                    !is_null($path->head) => Head::class,
                    !is_null($path->options) => Options::class,
                };
                $phpNamespace->addUse($verb);

                $method = $class->addMethod(
                    empty($operation->operationId)
                        ? $this->toFunctionName($pathDefinition['url'])
                        : $operation->operationId
                );
                $method->addAttribute($verb, [$pathDefinition['url']]);

                $method->addComment($operation->description."\n\n");

                $argumentTypes = [];
                foreach ($operation->parameters as $operationParameter) {
                    if ($operationParameter->in === 'body') {
                        $argumentTypes[Json::class] = ['type' => 'array', 'name' => 'body'];
                        $method->addComment("@param array \$body {$operationParameter->description}");
                        $phpNamespace->addUse(Json::class);
                    } elseif ($operationParameter->in === 'query') {
                        $argumentTypes[Query::class] = ['type' => 'array', 'name' => 'query'];
                        $method->addComment("@param array \$query['{$operationParameter->name}'] {$operationParameter->description}");
                        $phpNamespace->addUse(Query::class);
                    } elseif ($operationParameter->in === 'header') {
                        $argumentTypes[Headers::class] = ['type' => 'array', 'name' => 'header'];
                        $method->addComment("@param array \$headers {$operationParameter->description}");
                        $phpNamespace->addUse(Headers::class);
                    } elseif ($operationParameter->in === 'path') {
                        $pathParamType = $operationParameter->getSerializableData()?->type === 'integer' ? 'int' : 'string';
                        $_pathParam = $method->addParameter($this->toFunctionName($operationParameter->name))
                            ->setType($pathParamType);
                        if (!$operationParameter->required) {
                            $_pathParam->setNullable();
                            $_pathParam->setDefaultValue(null);
                            $method->addComment("@param ?$pathParamType \${$operationParameter->name} {$operationParameter->description}");
                        } else {
                            $method->addComment("@param $pathParamType \${$operationParameter->name} {$operationParameter->description}");
                        }
                        $phpNamespace->addUse(PathParam::class);
                        $_pathParam->addAttribute(PathParam::class, [$operationParameter->name]);
                    } elseif ($operationParameter->in === 'formData') {
                        $argumentTypes[FormData::class] = ['type' => 'array', 'name' => 'formData'];
                        $method->addComment("@param array \$formData['{$operationParameter->name}'] {$operationParameter->description}");
                        $phpNamespace->addUse(FormData::class);
                    } else {
                        throw new RuntimeException("Unhandled parameter type. $operationParameter->in $operationParameter->name");
                    }
                }

                foreach ($argumentTypes as $attr => $argumentType) {
                    $method->addParameter($argumentType['name'])
                        ->setType($argumentType['type'])
                        ->addAttribute($attr);
                }

                foreach ($operation->security ?? [] as $securityRequirement) {
                    foreach (
                        array_keys(json_decode(json_encode($securityRequirement->getSerializableData()), true))
                        as $securityName
                    ) {
                        $securityDefinition = $pathDefinition['globalSecurityDefinitions']->$securityName;

                        if ($securityDefinition['type'] === 'oauth2') {
                            continue;
                        }

                        $authKeyParam = $method->addParameter('authorization');
                        $authKeyType = 'string';
                        if ($securityDefinition['in'] === 'header') {
                            if ($securityDefinition['type'] === 'apiKey') {
                                $authKeyParam->setType($authKeyType);
                                $authKeyParam->addAttribute(HeaderParam::class, [$securityDefinition['name']]);
                                $phpNamespace->addUse(HeaderParam::class);
                            } else {
                                $authKeyParam->setType($authKeyType = 'array');
                                $authKeyParam->addAttribute(Basic::class);
                                $phpNamespace->addUse(Basic::class);
                            }
                        } else {
                            $authKeyParam->setType($authKeyType);
                            $authKeyParam->addAttribute(QueryParam::class, [$securityDefinition['name']]);
                            $phpNamespace->addUse(QueryParam::class);
                        }
                        $method->addComment("@param $authKeyType \$authorization");
                    }
                }

                $method->setPublic();
                if (in_array('application/json', $operation->getSerializableData()?->produces ?? [], true)) {
                    $method->setReturnType('array');
                    $method->addComment("\n@return array");
                } else {
                    $phpNamespace->addUse(ResponseInterface::class);
                    $method->setReturnType(ResponseInterface::class);
                    $method->addComment("\n@return ".ResponseInterface::class);
                }
            }

            $classes[$className] = (new PsrPrinter())->printFile($phpFile);
        }

        return $classes;
    }

    private function convertTagToInterfaceName(string $tag): string
    {
        return ucfirst($this->toFunctionName($tag)).'ClientInterface';
    }

    private function toFunctionName(string $name): string
    {
        $pieces = explode(' ', $name);

        $newPieces = [];
        foreach ($pieces as $piece) {
            $newPieces[] = str_replace(['-', '_', ',', ';', '#', '@', '$', '/', '\\'], '', ucfirst($piece));
        }

        return lcfirst(implode('', $newPieces));
    }
}
