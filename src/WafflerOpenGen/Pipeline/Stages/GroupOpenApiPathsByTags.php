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

use stdClass;
use Waffler\Pipeline\Contracts\StageInterface;

/**
 * Class GroupOpenApiPathsByTags.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
class GroupOpenApiPathsByTags implements StageInterface
{
    /**
     * @param \cebe\openapi\SpecObjectInterface $value
     *
     * @return array<non-empty-string, array<string, mixed>>
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function handle(mixed $value): array
    {
        $paths = [];

        foreach ($value->paths as $url => $path) {
            /** @var \cebe\openapi\spec\Operation $operation */
            $operation = $path->get ?? $path->post ?? $path->put ?? $path->patch ?? $path->delete ?? $path->head ?? $path->options;

            $tag = $operation->tags[0];
            $paths[$tag][] = [
                'url' => $url,
                'path' => $path,
                'operation' => $operation,
                'tag' => $tag,
                'globalSecurityDefinitions' => $value->getSerializableData()?->securityDefinitions ?? new stdClass()
            ];
        }
        return $paths;
    }
}
