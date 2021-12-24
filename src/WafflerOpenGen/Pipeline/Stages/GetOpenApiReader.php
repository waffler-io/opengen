<?php

namespace Waffler\Opengen\Pipeline\Stages;

use cebe\openapi\Reader;
use InvalidArgumentException;
use SplFileInfo;
use Waffler\Pipeline\Contracts\StageInterface;

/**
 * Class GetOpenApiReader.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
class GetOpenApiReader implements StageInterface
{

    /**
     * @param non-empty-string $value
     *
     * @return mixed
     * @throws \cebe\openapi\exceptions\IOException
     * @throws \cebe\openapi\exceptions\TypeErrorException
     * @throws \cebe\openapi\exceptions\UnresolvableReferenceException
     * @throws \cebe\openapi\json\InvalidJsonPointerSyntaxException
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function handle(mixed $value): mixed
    {
        $fileInfo = new SplFileInfo($value);

        return match ($fileInfo->getExtension()) {
            'json' => Reader::readFromJsonFile($value),
            'yaml' => Reader::readFromYamlFile($value),
            default => throw new InvalidArgumentException("File extension '{$fileInfo->getExtension()}' is not valid for OpenAPI format.")
        };
    }
}