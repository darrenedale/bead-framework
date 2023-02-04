<?php

declare(strict_types=1);

namespace BeadTests\Helpers;

use Bead\Application;
use Bead\Contracts\Translator;
use BeadTests\Framework\TestCase;
use Mockery;
use TypeError;

use function Bead\Helpers\I18n\tr;

final class I18nTest extends TestCase
{
	private Application $m_app;
	private Translator $m_translator;

	public function setUp(): void
	{
		$this->m_app = Mockery::mock(Application::class);
		$this->m_translator = new class implements Translator
		{
			public function hasTranslation(string $string): bool
			{
				return true;
			}

			public function translate(string $string, string $file = null, int $line = null, mixed ... $args): string
			{
				return match($string) {
					"window" => "fenetre",
					"door" => "porte",
					"floor %1" => "etage %1",
					"exit %1 %2" => "sortie %2 %1",
					default => $string,
				};
			}

			public function language(): string
			{
				return "fr_FR";
			}

			public function setLanguage(string $language): void
			{
			}
		};

		uopz_set_return(Application::class, "instance", $this->m_app);
	}

	public function tearDown(): void
	{
		uopz_unset_return(Application::class, "instance");
		Mockery::close();
	}

	public function dataForTestTr(): iterable
	{
		yield from [
			"typicalWindow" => ["window", "fenetre",],
			"typicalDoor" => ["door", "porte",],
			"typicalWithArgs" => ["floor %1", "etage 42", 42,],
			"typicalWithArgOrderChanged" => ["exit %1 %2", "sortie 3.1415927 42", 42, 3.1415927,],
			"extremeNoTranslation" => ["wall", "wall",],
		];
	}

	/**
	 * @dataProvider dataForTestTr
	 *
	 * @param string $str The string to translate.
	 * @param string $expected The expected translation.
	 * @param mixed ...$args The content for the translation placeholders.
	 */
	public function testTr(string $str, string $expected, mixed ... $args): void
	{
		$this->m_app->shouldReceive("translator")
			->andReturn($this->m_translator);

		$actual = tr($str, __FILE__, __LINE__, ...$args);
		self::assertEquals($expected, $actual);
	}

	public function testTrWithoutTranslator(): void
	{
		$this->m_app->shouldReceive("translator")
			->andReturn(null);

		self::assertEquals("foo", tr("foo"));
		self::assertEquals("foo bar", tr("foo %1", __FILE__, __LINE__, "bar"));
		self::assertEquals("foo bar baz", tr("foo %2 %1", __FILE__, __LINE__, "baz", "bar"));
	}

	public function testTrWithoutApp(): void
	{
		uopz_set_return(Application::class, "instance", null);

		self::assertEquals("foo", tr("foo"));
		self::assertEquals("foo bar", tr("foo %1", __FILE__, __LINE__, "bar"));
		self::assertEquals("foo bar baz", tr("foo %2 %1", __FILE__, __LINE__, "baz", "bar"));
	}
}
