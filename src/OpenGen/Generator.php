<?php

/*
 * This file is part of Waffler.
 *
 * (c) Erick Johnson Almeida de Menezes <erickmenezes.dev@gmail.com>
 *
 * This source file is subject to the MIT licence that is bundled
 * with this source code in the file LICENCE.
 */

namespace Waffler\OpenGen;

use Waffler\OpenGen\Contracts\GeneratorInterface;
use Waffler\OpenGen\Pipeline\Stages\GenerateWafflerInterfacesForEachArrayKey;
use Waffler\OpenGen\Pipeline\Stages\GetInterfaceBuilderForFile;
use Waffler\OpenGen\Pipeline\Stages\GetOpenApiReader;
use Waffler\OpenGen\Pipeline\Stages\OutputClassToDirectory;
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
     * @param array<string, mixed> $options
     * @return array<string, string>
     */
    public function fromOpenApiFile(
        string $openApiFilePath,
        string $outputDir,
        string $classesNamespace,
        array $options = []
    ): array {
        $options['namespace'] = $classesNamespace;
        return (new Pipeline())
            ->run($openApiFilePath)
            ->through([
                new GetOpenApiReader(),
                new OutputClassToDirectory($outputDir, $options)
            ])
            ->thenReturn();
    }
}
