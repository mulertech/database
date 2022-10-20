<?php


namespace mtphp\Database\Tests;


use mtphp\Database\NonRelational\DocumentStore\FileExtension\Json;
use mtphp\Database\NonRelational\DocumentStore\FileExtension\Yaml;
use mtphp\Database\NonRelational\DocumentStore\FileManipulation;
use mtphp\Database\NonRelational\DocumentStore\FileType\Env;
use mtphp\Database\NonRelational\DocumentStore\PathManipulation;
use PHPUnit\Framework\TestCase;
use mtphp\Database\Tests\Files\FakeClass;

class NonRelationalTest extends TestCase
{

    private const TEST_ARRAY = [
        'fruits' => [
            [
                'kiwis' => 3,
                'mangues' => 4,
                'bananes' => null
            ],
            [
                'panier' => true
            ]
        ],
        'legumes' => [
            'patates' => 'amandine',
            'poireaux' => false
        ],
        'viandes' => [
            'poisson',
            'poulet',
            'boeuf'
        ]
    ];

    private const TEST_ENV = '#SOME COMMENTS
key1=value1
#OTHERS COMMENTS
key2="value2"
key3=\'value3\'';

    /**
     * Test create json file with the content (constant TEST_ARRAY) given.
     */
    public function testJsonSaveFile(): void
    {
        self::assertTrue(
            Json::saveFile(
                __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json',
                self::TEST_ARRAY
            )
        );
    }

    public function testJsonSaveNewFile(): void
    {
        if (realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json') !== false) {
            unlink(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json');
        }
        self::assertTrue(
            Json::saveFile(
                __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json',
                self::TEST_ARRAY
            )
        );
    }

    /**
     * Test open json file and compare with the constant TEST_ARRAY.
     */
    public function testJsonOpenFile(): void
    {
        self::assertEquals(
            self::TEST_ARRAY,
            Json::openFile(
                __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json'
            )
        );
    }

    /**
     * Test create yaml file with the content (constant TEST_ARRAY) given.
     */
    public function testYamlSaveFile(): void
    {
        self::assertTrue(
            Yaml::saveFile(
                __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'yamltest.yaml',
                self::TEST_ARRAY
            )
        );
    }

    /**
     * Test open yaml file and compare with the constant TEST_ARRAY.
     */
    public function testYamlOpenFile(): void
    {
        self::assertEquals(
            self::TEST_ARRAY,
            Yaml::openFile(
                __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'yamltest.yaml'
            )
        );
    }

    /**
     * Convert jsontest.json to jsontest.yaml
     */
    public function testConvertFile(): void
    {
        FileManipulation::convertFile(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json',
            new Json(),
            new Yaml()
        );
        self::assertEquals(
            Json::openFile(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json'),
            Yaml::openFile(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.yaml')
        );
    }

    /**
     * Save env file
     */
    public function testEnvSavefile(): void
    {
        self::assertTrue(
            Env::saveFile(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'test.env', self::TEST_ENV)
        );
    }

    /**
     * Open env file
     */
    public function testEnvOpenFile(): void
    {
        self::assertEquals(
            self::TEST_ENV,
            Env::openFile(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'test.env')
        );
    }

    /**
     * Parse an environment file into an array
     */
    public function testParseEnvFile(): void
    {
        self::assertEquals(
            ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'],
            Env::parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'test.env')
        );
    }

    public function testFileListRecursive(): void
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'FileList';
        $list[] = $dir . DIRECTORY_SEPARATOR . 'path1' . DIRECTORY_SEPARATOR . 'path12' . DIRECTORY_SEPARATOR . 'path121' . DIRECTORY_SEPARATOR . 'yamltest2.yaml';
        $list[] = $dir . DIRECTORY_SEPARATOR . 'yamltest1.yaml';
        self::assertEquals($list, PathManipulation::fileList($dir, true));
    }

    public function testFileListNotRecursive(): void
    {
        $dir = __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'FileList';
        $list[] = $dir . DIRECTORY_SEPARATOR . 'yamltest1.yaml';
        self::assertEquals($list, PathManipulation::fileList($dir, false));
    }

    public function testFileClassName(): void
    {
        self::assertEquals(FakeClass::class, (new FileManipulation())->fileClassName(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'FakeClass.php'));
    }

}