<?php
/*
 * Copyright 2005-2013 the original author or authors.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Encapsulates work with database. Implenets singleton pattern to provide only
 * one instance.
 */
Class Database{

	const FETCH_ASSOC = 1;
	const FETCH_NUM = 2;
	const FETCH_BOTH = 4;
	const RETURN_ONE_ROW = 8;
	const RETURN_ALL_ROWS = 16;

	/**
	 * An instance of Database class
	 * @var Database
	 */
	protected static $instance = NULL;

	/**
	 * PDO object
	 * @var PDO
	 */
	protected $dbh = NULL;

	/**
	 * Database host
	 * @var string
	 */
	protected $dbHost = '';

	/**
	 * Database user's login
	 * @var string
	 */
	protected $dbLogin = '';

	/**
	 * Database user's password
	 * @var string
	 */
	protected $dbPass = '';

	/**
	 * Database name
	 * @var string
	 */
	protected $dbName = '';

	/**
	 * Tables prefix
	 * @var string
	 */
	protected $tablesPrefix = '';

	/**
	 * Database connection encoding. Use only if Database::$forceCharsetInConnection set to true
	 * @var string
	 *
	 * @see Database::$forceCharsetInConnection
	 */
	protected $dbEncoding = 'utf8';

	/**
	 * Determine if connection must be forced to charset, specified in
	 *   Database::$dbEncoding
	 * @var boolean
	 *
	 * @see Database::$dbEncoding
	 */
	protected $forceCharsetInConnection = true;

	/**
	 * Determine if connection to the database must be persistent
	 * @var type
	 */
	protected $usePersistentConnection = false;

	/**
	 * Array of prepared SQL statements
	 * @var array
	 */
	protected $preparedStatements = array();

	/**
	 * Id of the last query
	 * @var type
	 */
	protected $lastQuery = NULL;

	/**
	 * Controls if exception must be processed into class or thrown
	 * @var boolean
	 */
	protected $throwExceptions = false;

	/**
	 * Get instance of Database class. If no instance exists, creates new instance.
	 *
	 * @return Database
	 */
	public static function getInstance(){
		if (is_null(self::$instance)) {
			self::$instance = new Database();
		}
		return self::$instance;
	}

	/**
	 * Forbid clone objects
	 */
	private final function __clone() {}

	/**
	 * Database class constructor. Set internal database and connection
	 * properties. Create PDO object and store it in the Database object.
	 *
	 * @global string $mysqlhost Contains mysql host. defined in
	 *   libs/config.php
	 * @global string $mysqllogin Contains mysql login. Defined in
	 *   libs/config.php
	 * @global string $mysqlpass Contains mysql password. Defined in
	 *   libs/config.php
	 * @global string $mysqldb Contains mysql database name. Defined in
	 *   libs/config.php
	 * @global string $dbencoding Contains connection encoding. Defined in
	 *   libs/config.php
	 * @global string $mysqlprefix Contains mysql tables prefix. Defined in
	 *   libs/config.php
	 * @global boolean $force_charset_in_connection Control force charset in
	 *   conection or not. Defined in libs/config.php
	 * @global boolean $use_persistent_connection Control use persistent
	 *   connection to the database or not. Defined in libs/config.php
	 */
	protected function __construct(){
		global $mysqlhost, $mysqllogin, $mysqlpass, $mysqldb, $dbencoding,
			$mysqlprefix, $force_charset_in_connection, $use_persistent_connection;
		try{
			if (! extension_loaded('PDO')) {
				throw new Exception('PDO extension is not loaded');
			}

			if (! extension_loaded('pdo_mysql')) {
				throw new Exception('pdo_mysql extension is not loaded');
			}

			// Set database and connection properties
			$this->dbHost = $mysqlhost;
			$this->dbLogin = $mysqllogin;
			$this->dbPass = $mysqlpass;
			$this->dbName = $mysqldb;
			$this->dbEncoding = $dbencoding;
			$this->tablesPrefix = $mysqlprefix;
			$this->forceCharsetInConnection = $force_charset_in_connection;
			$this->usePersistentConnection = $use_persistent_connection;

			// Create PDO object
			$this->dbh = new PDO(
				"mysql:host={$this->dbHost};dbname={$this->dbName}",
				$this->dbLogin,
				$this->dbPass
			);

			if ($this->forceCharsetInConnection) {
				$this->dbh->exec("SET NAMES ".$this->dbh->quote($this->dbEncoding));
			}

		} catch(Exception $e) {
			$this->handleError($e);
		}
	}

	/**
	 * Handles errors
	 * @param Exception $e
	 */
	protected function handleError(Exception $e){
		if ($this->throwExceptions) {
			throw $e;
		}
		die($e->getMessage());
	}

	/**
	 * Set if exceptions must be process into the class or thrown.
	 * @param boolean $value
	 */
	public function throwExeptions($value){
		$this->throwExceptions = $value;
	}

	/**
	 * Database class destructor.
	 */
	public function __destruct(){
		foreach($this->preparedStatements as $key => $statement) {
			$this->preparedStatements[$key] = NULL;
		}
		$this->dbh = NULL;
		self::$instance = NULL;
	}

	/**
	 * Executes SQL query.
	 * In SQL query can be used PDO style placeholders:
	 * unnamed placeholders (question marks '?') and named placeholders (like
	 * ':name').
	 * If unnamed placeholders are used, $values array must have numeric indexes.
	 * If named placeholders are used, $values param must be an associative array
	 * with keys corresponding to the placeholders names
	 *
	 * Table prefix automatically substitute if table name puts in curly braces
	 *
	 * @param string $query SQL query
	 * @param array $values Values, that must be substitute instead of
	 *   placeholders in SQL query.
	 * @param array $params Array of query parameters. It can contains values with
	 *   following keys:
	 *   - 'return_rows' control if rows must be returned and how many rows must
	 *     be returnd. The value can be Database::RETURN_ONE_ROW for olny one row
	 *     or Database::RETURN_ALL_ROWS for all rows. If this key not specified,
	 *     the function will not return any rows.
	 *   - 'fetch_type' control indexes in resulting rows. The value can be
	 *     Database::FETCH_ASSOC for associative array, Database::FETCH_NUM for
	 *     array with numeric indexes and Database::FETCH_BOTH for both indexes.
	 *     Default value is Database::FETCH_ASSOC.
	 * @return mixed If 'return_rows' key of the $params array is specified,
	 *   returns one or several rows (depending on $params['return_rows'] value) or
	 *   boolean false on fail.
	 *   If 'return_rows' key of the $params array is not specified, returns
	 *   boolean true on success or false on fail.
	 *
	 * @see Database::RETURN_ONE_ROW
	 * @see Database::RETURN_ALL_ROWS
	 * @see Database::FETCH_ASSOC
	 * @see Database::FETCH_NUM
	 * @see Database::FETCH_BOTH
	 */
	public function query($query, $values = NULL, $params = array()){
		try{
			$query = preg_replace("/\{(\w+)\}/", $this->tablesPrefix."$1", $query);

			$query_key = md5($query);
			if (! array_key_exists($query_key, $this->preparedStatements)) {
				$this->preparedStatements[$query_key] = $this->dbh->prepare($query);
			}

			$this->lastQuery = $query_key;

			// Execute query
			$this->preparedStatements[$query_key]->execute($values);

			// Check if error occurs
			if ($this->preparedStatements[$query_key]->errorCode() !== '00000') {
				$errorInfo = $this->preparedStatements[$query_key]->errorInfo();
				throw new Exception(' Query failed: ' . $errorInfo[2]);
			}

			// No need to return rows
			if (! array_key_exists('return_rows', $params)) {
				return true;
			}

			// Some rows must be returned

			// Get indexes type
			if (! array_key_exists('fetch_type', $params)) {
				$params['fetch_type'] = Database::FETCH_ASSOC;
			}
			switch($params['fetch_type']){
				case Database::FETCH_NUM:
					$fetch_type = PDO::FETCH_NUM;
					break;
				case Database::FETCH_ASSOC:
					$fetch_type = PDO::FETCH_ASSOC;
					break;
				case Database::FETCH_BOTH:
					$fetch_type = PDO::FETCH_BOTH;
					break;
				default:
					throw new Exception("Unknown 'fetch_type' value!");
			}

			// Get results
			$rows = array();
			if ($params['return_rows'] == Database::RETURN_ONE_ROW) {
				$rows = $this->preparedStatements[$query_key]->fetch($fetch_type);
			} elseif ($params['return_rows'] == Database::RETURN_ALL_ROWS) {
				$rows = $this->preparedStatements[$query_key]->fetchAll($fetch_type);
			} else {
				throw new Exception("Unknown 'return_rows' value!");
			}
			$this->preparedStatements[$query_key]->closeCursor();

			return $rows;
		} catch(Exception $e) {
			$this->handleError($e);
		}
	}

	/**
	 * Returns value of PDOStatement::$errorInfo property for last query
	 * @return string Error info array
	 *
	 * @see PDOStatement::$erorrInfo
	 */
	public function errorInfo(){
		if (is_null($this->lastQuery)) {
			return false;
		}
		try{
			$errorInfo = $this->preparedStatements[$this->lastQuery]->errorInfo();
		} catch (Exception $e) {
			$this->handleError($e);
		}
		return $errorInfo;
	}

	/**
	 * Returns the ID of the last inserted row
	 *
	 * @return int The ID
	 */
	public function insertedId(){
		try{
			$lastInsertedId = $this->dbh->lastInsertId();
		} catch(Exception $e) {
			$this->handleError($e);
		}
		return $lastInsertedId;
	}

	/**
	 * Get count of affected rows in the last query
	 *
	 * @return int Affected rows count
	 */
	public function affectedRows(){
		if (is_null($this->lastQuery)) {
			return false;
		}
		try{
			$affected_rows =  $this->preparedStatements[$this->lastQuery]->rowCount();
		} catch(Exception $e) {
			$this->handleError($e);
		}
		return $affected_rows;
	}

}

?>