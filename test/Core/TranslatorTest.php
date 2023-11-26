<?php

namespace BeadTests\Core;

use Bead\Core\Translator;
use InvalidArgumentException;
use BeadTests\Framework\TestCase;

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
        parent::tearDown();
    }

    /** Ensure we get the expected default language. */
    public function testConstructor1(): void
    {
        self::assertEquals("en", $this->m_translator->language());
    }

    /** Ensure we can set a language in the constructor. */
    public function testConstructor2(): void
    {
        $translator = new Translator("fr");
        self::assertEquals("fr", $translator->language());
    }

    /** Ensure we get back the expected language. */
    public function testLanguage1(): void
    {
        $this->m_translator->setLanguage("fr");
        self::assertEquals("fr", $this->m_translator->language());
    }

    /** Ensure we get back the expected language for a compound language tag. */
    public function testLanguage2(): void
    {
        $this->m_translator->setLanguage("fr-BE");
        self::assertEquals("fr-BE", $this->m_translator->language());
    }

    /** Ensure we get back the expected generic language. */
    public function testGenericLanguage1(): void
    {
        $this->m_translator->setLanguage("fr");
        self::assertEquals("fr", $this->m_translator->genericLanguage());
    }

    /** Ensure we get back the expected generic language for a compound language tag. */
    public function testGenericLanguage2(): void
    {
        $this->m_translator->setLanguage("fr-BE");
        self::assertEquals("fr", $this->m_translator->genericLanguage());
    }

    public static function dataForTestSetLanguage1(): iterable
    {
        yield "leading whitespace language only" => ["  en"];
        yield "trailing whitespace language only" => ["fr  "];
        yield "leading and trailing whitespace language only" => ["  de  "];
        yield "leading whitespace compound" => ["  en-GB"];
        yield "trailing whitespace compound" => ["fr-BE  "];
        yield "leading and trailing whitespace compound" => ["  de-AT  "];
    }

    /**
     * Ensure whitespace is trimmed when setting the language.
     *
     * @dataProvider dataForTestSetLanguage1
     */
    public function testSetLanguage1(): void
    {
        $this->m_translator->setLanguage("fr-BE");
        self::assertEquals("fr", $this->m_translator->genericLanguage());
    }

    public static function dataForTestSetLanguage2(): iterable
    {
        yield "empty" => [""];
        yield "just whitespace" => ["  "];
        yield "whitespace before region" => ["en- gb"];
        yield "whitespace after language" => ["en -gb"];
        yield "whitespace after language and before region" => ["en - gb"];
        yield "excess language characters, language only" => ["enab"];
        yield "insufficient language characters, language only" => ["e"];
        yield "invalid language characters, language only" => ["3n"];
        yield "excess language characters, compound" => ["enab-GB"];
        yield "insufficient language characters, compound" => ["e-GB"];
        yield "invalid language characters, compound" => ["3n-GB"];
        yield "excess region characters" => ["en-GBB"];
        yield "insufficient region characters" => ["en-G"];
        yield "invalid region characters" => ["en-G3"];
    }

    /**
     * Ensure we get the expected exception with a malformed language tag.
     *
     * @dataProvider dataForTestSetLanguage2
     */
    public function testSetLanguage2(string $language): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage("Expected valid IETF language tag, found \"{$language}\"");
        $this->m_translator->setLanguage($language);
    }

    /** Ensure missing translation file indicates there is no translation. */
    public function testHasTranslation1(): void
    {
        $this->m_translator->setLanguage("en-GB");
        $actual = $this->m_translator->hasTranslation("nonsense");
        self::assertFalse($actual);
    }

    /** Ensure a translation file with a translation indicates there is a translation available. */
    public function testHasTranslation2(): void
    {
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("fr");
        $actual = $this->m_translator->hasTranslation("door");
        self::assertTrue($actual);
    }

    /** Ensure a translation file without a matching translation indicates there is no translation available. */
    public function testHasTranslation3(): void
    {
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("fr");
        $actual = $this->m_translator->hasTranslation("window");
        self::assertFalse($actual);
    }

    /** Ensure we get true when the translation file doesn't have a translation but the generic-language one does. */
    public function testHasTranslation4(): void
    {
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("fr-FR");
        $actual = $this->m_translator->hasTranslation("door");
        self::assertTrue($actual);
    }

    /** Ensure we get true when the main translation file doesn't exist but the generic-language one does and has a translation. */
    public function testHasTranslation5(): void
    {
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("es-CL");
        $actual = $this->m_translator->hasTranslation("door");
        self::assertTrue($actual);
    }

    /** Ensure we can add a search path. */
    public function testAddSearchPath1(): void
    {
        $searchPath = realpath(__DIR__ . "/files/translations/");
        $this->m_translator->addSearchPath($searchPath);
        self::assertTrue(in_array($searchPath, $this->m_translator->searchPaths()));
    }

    /** Ensure search paths get canonicalised. */
    public function testAddSearchPath2(): void
    {
        $searchPath = realpath(__DIR__ . "/files/../files/translations/");
        $this->m_translator->addSearchPath(__DIR__ . "/files/../files/translations/");
        self::assertTrue(in_array($searchPath, $this->m_translator->searchPaths()));
    }

    /** Ensure duplicate search paths do not get added. */
    public function testAddSearchPath3(): void
    {
        $paths = $this->m_translator->searchPaths();
        $searchPath = realpath(__DIR__ . "/files/../files/translations/");
        self::assertFalse(in_array($searchPath, $paths));
        $this->m_translator->addSearchPath(__DIR__ . "/files/../files/translations/");
        $paths = $this->m_translator->searchPaths();
        self::assertTrue(in_array($searchPath, $paths));
        $this->m_translator->addSearchPath($searchPath);
        self::assertEquals($paths, $this->m_translator->searchPaths());
    }

    /** Ensure search paths can be removed. */
    public function testRemoveSearchPath1(): void
    {
        $searchPath = realpath(__DIR__ . "/files/translations/");
        $this->m_translator->addSearchPath($searchPath);
        self::assertTrue(in_array($searchPath, $this->m_translator->searchPaths()));
        $this->m_translator->removeSearchPath($searchPath);
        self::assertFalse(in_array($searchPath, $this->m_translator->searchPaths()));
    }

    /** Ensure search paths are canonicalised before being removed. */
    public function testRemoveSearchPath2(): void
    {
        $searchPath = realpath(__DIR__ . "/files/translations/");
        $this->m_translator->addSearchPath($searchPath);
        self::assertTrue(in_array($searchPath, $this->m_translator->searchPaths()));
        $this->m_translator->removeSearchPath(__DIR__ . "/files/../files/translations/");
        self::assertFalse(in_array($searchPath, $this->m_translator->searchPaths()));
    }

    /** Ensure removing a search path that's not a valid path does not filter the paths. */
    public function testRemoveSearchPath3(): void
    {
        $path = "/this/path/does/not/exist";
        self::assertFalse(file_exists($path));
        $arrayFilterCalled = false;

        $this->mockFunction("array_filter", function () use (&$arrayFilterCalled) {
            $arrayFilterCalled = true;
        });

        $this->m_translator->removeSearchPath($path);
        self::assertFalse($arrayFilterCalled);
    }

    /** Ensure clearing the search paths wipes them all out. */
    public function testClearSearchPaths1(): void
    {
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        self::assertNotEmpty($this->m_translator->searchPaths());
        $this->m_translator->clearSearchPaths();
        self::assertEmpty($this->m_translator->searchPaths());
    }

    /** Ensure when there's no translations file we get the original string back */
    public function testTranslate1(): void
    {
        $this->m_translator->setLanguage("de-AT");
        $actual = $this->m_translator->translate("nonsense");
        self::assertEquals("nonsense", $actual);
    }

    /** ensure when there's a translation available we get it */
    public function testTranslate2(): void
    {
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("fr");
        $actual = $this->m_translator->translate("door");
        self::assertEquals("porte", $actual);
    }

    /** ensure when there's a file loaded we get the original string back when there's no translation available */
    public function testTranslate3(): void
    {
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("fr");
        $actual = $this->m_translator->translate("window");
        self::assertEquals("window", $actual);
    }

    /** Ensure we fall back to the generic language if there's no region-specific translation */
    public function testTranslate4(): void
    {
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("fr-FR");
        $actual = $this->m_translator->translate("door");
        self::assertEquals("porte", $actual);
    }

    /** Ensure we prefer region-specific translations if both the region-specific and generic language files have a translation */
    public function testTranslate5(): void
    {
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("fr-FR");
        $actual = $this->m_translator->translate("fork");
        self::assertEquals("forchette", $actual);
    }

    /** Ensure we get the generic translation when the main translation file doesn't exist but the generic-language one does. */
    public function testTranslate6(): void
    {
        $this->m_translator->addSearchPath(__DIR__ . "/files/translations/");
        $this->m_translator->setLanguage("es-CL");
        $actual = $this->m_translator->translate("door");
        self::assertEquals("puerta", $actual);
    }
}
