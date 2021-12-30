# Waffler OpenAPI Code Generator

This package reads an open api file (like swagger) and generates ready-to-use
waffler interfaces.

## Supported OpenApi specs:

| Name     | Version |
|----------|---------|
| Swagger  | 2.0     |
| Open Api | 3.0.x   |


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
    [] // Generation options.
);
```

## Generation options:

### Option: `interface_suffix`:
Adding this option, you can modify the `ClientInterface` interface suffix.
- Type: `string`

Example code:
```php
[
    'interface_suffix' => 'Api' // Replaces 'ClientInterface'
]
```

### Option: `ignore`:
Adding this option, you can specify elements to ignore in the code generation.
Available items to ignore are:
- `parameters`
- `methods`

Example code:
```php
[
    'ignore' => [
        'parameters' => [],
        'methods' => []
    ]
]
```

### Option: `ignore.parameters`:
Adding this option, you can specify which parameter types you want to ignore in the code generation.
Available parameter types are:
- `header`
- `query`
- `path`
- `formData`

Example code:
```php
[
    'ignore' => [
        'parameters' => [
            'header' => ['Authorization', 'other_header_name']
        ]
    ]
]
```

### Option: `ignore.methods`:
Adding this option, you can specify which method names you want to ignore in the code generation.
The method names are the `operationId` in openapi spec files. 

Example code:
```php
[
    'ignore' => [
        'methods' => ['getById', 'deleteUser']
    ]
]
```

### Option: `remove_method_prefix`:
Adding this option, you can remove the prefix of operationIds from code generation.
In the example below, we`ll 

Example code:
```php
[
    'remove_method_prefix' => '/\w*\//'
]
```
