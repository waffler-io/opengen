<?php

namespace Waffler\OpenGen\Tests\Unit;

use Closure;
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
    private const OUTPUT_DIR = __DIR__.'/../Fixtures/SwaggerPetshop';

    protected function setUp(): void
    {
        parent::setUp();

        $dirname = self::OUTPUT_DIR;
        // @phpstan-ignore-next-line
        array_map(Closure::fromCallable('unlink'), (array)glob("$dirname/*.*"));
        rmdir($dirname);
    }

    public function testItMustGenerateSwaggerPetshopApiSpec(): void
    {
        $generator = new Generator();

        $generator->fromOpenApiFile(
            __DIR__ . '/../Fixtures/swagger-petshop.json',
            self::OUTPUT_DIR,
            'Waffler\\OpenGen\\Tests\\Fixtures\\SwaggerPetshop'
        );

        $this->assertDirectoryExists(self::OUTPUT_DIR);
        $this->assertFileExists(self::OUTPUT_DIR.'/PetClientInterface.php');
        $this->assertFileExists(self::OUTPUT_DIR.'/StoreClientInterface.php');
        $this->assertFileExists(self::OUTPUT_DIR.'/UserClientInterface.php');
        interface_exists(PetClientInterface::class);
        interface_exists(StoreClientInterface::class);
        interface_exists(UserClientInterface::class);
    }
}