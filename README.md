# Waffler OpenAPI Code Generator

This package reads an open api file (like swagger) and generates ready-to-use
waffler interfaces.

## How to install
```shell
composer require waffler/opengen
```
** This package requires PHP 8 or above. **

## How to use
```php
<?php

require __DIR__.'/vendor/autoload.php';

use Waffler\OpenGen\Generator;

$generator = new Generator();

$generationMap = $generator->fromOpenApiFile(
    'path/to/openapi-file.yaml',
    'path/to/output-dir/',
    'Desired\\Namespace',
    // Generation options.
);
```

Work in progress.