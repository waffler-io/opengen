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
    public function testItMustGenerateKlingoApiSpec(): void
    {
        $generator = new Generator();

        $generator->fromOpenApiFile(
            '/home/erick/Downloads/openapi-test.json',
            '/home/erick/Downloads/KlingoTest',
            'Klingo'
        );

        $this->expectNotToPerformAssertions();
    }
    public function testItMustGenerateSwaggerPetshopApiSpec(): void
    {
        $generator = new Generator();

        $generator->fromOpenApiFile(
            '/home/erick/Downloads/swagger.json',
            '/home/erick/Downloads/SwaggerPetshop',
            'Swagger\\Petshop'
        );

        $this->expectNotToPerformAssertions();
    }
}