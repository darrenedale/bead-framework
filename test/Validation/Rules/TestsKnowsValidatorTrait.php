<?php

namespace Equit\Test\Validation\Rules;

use Equit\Validation\Validator;
use Equit\Validation\ValidatorAwareRule;
use Mockery;

trait TestsKnowsValidatorTrait
{
	/**
	 * Importing classes must provide a rule to test with.
	 */
	abstract protected function createValidatorAwareRule(): ValidatorAwareRule;

	/**
	 * Ensure the validator can be set and retrieved.
	 */
	public function testValidator(): void
	{
		$rule = self::createValidatorAwareRule();
		$this->assertNull($rule->validator());

		$validator = Mockery::mock(Validator::class);
		$rule->setValidator($validator);
		self::assertSame($validator, $rule->validator());
	}
}
