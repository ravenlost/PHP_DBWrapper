<?php

namespace CorbeauPerdu\Database;

use PDO;

// Include config files
require_once ( 'DBWrapper.php' );

/******************************************************************************
 * DBWrapper Configuration, as part of the DBWrapper class project
 * Copyright (C) 2020, Patrick Roy
 * This file may be used under the terms of the GNU Lesser General Public License, version 3.
 * For more details see: https://www.gnu.org/licenses/lgpl-3.0.html
 * This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
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
