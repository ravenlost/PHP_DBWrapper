<?php

namespace CorbeauPerdu\Database;

use PDO;

// Include config files
require_once ( 'DBWrapper.php' );

/******************************************************************************
 * DBWrapper Configuration, as part of the DBWrapper class project
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
 * Last Modified : 2020/03/09 - First release
 *
 * @author Patrick Roy
 * @version 1.0
 * @since 1.0
 ******************************************************************************/
class DBWrapperConfig
{
  // @formatter:off
   private static $configs = array (
      "DATABASE_HOST" => "localhost",
      "DATABASE_PORT" => "3306",
      "DATABASE_USER" => "dbuser",
      "DATABASE_PASS" => "dbpasswd",
      "DATABASE_NAME" => "dbname",
      "DATABASE_TYPE" => DBWrapper::DBTYPE_MYSQL,             // either "mysql" or "oracle"
      "DATABASE_CHARSET" => DBWrapper::CHARSET_UTF8MB4,       // set the proper DB charset here as to "also" protect against SQL injections
      "DATABASE_THROW_EX_ON_NODATA" => true,                  // throw an exception when sql selects returns no data, otherwise, will return NULL
      "DATABASE_USE_COMMIT_EACH_EXEC_V1" => true,             // user faster DBWrapperStatement::stmtCommitOnEachExecV1()? Otherwise, will use HOTFIX V2, but it's slower...
      "DATABASE_INCLUDE_DATAROWS_IN_MULTISTMNTEX" => true,    // if errors occur when running a multiple-execute statement, include failed datatows array in $errorList of MultiStatementException ?
      "DATABASE_PDO_ATTRIBUTES" => [
               PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
               PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // defautlt column fetch type in selects (FETCH_ASSOC returns data with only column names, and not their number!)
               PDO::ATTR_EMULATE_PREPARES => false,               // set to false to protect against SQL Injections
               PDO::ATTR_AUTOCOMMIT => false,                     // turn off autocommit!
               PDO::ATTR_PERSISTENT => true,                      // is this needed for updates, inserts, etc?
      ]
   );
   // @formatter:on

  /******************************************************************************
   * Get value of configuration array
   *
   * Usage examples:
   *
   * <pre>
   * dbconfig::getConfig('configname');
   * dbconfig::getConfig()
   * </pre>
   *
   * @param  string the config value name to get (optional!)
   * @return mixed either a string config value, OR an array of all config values if no parameters were passed!
   ******************************************************************************/
  protected static function getConfig($key)
  {
    return $key !== false ? self::$configs[$key] : self::$configs;
  }
}

?>
