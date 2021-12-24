<?php

namespace Waffler\OpenGen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waffler\Opengen\Generator;

/**
 * Class ReadingOpenApiSpecTest.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 * @coversNothing
 */
class ReadingOpenApiSpecTest extends TestCase
{
    public function testItMustGenerateSwaggerPetshopApiSpec(): void
    {
        $generator = new Generator();

        $generator->fromOpenApiFile(
            __DIR__ . '/../Fixtures/swagger-petshop.json',
            __DIR__.'/../Fixtures/SwaggerPetshop',
            'Swagger\\Petshop'
        );

        $this->expectNotToPerformAssertions();
    }
}