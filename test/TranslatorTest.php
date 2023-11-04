<?php

namespace BeadTests;

use Bead\Translator;
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
        self::assertEquals($defaultLanguage->getValue(), $translator->language());

        $translator = new Translator("fr");
        self::assertEquals("fr", $translator->language());
    }

    public function testLanguage(): void
    {
        // ensure we can set a language
        $this->m_translator->setLanguage("en");
        self::assertEquals("en", $this->m_translator->language());

        // ensure we can change language
        $this->m_translator->setLanguage("fr");
        self::assertEquals("fr", $this->m_translator->language());
    }

    public function testHasTranslation(): void
    {
        // ensure when there's no translations file we get false back
        $this->m_translator->setLanguage("this-language-does-not-exist");
        $actual = $this->m_translator->hasTranslation("nonsense");
        self::assertFalse($actual);

        // ensure when there's a translations available we get true
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("fr");
        $actual = $this->m_translator->hasTranslation("door");
        self::assertTrue($actual);

        // ensure when there's a file loaded we get false back when there's no translation available
        $actual = $this->m_translator->hasTranslation("window");
        self::assertFalse($actual);
    }

    public function testSetLanguage(): void
    {
        self::assertNotEquals("fr", $this->m_translator->language());
        $this->m_translator->setLanguage("fr");
        self::assertEquals("fr", $this->m_translator->language());
    }

    public function testAddSearchPath(): void
    {
        $searchPath = realpath(__DIR__ . "/files/translations/");
        $this->m_translator->addSearchPath($searchPath);
        self::assertTrue(in_array($searchPath, $this->m_translator->searchPaths()));
    }

    public function testTranslate(): void
    {
        // ensure when there's no translations file we get the original string back
        $this->m_translator->setLanguage("this-language-does-not-exist");
        $actual = $this->m_translator->translate("nonsense");
        self::assertEquals("nonsense", $actual);

        // ensure when there's a translations available we get it
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("fr");
        $actual = $this->m_translator->translate("door");
        self::assertEquals("porte", $actual);

        // ensure when there's a file loaded we get the original string back when there's no translation available
        $actual = $this->m_translator->translate("window");
        self::assertEquals("window", $actual);
    }

    public function testRemoveSearchPath(): void
    {
        $searchPath = realpath(__DIR__ . "/files/translations/");
        $this->m_translator->addSearchPath($searchPath);
        self::assertTrue(in_array($searchPath, $this->m_translator->searchPaths()));
        $this->m_translator->removeSearchPath($searchPath);
        self::assertFalse(in_array($searchPath, $this->m_translator->searchPaths()));
    }

    public function testClearSearchPaths(): void
    {
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        self::assertNotEmpty($this->m_translator->searchPaths());
        $this->m_translator->clearSearchPaths();
        self::assertEmpty($this->m_translator->searchPaths());
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

        self::assertEquals($searchPaths, $this->m_translator->searchPaths());
    }
}
