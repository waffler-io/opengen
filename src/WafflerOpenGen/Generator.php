<?php

/*
 * This file is part of Waffler.
 *
 * (c) Erick Johnson Almeida de Menezes <erickmenezes.dev@gmail.com>
 *
 * This source file is subject to the MIT licence that is bundled
 * with this source code in the file LICENCE.
 */

namespace Waffler\Opengen;

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
    public function fromOpenApiFile(string $openApiFilePath, string $outputDir, string $classesNamespace): void
    {
        (new Pipeline())
            ->run($openApiFilePath)
            ->through([
                new GetOpenApiReader(),
                new GroupOpenApiPathsByTags(),
                new GenerateWafflerInterfacesForEachArrayKey($classesNamespace),
                new OutputClassToDirectory($outputDir)
            ])
            ->thenReturn();
    }
}
