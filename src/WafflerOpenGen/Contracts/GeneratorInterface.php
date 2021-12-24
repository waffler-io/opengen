<?php

/*
 * This file is part of Waffler.
 *
 * (c) Erick Johnson Almeida de Menezes <erickmenezes.dev@gmail.com>
 *
 * This source file is subject to the MIT licence that is bundled
 * with this source code in the file LICENCE.
 */

namespace Waffler\Opengen\Contracts;

/**
 * Interface GeneratorInterface.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
interface GeneratorInterface
{
    /**
     * Generates waffler interfaces from open-api files.
     *
     * @param non-empty-string $openApiFilePath
     * @param non-empty-string $outputDir
     * @param non-empty-string $classNamespace
     *
     * @return void
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function fromOpenApiFile(string $openApiFilePath, string $outputDir, string $classNamespace): void;
}
