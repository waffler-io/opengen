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
use Exception;
use InvalidArgumentException;
use Nette\PhpGenerator\PsrPrinter;
use SplFileInfo;
use Symfony\Component\Filesystem\Filesystem;
use Waffler\OpenGen\Contracts\GeneratorInterface;
use Waffler\OpenGen\Contracts\InterfaceBuilder;
use Waffler\OpenGen\SpecificationTypes\OpenApi\V3\InterfaceBuilder as OpenApiV3;
use Waffler\OpenGen\SpecificationTypes\Swagger\V2\InterfaceBuilder as SwaggerV2;

/**
 * Class Generator.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
class Generator implements GeneratorInterface
{
    /**
     * @inheritDoc
     *
     * @param string               $openApiFilePath
     * @param string               $outputDir
     * @param string               $classesNamespace
     * @param array<string, mixed> $options
     *
     * @return array<string, string>
     * @throws \cebe\openapi\exceptions\IOException
     * @throws \cebe\openapi\exceptions\TypeErrorException
     * @throws \cebe\openapi\exceptions\UnresolvableReferenceException
     * @throws \cebe\openapi\json\InvalidJsonPointerSyntaxException
     * @throws \Exception
     */
    public function fromOpenApiFile(
        string $openApiFilePath,
        string $outputDir,
        string $classesNamespace,
        array $options = []
    ): array {
        $options['namespace'] = $classesNamespace;
        $openApi = $this->getReaderForFile($openApiFilePath);
        $interfaceBuilder = $this->getInterfaceBuilder($openApi, $options);
        $interfaces = $interfaceBuilder->buildInterfaces($openApi);
        return $this->printInterfaces($interfaces, $outputDir);
    }

    /**
     * @param string $filePath
     *
     * @return \cebe\openapi\spec\OpenApi
     * @throws \cebe\openapi\exceptions\IOException
     * @throws \cebe\openapi\exceptions\TypeErrorException
     * @throws \cebe\openapi\exceptions\UnresolvableReferenceException
     * @throws \cebe\openapi\json\InvalidJsonPointerSyntaxException
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    private function getReaderForFile(string $filePath): OpenApi
    {
        $fileInfo = new SplFileInfo($filePath);

        return match ($fileInfo->getExtension()) {
            'json' => Reader::readFromJsonFile($filePath),
            'yaml', 'yml' => Reader::readFromYamlFile($filePath),
            default => throw new InvalidArgumentException("File extension '{$fileInfo->getExtension()}' is not valid for OpenAPI format.")
        };
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
            $fileName = "$outputDir/$name.php";
            if ($filesystem->exists($fileName)) {
                $filesystem->remove($fileName);
            }
            $classNameFileMap[$name] = $fileName;
            $filesystem->touch($fileName);
            $filesystem->appendToFile($fileName, $classFile);
        }

        // @phpstan-ignore-next-line
        return $classNameFileMap;
    }

    /**
     * @param \cebe\openapi\spec\OpenApi $api
     * @param array<string, mixed>       $options
     *
     * @return \Waffler\OpenGen\Contracts\InterfaceBuilder
     * @throws \Exception
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    private function getInterfaceBuilder(OpenApi $api, array $options): InterfaceBuilder
    {
        if (!is_null($api->openapi)) { //@phpstan-ignore-line
            return new OpenApiV3($options);
        } elseif (!is_null($api->swagger)) { //@phpstan-ignore-line
            return new SwaggerV2($options);
        }

        //@phpstan-ignore-next-line
        throw new Exception("Unknown specification file type.");
    }
}
