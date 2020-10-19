<?php

namespace CorbeauPerdu\Database;

use PDO;
use PDOException;
use PDOStatement;
use CorbeauPerdu\Database\Exceptions\DBWrapperStatementException;
use CorbeauPerdu\Database\Exceptions\DBWrapperMultiStatementException;

// Include config files
require_once ( 'DBWrapperExceptions.php' );

/******************************************************************************
 * DB Wrapper Statement class, as part of the DBWrapper class project
 * Copyright (C) 2020, Patrick Roy
 * This file may be used under the terms of the GNU Lesser General Public License, version 3.
 * For more details see: https://www.gnu.org/licenses/lgpl-3.0.html
 * This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 *
 * <pre>
 * Name: DBWrapperStatement
 *
 * Extends DBConfig class
 *
 * Required: DBWrapperStatementException, DBWrapperMultiStatementException
 *
 * Last Modified : 2020/03/09 - First release
 * </pre>
 *
 * @author      Patrick Roy
 * @version     1.0
 ******************************************************************************/
class DBWrapperStatement
{
  private const DATA_TYPE_BOOL = 'boolean';
  private const DATA_TYPE_INT = 'integer';
  private const DATA_TYPE_DOUBLE = 'double';
  private const DATA_TYPE_STRING = 'string';
  private const DATA_TYPE_ARRAY = 'array';
  private const DATA_TYPE_OBJECT = 'object';
  private const DATA_TYPE_NULL = "NULL";
  private const SQLPARAM_PATTERN_MATCH = '/(?<param>:\w+)/';
  private const SQLPARAM_DYNVAR_PREFIX = 'bind_';
  private $_project = null;
  private $_conn = null;
  private $_sql = null;
  private $_paramValues = null;
  private $_paramValuesInclConfigs = false;
  private $_paramValuesSingleVal = false;
  private $_paramConfigs = null;
  private $_debug = false;
  private $_includeDataInMultiEX = false;
  private $_useCommitOnEachExecV1 = true;
  private $_isMultiStatement = false;
  private $_sqlParameters = null;
  private $_sqlParameters_count = null;
  private $_runend = null;
  private $_runstart = null;
  private $_statement = null;
  private $_lastInsertId = null;
  private $_stmtAffectedRows = 0;
  private $_stmtFailedRows = null;

  /******************************************************************************
   * Class Constructor.
   *
   * <pre>
   * Usage:
   *
   *  OPTION 1: preparing a statement for a single data execute
   *            i.e. single select, or single sql insert, update or delete...
   *
   *  $sql = "select * from users where country = :usercountry and age = :userage";
   *
   *  // parameter value/config pairs
   *  $paramValues = [
   *      ':usercountry' => [ $userCountry, PDO::PARAM_STR],
   *      ':userage' => [ $userAge, PDO::PARAM_INT]
   *  ];
   *
   *  // or...
   *
   *  $paramValues = $userId; // for a single parameter in the sql, or...
   *  $paramValues = array( $userCountry, $userAge ); // for multiple parameters in the sql
   *
   *
   *  $dbWrapperStmt = new DBWrapperStatement($conn, $sql, $paramValues, null, false, true, false, "MYPROJECT");
   *  $stmt = $dbWrapperStmt->runStatement(true,false);
   *
   *  OPTION 2: preparing a statement for multiple data execute
   *            i.e. multiple sql inserts, deletes, etc...
   *
   *  $sql = "delete * from users where country = :usercountry and age = :userage";
   *
   *  $paramValues = array(
   *      array( "canada", 25 ),
   *      array( "belgique", 25 ),
   *  );
   *
   *  // (optional!) to go alongside $paramValues, provide a parameter value configurations array
   *  // if $paramConfigs is not provided, DBWrapperStatement will dynamically create binding variables (i.e. ':username') based on what it finds in the SQL
   *  // and determine itself what datatype each column is
   *  $paramConfigs = [
   *      ':username' =>  PDO::PARAM_STR ,
   *      ':email' =>  PDO::PARAM_STR ,
   *  ];
   *
   *  $dbWrapperStmt = new DBWrapperStatement($conn, $sql, $paramValues, $paramConfigs, false, false, "MYPROJECT");
   *  $dbWrapperStmt->runStatement(false, false);
   *
   *  Notes for both options: if providing an array of parameter values only, without the paramConfigs,
   *  the parameter values in the arrays must be entered in the same order as the parameters defined in the SQL!
   * </pre>
   *
   * @param PDO $pdoconn PDO connection reference
   * @param string $sql to prepare
   * @param mixed $paramValues (optional) parameter values (see usage above!)
   * @param array $paramConfigs (optional) if $paramValues is just parameters values, an array holding parameter configs i.e. $array = [ ':paramname' => PDO::PARAM_STR ]
   * @param boolean $includeDataInMultiEX (optional) include failed datarows in multistatement exceptions?
   * @param boolean $useCommitOnEachExecV1 (optional) include failed datarows in multistatement exceptions?
   * @param boolean $debug (optional) log to system log or not
   * @param string $project (optional) simple project name to output if $debug is on
   * @throws DBWrapperStatementException
   ******************************************************************************/
  public function __construct(&$conn, $sql, $paramValues = null, $paramConfigs = null, $includeDataInMultiEX = false, $useCommitOnEachExecV1 = true, $debug = false, $project = "UNDEFINED_PROJECT")
  {
    $this->_conn = &$conn;
    $this->_sql = trim($sql);
    $this->_paramValues = $paramValues;
    $this->_paramConfigs = $paramConfigs;
    $this->_includeDataInMultiEX = $includeDataInMultiEX;
    $this->_useCommitOnEachExecV1 = $useCommitOnEachExecV1;
    $this->_debug = $debug;
    $this->_project = $project;

    try
    {

      // set the SQL parameters array and count
      $this->_getSQLParameters();

      // throw exception if SQL has parameters, but no values were passed to replace them with
      if ( ( $this->_sqlParameters_count > 0 ) and ( ! isset($paramValues) ) )
      {
        throw new DBWrapperStatementException("Your SQL defines '$this->_sqlParameters_count' parameters, but no parameter values were passed along to replace them with!", 0, $this->_project, $this->_debug);
      }

      // CHECK WHAT WE HAVE IN PARAMETER DATA AND VALIDATE PARAMETER DATA ACCORDINGLY

      // ---------------------
      // passed an array of parameter values
      // ---------------------
      if ( is_array($paramValues) )
      {
        // associative array i.e. $array = [ ':param' => [value, type] ])
        if ( $this->_isAssocArray($paramValues) )
        {
          $this->_validateParamValuesInclConfigs();
        }
        // array of parameter values only
        else
        {
          $this->_validateParamValuesOnly();
        }
      }
      // ---------------------
      // just passing a single parameter value (not null!)
      // ---------------------
      elseif ( isset($paramValues) )
      {
        // code expects parameter values to be in an array, push single value to new array
        $this->_paramValuesSingleVal = true;
        $this->_paramValues = array ( $paramValues );
        $this->_validateParamValuesOnly();
      }
      // ---------------------
      // passed NULL
      // ---------------------
      else
      {
        // Do nothing... it's accepted ! (i.e. "select * from table")
      }
    }
    catch ( DBWrapperStatementException $ex )
    {
      throw $ex;
    }
  }

  /******************************************************************************
   * getSQLParameters()
   * Set the $sqlParameters array with all of the parameters defined in the SQL
   ******************************************************************************/
  private function _getSQLParameters()
  {
    $this->_sqlParameters_count = preg_match_all(self::SQLPARAM_PATTERN_MATCH, $this->_sql, $this->_sqlParameters);
  }

  /******************************************************************************
   * validateParamValuesInclConfigs()
   * Checks validity of parameter value/config pairs array
   * @throws DBWrapperStatementException
   ******************************************************************************/
  private function _validateParamValuesInclConfigs()
  {
    $this->_paramValuesInclConfigs = true;

    if ( $this->_debug ) error_log($this->_project . " DBWrapperStatement::validateParamValuesInclConfigs(): Sent an associative array of paramname => [value, type]", 0);

    $paramKeys_count = count(array_keys($this->_paramValues)); // get total parameters passed in data array

    // 1st: check that we've properly defined the same amount of parameters as they are defined in the SQL
    if ( $this->_sqlParameters_count != $paramKeys_count )
    {
      throw new DBWrapperStatementException("The number of parameters defined in your SQL statement (" . $this->_sqlParameters_count . ") doesn't match the amount defined in your parameter config/value array ($paramKeys_count) !", 0, $this->_project, $this->_debug);
    }

    // 2nd: check that the sql parameters defined also exists in the parameters defined in the data array
    foreach ( $this->_sqlParameters['param'] as $sqlParamName )
    {
      if ( ! array_key_exists($sqlParamName, $this->_paramValues) )
      {
        throw new DBWrapperStatementException("The SQL parameter named '$sqlParamName' doesn't exist in your parameter config/value array! Also make sure to trim leading and trailing spaces in your parameter names...", 0, $this->_project, $this->_debug);
      }
    }
  }

  /******************************************************************************
   * validateParamValuesOnly()
   * Checks validity of parameter values passed to the constructor,
   * and (if provided) the paramConfigs as well
   * @throws DBWrapperStatementException
   ******************************************************************************/
  private function _validateParamValuesOnly()
  {
    $this->_paramValuesInclConfigs = false;

    // get the number of parameter values defined (will be either number of element values, or number of rows of values)
    $paramValues_count = count($this->_paramValues);

    // ------------------------------
    // multidimensional array of values: loop every row of data and make sure they have the same amount of columns has defined by the number of SQL parameters
    // ------------------------------
    if ( is_array($this->_paramValues[0]) )
    {

      $this->_isMultiStatement = true;

      for ( $row = 0; $row < $paramValues_count; $row ++ )
      {
        $dataCols_count = count($this->_paramValues[$row]);
        if ( $dataCols_count != $this->_sqlParameters_count )
        {
          throw new DBWrapperStatementException("Your parameter data array row " . ( $row + 1 ) . " provides $dataCols_count value(s), but you have " . $this->_sqlParameters_count . " defined in your SQL!", 0, $this->_project, $this->_debug);
        }
      }
    }
    // ------------------------------
    // simple array of value: check number of columns/elements defined
    // ------------------------------
    else
    {
      if ( $paramValues_count != $this->_sqlParameters_count )
      {
        if ( $this->_paramValuesSingleVal )
        {
          throw new DBWrapperStatementException("You've provided a single parameter value, but the number of parameters defined in the SQL is " . $this->_sqlParameters_count . " !", 0, $this->_project, $this->_debug);
        }
        else
        {
          throw new DBWrapperStatementException("Your parameter data array provides $paramValues_count value(s), but you have " . $this->_sqlParameters_count . " defined in your SQL!", 0, $this->_project, $this->_debug);
        }
      }
    }

    // ------------------------------
    // we've provided a $paramConfigs, validate it!
    // ------------------------------
    if ( isset($this->_paramConfigs) )
    {

      // proper type of array
      if ( ! ( $this->_isAssocArray($this->_paramConfigs) ) )
      {
        throw new DBWrapperStatementException("The configuration array passed to the constructor isn't valid! It should be an associative array i.e. \$array = [':paramname' =>  PDO::PARAM_STR]", 0, $this->_project, $this->_debug);
      }

      // get total parameters passed in config array
      $paramConfigKeys_count = count(array_keys($this->_paramConfigs));

      // check that we've defined the same amount of config parameters as what's defined in the SQL
      if ( $this->_sqlParameters_count != $paramConfigKeys_count )
      {
        throw new DBWrapperStatementException("The number of parameters defined in your SQL statement (" . $this->_sqlParameters_count . ") doesn't match the amount defined in your parameter configuration array ($paramConfigKeys_count) !", 0, $this->_project, $this->_debug);
      }

      // check that the sql parameters defined also exists in the parameters defined in the config array
      foreach ( $this->_sqlParameters['param'] as $sqlParamName )
      {
        if ( ! array_key_exists($sqlParamName, $this->_paramConfigs) )
        {
          throw new DBWrapperStatementException("The SQL parameter named '$sqlParamName' doesn't exist in your parameter configuration array! Also make sure to trim leading and trailing spaces in your parameter names...", 0, $this->_project, $this->_debug);
        }
      }
    }

    // ------------------------------
    // no parameter config provided, generate one with :paramname => datatype
    // ------------------------------
    else
    {
      $this->_generateParamConfigs();
    }
  }

  /******************************************************************************
   * generateParamConfigs()
   * Check datatype for every columns of parameter values
   * and generate a new paramConfigs array with $paramConfigs = [ ':paramname' => datatype ]
   ******************************************************************************/
  private function _generateParamConfigs()
  {
    $paramNameTypePairs = null;
    for ( $col = 0; $col < $this->_sqlParameters_count; $col ++ )
    {
      $paramName = $this->_sqlParameters['param'][$col];

      // if we have multi row / data array, check data type of 1st row only...
      // assuming all rows are same datatypes!
      switch ( gettype(( $this->_isMultiStatement ) ? $this->_paramValues[0][$col] : $this->_paramValues[$col]) )
      {
        case self::DATA_TYPE_BOOL:
          $paramNameTypePairs[$paramName] = PDO::PARAM_BOOL;
          break;
        case self::DATA_TYPE_INT:
          $paramNameTypePairs[$paramName] = PDO::PARAM_INT;
          break;
        case self::DATA_TYPE_DOUBLE:
          $paramNameTypePairs[$paramName] = PDO::PARAM_STR;
          break;
        case self::DATA_TYPE_STRING:
          $paramNameTypePairs[$paramName] = PDO::PARAM_STR;
          break;
        case self::DATA_TYPE_ARRAY:
          $paramNameTypePairs[$paramName] = PDO::PARAM_STR;
          break;
        case self::DATA_TYPE_OBJECT:
          $paramNameTypePairs[$paramName] = PDO::PARAM_STR;
          break;
        case self::DATA_TYPE_NULL:
          $paramNameTypePairs[$paramName] = PDO::PARAM_STR;
          break;
        default:
          $paramNameTypePairs[$paramName] = PDO::PARAM_STR;
      }
    }

    $this->_paramConfigs = $paramNameTypePairs;
  }

  /******************************************************************************
   * runStatement()
   * Get (ideally by reference!) a prepared & executed PDO Statement object.
   *
   * <pre>
   * Usage:
   *    $stmt = &$DBWrapperStatement->runStatement(true, false); // notice the additional '&' symbol here!
   * </pre>
   *
   * @param boolean $returnStatement return the executed PDOStatement ?
   * @param boolean $multiTransactions (optional!) if true, run 1 transaction PER ROW to commit; else run a single transaction for all rows!
   * @return PDOStatement prepared and executed PDO Statement object
   * @throws DBWrapperStatementException
   * @see PDOStatement
   ******************************************************************************/
  public function &runStatement($returnStatement = true, $multiTransactions = false)
  {
    $this->_stmtAffectedRows = 0;
    $this->_lastInsertId = null;

    // ------------------------
    // multi statement run
    // ------------------------
    if ( $this->_isMultiStatement )
    {
      try
      {
        $this->_runMultiStatement($multiTransactions);
        return $this->_statement;
      }
      catch ( DBWrapperStatementException | DBWrapperMultiStatementException $ex )
      {
        throw $ex;
      }
      finally{
        if ( ! $returnStatement ) unset($this->_statement);
      }
    }

    // ------------------------
    // single statement run
    // ------------------------
    else
    {
      try
      {
        $this->_dbtimer(true);

        // log database activity if $debug
        if ( $this->_debug ) error_log($this->_project . " DBWrapperStatement::runStatement() preparing single execute PDO statement...", 0);

        // create PDO Statement object
        $this->_statement = $this->_conn->prepare($this->_sql);

        // begin transaction
        $this->_conn->beginTransaction();

        if ( isset($this->_paramValues) and count($this->_paramValues) > 0 )
        {
          // CHECK TYPE OF PARAMETER VALUES

          // provided paramValues with ':name' => [value, type]
          if ( $this->_paramValuesInclConfigs === true )
          {
            foreach ( $this->_paramValues as $name => $config )
            {
              $value = $config[0];
              $type = $config[1];

              // debug
              //echo 'name : ' . $name . PHP_EOL;
              //echo 'value: ' . $value . PHP_EOL;
              //echo 'type : ' . $type . PHP_EOL . PHP_EOL;

              $this->_statement->bindValue($name, $value, $type);
            }
          }

          // provided paramValues with parameter values only
          else
          {
            $i = 0;
            foreach ( $this->_paramConfigs as $name => $type )
            {
              $value = $this->_paramValues[$i];

              // debug
              //echo 'name : ' . $name . PHP_EOL;
              //echo 'value: ' . $value . PHP_EOL;
              //echo 'type : ' . $type . PHP_EOL . PHP_EOL;

              $this->_statement->bindValue($name, $value, $type);
              $i ++;
            }
          }
        }

        $this->_statement->execute();

        # get last inserted row if, if any
        $this->_lastInsertId = $this->_conn->lastInsertId();

        // set the number of affected rows
        $this->_stmtAffectedRows = $this->_statement->rowCount();

        // commit transaction
        $this->_conn->commit();

        return $this->_statement;
      }
      catch ( PDOException $ex )
      {
        if ( $this->_conn->inTransaction() ) $this->_conn->rollBack(); // yes, rollback even on sql selects, since it won't harm anything

        unset($this->_stmtAffectedRows);
        unset($this->_lastInsertId);

        $errstr = "Failed to prepare and execute PDO Statement! Transaction was rolled back...\n";
        $errstr .= $ex->getMessage();

        $stmtEx = new DBWrapperStatementException($errstr, 0, $this->_project, $this->_debug);
        $stmtEx->setDbcode($ex->errorInfo[1]);
        $stmtEx->setPdoCode($ex->getCode());
        throw $stmtEx;
      }
      finally {

        $this->_dbtimer(false);

        // if we're returning a REFERENCE to the statement,
        // clean up of $stmt should be done whereever the statement is used, when finished using it! (i.e. DBWrapper::readDataEXE())
        // UNLESS we're NOT returning the statement, then kill it here
        if ( ! $returnStatement ) unset($this->_statement);
      }
    }
  }

  /******************************************************************************
   * runMultiStatement()
   * Get (ideally by reference!) a prepared & executed PDO Statement object.
   * This is intended for a mutliple executes (i.e. multiple sql inserts, deletes, etc)
   *
   * Usage:
   *    $DBWrapperStatement->runMultiStatement(false);
   *
   * @todo: Using a bindParam() prepared statement in stmtCommitOnEachExecV1(),
   *        along with the PDO Attribute "PDO::ATTR_EMULATE_PREPARES => FALSE",
   *        causes server segfault / httpd 500 error on NAS when it tries to Rollback() on error!
   *        Temporarily replaced with a PDO::quote() and PDO::exec() in stmtCommitOnEachExecV2()
   *
   *
   * @param boolean $multiTransactions if true, run 1 transaction PER ROW to commit; else run a single transaction for all rows!
   * @throws DBWrapperStatementException
   * @throws DBWrapperMultiStatementException
   * @see PDOStatement
   ******************************************************************************/
  private function _runMultiStatement($multiTransactions = false)
  {
    try
    {
      if ( $multiTransactions )
      {
        // FYI: speed test for 3 rows inserts
        // Runtime for stmtCommitOnEachExecV1() = 0.04123592376709
        // Runtime for stmtCommitOnEachExecV2() = 0.085745096206665

        if ( $this->_useCommitOnEachExecV1 )
        {
          $this->_stmtCommitOnEachExecV1();
        }
        else
        {
          $this->_stmtCommitOnEachExecV2(); // this is a HOTFIX to V1
        }

        // for debug _stmtCommitOnEachExec
        //$this->_stmtCommitOnEachExecTEST();
      }
      else
      {
        $this->_stmtCommitAtEndOnly();
      }
    }
    catch ( DBWrapperStatementException | DBWrapperMultiStatementException $ex )
    {
      throw $ex;
    }
  }

  /******************************************************************************
   * TEST multiInsert / Commit on each execute!
   ******************************************************************************/
  private function _stmtCommitOnEachExecTEST()
  {
    echo "TESTING...";

    // init data
    //@formatter:off
    $data = array (
        array ( "jdoe1", "jdoe@email.ca", "abc123"),
        array ( "jdoe2", "jdoe@email.ca", "abc123"),
        array ( "jdoe3", "jdoe@email.ca", "abc123"),
        array ( "jdoe4", "jdoe@email.ca", "abc123"),
        array ( "jdoe5", "jdoe@email.ca", "abc123"),
    );
    //@formatter:on

    // init variables
    $username = null;
    $email = null;
    $passwd = null;

    $sql = "INSERT INTO tbl_users (USERNAME, EMAIL, PASSWD) VALUES (:username, :email, :passwd)";

    try
    {
      $con = $this->_conn;

      $stmt = $con->prepare($sql);
      $stmt->bindParam(':username', $username, PDO::PARAM_STR);
      $stmt->bindParam(':email', $email, PDO::PARAM_STR);
      $stmt->bindParam(':passwd', $passwd, PDO::PARAM_STR);
    }
    catch ( PDOException $ex )
    {
      die($ex->getMessage());
    }

    $errorList = array ();
    $totalRows = count($data);
    for ( $row = 0; $row < $totalRows; $row ++ )
    {
      $username = $data[$row][0];
      $email = $data[$row][1];
      $passwd = $data[$row][2];

      try
      {
        $con->beginTransaction();
        $stmt->execute();
        $con->commit();
      }
      catch ( PDOException $ex )
      {
        // IF USING $this->_conn AS CONNECTION, SEGFAULT IS PRODUCED ON ROLLBACK!
        if ( $con->inTransaction() ) $con->rollBack();

        // just push the error to an array of failed transactions
        array_push($errorList, array ( $row, $ex->errorInfo[1], $ex->getMessage() ));
      }
    }

    if ( $errorList )
    {
      // print the errors
      foreach ( $errorList as $error )
      {
        echo "Row #:" . $error[0] . "<br/>";
        echo "Code :" . $error[1] . "<br/>";
        echo "Mesg :" . $error[2] . "<br/><br/>";
      }
    }
  }

  /******************************************************************************
   * _stmtCommitOnEachExecV1()
   * Loops the data and assigns values to every SQL parameters, starts a transaction inside the loop,
   * executes the statement, then commits the executed statement inside the loop!
   * If a PDO error occurs, rollback is done only for the failed row, error is populated into an errorList array,
   * and the loop goes on, trying to execute and commit the remaining rows
   *
   * @todo: using bindParam, along with "PDO::ATTR_EMULATE_PREPARES => FALSE" causes rollback() to server segfault on NAS!
   *        temporarily replaced with _stmtCommitOnEachExecV2()
   *
   * @see DBWrapperStatement::_stmtCommitOnEachExecV2()
   *
   * @throws DBWrapperStatementException
   * @throws DBWrapperMultiStatementException
   ******************************************************************************/
  private function _stmtCommitOnEachExecV1()
  {
    // log database activity if $debug
    if ( $this->_debug ) error_log($this->_project . " DBWrapperStatement-::runMultiStatement()::preparing multi-exec/commit-every-row PDO statement...", 0);

    /*********************
     * FIRST, PREPARE STATEMENT AND BIND PARAMETERS
     *********************/
    try
    {
      $this->_dbtimer(true);

      // create PDO Statement object
      $this->_statement = $this->_conn->prepare($this->_sql);

      // initialize empty variable for each of the parameter name to hold the data in
      // and bind them to the PDOStatement
      foreach ( $this->_paramConfigs as $paramName => $paramType )
      {
        $paramvar = self::SQLPARAM_DYNVAR_PREFIX . substr($paramName, 1); // remove PARAM_CHAR (:) from the paramname
        $$paramvar = null; // initialize the paramname dynamic variable

        // debug
        //echo "\$this->_statement->bindParam('$paramName', $$paramvar, $paramType)<br/>";

        $this->_statement->bindParam($paramName, $$paramvar, $paramType);
      }
    }
    catch ( PDOException $ex )
    {
      $errstr = "Failed to prepare statement and bind parameters!\n";
      $errstr .= $ex->getMessage();

      $stmtEx = new DBWrapperStatementException($errstr, 0, $this->_project, $this->_debug);
      $stmtEx->setDbcode($ex->errorInfo[1]);
      $stmtEx->setPdoCode($ex->getCode());
      throw $stmtEx;
    }

    /*********************
     * LOOP DATA ROWS, ASSIGN VALUES TO SQL PARAMETERS
     * START TRANSACTION, EXECUTE AND COMMIT WITHIN THE LOOP!
     * IF ONE FAILS, WILL APPEND ERROR TO ERRORLIST AND STILL TRY THE REMAINING ROWS
     *********************/
    $totalRows = count($this->_paramValues);
    $errorList = array ();
    $lastDbCode = null;
    $lastPdoCode = null;

    // loop the data, assign value to each dynamic variable (which are in turn, columns)
    for ( $row = 0; $row < $totalRows; $row ++ )
    {
      // assign columns values to proper variables, and only after execute!
      $i = 0;
      foreach ( $this->_paramConfigs as $paramName => $paramType )
      {
        $paramvar = self::SQLPARAM_DYNVAR_PREFIX . substr($paramName, 1); // remove PARAM_CHAR (:) from the paramname
        $$paramvar = $this->_paramValues[$row][$i];

        // debug
        //echo "\$$paramvar = " . $this->_paramValues[$row][$i] . " type = $paramType <br/>";

        $i ++;
      }

      try
      {
        $lastKnownInsertId = $this->_lastInsertId;

        // commit change on every execute
        $this->_conn->beginTransaction();
        $this->_statement->execute();
        $this->_lastInsertId = $this->_conn->lastInsertId();
        $this->_conn->commit();

        // set the number of affected rows
        $affectedRows = $this->_statement->rowCount();
        $this->_stmtAffectedRows = $this->_stmtAffectedRows + $affectedRows;
      }
      catch ( PDOException $ex )
      {
        // CAUTION!!: rollback here causes server segfault, httpd 500 error!
        if ( $this->_conn->inTransaction() ) $this->_conn->rollBack();

        $this->_lastInsertId = $lastKnownInsertId;
        $lastDbCode = $ex->errorInfo[1];
        $lastPdoCode = $ex->getCode();

        // push the error to errorList; check if we also include the failed rows of data in the errorList array or not
        if ( $this->_includeDataInMultiEX )
        {
          array_push($errorList, array ( $row, $lastDbCode, $ex->getMessage(), $this->_paramValues[$row] ));
        }
        else
        {
          array_push($errorList, array ( $row, $lastDbCode, $ex->getMessage() ));
        }
      }
    }

    // if we got errors
    if ( $errorList )
    {
      $this->_dbtimer(false);
      $totalErrors = count($errorList);

      $this->_stmtFailedRows = $totalErrors;

      // log database activity if $debug
      if ( $this->_debug ) error_log($this->_project . " DBWrapperStatement-::runMultiStatement()::COMMIT_EVERY_ROW Transactions failed for $totalErrors out of $totalRows data rows!", 0);

      // generate new exception
      if ( $totalRows == $totalErrors )
      {
        $errstr = "Failed to execute PDO Statement for ALL data rows! Transactions were rolled back...\n";
      }
      else
      {
        $errstr = "Failed to execute PDO Statement for $totalErrors out of $totalRows data rows! Failed transactions were rolled back...\n";
      }

      $multiStmtEx = new DBWrapperMultiStatementException($errstr, 0, $this->_project, $this->_debug);
      $multiStmtEx->setErrorList($errorList);
      $multiStmtEx->setDbCode($lastDbCode); // just setting the last known database driver error code
      $multiStmtEx->setPdoCode($lastPdoCode);
      throw $multiStmtEx;
    }

    // no errors
    else
    {
      $this->_dbtimer(false);
    }
  }

  /******************************************************************************
   * _stmtCommitOnEachExecV2()
   * This is a temporary HOTFIX function to replace _stmtCommitOnEachExecV1().
   * If using the lather function on NAS, the rollback on errors causes server segfault!!
   * @throws DBWrapperMultiStatementException
   ******************************************************************************/
  private function _stmtCommitOnEachExecV2()
  {
    if ( $this->_debug ) error_log($this->_project . " DBWrapperStatement-::runMultiStatement()::preparing multi-exec/commit-every-row PDO statement...HOTFIX!", 0);

    $totalRows = count($this->_paramValues);
    $errorList = array ();

    $this->_dbtimer(true);
    $lastDbCode = null;
    $lastPdoCode = null;

    for ( $row = 0; $row < $totalRows; $row ++ )
    {
      $sql_toexec = $this->_sql;

      $paramID = 0;
      foreach ( $this->_paramConfigs as $paramName => $paramType )
      {
        $paramValue = $this->_conn->quote($this->_paramValues[$row][$paramID], $paramType);
        $sql_toexec = str_replace($paramName, $paramValue, $sql_toexec);
        $paramID ++;
      }

      try
      {
        $lastKnownInsertId = $this->_lastInsertId;

        $this->_conn->beginTransaction();
        $affectedRows = $this->_conn->exec($sql_toexec);
        $this->_lastInsertId = $this->_conn->lastInsertId();
        $this->_conn->commit();

        // set the number of affected rows
        $this->_stmtAffectedRows = $this->_stmtAffectedRows + $affectedRows;
      }
      catch ( PDOException $ex )
      {
        if ( $this->_conn->inTransaction() ) $this->_conn->rollBack();

        $this->_lastInsertId = $lastKnownInsertId;
        $lastDbCode = $ex->errorInfo[1];
        $lastPdoCode = $ex->getCode();

        // push the error to an array of failed transactions; check if we also include the failed rows of data in the errorList array or not
        if ( $this->_includeDataInMultiEX )
        {
          array_push($errorList, array ( $row, $lastDbCode, $ex->getMessage(), $this->_paramValues[$row] ));
        }
        else
        {
          array_push($errorList, array ( $row, $lastDbCode, $ex->getMessage() ));
        }
      }
    }

    // if we got errors
    if ( $errorList )
    {
      $this->_dbtimer(false);
      $totalErrors = count($errorList);

      $this->_stmtFailedRows = $totalErrors;

      // generate new exception
      if ( $totalRows == $totalErrors )
      {
        $errstr = "Failed to execute SQL Statement for ALL data rows! Transactions were rolled back...\n";
      }
      else
      {
        $errstr = "Failed to execute SQL Statement for $totalErrors out of $totalRows data rows! Failed transactions were rolled back...\n";
      }

      $multiStmtEx = new DBWrapperMultiStatementException($errstr, 0, $this->_project, $this->_debug);
      $multiStmtEx->setErrorList($errorList);
      $multiStmtEx->setDbCode($lastDbCode); // just setting the last known database driver error code
      $multiStmtEx->setPdoCode($lastPdoCode);
      throw $multiStmtEx;
    }

    // no errors
    else
    {
      $this->_dbtimer(false);
    }
  }

  /******************************************************************************
   * _stmtCommitAtEndOnly()
   * Starts a transaction, loops the data and assigns values to every SQL parameters,
   * executes the statement, and only at the end of the loop, commits all the executed statements!
   * If a PDO error occurs, rollback ALL transactions (nothing it commited!) and throw exception.
   * @throws DBWrapperStatementException
   ******************************************************************************/
  private function _stmtCommitAtEndOnly()
  {
    // log database activity if $debug
    if ( $this->_debug ) error_log($this->_project . " DBWrapperStatement-::runMultiStatement()::preparing multi-exec/commit-at-end-only PDO statement...", 0);

    /*********************
     * FIRST, PREPARE STATEMENT AND BIND PARAMETERS
     *********************/
    try
    {
      $this->_dbtimer(true);

      // create PDO Statement object
      $this->_statement = $this->_conn->prepare($this->_sql);

      // initialize empty variable for each of the parameter name to hold the data in
      // and bind them to the PDOStatement
      foreach ( $this->_paramConfigs as $paramName => $paramType )
      {
        $paramvar = self::SQLPARAM_DYNVAR_PREFIX . substr($paramName, 1); // remove PARAM_CHAR (:) from the paramname
        $$paramvar = null; // initialize the paramname dynamic variable

        // debug
        //echo "\$this->_statement->bindParam('$paramName', $$paramvar, $paramType)<br/>";

        $this->_statement->bindParam($paramName, $$paramvar, $paramType);
      }
    }
    catch ( PDOException $ex )
    {
      $errstr = "Failed to prepare statement and bind parameters!\n";
      $errstr .= $ex->getMessage();

      $stmtEx = new DBWrapperStatementException($errstr, 0, $this->_project, $this->_debug);
      $stmtEx->setDbcode($ex->errorInfo[1]);
      $stmtEx->setPdoCode($ex->getCode());
      throw $stmtEx;
    }

    /*********************
     * LOOP DATA ROWS, ASSIGN VALUES TO SQL PARAMETERS AND EXECUTE
     * COMMIT ONLY ONCE EVERY ROW WAS PROCESSED
     *********************/
    try
    {
      // we'll be commiting only at the end of every statement executes
      $this->_conn->beginTransaction();

      // loop the data, assign value to each dynamic variable (which are in turn, columns)
      for ( $row = 0; $row < count($this->_paramValues); $row ++ )
      {
        // assign columns values to proper variables, and only after execute!
        $i = 0;
        foreach ( array_keys($this->_paramConfigs) as $paramName )
        {
          $paramvar = self::SQLPARAM_DYNVAR_PREFIX . substr($paramName, 1); // remove PARAM_CHAR (:) from the paramname
          $$paramvar = $this->_paramValues[$row][$i];

          // debug
          //echo "\$$paramvar = " . $this->_paramValues[$row][$i] . "<br/>";

          $i ++;
        }

        // only execute statement, committing at the end of the loop only!
        $this->_statement->execute();
        $this->_lastInsertId = $this->_conn->lastInsertId();

        // set the number of affected rows
        $affectedRows = $this->_statement->rowCount();
        $this->_stmtAffectedRows = $this->_stmtAffectedRows + $affectedRows;
      }

      $this->_conn->commit();
    }
    catch ( PDOException $ex )
    {
      if ( $this->_conn->inTransaction() ) $this->_conn->rollBack();

      $this->_stmtAffectedRows = 0;
      $this->_lastInsertId = null;

      $errstr = "Failed to execute PDO Statement! Transactions were rolled back...\n";
      $errstr .= $ex->getMessage();

      $stmtEx = new DBWrapperStatementException($errstr, 0, $this->_project, $this->_debug);
      $stmtEx->setDbcode($ex->errorInfo[1]);
      $stmtEx->setPdoCode($ex->getCode());
      throw $stmtEx;
    }
    finally {
      $this->_dbtimer(false);
    }
  }

  /******************************************************************************
   * isAssocArray()
   * <pre>
   * Tries to check if a given array is an associative array (i.e. perl hash) or not.
   * The only time this will fail is if it's given an associative array
   * who's keys are all sequential intergers, AND starting with integer '0', like:
   *
   * $array = [
   *    0 => [ "somevalue", 5 ],
   *    1 => [ 9, "test" ],
   *    2 => [ null, "blabla" ]
   * ];
   * </pre>
   * @param array $array
   * @return boolean
   ******************************************************************************/
  private function _isAssocArray($array)
  {
    if ( ! is_array($array) ) return false;

    $keys = array_keys($array);

    // loop array key names,
    // if one ISN'T a sequential integer, we have an associative array
    for ( $i = 0; $i < count($keys); $i ++ )
    {
      if ( $keys[$i] !== $i )
      {
        return true;
      }
    }
    return false;
  }

  /******************************************************************************
   * getRuntime()
   * @returns number|null execution time of last database request
   ******************************************************************************/
  public function getRuntime()
  {
    if ( isset($this->_runend) and isset($this->_runstart) )
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
   * Class Destructor.
   ******************************************************************************/
  public function __destruct()
  {
    unset($this->_sql);
    unset($this->_paramValues);
    unset($this->_paramValuesSingleVal);
    unset($this->_paramValuesInclConfigs);
    unset($this->_paramConfigs);
    unset($this->_debug);
    unset($this->_isMultiStatement);
    unset($this->_sqlParameters);
    unset($this->_sqlParameters_count);
    unset($this->_runend);
    unset($this->_runstart);
    unset($this->_stmtAffectedRows);
    unset($this->_stmtFailedRows);
    unset($this->_statement);
    unset($this->_lastInsertId);
    unset($this->_project);
    unset($this->_includeDataInMultiEX);
    unset($this->_useCommitOnEachExecV1);
  }
}
?>
