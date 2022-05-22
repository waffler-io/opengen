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

use Waffler\OpenGen\Adapters\SpecificationAdapterInterface;

/**
 * Interface GeneratorInterface.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
interface ClientGeneratorInterface
{
    /**
     * Generates API clients from a json specification file.
     *
     * @param string $jsonSpecificationFilePath
     * @param string $outputDirectory
     *
     * @return array<class-string> The generated interfaces.
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function generateFromJsonFile(string $jsonSpecificationFilePath, string $outputDirectory): array;

    /**
     * Generates API clients from a json specification file.
     *
     * @param string $yamlSpecificationFilePath
     * @param string $outputDirectory
     *
     * @return array<class-string> The generated interfaces.
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function generateFromYamlFile(string $yamlSpecificationFilePath, string $outputDirectory): array;

    /**
     * Generates API clients from a json specification string.
     *
     * @param string $jsonSpecification
     * @param string $outputDirectory
     *
     * @return array<class-string> The generated interfaces.
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function generateFromJson(string $jsonSpecification, string $outputDirectory): array;

    /**
     * Generates API clients from a yaml specification string.
     *
     * @param string $yamlSpecification
     * @param string $outputDirectory
     *
     * @return array<class-string> The generated interfaces.
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function generateFromYaml(string $yamlSpecification, string $outputDirectory): array;

    /**
     * Retrieves the adapter that is being used to generate the waffler interfaces.
     *
     * @return \Waffler\OpenGen\Adapters\SpecificationAdapterInterface
     */
    public function getAdapter(): SpecificationAdapterInterface;
}
