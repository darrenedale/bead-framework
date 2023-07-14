<?php

declare(strict_types=1);

namespace BeadTests\Encryption;

use Bead\Encryption\Crypter;
use Bead\Testing\XRay;
use Exception;
use PHPUnit\Framework\TestCase;

class CrypterTest extends TestCase
{

	public function testConstructor(): void
	{
		$crypter = new Crypter('a-bad-key');
		self::assertEquals('a-bad-key', (new XRay($crypter))->key());
	}


	public function testConstructorThrows(): void
	{
		self::expectException(Exception::class);
		self::expectExceptionMessage('The encryption key must not be empty');
		new Crypter('');
	}
}
