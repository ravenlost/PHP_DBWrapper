<?php

namespace CorbeauPerdu\Database\Exceptions;

use Exception;

/******************************************************************************
 * DB Wrapper Parent Custom Exception class, as part of the DBWrapper class project.
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
 * <pre>
 * Name: DBWrapperException
 * Notes: This is the parent of all other DB Exceptions! See child exception classes below...
 *
 * List of custom DBWrapper Exception error codes:
 *
 * DBWrapperGenericException => 1
 * DBWrapperOpenDBException => 2
 * DBWrapperCloseDBException => 3
 * DBWrapperStoreDataException => 4
 * DBWrapperReadDataException => 5
 * DBWrapperReadColsException => 6
 * DBWrapperNoDataFoundException => 7
 * DBWrapperStatementException => 8
 * DBWrapperMultiStatementException => 9
 * DBWrapperBusyException => 10
 *
 * Note you can override these by putting a custom code when throwing the DBWrapperExceptions...
 * Just keep 0 to 10 reserved for internal DBWrapper...
 *
 * Available Functions when catching these exceptions:
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
 * We can then act upon this, say by showing an error to the webuser saying "hey! that user already exists!" or "hey! can't delete that user! he's got existing contracts attached to him!"
 *
 * Extends Exception class
 *
 * Last Modified : 2020/03/09 by PRoy - First release
 *                 2020/05/17 by PRoy - $message in the parent constructor no longer has the full stacktrace! It holds just the $message, as it should! 
 * </pre>
 *
 * @author      Patrick Roy
 * @version     1.1
 ******************************************************************************/
abstract class DBWrapperException extends Exception
{
  private $_errorMessage;
  private $_errorList;
  private $_dbcode;
  private $_pdocode;

  /******************************************************************************
   * Class Constructor.
   * @param string $message error message to generate! This in turn can be caught using ex.getMessage();
   * @param integer $code error code ($ex.getCode())
   * @param string $projectname name of this project to show in logs
   * @param boolean $debug log to system log or not
   ******************************************************************************/
  public function __construct($message, $code = 0, $projectname = null, $debug = false)
  {
    $childClassName = explode("\\", get_class($this));
    $childClassName = end($childClassName);

    if ( ! isset($code) or $code == 0 )
    {
      switch ( $childClassName )
      {
        case "DBWrapperGenericException":
          $code = 1;
          break;
        case "DBWrapperOpenDBException":
          $code = 2;
          break;
        case "DBWrapperCloseDBException":
          $code = 3;
          break;
        case "DBWrapperStoreDataException":
          $code = 4;
          break;
        case "DBWrapperReadDataException":
          $code = 5;
          break;
        case "DBWrapperReadColsException":
          $code = 6;
          break;
        case "DBWrapperNoDataFoundException":
          $code = 7;
          break;
        case "DBWrapperStatementException":
          $code = 8;
          break;
        case "DBWrapperMultiStatementException":
          $code = 9;
          break;
        case "DBWrapperBusyException":
          $code = 10;
          break;
        default:
          $code = 0;
      }
    }

    // construct a fully detailed error message if DEBUG is on for log only
    if ( $debug )
    {
      $this->_errorMessage = $childClassName . ": ";
      $this->_errorMessage .= $message . "\n\n";
      $this->_errorMessage .= "  thrown in " . $this->getFile() . ": " . $this->getLine() . "\n\n";
      $this->_errorMessage .= "Stack trace: \n" . preg_replace("/\w+\\\/", "", $this->getTraceAsString()) . "\n";

      // log to filesystem
      error_log($projectname . " " . $this->_errorMessage, 0);
    }

    // reinit error message to construct with JUST the message!
    $this->_errorMessage = $childClassName . ": ";
    $this->_errorMessage .= $message;
    
    // create parent Exception object
    parent::__construct($this->_errorMessage, $code);
  }

  /******************************************************************************
   * Output error message in web-readable format (i.e. replace '\n' with '<br/>')
   * @return string
   ******************************************************************************/
  public function getMessage_toWeb()
  {
    return str_replace("  ", "&nbsp;&nbsp;", str_replace("\n", "<br/>\n", $this->_errorMessage));
  }

  /******************************************************************************
   * get database driver error code
   * if exception comes from MultiStatementException, then this will be the last failed row DB error code only
   * @return number
   ******************************************************************************/
  public function getDbCode()
  {
    return $this->_dbcode;
  }

  /******************************************************************************
   * set database driver error code
   * @param number $dbcode
   ******************************************************************************/
  public function setDbCode($dbcode)
  {
    $this->_dbcode = $dbcode;
  }

  /******************************************************************************
   * get PDO error code
   * if exception comes from MultiStatementException, then this will be the last failed row PDO error code only
   * @return mixed
   ******************************************************************************/
  public function getPdoCode()
  {
    return $this->_pdocode;
  }

  /******************************************************************************
   * set PDO error code
   * @param number $dbcode
   ******************************************************************************/
  public function setPdoCode($pdocode)
  {
    $this->_pdocode = $pdocode;
  }

  /******************************************************************************
   * set array holding list of errors from multiple rows updates
   * <pre>
   * $_errorList[0] = multi-data row number
   * $_errorList[1] = database driver error code
   * $_errorList[2] = error message
   * $_errorList[3] = array of failed rows (if set in DBWrapperConfig)
   * </pre>
   * @param array $_errorList
   ******************************************************************************/
  public function setErrorList($errorList)
  {
    $this->_errorList = $errorList;
  }

  /******************************************************************************
   * get array holding list of errors from multiple rows updates
   * <pre>
   * $_errorList[0] = multi-data row number
   * $_errorList[1] = database driver error code
   * $_errorList[2] = error message
   * $_errorList[3] = array of failed rows (if set in DBWrapperConfig)
   * </pre>
   * @return array
   ******************************************************************************/
  public function getErrorList()
  {
    return $this->_errorList;
  }

  /******************************************************************************
   * get list of errors from multiple rows updates, as a big string
   * @param string $showDataRows along the error msgs, display the rows of data that failed as well?
   * @return string
   ******************************************************************************/
  public function getErrorList_toString($showDataRows = false)
  {
    $errListstr = null;

    if ( is_array($this->_errorList) )
    {
      $errListstr .= "The following rows failed to commit:\n\n";
      foreach ( $this->_errorList as $error )
      {
        $row = sprintf("%04d", $error[0]);
        $dbCode = $error[1];
        $message = $error[2];
        $errListstr .= "Data Row #" . $row . " [dbcode: $dbCode] $message\n";
      }

      if ( ( isset($this->_errorList[0][3]) ) and ( $showDataRows ) )
      {
        $colCount = count($this->_errorList[0][3]);
        $errListstr .= "\nTheses are the following row data: \n\n";

        foreach ( $this->_errorList as $error )
        {
          $errListstr .= "Data Row #" . sprintf("%04d", $error[0]) . " = ( ";
          for ( $i = 0; $i < $colCount; $i ++ )
          {
            if ( is_string($error[3][$i]) )
            {
              $errListstr .= "\"" . strval($error[3][$i]) . "\", ";
            }
            else
            {
              $errListstr .= strval($error[3][$i]) . ", ";
            }
          }
          $errListstr = substr($errListstr, 0, - 2);
          $errListstr .= " )\n";
        }
      }
    }
    return $errListstr;
  }

  /******************************************************************************
   * same has getErrorList_toString(), but will output for web pages
   * @param string $showDataRows along the error msgs, display the rows of data that failed as well?
   * @return string
   ******************************************************************************/
  public function getErrorList_toWeb($showDataRows = false)
  {
    return str_replace("\n", "<br/>\n", $this->getErrorList_toString($showDataRows));
  }
}

/******************************************************************************
 * DB Wrapper Exception Child classes
 ******************************************************************************/
// @formatter:off
class DBWrapperGenericException extends DBWrapperException {}
class DBWrapperOpenDBException extends DBWrapperException {}
class DBWrapperCloseDBException extends DBWrapperException {}
class DBWrapperStoreDataException extends DBWrapperException {}
class DBWrapperReadDataException extends DBWrapperException {}
class DBWrapperReadColsException extends DBWrapperException {}
class DBWrapperNoDataFoundException extends DBWrapperException {}
class DBWrapperStatementException extends DBWrapperException {}
class DBWrapperMultiStatementException extends DBWrapperException {}
class DBWrapperBusyException extends DBWrapperException {}
// @formatter:on

?>
