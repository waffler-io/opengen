<?php

/*
 * This file is part of Waffler.
 *
 * (c) Erick Johnson Almeida de Menezes <erickmenezes.dev@gmail.com>
 *
 * This source file is subject to the MIT licence that is bundled
 * with this source code in the file LICENCE.
 */

namespace Waffler\Opengen\Pipeline\Stages;

use Symfony\Component\Filesystem\Filesystem;
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
    ) {
    }

    /**
     * @param array<non-empty-string, non-empty-string> $value
     *
     * @return mixed
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function handle(mixed $value): bool
    {
        $filesystem = new Filesystem();

        if (!$filesystem->exists($this->outputDir)) {
            $filesystem->mkdir($this->outputDir, 0700);
        }

        foreach ($value as $className => $classFile) {
            $fileName = "$this->outputDir/$className.php";
            if ($filesystem->exists($fileName)) {
                $filesystem->remove($fileName);
            }
            $filesystem->touch($fileName);
            $filesystem->appendToFile($fileName, $classFile);
        }

        return true;
    }
}
