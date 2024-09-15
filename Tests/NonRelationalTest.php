<?php

namespace MulerTech\Database\Tests;

use MulerTech\Database\Mapping\MtColumn;
use MulerTech\Database\Mapping\MtEntity;
use MulerTech\Database\NonRelational\DocumentStore\FileContent\AttributeReader;
use MulerTech\Database\NonRelational\DocumentStore\FileExtension\Json;
use MulerTech\Database\NonRelational\DocumentStore\FileExtension\Php;
use MulerTech\Database\NonRelational\DocumentStore\FileExtension\Yaml;
use MulerTech\Database\NonRelational\DocumentStore\FileManipulation;
use MulerTech\Database\NonRelational\DocumentStore\FileType\Env;
use MulerTech\Database\NonRelational\DocumentStore\PathManipulation;
use MulerTech\Database\Tests\Files\Entity\User;
use MulerTech\Database\Tests\Files\FakeClass;
use MulerTech\Database\Tests\Files\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionProperty;
use RuntimeException;

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

    private const TEST_MULTILINE = 'test brut file
second line
third line';

    private const TEST_MULTILINE_WITH_INSERT = 'test brut file
second line
inserted line
third line';

    // Test of FileContent : AttributeReader
    public function testAttributeReaderGetClassAttributeNamed(): void
    {
        $reader = new AttributeReader();
        $ClassAttribute = $reader->getClassAttributeNamed(User::class, MtEntity::class);
        self::assertInstanceOf(MtEntity::class, $ClassAttribute->newInstance());
        self::assertEquals(UserRepository::class, $ClassAttribute->newInstance()->repository);
    }

    public function testAttributeReaderGetInstanceOfClassAttributeNamed(): void
    {
        $reader = new AttributeReader();
        $ClassAttribute = $reader->getInstanceOfClassAttributeNamed(User::class, MtEntity::class);
        self::assertInstanceOf(MtEntity::class, $ClassAttribute);
        self::assertEquals(UserRepository::class, $ClassAttribute->repository);
    }

    public function testAttributeReaderGetPropertiesAttributes(): void
    {
        $reader = new AttributeReader();
        $properties = $reader->getPropertiesAttributes(User::class);
        self::assertCount(3, $properties);
        $reflectionProperty = $properties[1];
        self::assertInstanceOf(ReflectionProperty::class, $reflectionProperty);
        $reflectionAttribute = $reflectionProperty->getAttributes(MtColumn::class)[0];
        self::assertInstanceOf(ReflectionAttribute::class, $reflectionAttribute);
        self::assertEquals('John', $reflectionAttribute->newInstance()->columnDefault);
    }

    public function testAttributeReaderGetInstanceOfPropertiesAttributesNamed(): void
    {
        $reader = new AttributeReader();
        $properties = $reader->getInstanceOfPropertiesAttributesNamed(User::class, MtColumn::class);
        self::assertCount(3, $properties);
        self::assertInstanceOf(MtColumn::class, $properties['username']);
        self::assertEquals('John', $properties['username']->columnDefault);
    }

    // Test of FileExtension : Json
    public function testJsonSaveAndOpenFile(): void
    {
        $jsonTestFile = new Json(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json'
        );
        self::assertTrue($jsonTestFile->saveFile(self::TEST_ARRAY));
        self::assertEquals(self::TEST_ARRAY, $jsonTestFile->openFile());
    }

    public function testJsonSaveAndOpenNewFile(): void
    {
        if (realpath(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json') !== false) {
            unlink(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json');
        }
        $jsonTestFile = new Json(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json'
        );
        self::assertTrue($jsonTestFile->saveFile(self::TEST_ARRAY));
        self::assertEquals(self::TEST_ARRAY, $jsonTestFile->openFile());
    }

    // Test of FileExtension : Yaml
    public function testYamlSaveAndOpenFile(): void
    {
        $yamlTestFile = new Yaml(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'yamltest.yaml'
        );
        self::assertTrue($yamlTestFile->saveFile(self::TEST_ARRAY));
        self::assertEquals(self::TEST_ARRAY, $yamlTestFile->openFile());
    }

    public function testYamlOpenAndThrowException(): void
    {
        $this->expectExceptionMessage('Unable to read the content of file "yamltest.nope".');
        $yamlTestNopeFile = new Yaml('yamltest.nope');
        $yamlTestNopeFile->openFile();
    }

    // Test of FileExtension : Php
    public function testPhpSaveAndOpenFile(): void
    {
        $phpTestFile = new Php(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'PhpTestClass.php'
        );
        $phpTestFileCopy = new Php(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'PhpTestCopy.php'
        );
        self::assertTrue($phpTestFileCopy->saveFile($phpTestFile->openFile()));
        self::assertEquals($phpTestFile->openFile(), $phpTestFileCopy->openFile());
        unlink(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'PhpTestCopy.php');
    }

    public function testPhpOpenAndThrowException(): void
    {
        $this->expectExceptionMessage('Unable to read the content of file "phptest.nope".');
        $phpTestNopeFile = new Yaml('phptest.nope');
        $phpTestNopeFile->openFile();
    }

    // Test of FileManipulation
    public function testCheckExtension(): void
    {
        $yamlFile = new Yaml('test.yaml');
        self::assertTrue($yamlFile->checkExtension());
    }

    public function testCheckExtensionWithException(): void
    {
        $yamlFile = new Yaml('test.nope');
        $this->expectExceptionMessage('Class FileManipulation, function checkExtension. The given filename does not have the yaml extension.');
        $yamlFile->checkExtension();
    }

    public function testFolderExists(): void
    {
        self::assertTrue(FileManipulation::folderExists(__DIR__ . DIRECTORY_SEPARATOR . 'Files'));
        self::assertFalse(FileManipulation::folderExists(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Nope'));
    }

    public function testFirstExistingParentFolder(): void
    {
        self::assertEquals(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files',
            FileManipulation::firstExistingParentFolder(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'NopeFolder' . DIRECTORY_SEPARATOR . 'NopeChildrenFolder')
        );
    }

    public function testFolderCreateAndDelete(): void
    {
        self::assertTrue(FileManipulation::folderCreate(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'NewFolder'));
        self::assertTrue(FileManipulation::folderExists(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'NewFolder'));
        self::assertTrue(FileManipulation::folderDelete(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'NewFolder'));
    }

    public function testFolderCreateRecursiveAndDelete(): void
    {
        self::assertTrue(FileManipulation::folderCreate(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'NewFolder' . DIRECTORY_SEPARATOR . 'NewSubFolder', 0770, true));
        self::assertTrue(FileManipulation::folderExists(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'NewFolder' . DIRECTORY_SEPARATOR . 'NewSubFolder'));
        self::assertTrue(FileManipulation::folderDelete(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'NewFolder' . DIRECTORY_SEPARATOR . 'NewSubFolder'));
        self::assertTrue(FileManipulation::folderDelete(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'NewFolder'));
    }

    public function testFolderDeleteAndThrowException(): void
    {
        self::assertTrue(FileManipulation::folderDelete(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Nope'));
    }

    public function testOpenFileAndThrowException(): void
    {
        $this->expectExceptionMessage('Unable to read the content of file "nope.file".');
        $nopeFile = new FileManipulation('nope.file');
        $nopeFile->openFile();
    }

    public function testOpenFile(): void
    {
        $brutFile = new FileManipulation(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'brut.file'
        );
        self::assertEquals('test brut file', $brutFile->openFile());
    }

    public function testSaveNewFile(): void
    {
        $testFile = new FileManipulation(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'FolderTmp' . DIRECTORY_SEPARATOR . 'test.file'
        );
        self::assertTrue($testFile->saveFile('test brut file', true));
        self::assertEquals('test brut file', $testFile->openFile());
        unlink(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'FolderTmp' . DIRECTORY_SEPARATOR . 'test.file');
        self::assertTrue(FileManipulation::folderDelete(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'FolderTmp'));
    }

    public function testSaveExistingFile(): void
    {
        $brutFile = new FileManipulation(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'brut.file'
        );
        self::assertTrue($brutFile->saveFile('test save existing file'));
        self::assertEquals('test save existing file', $brutFile->openFile());
        self::assertTrue($brutFile->saveFile('test brut file'));
        self::assertEquals('test brut file',$brutFile->openFile());
    }

    public function testSaveFileWithoutParentFolder(): void
    {
        $this->expectException(RuntimeException::class);
        $testFile = new FileManipulation(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'Nope' . DIRECTORY_SEPARATOR . 'test.file'
        );
        $testFile->saveFile('test save file without parent folder and without recursive');
    }

    public function testConvertFile(): void
    {
        $jsonTestFile = new Json(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json'
        );
        $yamlTestFile = new Yaml(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.yaml'
        );
        $jsonTestFile->convertFile($yamlTestFile);
        self::assertEquals($jsonTestFile->openFile(), $yamlTestFile->openFile());
        unlink(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.yaml');
    }

    public function testGetExtension(): void
    {
        $jsonTestFile = new Json(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'jsontest.json'
        );
        self::assertEquals('json', $jsonTestFile->getExtension());
    }

    public function testCountLines(): void
    {
        $multilineFile = new FileManipulation(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'multilinesOccurence.file'
        );
        self::assertEquals(4, $multilineFile->countLines());
    }

    public function testInsertContent(): void
    {
        $multilineFile = new FileManipulation(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'multilines.file'
        );
        $multilineFile->saveFile(self::TEST_MULTILINE);
        $multilineFile->insertContent(3, 'inserted line');
        self::assertEquals(self::TEST_MULTILINE_WITH_INSERT, $multilineFile->openFile());
        unlink(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'multilines.file');
    }

    public function testLastOccurrence(): void
    {
        $fakeClassFile = new Php(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'FakeClass.php'
        );
        self::assertEquals(3, $fakeClassFile->firstOccurrence('namespace'));
    }

    public function testFileClassName(): void
    {
        $fakeClassFile = new Php(
            __DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'FakeClass.php'
        );
        self::assertEquals(
            FakeClass::class,
            $fakeClassFile->fileClassName()
        );
    }

    // Test env file
    public function testEnvSaveAndOpenfile(): void
    {
        $envFile = new Env(__DIR__ . DIRECTORY_SEPARATOR . 'Files' . DIRECTORY_SEPARATOR . 'test.env');
        self::assertTrue($envFile->saveFile(self::TEST_ENV));
        self::assertEquals(self::TEST_ENV, $envFile->openFile());
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

    // Test PathManipulation
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

}