<?php

namespace BeadTests\Validation\Rules;

use BeadTests\Framework\TestCase;
use Equit\Validation\Rules\Nullable;
use Equit\Validation\ValidatorAwareRule;
use Equit\Validation\Validator;
use Mockery;

class NullableTest extends TestCase
{
	use TestsKnowsValidatorTrait;

	/** @var Validator&MockInterface  */
	private $m_validator;

	public function setUp(): void
	{
		$this->m_validator = Mockery::mock(Validator::class);
	}

	public function tearDown(): void
	{
		unset($this->m_validator);
	}

	/**
	 * Provide a Rule instance for the TestsKnowsValidatorTrait trait.
	 *
	 * @return ValidatorAwareRule The rule to test with.
	 */
	protected function createValidatorAwareRule(): ValidatorAwareRule
	{
		return new Nullable();
	}

	/**
	 * Create a new rule instance to test with.
	 *
	 * The mock validator will be set as the rule's validator.
	 *
	 * @return Nullable The test rule.
	 */
	private function createNullableRule(): Nullable
	{
		$rule = new Nullable();
		$rule->setValidator($this->m_validator);
		return $rule;
	}

	/**
	 * Test data provider for all tests.
	 *
	 * @return iterable The test data.
	 */
	public function dataForAllTests(): iterable
	{
		yield from [
			"typicalFalse" => [false, false,],
			"typicalTrue" => [true, false,],
			"typicalInt0" => [0, false,],
			"typicalFloat0" => [0.0, false,],
			"typicalEmptyString" => ["", true,],
			"typicalNull" => [null, true,],
			"typicalEmptyArray" => [[], true,],
			"extremeInt0Array" => [[0,], false,],
			"extremeFloat0Array" => [[0.0,], false,],
			"extremeEmptyStringArray" => [["",], false,],
			"extremeNullArray" => [[null,], false,],
		];
	}

	/**
	 * @dataProvider dataForAllTests
	 *
	 * @param mixed $value The value to validate.
	 * @param bool $isNull Whether the value ought to be considererd null.
	 */
	public function testPasses($value, bool $isNull): void
	{
		$rule = $this->createNullableRule();

		if ($isNull) {
			$this->m_validator->shouldReceive("clearErrors")->with("field")->once();
			$this->m_validator->shouldReceive("skipRemainingRules")->with("field")->once();
		} else {
			$this->m_validator->shouldNotReceive("clearErrors");
			$this->m_validator->shouldNotReceive("skipRemainingRules");
		}

		// rule always passes, our test expectations are handled by Mockery
		$this->assertTrue($rule->passes("field", $value));
	}

	/**
	 * @dataProvider dataForAllTests
	 *
	 * @param mixed $value The value to validate.
	 * @param bool $isNull Whether the value ought to be considererd null.
	 */
	public function testConvert($value, bool $isNull): void
	{
		$rule = $this->createNullableRule();

		if ($isNull) {
			$this->assertEquals(null, $rule->convert($value));
		} else {
			$this->assertEquals($value, $rule->convert($value));
		}
	}

	/**
	 * Ensure the message is always an empty string.
	 *
	 * The rule always passes, so there is never any need for the message.
	 *
	 * @dataProvider dataForAllTests
	 *
	 * @param mixed $value The value to test with.
	 * @param bool $isNull Ignored.
	 */
	public function testMessage($value, bool $isNull): void
	{
		$rule = $this->createNullableRule();
		$this->m_validator->shouldIgnoreMissing();
		$rule->passes("field", $value);
		$this->assertEquals("", $rule->message("field"));
	}
}
