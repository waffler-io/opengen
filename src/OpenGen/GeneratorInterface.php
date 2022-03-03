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

use Waffler\OpenGen\Adapters\AdapterInterface;

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
     * @param non-empty-string $specificationFilePath
     * @param non-empty-string $outputDirectory
     *
     * @return array<string, string> InterfaceName => Output file.
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function generateFromSpecificationFile(
        string $specificationFilePath,
        string $outputDirectory,
    ): array;

    /**
     * Retrieves the adapter that is being used to generate the waffler interfaces.
     *
     * @return \Waffler\OpenGen\Adapters\AdapterInterface
     */
    public function getAdapter(): AdapterInterface;
}
