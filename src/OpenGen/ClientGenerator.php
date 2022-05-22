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

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use InvalidArgumentException;
use Nette\PhpGenerator\PsrPrinter;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;
use Waffler\OpenGen\Adapters\SpecificationAdapterInterface;

/**
 * Class Generator.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
class ClientGenerator implements ClientGeneratorInterface
{
    /**
     * @param \Waffler\OpenGen\Adapters\SpecificationAdapterInterface $specificationTypeAdapter If the specification
     *                                                                                          type is swagger, use
     *                                                                                          SwaggerV2Adapter,
     *                                                                                          otherwise use
     *                                                                                          OpenApiV3Adapter.
     */
    public function __construct(
        private SpecificationAdapterInterface $specificationTypeAdapter,
    ) {
    }

    public function generateFromJsonFile(string $jsonSpecificationFilePath, string $outputDirectory): array
    {
        return $this->generate(
            Reader::readFromJsonFile($jsonSpecificationFilePath),
            $outputDirectory,
        );
    }

    public function generateFromYamlFile(string $yamlSpecificationFilePath, string $outputDirectory): array
    {
        return $this->generate(
            Reader::readFromYamlFile($yamlSpecificationFilePath),
            $outputDirectory,
        );
    }

    public function generateFromYaml(string $yamlSpecification, string $outputDirectory): array
    {
        return $this->generate(
            Reader::readFromYaml($yamlSpecification),
            $outputDirectory,
        );
    }

    public function generateFromJson(string $jsonSpecification, string $outputDirectory): array
    {
        return $this->generate(
            Reader::readFromJson($jsonSpecification),
            $outputDirectory,
        );
    }

    public function getAdapter(): SpecificationAdapterInterface
    {
        return $this->specificationTypeAdapter;
    }

    private function generate(OpenApi $openApi, string $outputDirectory): array
    {
        return $this->printInterfaces(
            $this->specificationTypeAdapter->buildInterfaces($openApi),
            $outputDirectory,
        );
    }

    /**
     * @param array<string, \Nette\PhpGenerator\PhpFile> $interfaces
     *
     * @return array<class-string>
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    private function printInterfaces(array $interfaces, string $outputDir): array
    {
        $filesystem = new Filesystem();

        if (!$filesystem->exists($outputDir)) {
            $filesystem->mkdir($outputDir, 0700);
        }

        $classNameFileMap = [];
        $psrPrinter = new PsrPrinter();

        foreach ($interfaces as $name => $phpFile) {
            $classFile = $psrPrinter->printFile($phpFile);
            $fileName = $outputDir.DIRECTORY_SEPARATOR.$name.".php";
            if ($filesystem->exists($fileName)) {
                $filesystem->remove($fileName);
            }
            $classNameFileMap[$name] = $fileName;
            $filesystem->touch($fileName);
            $filesystem->appendToFile($fileName, $classFile);
        }

        return $classNameFileMap;
    }
}
