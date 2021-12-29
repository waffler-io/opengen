<?php

/*
 * This file is part of Waffler.
 *
 * (c) Erick Johnson Almeida de Menezes <erickmenezes.dev@gmail.com>
 *
 * This source file is subject to the MIT licence that is bundled
 * with this source code in the file LICENCE.
 */

namespace Waffler\OpenGen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waffler\Client\Factory;
use Waffler\OpenGen\Generator;
use Waffler\OpenGen\Tests\Fixtures\SwaggerPetshop\PetClientInterface;
use Waffler\OpenGen\Tests\Fixtures\SwaggerPetshop\StoreClientInterface;
use Waffler\OpenGen\Tests\Fixtures\SwaggerPetshop\UserClientInterface;
use Waffler\OpenGen\Tests\Fixtures\JsonPlaceholder\UserClientInterface as JsonPlaceholderUser;

/**
 * Class ReadingOpenApiSpecTest.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 * @coversNothing
 */
class ReadingOpenApiSpecTest extends TestCase
{
    private const PETSHOP_OUTPUT_DIR = __DIR__.'/../Fixtures/SwaggerPetshop';
    private const JSONPLACEHOLDER_OUTPUT_DIR = __DIR__.'/../Fixtures/JsonPlaceholder';

    public function testItMustGenerateSwaggerPetshopApiSpec(): void
    {
        $generator = new Generator();

        $generator->fromOpenApiFile(
            __DIR__.'/../Fixtures/swagger-petshop.json',
            self::PETSHOP_OUTPUT_DIR,
            'Waffler\\OpenGen\\Tests\\Fixtures\\SwaggerPetshop'
        );

        $this->assertDirectoryExists(self::PETSHOP_OUTPUT_DIR);
        $this->assertFileExists(self::PETSHOP_OUTPUT_DIR.'/PetClientInterface.php');
        $this->assertFileExists(self::PETSHOP_OUTPUT_DIR.'/StoreClientInterface.php');
        $this->assertFileExists(self::PETSHOP_OUTPUT_DIR.'/UserClientInterface.php');
        $this->assertTrue(interface_exists(PetClientInterface::class));
        $this->assertTrue(interface_exists(StoreClientInterface::class));
        $this->assertTrue(interface_exists(UserClientInterface::class));
        $this->assertInstanceOf(
            PetClientInterface::class,
            Factory::make(PetClientInterface::class)
        );
        $this->assertInstanceOf(
            StoreClientInterface::class,
            Factory::make(StoreClientInterface::class)
        );
        $this->assertInstanceOf(
            UserClientInterface::class,
            Factory::make(UserClientInterface::class)
        );
    }

    public function testItMustGenerateJsonPlaceholderApiSpec(): void
    {
        $generator = new Generator();

        $generator->fromOpenApiFile(
            __DIR__.'/../Fixtures/swagger-jsonplaceholder.json',
            self::JSONPLACEHOLDER_OUTPUT_DIR,
            'Waffler\\OpenGen\\Tests\\Fixtures\\JsonPlaceholder',
            [
                'ignore' => [
                    'parameters' => [
                        'header' => ['Authorization']
                    ]
                ],
                'remove_method_prefix' => '/\w*\//'
            ]
        );

        $this->assertDirectoryExists(self::JSONPLACEHOLDER_OUTPUT_DIR);
        $this->assertFileExists(self::JSONPLACEHOLDER_OUTPUT_DIR.'/UserClientInterface.php');
        $this->assertTrue(interface_exists(JsonPlaceholderUser::class));
        $client = Factory::make(JsonPlaceholderUser::class, [
            'base_uri' => 'https://jsonplaceholder.typicode.com/'
        ]);
        $this->assertInstanceOf(
            JsonPlaceholderUser::class,
            $client
        );
    }

    public function testItMustGenerateGithubApiSpec(): void
    {
        $generator = new Generator();

        $generator->fromOpenApiFile(
            __DIR__.'/../Fixtures/api.github.com.json',
            __DIR__.'/../Fixtures/GitHub',
            'Waffler\\OpenGen\\Tests\\Fixtures\\GitHub',
            [
                'remove_method_prefix' => '/\w*\//'
            ]
        );

        $this->expectNotToPerformAssertions();
    }
}
