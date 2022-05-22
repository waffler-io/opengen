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
use Waffler\OpenGen\Adapters\OpenApiV3Adapter;
use Waffler\OpenGen\Adapters\SwaggerV2Adapter;
use Waffler\OpenGen\ClientGenerator;
use Waffler\OpenGen\Tests\Fixtures\JsonPlaceholder\UserClientInterface as JsonPlaceholderUser;
use Waffler\OpenGen\Tests\Fixtures\SwaggerPetshop\PetClientInterface;
use Waffler\OpenGen\Tests\Fixtures\SwaggerPetshop\StoreClientInterface;
use Waffler\OpenGen\Tests\Fixtures\SwaggerPetshop\UserClientInterface;
use Waffler\Waffler\Client\Factory;

/**
 * Class ReadingOpenApiSpecTest.
 *
 * @author ErickJMenezes <erickmenezes.dev@gmail.com>
 * @covers \Waffler\OpenGen\ClientGenerator
 */
class ClientGeneratorTest extends TestCase
{
    private const PETSHOP_OUTPUT_DIR            = __DIR__.'/../Fixtures/SwaggerPetshop';
    private const JSONPLACEHOLDER_OUTPUT_DIR    = __DIR__.'/../Fixtures/JsonPlaceholder';
    private const GITHUB_OUTPUT_DIR             = __DIR__.'/../Fixtures/GitHub';

    public function testItMustGenerateSwaggerPetshopApiSpec(): void
    {
        $generator = new ClientGenerator(new SwaggerV2Adapter(
            namespace: 'Waffler\\OpenGen\\Tests\\Fixtures\\SwaggerPetshop',
        ));

        $generator->generateFromJsonFile(
            __DIR__.'/../Fixtures/swagger-petshop.json',
            self::PETSHOP_OUTPUT_DIR,
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
        $generator = new ClientGenerator(new SwaggerV2Adapter(
            namespace: 'Waffler\\OpenGen\\Tests\\Fixtures\\JsonPlaceholder',
            ignoreParameters: ['header' => ['Authorization']],
            ignoreMethods: ['user/all'],
            removeMethodPrefix: '/\w*\//',
        ));

        $generator->generateFromJsonFile(
            __DIR__.'/../Fixtures/swagger-jsonplaceholder.json',
            self::JSONPLACEHOLDER_OUTPUT_DIR,
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
        self::assertFalse((new \ReflectionClass($client))->hasMethod('all'));
    }

    public function testItMustGenerateGitHubApiSpecFromInternetHostedFile(): void
    {
        $generator = new ClientGenerator(new OpenApiV3Adapter(
            namespace: 'Waffler\\OpenGen\\Tests\\Fixtures\\GitHub',
            removeMethodPrefix: '/\w*\//',
        ));

        $generator->generateFromJsonFile(
            'https://raw.githubusercontent.com/github/rest-api-description/main/descriptions/api.github.com/api.github.com.json',
            self::GITHUB_OUTPUT_DIR,
        );

        $this->assertDirectoryExists(self::GITHUB_OUTPUT_DIR);
    }
}
