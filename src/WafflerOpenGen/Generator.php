<?php

namespace Waffler\Opengen;

use cebe\openapi\Reader;
use cebe\openapi\spec\PathItem;
use cebe\openapi\SpecObjectInterface;
use InvalidArgumentException;
use SplFileInfo;
use Waffler\Opengen\Contracts\GeneratorInterface;
use Waffler\Opengen\Pipeline\Stages\GenerateWafflerInterfacesForEachArrayKey;
use Waffler\Opengen\Pipeline\Stages\GetOpenApiReader;
use Waffler\Opengen\Pipeline\Stages\GroupOpenApiPathsByTags;
use Waffler\Opengen\Pipeline\Stages\OutputClassToDirectory;
use Waffler\Pipeline\Pipeline;

/**
 * Class Generator.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
class Generator implements GeneratorInterface
{
    /**
     * @inheritDoc
     */
    public function fromOpenApiFile(string $openApiFilePath, string $outputDir, string $classNamespace): void
    {
        (new Pipeline())
            ->run($openApiFilePath)
            ->through([
                new GetOpenApiReader(),
                new GroupOpenApiPathsByTags(),
                new GenerateWafflerInterfacesForEachArrayKey($classNamespace),
                new OutputClassToDirectory($outputDir)
            ])
            ->thenReturn();
    }
}