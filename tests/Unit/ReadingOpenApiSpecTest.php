<?php

namespace Waffler\OpenGen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waffler\Opengen\Generator;
use Waffler\OpenGen\Tests\Fixtures\SwaggerPetshop\PetClientInterface;
use Waffler\OpenGen\Tests\Fixtures\SwaggerPetshop\StoreClientInterface;
use Waffler\OpenGen\Tests\Fixtures\SwaggerPetshop\UserClientInterface;

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

        $outputDir = __DIR__.'/../Fixtures/SwaggerPetshop';
        $generator->fromOpenApiFile(
            __DIR__ . '/../Fixtures/swagger-petshop.json',
            $outputDir,
            'Waffler\\OpenGen\\Tests\\Fixtures\\SwaggerPetshop'
        );

        $this->assertDirectoryExists($outputDir);
        $this->assertFileExists($outputDir.'/PetClientInterface.php');
        $this->assertFileExists($outputDir.'/StoreClientInterface.php');
        $this->assertFileExists($outputDir.'/UserClientInterface.php');
        interface_exists(PetClientInterface::class);
        interface_exists(StoreClientInterface::class);
        interface_exists(UserClientInterface::class);
    }
}