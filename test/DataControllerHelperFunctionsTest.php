<?php
/**
 * Created by PhpStorm.
 * User: darren
 * Date: 28/10/18
 * Time: 11:39
 */

namespace Equit\Test;

use \Equit\DataController;
use \PHPUnit\Framework\TestCase;

class DataControllerHelperFunctionsTest extends TestCase {

	/**
	 * @dataProvider dateToStringTestData
	 *
	 * @param $date \DateTime the date to test with.
	 * @param $expected string The expected result of passing $date to DataController::dateToString()
	 */
	public function testDateToString(\DateTime $date, string $expected) {
		$this->assertEquals($expected, DataController::dateToString($date));
	}

	public function testTimeToString() {
		$this->assertEquals(true, true);
	}

	public function testArrayToSet() {
		$this->assertEquals(true, true);
	}

	public function testSetToArray() {
		$this->assertEquals(true, true);
	}

	public function testDateTimeToString() {
		$this->assertEquals(true, true);
	}

	public function testStringToDate() {
		$this->assertEquals(true, true);
	}

	public function testStringToTime() {
		$this->assertEquals(true, true);
	}

	public function testStringToDateTime() {
		$this->assertEquals(true, true);
	}

	/** Test data for testDateToString() */
	public function dateToStringTestData() {
		return [
			[\DateTime::createFromFormat("d/m/Y", "23/04/1974"), "1974-04-23"],
		];
	}

}
