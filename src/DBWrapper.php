<?php

namespace CorbeauPerdu\Database;

use PDO;
use PDOException;
use CorbeauPerdu\Database\Exceptions\DBWrapperException;
use CorbeauPerdu\Database\Exceptions\DBWrapperGenericException;
use CorbeauPerdu\Database\Exceptions\DBWrapperOpenDBException;
use CorbeauPerdu\Database\Exceptions\DBWrapperCloseDBException;
use CorbeauPerdu\Database\Exceptions\DBWrapperStoreDataException;
use CorbeauPerdu\Database\Exceptions\DBWrapperReadDataException;
use CorbeauPerdu\Database\Exceptions\DBWrapperReadColsException;
use CorbeauPerdu\Database\Exceptions\DBWrapperNoDataFoundException;
use CorbeauPerdu\Database\Exceptions\DBWrapperMultiStatementException;
use CorbeauPerdu\Database\Exceptions\DBWrapperStatementException;
use CorbeauPerdu\Database\Exceptions\DBWrapperBusyException;

// Include config files
require_once ( 'DBWrapperConfig.php' );
require_once ( 'DBWrapperStatement.php' );
require_once ( 'DBWrapperExceptions.php' );

/******************************************************************************
 * DB Wrapper (based on Perl and Java DB Wrapper by P.Roy).
 * 
 * MIT License
 * 
 * Copyright (c) 2020 Patrick Roy
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 *
 * <pre>
 * Name: DBWrapper.php
 *
 * Usage:
 *   use CorbeauPerdu\Database\DBWrapper;
 *   use CorbeauPerdu\Database\Exceptions\DBWrapperException;
 *
 *   require('DBWrapper.php');
 *
 *   try {
 *      // OPTION 1: provide database configuration to constructor
 *      $host = "127.0.0.1";
 *      $port = "3306";
 *      $database = "dbName";
 *      $user = "jdoe";
 *      $password = "pwd";
 *      $dbtype = DBWrapper::DBTYPE_MYSQL;
 *      $charset = DBWrapper::CHARSET_UTF8;
 *      $pdoAttribs = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
 *      $throwExOnNoData = true;
 *      $includeDataInMultiEX = false;
 *      $useCommitOnEachExecV1 = true
 *      $debug = true;
 *      $project = "MYPROJECT";
 *
 *      // Notes about $useCommitOnEachExecV1 (used if you plan on doing multi-inserts/updates/deletes and want to commit on each execute):
 *      //   They're are two versions of stmtCommitOnEachExec() in the DBWrapperStatement (V1 & V2).
 *      //   The V1 is faster, and better written. However, it caused a SEGFAULT on my server's setup (PHP 7.1 on an old NAS)
 *      //   The V2 is a replacement (HOTFIX) to it, which works well, but runs somewhat slower!
 *
 *      $mydb = new DBWrapper( $host, $port, $database, $user, $password, $dbType, $charset, $pdoAttribs, $throwExOnNoData, $includeDataInMultiEX, $useCommitOnEachExecV1, $debug, $project)
 *      ...
 *
 *      // OPTION 2: uses DBWrapperConfig.php to configure database connectivity
 *      $debug = true;
 *      $project = "MYPROJECT";
 *      $mydb = new DBWrapper( $debug, $project );
 *      ...
 *   } catch (DBWrapperException $ex) {
 *      die($ex.getMessage());
 *   }
 *
 * Main function listing:
 *
 * $mydb->OpenDB();                     // open DB connection
 * $mydb->CloseDB();                    // close DB connection
 * $con = &$mydb->getConnection();      // opens DB connection and returns a PDO connection object; do as you will with it afterwards
 *
 * $data = $mydb->readData(...);        // get data from database
 * $cols = $mydb->readColumnsMeta(...); // get table columns metadata
 * $mydb->storeData(...);               // store, update or delete data from database
 *
 * Note: you do not need to explicitaly call OpenDB / CloseDB, because the read*() and storeData() will take care of that, if you haven't!
 *
 * Other useful functions from DBWrapper:
 *
 * // get the number of affected rows (number of rows return from SELECTS, also number of successful DELETES, INSERTS, etc)
 * $mydb->getAffectedRows();
 *
 * // get the number of failed rows when doing a multi-execute statement
 * $mydb->getFailedRows();
 *
 * // get row ID of the last succesful INSERT
 * $mydb->getLastInsertId();
 *
 * // get execution time of query
 * $mydb->getRuntime();
 *
 *
 * DBWrapperException Functions to use when catching exception:
 *
 * $ex->getMessage()       // returns error message
 * $ex->getMessage_toWeb() // returns error message in a web readable format (i.e. replaces '\n' with '<br/>'
 * $ex->getCode()          // custom exception code
 * $ex->getDbCode()        // database driver error code (i.e. error codes returned by MySQL, and not PDO)
 * $ex->getPdoCode()       // SQLSTATE / PDOException error code
 * $ex->getErrorList()     // if we ran a multi-row insert/delete/update, and we've commited everyrow, the ones that failed will be in this retured array, along with the error messages
 * $ex->getErrorList_toString() // prints errorList array content with linebreaks (\n)
 * $ex->getErrorList_toWeb()    // prints errorList array content, formatted for the web (<br/>)
 *
 * $ex->getDbCode() can be quite handy for let's say we insert a new user, and want to catch if the user already exists.
 * In such a case, MySQL / getDbCode() will return code '1062': Integrity constraint violation: 1062 Duplicate entry...
 *
 * Another case scenario is if we try to delete say a user, but this user has data from another table attached to him, where MySQL is setup to prevent deletion of such a user
 * In such a case, MySQL / getDbCode() will return code '1451': Cannot delete or update a parent row: a foreign key constraint fails
 *
 * We can then act upon this, say by throwing an error to the webuser saying "hey! that user already exists!" or "hey! can't delete that user! he's got existing contracts attached to him!"
 *
 * Other things:
 * - If you create the DBWrapper object with it's $debug set to true, error messages in exceptions will show
 *   stacktrace information as well as output DB activity to system log
 * - The storeData() and read*() functions have a $closeDB parameter.
 *   So, to speed things up, if you have multiple different statement to run one after the other (select, then update, etc.),
 *   run the first statement with $closeDB set to FALSE, and proceed with the remaining statements, using the same DBWrapper object
 *   Set $closeDB back to TRUE on the last statement, or simply unset your DBWrapper / let your script exit
 *   which will eventually call the DBWrapper destruct method (thus closing the DB connection)
 *
 *
 * Required: DBWrapperConfig, DBWrapperStatement, DBWrapperExceptions
 *
 * Last Modified : 2020/03/09 by PRoy - First release
 *                 2020/03/12 by PRoy - Added $fetchStyle to readData()
 *                 2020/04/17 by PRoy - Added the insertData(), deleteData() and updateData(), all aliases to storeData()
 *                 2020/05/19 by PRoy - Added setThrowExOnNoData() to temporarily be able to change value for special requirements
 *                 2020/06/13 by PRoy - Renamed $fetchStyle to $fetchMode in readData() to allow sending an array of args to the PDOStatement::setFetchMode().
 *                                      Also added a $fetchAllRows to readData() to determine if we want all rows returned (use fetchAll()) or a single row (use fetch())
 *                 2020/07/19 by PRoy - Added getMySQLWarnings() and getDbType()
 * </pre>
 *
 * @author      Patrick Roy (ravenlost2@gmail.com)
 * @version     1.3.0
 *
 * @todo See if DBwrapper::readColumnsMeta() works on Oracle?
 * @todo Add a third consruct that would simply get a DSN name of the database to connect to
 * @todo Eventually see if it wouldn't be better to change DBWrapperStatement to an abstract class and extend DBWrapperConfig to it.
 *       Then, extend DBWrapperStatement to this DBWrapper class.
 ******************************************************************************/
class DBWrapper extends DBWrapperConfig
{
  public const VERSION = "1.3.0";
  public const DBTYPE_ORACLE = "oracle";
  public const DBTYPE_MYSQL = "mysql";
  public const CHARSET_UTF8 = "utf8";
  public const CHARSET_UTF8MB4 = "utf8mb4";
  public const CHARSET_BINARY = "binary";
  public const CHARSET_LATIN1 = "latin1";
  private $_project;
  private $_host;
  private $_port;
  private $_dbname;
  private $_user;
  private $_passwd;
  private $_pdoAttribs;
  private $_dbType = self::DBTYPE_MYSQL;
  private $_charset = self::CHARSET_UTF8;
  private $_throwExOnNoData = true;
  private $_includeDataInMultiEX = false;
  private $_useCommitOnEachExecV1 = true;
  private $_debug = true;
  private $_conn = null;
  private $_busy = false;
  private $_runstart = null;
  private $_runend = null;
  private $_runtime_stmntonly = null;
  private $_stmtAffectedRows = 0;
  private $_stmtFailedRows = null;
  private $_lastInsertId = null;

  /******************************************************************************
   * Class Constructor.
   *
   * Build a new DBWrapper instance using a TCP/IP connection.
   *
   * <pre>
   * They are 2 constructors possible.
   *
   * OPTION 1: rely on DBWrapperConfig for DB Configurations
   *
   * \@param boolean debug           If set, error log will include DB Activity, SQL, as well as stacktrace
   * \@param string project          Name of the project: used only to prepend errors in error_log
   *
   *
   * OPTION 2: pass along all required DB configurations
   *
   * \@param string host             Server where the database resides.
   * \@param string port             Port the database is running on.
   * \@param string dbname           The actual database name to connect to.<br/>
   *                                 For Oracle, just use the service name (i.e. 'ora9d1').<br/>
   *                                 For MySQL, put in the database name (i.e. 'phonedir').
   * \@param string user             Username to connect with.
   * \@param string password         Password to use for connection.
   * \@param string dbType           Database type we're connecting to (i.e. oracle, mysql)\n
   *                                 Users should use the proper constants!
   *                                 DBWrapper::DBTYPE_MYSQL or DBWrapper::DBTYPE_ORACLE
   * \@param string charset          Database characterset to use (utf8, utf8mb4, etc)
   * \@param array pdoAttribs        PDO Attrbutes for connection
   * \@param boolean throwExOnNoData Throw an exception when no data found on SQL Selects instead of returning NULL ?
   * \@param boolean includeDataInMultiEX Include failed datarows in multistatement exceptions?
   * \@param boolean useCommitOnEachExecV1 Use faster DBWrapperStatement::stmtCommitOnEachExecV1()?
   * \@param boolean debug           If set, error log will include DB Activity, SQL, as well as stacktrace
   * \@param string project          Name of the project: used only to prepend errors in error_log
   *
   * </pre>
   * @see DBWrapper::_construct_incl_configs()
   * @see DBWrapper::_construct_use_dbconfig()
   *
   * @throws DBWrapperGenericException
   ******************************************************************************/
  public function __construct()
  {
    $totalArgs = func_num_args();

    if ( ( $totalArgs >= 0 ) and ( $totalArgs <= 2 ) )
    {
      call_user_func_array(array ( $this, '_construct_use_dbconfig' ), func_get_args());
    }
    elseif ( ( $totalArgs >= 6 ) and ( $totalArgs <= 13 ) )
    {
      call_user_func_array(array ( $this, '_construct_incl_configs' ), func_get_args());
    }
    else
    {
      throw new DBWrapperGenericException("Invalid call to DBWrapper Constructor!", 0, $this->_project, $this->_debug);
    }
  }

  /******************************************************************************
   * Class Constructor.
   * Build a new DBWrapper instance using a TCP/IP connection.
   *
   * @param string host             Server where the database resides.
   * @param string port             Port the database is running on.
   * @param string dbname           The actual database name to connect to.<br/>
   *                                For Oracle, just use the service name (i.e. 'ora9d1').<br/>
   *                                For MySQL, put in the database name (i.e. 'phonedir').
   * @param string user             Username to connect with.
   * @param string password         Password to use for connection.
   * @param string dbType           Database type we're connecting to (i.e. oracle, mysql)\n
   *                                Users should use the proper constants!
   *                                DBWrapper::DBTYPE_MYSQL or DBWrapper::DBTYPE_ORACLE
   * @param string charset          Database characterset to use (utf8, utf8mb4, etc)
   * @param array pdoAttribs        PDO Attrbutes for connection
   * @param boolean throwExOnNoData PDO Attrbutes for connection
   * @param boolean includeDataInMultiEX Include failed datarows in multistatement exceptions?
   * @param boolean useCommitOnEachExecV1 Use faster DBWrapperStatement::stmtCommitOnEachExecV1()?
   * @param boolean debug           If set, error log will include DB Activity, SQL, as well as stacktrace
   * @param string project          Name of the project: used only to prepend errors in error_log
   ******************************************************************************/
  private function _construct_incl_configs($host, $port, $dbname, $user, $passwd, $dbType, $charset = self::CHARSET_UTF8, $pdoAttribs = null, $throwExOnNoData = true, $includeDataInMultiEX = false, $useCommitOnEachExecV1 = true, $debug = false, $project = "UNDEFINED_PROJECT")
  {
    $this->_host = $host;
    $this->_port = $port;
    $this->_dbname = $dbname;
    $this->_user = $user;
    $this->_passwd = $passwd;
    $this->_dbType = strtolower($dbType);
    $this->_charset = $charset;
    $this->_pdoAttribs = $pdoAttribs;
    $this->_throwExOnNoData = $throwExOnNoData;
    $this->_includeDataInMultiEX = $includeDataInMultiEX;
    $this->_useCommitOnEachExecV1 = $useCommitOnEachExecV1;
    $this->_debug = $debug;
    $this->_project = $project;
  }

  /******************************************************************************
   * Class Constructor.
   * Build a new DBWrapper instance using a TCP/IP connection.
   *
   * Since we didn't pass host, db information, rely on DBWrapperConfig for this!
   *
   * @param boolean $debug If set, error log will include DB Activity, SQL, as well as stacktrace
   * @param boolean $project Name of the project: used only to prepend errors in error_log
   ******************************************************************************/
  private function _construct_use_dbconfig(bool $debug = false, string $project = "UNDEFINED_PROJECT")
  {
    $this->_host = DBWrapperConfig::getConfig('DATABASE_HOST');
    $this->_port = DBWrapperConfig::getConfig('DATABASE_PORT');
    $this->_dbname = DBWrapperConfig::getConfig('DATABASE_NAME');
    $this->_user = DBWrapperConfig::getConfig('DATABASE_USER');
    $this->_passwd = DBWrapperConfig::getConfig('DATABASE_PASS');
    $this->_dbType = strtolower(DBWrapperConfig::getConfig('DATABASE_TYPE'));
    $this->_charset = DBWrapperConfig::getConfig('DATABASE_CHARSET');
    $this->_pdoAttribs = DBWrapperConfig::getConfig('DATABASE_PDO_ATTRIBUTES');
    $this->_throwExOnNoData = DBWrapperConfig::getConfig('DATABASE_THROW_EX_ON_NODATA');
    $this->_includeDataInMultiEX = DBWrapperConfig::getConfig('DATABASE_INCLUDE_DATAROWS_IN_MULTISTMNTEX');
    $this->_useCommitOnEachExecV1 = DBWrapperConfig::getConfig('DATABASE_USE_COMMIT_EACH_EXEC_V1');
    $this->_debug = $debug;
    $this->_project = $project;
  }

  /******************************************************************************
   * Opens a connection with the database (if it's not already opened!) using PDO drivers
   * @see DBWrapper::openDBEXE()
   * @throws DBWrapperOpenDBException
   ******************************************************************************/
  public function openDB()
  {
    if ( ! isset($this->_conn) )
    {
      try
      {
        // log database activity if $debug
        if ( $this->_debug ) error_log($this->_project . " DBWrapper::openDB(): opening database...", 0);

        $this->_openDBEXE();
      }
      catch ( DBWrapperOpenDBException $ex )
      {
        throw $ex;
      }
    }
  }

  /******************************************************************************
   * Opens a connection with the database (if it's not already opened!)
   * and returns (ideally by reference!) the PDO Connection object
   *
   * If using this method, the class using DBWrapper is expected to explicitally call the closeDB()
   *
   * Usage:
   *    $conn = &$MyDB->getConnection(); // notice the additional '&' symbol here!
   *
   * @see DBWrapper::openDBEXE()
   * @see PDO
   * @return PDO connection object (by reference!)
   * @throws DBWrapperOpenDBException
   ******************************************************************************/
  public function &getConnection()
  {
    if ( ! isset($this->_conn) )
    {
      try
      {
        $this->_openDBEXE();

        // log database activity if $debug
        if ( $this->_debug ) error_log($this->_project . " Database opened, returning PDO Connection!", 0);
      }
      catch ( DBWrapperOpenDBException $ex )
      {
        throw $ex;
      }
    }

    return $this->_conn;
  }

  /******************************************************************************
   * Opens a connection with the database (if it's not already opened!) using PDO drivers
   * @throws DBWrapperOpenDBException
   ******************************************************************************/
  private function _openDBEXE()
  {
    if ( $this->_dbType == self::DBTYPE_MYSQL )
    {
      $dsn = "mysql:dbname=" . $this->_dbname . ";host=" . $this->_host . ";port=" . $this->_port . ";charset=" . $this->_charset;
    }
    elseif ( $this->_dbType == self::DBTYPE_ORACLE )
    {
      $dsn = "oracle:dbname=" . $this->_dbname . ";host=" . $this->_host . ";port=" . $this->_port . ";charset=" . $this->_charset;
    }
    else
    {
      throw new DBWrapperOpenDBException("Unknown database type! Please use either DBWrapper::DBTYPE_MYSQL or DBWrapper::DBTYPE_ORACLE!", 0, $this->_project, $this->_debug);
    }

    // Connect to DB and set attributes
    try
    {
      $this->_conn = new PDO($dsn, $this->_user, $this->_passwd, $this->_pdoAttribs);
    }
    catch ( PDOException $ex )
    {
      $errStr = "Error obtaining database connection!\n";
      $errStr .= $ex->getMessage();

      $openDBEx = new DBWrapperOpenDBException($errStr, 0, $this->_project, $this->_debug);
      $openDBEx->setDbcode($ex->errorInfo[1]);
      $openDBEx->setPdoCode($ex->getCode());
      throw $openDBEx;
    }
  }

  /******************************************************************************
   * Closes the connection with the database if it's opened!
   * @see DBWrapper::closeDBEXE()
   * @throws DBWrapperCloseDBException
   ******************************************************************************/
  public function closeDB()
  {
    if ( isset($this->_conn) )
    {
      try
      {
        // log database activity if $debug
        if ( $this->_debug ) error_log($this->_project . " DBWrapper::closeDB(): closing database...", 0);

        $this->_closeDBEXE();
      }
      catch ( DBWrapperCloseDBException $ex )
      {
        throw $ex;
      }
    }
  }

  /******************************************************************************
   * Closes the connection with the database if it's opened!
   * @throws DBWrapperCloseDBException
   ******************************************************************************/
  private function _closeDBEXE()
  {
    try
    {
      // first, commit any pending transactions!
      if ( $this->_conn->inTransaction() ) $this->_conn->commit();
    }
    catch ( PDOException $ex )
    {
      $this->_conn->rollBack();
      $errStr = "Unable to commit pending transactions before closing DB connection.\n";
      $errStr .= $ex->getMessage();

      $closeDBEx = new DBWrapperCloseDBException($errStr, 0, $this->_project, $this->_debug);
      $closeDBEx->setDbcode($ex->errorInfo[1]);
      $closeDBEx->setPdoCode($ex->getCode());
      throw $closeDBEx;
    }
    finally {
      unset($this->_conn);
    }
  }

  /******************************************************************************
   * Returns column attributes (name, types, etc) from a given table into an associative string array.
   * <pre>
   * Usage examples:
   *   $columnsMetaData = $MyDB->readColumnsMeta("tablename", null, true);
   *   foreach ( $columnsMetaData as $row ) {
   *      echo $row['Field'] .": ". $row['Type'] . "\n";
   *   }
   *
   * Notes: This method takes care of opening / closing the database connection has it needs it!
   *        You do not need to explicitaly call the openDB / closeDB methods.
   * </pre>
   *
   * @param     string $table Table to get the columns metadata from
   * @param     string $colName (optional) Column name to retrieve metadata from
   * @param     boolean (optional) $closeDB Force a close DB ?
   * @throws    DBWrapperException
   * @return    string associative array with columns metadata
   * @see       DBWrapper::readColumnsMetaEXE()
   ******************************************************************************/
  public function readColumnsMeta($table, $colName = null, $closeDB = true)
  {
    if ( ( strlen($table) > 0 ) and ( ! preg_match('/\s/', $table) ) ) // check $table is not empty nor null; strlen returns '0' if true in both cases
    {
      try
      {
        $paramData = null;

        if ( $this->_dbType == self::DBTYPE_MYSQL )
        {
          if ( isset($colName) )
          {
            $sql = "SHOW COLUMNS FROM " . $this->_dbname . "." . $table . " WHERE Field=:colName";
            $paramData = $colName;
          }
          else
          {
            $sql = "SHOW COLUMNS FROM " . $this->_dbname . "." . $table;
          }
        }
        elseif ( $this->_dbType == self::DBTYPE_ORACLE )
        {
          if ( isset($colName) )
          {
            $sql = "SELECT table_name, column_name, data_type, data_length FROM USER_TAB_COLUMNS WHERE table_name = :tableName AND column_name = :colName";
            $paramData = array ( $table, $colName );
          }
          else
          {
            $sql = "SELECT table_name, column_name, data_type, data_length FROM USER_TAB_COLUMNS WHERE table_name = :tableName";
            $paramData = $table;
          }
        }

        $columnsMetaData = $this->readData($sql, $paramData, null, null, true, $closeDB);

        if ( ! isset($columnsMetaData) ) throw new DBWrapperReadColsException("Invalid table/column name provided! ", 0, $this->_project, $this->_debug);

        return $columnsMetaData;
      }
      catch ( DBWrapperNoDataFoundException $ex )
      {
        throw new DBWrapperReadColsException("Invalid table/column name provided! ", 0, $this->_project, $this->_debug);
      }
      catch ( DBWrapperException $ex )
      {
        throw $ex;
      }
      finally{
        // clean up
        unset($columnsMetaData);
      }
    }
    else
    {
      throw new DBWrapperReadColsException("Invalid call to readColumnsMeta()! Please specify valid tablename!", 0, $this->_project, $this->_debug);
    }
  }

  /******************************************************************************
   * readData()
   *
   * Returns an array containing the data returned by SQL Selects<br/>
   *
   * Notes: This method takes care of opening / closing the database connection as it needs it!
   *        You do not need to explicitaly call the openDB / closeDB methods.
   *
   * <pre>
   * Usage examples:
   *
   *   $sql = "SELECT * FROM users WHERE type = :usertype and country = :country";
   *
   *   // parameter values / config pairs
   *   $paramValues = [
   *     ':usertype' => [ $userType, PDO::PARAM_INT ],
   *     ':country' => [ $userCountry, PDO::PARAM_STR ],
   *   ];
   *
   *   // or...
   *
   *   $paramValues = $userId; // for a single parameter in the sql, or...
   *   $paramValues = array( $userType, $userCountry ); // for multiple parameters in the sql
   *
   *   $data = $MyDB->readData($sql, $paramValues, null, null, true, false);
   *
   *   // PRINT THE DATA
   *   foreach ( $data as $row ) {
   *      echo $row['USERNAME'] .": ". $row['EMAIL'] . "\n";
   *   }
   *
   * Notes: If we've provided a simple array of parameter values only, without any paramConfigs,
   *        DBWrapperStatement will determine itself binding sql variables and datatypes on each columns.
   *        It is also assumed the values entered in the $paramValues array is in the same order as the parameters inside the SQL!
   *
   * Additional notes:
   * If you wish to use sql wildcards (i.e. select * from table where username like '%jdo%'), you need to put them in the paramValues!
   *      $sql = "select * from table where username like :userpattern";
   *      $paramValue = "%jdo%";
   *      $data = $MyDB->readData($sql, $paramValues, null, null, true, false);
   * </pre>
   *
   * @param string $sql to run on the DB
   * @param mixed $paramValues (optional) parameters config and value pairs OR array of parameter values only
   * @param array $paramConfigs (optional) associative array for parameter configurations i.e. $array = [ ':paramname' => PDO::PARAM_STR ]
   * @param mixed $fetchMode (optional) arguments to pass to \PDOStatement::setFetchMode() function (i.e. PDO::FETCH_ASSOC, array(PDO::FETCH_INTO, $myobj), etc.) to determine how the data is returned.
   *                                    If null, then default PDO Fetch Style (PDO::ATTR_DEFAULT_FETCH_MODE) is used.
   * @param boolean $fetchAllRows (optional) if true, will return all found rows with fetchAll(), else returns first row only with fetch()
   * @param boolean $closeDB (optional) force closing DB connection
   *
   * @throws    DBWrapperOpenDBException
   * @throws    DBWrapperNoDataFoundException
   * @throws    DBWrapperStatementException
   * @throws    DBWrapperReadDataException
   * @throws    DBWrapperCloseDBException
   * @throws    DBWrapperBusyException
   *
   * @return    array with data
   *
   * @see       DBWrapper::readDataEXE()
   * @see       \PDOStatement::setFetchMode()
   ******************************************************************************/
  public function readData($sql, $paramValues = null, $paramConfigs = null, $fetchMode = null, bool $fetchAllRows = true, $closeDB = true)
  {
    // if $paramValues is just data values only, confirm it has only 1 row, since we're READing from the database!
    if ( isset($paramValues) and isset($paramValues[0]) and is_array($paramValues[0]) )
    {
      throw new DBWrapperReadDataException("Invalid call to readData()! \$paramValues can only have 1 row of parameter values! Don't send arrays within an array!", 0, $this->_project, $this->_debug);
    }

    // validate fetchMode
    if ( isset($fetchMode) and ( ( ! is_int($fetchMode) ) and ( ! is_array($fetchMode) ) ) ) throw new DBWrapperReadDataException('Wrong type of fetch mode argument passed along in readData()!', 0, $this->_project, $this->_debug);
    if ( is_int($fetchMode) ) $fetchMode = array ( $fetchMode ); // just push fetchArgs to array if it's a single arg for the call_user_func_array

    $sql = trim($sql);

    if ( strlen($sql) > 0 ) // check $sql is not empty nor null; strlen returns '0' if true in both cases
    {

      if ( ! $this->_busy )
      {
        $this->_busy = true;

        try
        {
          $this->_dbtimer(true);

          // open database...
          $this->openDB();

          // log database activity if $debug
          if ( $this->_debug )
          {
            error_log($this->_project . " DBWrapper::readData() SQL = \"$sql\"", 0);
            if ( isset($paramValues) ) error_log($this->_project . " DBWrapper::readData() VALUES = " . json_encode($paramValues), 0);
          }

          // fetch data...
          $data = $this->_readDataEXE($sql, $paramValues, $paramConfigs, $fetchMode, $fetchAllRows);
          return $data;
        }
        catch ( DBWrapperOpenDBException | DBWrapperStatementException | DBWrapperReadDataException $ex )
        {
          throw $ex;
        }
        catch ( DBWrapperNoDataFoundException $ex )
        {
          if ( $this->_throwExOnNoData )
          {
            throw $ex;
          }
          else
          {
            return null;
          }
        }

        // even if an exception occurs (i.e. invalid sql, etc), try closing DB if $closeDB is set!
        // if $closeDB is false, then the class using DBWrapper is expected to explicitally call the closeDB() OR, destruct method will do it when script ends
        finally{

          // clean up
          unset($data);

          if ( $closeDB )
          {
            try
            {
              $this->closeDB();
            }
            catch ( DBWrapperCloseDBException $ex )
            {
              throw $ex;
            }
            finally {
              $this->_busy = false;
            }
          }
          else
          {
            $this->_busy = false;
          }

          $this->_dbtimer(false);
        }
      }
      else
      {
        // throw busy Exception
        throw new DBWrapperBusyException("DB Busy, Please wait for result before submiting a new request!", 0, $this->_project, $this->_debug);
      }
    }
    else
    {
      throw new DBWrapperReadDataException("Invalid call to readData()! Please specify valid SQL string!", 0, $this->_project, $this->_debug);
    }
  }

  /******************************************************************************
   * readDataEXE()
   * @param string $sql
   * @param mixed $paramValues
   * @param array $paramConfigs
   * @param array $fetchMode
   * @param boolean $fetchAllRows
   * @throws DBWrapperNoDataFoundException
   * @throws DBWrapperStatementException
   * @throws DBWrapperReadDataException
   * @return array
   * @see DBWrapper::readData()
   ******************************************************************************/
  private function _readDataEXE($sql, $paramValues = null, $paramConfigs = null, $fetchMode = null, bool $fetchAllRows = true)
  {
    try
    {
      $dataFound = false;

      // open a database connection (will open only if not already established)
      $conn = &$this->getConnection();

      // get a prepared PDO statement
      $dbWrapperStmt = new DBWrapperStatement($conn, $sql, $paramValues, $paramConfigs, $this->_includeDataInMultiEX, $this->_useCommitOnEachExecV1, $this->_debug, $this->_project);

      // get data results set
      $results = &$dbWrapperStmt->runStatement(true, false);

      // update number of affected rows
      $this->_stmtAffectedRows = $dbWrapperStmt->getAffectedRows();

      // check results and put data in array
      if ( $this->_stmtAffectedRows > 0 )
      {
        // set fetch mode
        if ( isset($fetchMode) and ( call_user_func_array(array ( $results, 'setFetchMode' ), $fetchMode) === false ) )
        {
          throw new DBWrapperReadDataException('Failed to set the desired fetch mode!', 0, $this->_project, $this->_debug);
        }

        // type of fetch to use
        if ( $fetchAllRows )
        {
          $data = $results->fetchAll();
        }
        else
        {
          $data = $results->fetch();
        }

        $dataFound = true;
      }

      $results->closeCursor(); // release DB cursor

      if ( ! $dataFound )
      {
        $errorMsg = "No data was found in the last query!" . PHP_EOL . PHP_EOL;
        $errorMsg .= "SQL = \"$sql\"" . PHP_EOL;

        if ( isset($paramValues) ) $errorMsg .= "VALUES = " . json_encode($paramValues);

        throw new DBWrapperNoDataFoundException($errorMsg, 0, $this->_project, $this->_debug);
      }

      return $data;
    }
    catch ( DBWrapperStatementException $ex )
    {
      throw $ex;
    }
    catch ( PDOException $ex )
    {
      $errStr = "Failed retrieving the statement data with fetchAll() Hey Jako! Did you really do a SELECT statement !?\n";
      $errStr .= $ex->getMessage();

      $readDataEx = new DBWrapperReadDataException($errStr, 0, $this->_project, $this->_debug);
      $readDataEx->setDbcode($ex->errorInfo[1]);
      $readDataEx->setPdoCode($ex->getCode());
      throw $readDataEx;
    }
    finally {
      if ( isset($dbWrapperStmt) )
      {
        $this->_stmtAffectedRows = $dbWrapperStmt->getAffectedRows();
        $this->_runtime_stmntonly = $dbWrapperStmt->getRuntime();
        $this->_lastInsertId = $dbWrapperStmt->getLastInsertId();
      }
      // if we don't have a $dbWrapperStmt, then we ran into an exception, no point in keeping previous statement data
      else
      {
        unset($this->_stmtAffectedRows);
        unset($this->_runtime_stmntonly);
        unset($this->_lastInsertId);
      }

      // clean up
      unset($data);
      unset($results);
      unset($dbWrapperStmt);
    }
  }

  /******************************************************************************
   * storeData()
   *
   * To insert, delete or update data into a database.<br/>
   *
   * Notes: This method takes care of opening / closing the database connection as it needs it!
   *        You do not need to explicitaly call the openDB / closeDB methods.
   *
   * <pre>
   * Usage examples:
   *
   *    OPTION 1: parameter values / configuration pairs
   *
   *    $paramConfigDataPairs = [
   *      ':id' => [ $userid, PDO::PARAM_INT ],
   *    ];
   *
   *    $MyDB->storeData("DELETE FROM tablename WHERE field1 = :id", $paramConfigDataPairs, null, false, false);
   *
   *    OPTION 2: parameter values only, multiple rows!
   *
   *    $paramValues = array (
   *        array ( "jdoe1", "jdoe@email.ca"),
   *        array ( "jane", "jijane@hotmail.com"),
   *    );
   *
   *    OR single row array of parameter values:
   *
   *    $paramValues = array ( "jdoe1", "jdoe@email.ca" );
   *
   *    OR single parameter value:
   *
   *    $paramValues = 9;
   *
   *    // (optional!) to go alongside $paramValues, provide parameter value configurations
   *    // if $paramConfigs is not provided, DBWrapperStatement will dynamically create binding variables (i.e. ':username') based on what it finds in the SQL
   *    // and determine itself what datatype each column is
   *    $paramConfigs = [
   *        ':username' =>  PDO::PARAM_STR ,
   *        ':email' =>  PDO::PARAM_STR ,
   *    ];
   *
   *    // force paramname / type to use
   *    $MyDB->storeData("INSERT INTO tablename (field1, field2) VALUES (:username,:email)", $paramValues, $paramConfigs, false, false);
   *
   *    // let DBWrapperStatement determine datatypes for each column
   *    $MyDB->storeData("INSERT INTO tablename (field1, field2) VALUES (:username, :email)", $paramValues, null, false, false);
   *
   * Notes: If we've provided an array of parameter values only, without any paramConfigs,
   *        DBWrapperStatement will determine itself binding sql variables and datatypes on each columns.
   *        It is also assumed the values entered in the $paramValues array is in the same order as the parameters inside the SQL!
   *
   * Additional note: $this->_lastInsertId = $dbWrapperStmt->getLastInsertId();
   * If you wish to use sql wildcards (i.e. delete from table where username like '%jdo%'),
   * you need to put them in the paramValues, like so:
   *      $sql = "delete from table where username like :userpattern";
   *      $paramValue = "%jdo%";
   *      $data = $MyDB->storeData($sql, $paramValues, null, false, false);
   * </pre>
   *
   * @param string $sql to run on the DB
   * @param array $paramValues parameter values & config pairs OR parameter values only
   * @param array $paramConfigs associative array for parameter configurations i.e. $array = [ ':paramname' => PDO::PARAM_STR ]
   * @param boolean $multiTransactions if true, run 1 transaction PER ROW to commit; else run a single transaction for all rows!
   * @param boolean $closeDB force closing DB connection
   *
   * @throws    DBWrapperOpenDBException
   * @throws    DBWrapperStatementException
   * @throws    DBWrapperMultiStatementException
   * @throws    DBWrapperStoreDataException
   * @throws    DBWrapperCloseDBException
   * @throws    DBWrapperBusyException
   *
   * @return int number of affected rows from the query
   *
   * @see       DBWrapper::storeDataEXE()
   ******************************************************************************/
  public function storeData($sql, $paramValues = null, $paramConfigs = null, $multiTransactions = false, $closeDB = true)
  {
    $sql = trim($sql);

    if ( strlen($sql) > 0 ) // check $sql is not empty nor null; strlen returns '0' if true in both cases
    {

      if ( ! $this->_busy )
      {
        $this->_busy = true;

        try
        {
          $this->_dbtimer(true);

          // open database
          $this->openDB();

          // log database activity if $debug          
          if ( $this->_debug )
          {
            error_log($this->_project . " DBWrapper::storeData() SQL = \"$sql\"", 0);
            if ( isset($paramValues) ) error_log($this->_project . " DBWrapper::storeData() VALUES = " . json_encode($paramValues), 0);
          }

          // store data....
          $this->_storeDataEXE($sql, $paramValues, $paramConfigs, $multiTransactions);

          // if no errors at all, return number of affected rows
          // in the case of a DBWrapperMultiStatementException,
          // one can still get the number of successful queries, even though some failed, from within the catch exception block with $mydb->getAffectedRows()
          return $this->getAffectedRows();
        }
        catch ( DBWrapperOpenDBException | DBWrapperStatementException | DBWrapperMultiStatementException | DBWrapperStoreDataException $ex )
        {
          throw $ex;
        }

        // even if an exception occurs (i.e. invalid sql, etc), try closing DB if $closeDB is set!
        // if $closeDB is false, then the class using DBWrapper is expected to explicitally call the closeDB() OR, destruct method will do it when script ends
        finally{

          if ( $closeDB )
          {
            try
            {
              $this->closeDB();
            }
            catch ( DBWrapperCloseDBException $ex )
            {
              throw $ex;
            }
            finally {
              $this->_busy = false;
            }
          }
          else
          {
            $this->_busy = false;
          }

          $this->_dbtimer(false);
        }
      }
      else
      {
        // throw busy Exception
        throw new DBWrapperBusyException("DB Busy, Please wait for result before submiting a new request!", 0, $this->_project, $this->_debug);
      }
    }
    else
    {
      throw new DBWrapperStoreDataException("Invalid call to storeData()! Please specify valid SQL string!", 0, $this->_project, $this->_debug);
    }
  }

  /******************************************************************************
   * storeDataEXE()
   * @param string $sql
   * @param array $paramValues
   * @param array $paramConfigs
   * @param boolean $multiTransactions
   * @throws DBWrapperOpenDBException
   * @throws DBWrapperStatementException
   * @throws DBWrapperMultiStatementException
   * @see DBWrapper::storeData()
   ******************************************************************************/
  private function _storeDataEXE($sql, $paramValues = null, $paramConfigs = null, $multiTransactions = false)
  {
    try
    {
      // open a database connection (will open only if not already established)
      $conn = &$this->getConnection();

      // prepare and execute PDO statement with custom DBWrapperStatement
      $dbWrapperStmt = new DBWrapperStatement($conn, $sql, $paramValues, $paramConfigs, $this->_includeDataInMultiEX, $this->_useCommitOnEachExecV1, $this->_debug, $this->_project);
      $dbWrapperStmt->runStatement(false, $multiTransactions);
    }
    catch ( DBWrapperOpenDBException | DBWrapperStatementException | DBWrapperMultiStatementException $ex )
    {
      throw $ex;
    }
    finally {
      if ( isset($dbWrapperStmt) )
      {
        $this->_stmtAffectedRows = $dbWrapperStmt->getAffectedRows();
        $this->_stmtFailedRows = $dbWrapperStmt->getFailedRows();
        $this->_runtime_stmntonly = $dbWrapperStmt->getRuntime();
        $this->_lastInsertId = $dbWrapperStmt->getLastInsertId();
      }
      // if we don't have a $dbWrapperStmt, then we ran into an exception, no point in keeping previous statement data
      else
      {
        unset($this->_stmtAffectedRows);
        unset($this->_stmtFailedRows);
        unset($this->_runtime_stmntonly);
        unset($this->_lastInsertId);
      }

      // clean up
      unset($dbWrapperStmt);
    }
  }

  /******************************************************************************
   * insertData() - Alias to storeData()
   * @param string $sql
   * @param string $paramValues
   * @param string $paramConfigs
   * @param boolean $multiTransactions
   * @param boolean $closeDB
   * @return int number of affected rows from the query
   * @throws DBWrapperOpenDBException
   * @throws DBWrapperStatementException
   * @throws DBWrapperMultiStatementException
   * @throws DBWrapperStoreDataException
   * @throws DBWrapperCloseDBException
   * @throws DBWrapperBusyException
   ******************************************************************************/
  public function insertData($sql, $paramValues = null, $paramConfigs = null, $multiTransactions = false, $closeDB = true)
  {
    try
    {
      return call_user_func_array(array ( $this, 'storeData' ), func_get_args());
    }
    catch ( DBWrapperException $ex )
    {
      throw $ex;
    }
  }

  /******************************************************************************
   * deleteData() - Alias to storeData()
   * @param string $sql
   * @param string $paramValues
   * @param string $paramConfigs
   * @param boolean $multiTransactions
   * @param boolean $closeDB
   * @return int number of affected rows from the query
   * @throws DBWrapperOpenDBException
   * @throws DBWrapperStatementException
   * @throws DBWrapperMultiStatementException
   * @throws DBWrapperStoreDataException
   * @throws DBWrapperCloseDBException
   * @throws DBWrapperBusyException
   ******************************************************************************/
  public function deleteData($sql, $paramValues = null, $paramConfigs = null, $multiTransactions = false, $closeDB = true)
  {
    try
    {
      return call_user_func_array(array ( $this, 'storeData' ), func_get_args());
    }
    catch ( DBWrapperException $ex )
    {
      throw $ex;
    }
  }

  /******************************************************************************
   * updateData() - Alias to storeData()
   * @param string $sql
   * @param string $paramValues
   * @param string $paramConfigs
   * @param boolean $multiTransactions
   * @param boolean $closeDB
   * @return int number of affected rows from the query
   * @throws DBWrapperOpenDBException
   * @throws DBWrapperStatementException
   * @throws DBWrapperMultiStatementException
   * @throws DBWrapperStoreDataException
   * @throws DBWrapperCloseDBException
   * @throws DBWrapperBusyException
   ******************************************************************************/
  public function updateData($sql, $paramValues = null, $paramConfigs = null, $multiTransactions = false, $closeDB = true)
  {
    try
    {
      return call_user_func_array(array ( $this, 'storeData' ), func_get_args());
    }
    catch ( DBWrapperException $ex )
    {
      throw $ex;
    }
  }

  /******************************************************************************
   * getRuntime()
   * @param boolean get runtime of statement only? otherwise assumes runtime of all actions (openDB, read/store, closeDB)...
   * @returns number|null execution time of last database request
   ******************************************************************************/
  public function getRuntime($stmntonly = false)
  {
    if ( $stmntonly and isset($this->_runtime_stmntonly) )
    {
      return $this->_runtime_stmntonly;
    }
    elseif ( isset($this->_runend) and isset($this->_runstart) )
    {
      return ( $this->_runend - $this->_runstart );
    }
    return null;
  }

  /******************************************************************************
   * dbtimer()
   * set the execution start or stop variables
   * @param boolean true if starting timer; false if stopping
   * @see DBWrapper::getRuntime()
   ******************************************************************************/
  private function _dbtimer($start = true)
  {
    if ( $start )
    {
      $this->_runstart = microtime(true);
    }
    else
    {
      $this->_runend = microtime(true);
    }
  }

  /******************************************************************************
   * getDbType()
   * @returns int database type being used!
   ******************************************************************************/
  public function getDbType()
  {
    return $this->_dbType;
  }

  /******************************************************************************
   * getAffectedRows()
   * @returns int number of rows found in last SELECT statement, or number of rows which were updated (inserts, updates, deletes...)
   ******************************************************************************/
  public function getAffectedRows()
  {
    return ( isset($this->_stmtAffectedRows) ) ? $this->_stmtAffectedRows : 0;
  }

  /******************************************************************************
   * getFailedRows()
   * @returns int number of rows that failed to update in an inserts, updates, deletes...
   ******************************************************************************/
  public function getFailedRows()
  {
    return ( isset($this->_stmtFailedRows) ) ? $this->_stmtFailedRows : null;
  }

  /******************************************************************************
   * getLastInsertId()
   * @returns string Returns the ID of the last inserted row or sequence value
   * @see PDO::lastInsertId
   ******************************************************************************/
  public function getLastInsertId()
  {
    return ( isset($this->_lastInsertId) ) ? $this->_lastInsertId : null;
  }

  /******************************************************************************
   * setThrowExOnNoData()
   * Throw an exception when no data found on SQL Selects instead of returning NULL ?
   * @param bool $throwExOnNoData
   * @return boolean old value
   ******************************************************************************/
  public function setThrowExOnNoData(bool $throwExOnNoData)
  {
    $ov = $this->_throwExOnNoData ?? null;
    $this->_throwExOnNoData = $throwExOnNoData;
    return $ov;
  }

  /******************************************************************************
   * Returns metadata for a column in a result set
   * <pre>
   * THIS FUNCTION IS NOT YET IMPLEMENTED!!!
   *
   * As of 2020/03/12, only the following database PDO drivers support this method:
   *
   * PDO_DBLIB
   * PDO_MYSQL
   * PDO_PGSQL
   * PDO_SQLITE
   *
   * </pre>
   * @param int $column The 0-indexed column in the result set.
   * @return array
   * @todo not sure I will implement this, cause it means I have to keep the DBWrapperStatement and PDOStatement from read/storeData in memory!
   ******************************************************************************/
  public function getColumnMeta(int $column)
  {
    $stmnt = null;
    $retval = null;

    if ( isset($stmnt) )
    {
      try
      {
        $retval = $stmnt->getColumnMeta($column);
      }
      catch ( PDOException $ex )
      {
        throw new DBWrapperReadColsException($ex->getMessage(), 0, $this->_project, $this->_debug);
      }
    }

    return $retval;
  }

  /******************************************************************************
   * getMySQLWarnings()
   * retrieve MySQL warnings if any!
   * 
   * example output of warning:
   * <pre>
   * stdClass Object
   * {
   *   [Level] => Warning
   *   [Code] => 1264
   *   [Message] => Out of range value for column 'qty' at row 1
   * }
   * </pre>
   * 
   * @param boolean $lastOnly return only the last warning?
   * @return array|object array or single object holding warning
   ******************************************************************************/
  public function getMySQLWarnings(bool $lastOnly = false)
  {
    if ( isset($this->_conn) and ( $this->_dbType == self::DBTYPE_MYSQL ) )
    {
      if ( $lastOnly )
      {
        return $this->_conn->query("SHOW WARNINGS")->fetchObject();
      }
      else
      {
        return $this->_conn->query("SHOW WARNINGS")->fetchAll(PDO::FETCH_OBJ);
      }
    }
  }

  /******************************************************************************
   * Class Destructor.
   *
   * Before dying, tries closing database connection if it's still opened!
   *
   * @see DBWrapper::closeDB()
   * @throws DBWrapperCloseDBException
   ******************************************************************************/
  public function __destruct()
  {
    try
    {
      $this->closeDB();
    }
    catch ( DBWrapperCloseDBException $ex )
    {
      // no point in re-throwing because it's not possible to catch it when destruct() is called from PHP's internal termination handling,
      // which most of the time is, unless you unset($thisclass)
      //throw $ex;
    }
  }
}

?>
