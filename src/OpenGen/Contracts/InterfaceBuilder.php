<?php

/*
 * This file is part of Waffler.
 *
 * (c) Erick Johnson Almeida de Menezes <erickmenezes.dev@gmail.com>
 *
 * This source file is subject to the MIT licence that is bundled
 * with this source code in the file LICENCE.
 */

namespace Waffler\OpenGen\Contracts;

use cebe\openapi\spec\OpenApi;

/**
 * Interface ReaderInterface.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 */
interface InterfaceBuilder
{
    public const PHP_FILE_COMMENT = <<<EOL
This file is auto generated by Waffler OpenAPI Code generator package.

Do not modify unless you know exactly what you are doing.
EOL;

    public const DEFAULT_OPTIONS = [
        'interface_suffix' => 'ClientInterface',
        'namespace' => '',
    ];

    /**
     * @param \cebe\openapi\spec\OpenApi $specification
     *
     * @return array<non-empty-string, \Nette\PhpGenerator\PhpFile>
     * @author ErickJMenezes <erickmenezes.dev@gmail.com>
     */
    public function buildInterfaces(OpenApi $specification): array;
}