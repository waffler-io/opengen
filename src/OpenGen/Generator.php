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
use Waffler\OpenGen\Adapters\AdapterInterface;

/**
 * Class Generator.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
class Generator implements GeneratorInterface
{
    public function __construct(
        private AdapterInterface $openApiSpecAdapter,
    ) {
    }

    /**
     * @inheritDoc
     *
     * @param string $specificationFilePath
     * @param string $outputDirectory
     *
     * @return array<string, string>
     * @throws \cebe\openapi\exceptions\IOException
     * @throws \cebe\openapi\exceptions\TypeErrorException
     * @throws \cebe\openapi\exceptions\UnresolvableReferenceException
     * @throws \cebe\openapi\json\InvalidJsonPointerSyntaxException
     * @throws \Exception
     */
    public function generateFromSpecificationFile(
        string $specificationFilePath,
        string $outputDirectory,
    ): array {
        $openApi = $this->getReaderForFile($specificationFilePath);
        $interfaces = $this->openApiSpecAdapter->buildInterfaces($openApi);
        return $this->printInterfaces($interfaces, $outputDirectory);
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
            $fileName = $outputDir.DIRECTORY_SEPARATOR.$name.".php";
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

    public function getAdapter(): AdapterInterface
    {
        return $this->openApiSpecAdapter;
    }
}
