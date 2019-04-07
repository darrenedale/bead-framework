<?php
/**
 * Created by PhpStorm.
 * User: darren
 * Date: 27/10/18
 * Time: 21:28
 */

namespace Equit\Test {
	require_once("classes/equit/AppLog.php");
	require_once("classes/equit/DataController.php");

	class Entity {
		private $m_id = null;
		private $m_text = "";
		private $m_number = 0;
		private $m_date = null;
		private $m_time = null;
		private $m_dateAndTime = null;
		private $m_flag = false;
		private $m_singleEnum = "";
		private $m_multipleSet = [];

		public function id(): ?int {
			return $this->m_id;
		}

		public function setText(string $text): void {
			$this->m_text = $text;
		}

		public function text(): string {
			return $this->m_text;
		}

		public function setNumber(int $number): void {
			$this->m_number = $number;
		}

		public function number(): int {
			return $this->m_number;
		}

		public function setDate(\DateTime $date) {
			$this->m_date = $date;
		}

		public function date(): ?\DateTime {
			return $this->m_date;
		}

		public function setTime(\DateTime $time) {
			$this->m_time = $time;
		}

		public function time(): ?\DateTime {
			return $this->m_time;
		}

		public function setDateAndTime(\DateTime $dateAndTime) {
			$this->m_dateAndTime = $dateAndTime;
		}

		public function dateAndTime(): ?\DateTime {
			return $this->m_dateAndTime;
		}

		public function setFlag(bool $flag) {
			$this->m_flag = $flag;
		}

		public function flag(): bool {
			return $this->m_flag;
		}

		public function setSingleEnum(string $enumerator): bool {
			if(!in_array($enumerator, ["one", "two", "three"])) {
				return false;
			}

			$this->m_singleEnum = $enumerator;
			return true;
		}

		public function singleEnum(): string {
			return $this->m_singleEnum;
		}

		public function setMultipleSet(array $values): bool {
			$values = array_unique($values);

			foreach($values as $setValue) {
				if(!in_array($setValue, ["", "one", "two", "three"])) {
					\Equit\AppLog::error("value \"$setValue\" not found in valid set", __FILE__, __LINE__, __FUNCTION__);
					return false;
				}
			}

			$this->m_multipleSet = $values;
			return true;
		}

		public function multipleSet(): array {
			return $this->m_multipleSet;
		}
	}

	use Equit\DataController;
	use Equit\AppLog;
	use PHPUnit\Framework\TestCase;

	class DataControllerEntityMappingTest extends TestCase {

		// "mysql:host=localhost;dbname=aio_dev;charset=utf8"
		private const DefaultDbScheme  = "mysql";
		private const DefaultDbHost    = "localhost";
		private const DefaultDbPort    = 3306;
		private const DefaultDbName    = "libequit_datacontroller_test_db";
		private const DefaultTableName = "test_entity";
		private const DefaultUsername  = "libequit_test_user";
		private const DefaultPassword  = "perambul3";

		private static $s_entityFieldMapping = [];
		private static $s_primaryKeyMapping  = null;

		protected $scheme   = self::DefaultDbScheme;
		protected $host     = self::DefaultDbHost;
		protected $port     = self::DefaultDbPort;
		protected $dbName   = self::DefaultDbName;
		protected $table    = self::DefaultTableName;
		protected $userName = self::DefaultUsername;
		protected $password = self::DefaultPassword;
		protected $db       = null;

		public function __construct() {
			parent::__construct();

			self::$s_entityFieldMapping = [
				"text" => (object)["type" => DataController::CharField],
				"number" => (object)["type" => DataController::IntField],
				"date" => (object)["type" => DataController::DateField],
				"time" => (object)["type" => DataController::TimeField],
				"date_and_time" => (object)["type" => DataController::DateTimeField, "accessor" => "dateAndTime", "mutator" => "setDateAndTime"],
				"flag" => (object)["type" => DataController::BoolField],
				"single_enum" => (object)["type" => DataController::EnumField, "accessor" => "singleEnum", "mutator" => "setSingleEnum"],
				"multiple_set" => (object)["type" => DataController::SetField, "accessor" => "multipleSet", "mutator" => "setMultipleSet"],
			];

			self::$s_primaryKeyMapping = (object)["fieldName" => "id", "propertyName" => "m_id"];

			$resourceUri = "{$this->scheme}:host={$this->host};dbname={$this->dbName};charset=utf8";
			$this->db    = new DataController($resourceUri, $this->userName, $this->password);
			$log         = new AppLog("php://stderr");
			$log->open();
			AppLog::setMessageLog($log);
			AppLog::setErrorLog($log);
			AppLog::setWarningLog($log);
		}

		public function setUp() {
			$this->db->exec("DELETE FROM `{$this->table}`");

			$this->db->exec("INSERT INTO `{$this->table}` VALUES " . <<<EOT
(1, 'sample text', 42, '1974-04-23', '16:38', '1974-04-23 16:38', FALSE, 'two', 'one,three'),
(2, 'something a bit longer than the previous record\'s text', -1, '1999-12-31', '23:59:59', '1999-12-31 23:59', TRUE, 'one', 'three'),
(3, '', 44, '0000-00-00', '00:00:00', '01/01/2010 19:30:00', FALSE, 'three', 'two,three')
EOT
			);
		}

		public function testAddEntityMapping() {
			$this->assertInstanceOf(DataController::class, $this->db, "Data controller instance not available");
			$this->assertTrue($this->db->addEntityMapping($this->table, Entity::class, self::$s_primaryKeyMapping, self::$s_entityFieldMapping));
		}

		/**
		 * @depends testAddEntityMapping
		 */
		public function testRetrieveEntity() {
			$this->assertInstanceOf(DataController::class, $this->db, "Data controller instance not available");
			$this->db->addEntityMapping($this->table, Entity::class, self::$s_primaryKeyMapping, self::$s_entityFieldMapping);

			$entity = $this->db->find(Entity::class, 1);
			$this->assertInstanceOf(Entity::class, $entity, "The DataController::find() method did not provide a valid Entity instance for id = 1");
			$this->assertEquals(1, $entity->id());
			$this->assertEquals("sample text", $entity->text());
			$this->assertEquals(42, $entity->number());
			$this->assertEquals(false, $entity->flag());
			$this->assertEquals("two", $entity->singleEnum());
			$this->assertEquals(["one", "three"], $entity->multipleSet());

			$date = $entity->date();
			$this->assertInstanceOf(\DateTime::class, $date);
			$this->assertEquals(1974, (int)$date->format("Y"));
			$this->assertEquals(4, (int)$date->format("m"));
			$this->assertEquals(23, (int)$date->format("d"));

			$time = $entity->time();
			$this->assertInstanceOf(\DateTime::class, $time);
			$this->assertEquals(16, (int)$time->format("H"));
			$this->assertEquals(38, (int)$time->format("i"));
			$this->assertEquals(0, (int)$time->format("s"));

			$dateTime = $entity->dateAndtime();
			$this->assertInstanceOf(\DateTime::class, $dateTime);
			$this->assertEquals(1974, (int)$dateTime->format("Y"));
			$this->assertEquals(4, (int)$dateTime->format("m"));
			$this->assertEquals(23, (int)$dateTime->format("d"));
			$this->assertEquals(16, (int)$dateTime->format("H"));
			$this->assertEquals(38, (int)$dateTime->format("i"));
			$this->assertEquals(0, (int)$dateTime->format("s"));

			$entity = $this->db->find(Entity::class, 2);
			$this->assertInstanceOf(Entity::class, $entity, "The DataController::find() method did not provide a valid Entity instance for id = 2");
			$this->assertEquals(2, $entity->id());
			$this->assertEquals("something a bit longer than the previous record's text", $entity->text());
			$this->assertEquals(-1, $entity->number());
			$this->assertEquals(true, $entity->flag());
			$this->assertEquals("one", $entity->singleEnum());
			$this->assertEquals(["three"], $entity->multipleSet());

			$entity = $this->db->find(Entity::class, 3);
			$this->assertEquals(3, $entity->id());
			$this->assertInstanceOf(Entity::class, $entity, "The DataController::find() method did not provide a valid Entity instance for id = 3");
		}

		public function testUpdateEntity() {
			$this->assertInstanceOf(DataController::class, $this->db, "Data controller instance not available");
			$this->db->addEntityMapping($this->table, Entity::class, self::$s_primaryKeyMapping, self::$s_entityFieldMapping);
			$entity = $this->db->find(Entity::class, 1);
			$this->assertInstanceOf(Entity::class, $entity, "The DataController::find() method did not provide a valid Entity instance for id = 1");
			$entity->setText("different text");
			$this->assertEquals("different text", $entity->text(), "The different text failed to be assigned to the object");
			$this->assertTrue($this->db->update($entity), "The DataController::update() method failed");
			$entity = $this->db->find(Entity::class, 1);
			$this->assertInstanceOf(Entity::class, $entity, "The DataController::find() method did not provide a valid Entity instance for id = 1");
			$this->assertEquals("different text", $entity->text(), "The different text assigned to the entity was not stored in the database");
		}

		public function testInsertEntity() {
			$this->assertInstanceOf(DataController::class, $this->db, "Data controller instance not available");
			$this->db->addEntityMapping($this->table, Entity::class, self::$s_primaryKeyMapping, self::$s_entityFieldMapping);
			$entity = new Entity();
			$entity->setText("New object");
			$entity->setNumber(180);
			$dateTime = new \DateTime();
			$entity->setDate($dateTime);
			$entity->setTime($dateTime);
			$entity->setDateAndTime($dateTime);
			$entity->setFlag(false);
			$entity->setSingleEnum("two");
			$entity->setMultipleSet(["one", "three"]);

			$this->assertTrue($this->db->insert($entity), "failed to insert new entity");
			$insertedId = $entity->id();
			$this->assertGreaterThan(0, $insertedId, "the inserted entity did not receive a valid ID");

			$storedEntity = $this->db->find(Entity::class, $insertedId);

			$this->assertEquals("New object", $storedEntity->text(), "text for retrieved inserted object is not as expected");
			$this->assertEquals(180, $storedEntity->number(), "number for retrieved inserted object is not as expected");
			$this->assertEquals($dateTime->format("Ymd"), $storedEntity->date()->format("Ymd"), "date for retrieved inserted object is not as expected");
			$this->assertEquals($dateTime->format("His"), $storedEntity->time()->format("His"), "time for retrieved inserted object is not as expected");
			$this->assertEquals($dateTime->format("YmdHis"), $storedEntity->dateAndTime()->format("YmdHis"), "date and time for retrieved inserted object is not as expected");
			$this->assertEquals(false, $storedEntity->flag(), "flag for retrieved inserted object is not as expected");
			$this->assertEquals("two", $storedEntity->singleEnum(), "enum for retrieved inserted object is not as expected");
			$this->assertEquals(["one", "three"], $storedEntity->multipleSet(), "set for retrieved inserted object is not as expected");
		}

		public function testDeleteEntity() {
			$this->assertInstanceOf(DataController::class, $this->db, "Data controller instance not available");
			$this->db->addEntityMapping($this->table, Entity::class, self::$s_primaryKeyMapping, self::$s_entityFieldMapping);
			$this->assertTrue($this->db->delete(Entity::class, 1), "failed to delete entity with ID = 1");
			$this->assertNull($this->db->find(Entity::class, 1), "deleted entity still retrieved");
			$entity = $this->db->find(Entity::class, 2);
			$this->assertTrue($this->db->delete($entity), "failed to delete entity with ID = 2");
			$this->assertNull($this->db->find(Entity::class, 2), "deleted entity still retrieved");
		}
	}
}
