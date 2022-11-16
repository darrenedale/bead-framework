<?php

namespace BeadTests;

use Equit\Translator;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

class TranslatorTest extends TestCase
{
    private Translator $m_translator;

    public function setUp(): void
    {
        $this->m_translator = new Translator();
    }

    public function tearDown(): void
    {
        unset($this->m_translator);
    }

    public function testConstructor(): void
    {
        $translator = new Translator();
        $defaultLanguage = new ReflectionClassConstant(Translator::class, "DefaultLanguage");
        $this->assertEquals($defaultLanguage->getValue(), $translator->language());

        $translator = new Translator("fr");
        $this->assertEquals("fr", $translator->language());
    }

    public function testLanguage(): void
    {
        // ensure we can set a language
        $this->m_translator->setLanguage("en");
        $this->assertEquals("en", $this->m_translator->language());

        // ensure we can change language
        $this->m_translator->setLanguage("fr");
        $this->assertEquals("fr", $this->m_translator->language());
    }

    public function testHasTranslation(): void
    {
        // ensure when there's no translations file we get false back
        $this->m_translator->setLanguage("this-language-does-not-exist");
        $actual = $this->m_translator->hasTranslation("nonsense");
        $this->assertFalse($actual);

        // ensure when there's a translations available we get true
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("fr");
        $actual = $this->m_translator->hasTranslation("door");
        $this->assertTrue($actual);

        // ensure when there's a file loaded we get false back when there's no translation available
        $actual = $this->m_translator->hasTranslation("window");
        $this->assertFalse($actual);
    }

    public function testSetLanguage(): void
    {
        $this->assertNotEquals("fr", $this->m_translator->language());
        $this->m_translator->setLanguage("fr");
        $this->assertEquals("fr", $this->m_translator->language());
    }

    public function testAddSearchPath(): void
    {
        $searchPath = realpath(__DIR__ . "/files/translations/");
        $this->m_translator->addSearchPath($searchPath);
        $this->assertTrue(in_array($searchPath, $this->m_translator->searchPaths()));
    }

    public function testTranslate(): void
    {
        // ensure when there's no translations file we get the original string back
        $this->m_translator->setLanguage("this-language-does-not-exist");
        $actual = $this->m_translator->translate("nonsense");
        $this->assertEquals("nonsense", $actual);

        // ensure when there's a translations available we get it
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("fr");
        $actual = $this->m_translator->translate("door");
        $this->assertEquals("porte", $actual);

        // ensure when there's a file loaded we get the original string back when there's no translation available
        $actual = $this->m_translator->translate("window");
        $this->assertEquals("window", $actual);
    }

    public function testRemoveSearchPath(): void
    {
        $searchPath = realpath(__DIR__ . "/files/translations/");
        $this->m_translator->addSearchPath($searchPath);
        $this->assertTrue(in_array($searchPath, $this->m_translator->searchPaths()));
        $this->m_translator->removeSearchPath($searchPath);
        $this->assertFalse(in_array($searchPath, $this->m_translator->searchPaths()));
    }

    public function testClearSearchPaths(): void
    {
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->assertNotEmpty($this->m_translator->searchPaths());
        $this->m_translator->clearSearchPaths();
        $this->assertEmpty($this->m_translator->searchPaths());
    }

    public function testSearchPaths(): void
    {
        $searchPaths = [
            realpath(__DIR__ . "/files/translations/"),
            realpath(__DIR__ . "/files/"),
        ];

        foreach ($searchPaths as $searchPath) {
            $this->m_translator->addSearchPath($searchPath);
        }

        $this->assertEquals($searchPaths, $this->m_translator->searchPaths());
    }
}
