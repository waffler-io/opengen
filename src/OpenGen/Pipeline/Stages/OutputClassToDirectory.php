<?php

/*
 * This file is part of Waffler.
 *
 * (c) Erick Johnson Almeida de Menezes <erickmenezes.dev@gmail.com>
 *
 * This source file is subject to the MIT licence that is bundled
 * with this source code in the file LICENCE.
 */

namespace Waffler\OpenGen\Pipeline\Stages;

use cebe\openapi\spec\OpenApi;
use Nette\PhpGenerator\PsrPrinter;
use Symfony\Component\Filesystem\Filesystem;
use Waffler\OpenGen\Contracts\InterfaceBuilder;
use Waffler\OpenGen\SpecificationTypes\OpenApi\V3\InterfaceBuilder as OpenApiV3;
use Waffler\OpenGen\SpecificationTypes\Swagger\V2\InterfaceBuilder as SwaggerV2;
use Waffler\Pipeline\Contracts\StageInterface;

/**
 * Class OutputClassToDirectory.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
class OutputClassToDirectory implements StageInterface
{
    public function __construct(
        private string $outputDir,
        private array $options
    ) {
    }

    /**
     * @param array<non-empty-string, \Nette\PhpGenerator\PhpFile> $value
     *
     * @return array<class-string>
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function handle(mixed $value): array
    {
        $classMap = $this->getInterfaceBuilder($value)->buildInterface($value);
        $filesystem = new Filesystem();

        if (!$filesystem->exists($this->outputDir)) {
            $filesystem->mkdir($this->outputDir, 0700);
        }

        $classNameFileMap = [];
        $psrPrinter = new PsrPrinter();

        foreach ($classMap as $className => $phpFile) {
            $classFile = $psrPrinter->printFile($phpFile);
            $fileName = "$this->outputDir/$className.php";
            if ($filesystem->exists($fileName)) {
                $filesystem->remove($fileName);
            }
            $classNameFileMap[$className] = $fileName;
            $filesystem->touch($fileName);
            $filesystem->appendToFile($fileName, $classFile);
        }

        // @phpstan-ignore-next-line
        return $classNameFileMap;
    }

    private function getInterfaceBuilder(OpenApi $api): InterfaceBuilder
    {
        if (!is_null($api->openapi)) {
            return new OpenApiV3($this->options);
        } elseif (!is_null($api->swagger)) {
            return new SwaggerV2($this->options);
        }

        throw new Exception("Unknown specification file type.");
    }
}
