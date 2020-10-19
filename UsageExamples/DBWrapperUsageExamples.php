<?php
use CorbeauPerdu\Database\DBWrapper;
use CorbeauPerdu\Database\Exceptions\DBWrapperException;

// Include config file
require_once ( 'DBWrapper.php' );

/******************************************************************************
 * Demo on DBWrapper usage
 *
 * <pre>
 * $mydb = new DBWrapper(true,'MYPROJECT');
 *
 * $mydb->OpenDB();                // open DB connection...
 * $mydb->CloseDB();               // close DB connection...
 * $con = &$mydb->getConnection(); // opens DB connection and returns a PDO connection object; do as you will with it afterwards
 *
 * $data = $mydb->readData(...);        // get data from database
 * $cols = $mydb->readColumnsMeta(...); // get table columns metadata
 * $mydb->storeData(...);               // store, update or delete data from database
 *
 * Note: you do not need to explicitaly call OpenDB / CloseDB, since the read*() and storeData() will take care of that!
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
 * // get MySQL warnings
 * $mydb->getMySQLWarnings();
 *
 * // throw an exception when no data found on SQL Selects instead of returning NULL ?
 * $mydb->setThrowExOnNoData(bool)
 *
 * DBWrapperException additional functions:
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
 * Other things:
 * - If you create the DBWrapper object with it's $debug set to true, error messages in exceptions will show
 *   stacktrace information as well as output DB activity to system log
 * - The readData() has $fetchMode argument to pass to \PDOStatement::setFetchMode() function (i.e. PDO::FETCH_ASSOC, array(PDO::FETCH_INTO, $myobj), etc.) to determine how the data is returned.
 *   If null, then default PDO Fetch Style (PDO::ATTR_DEFAULT_FETCH_MODE) is used.
 * - Additionnaly, readData() has a $fetchAllRows boolean to return an array of rows using fetchAll(), or to return a single row of data using fetch()
 * - The storeData() and read*() functions have a $closeDB parameter.
 *   So, to speed things up, if you have multiple different statement to run one after the other (select, then update, etc.),
 *   run the first statement with $closeDB set to FALSE, and proceed with the remaining statements, using the same DBWrapper object
 *   Set $closeDB back to TRUE on the last statement, or simply unset your DBWrapper / let your script exit
 *   which will eventually call the DBWrapper destruct method (thus closing the DB connection)
 * </pre>
 *
 * @author Patrick Roy (ravenlost2@gmail.com)
 *******************************************************************************/

$debug = true;
$project = 'myproject';

/*****************************************************
 * Read data
 ****************************************************/
try
{
  echo "<h3>READ DATA</h3>";

  $mydb = new DBWrapper($debug, $project);

  // ---------
  // return a single row example

  // single sql parameter / value
  $sql = "SELECT * FROM tbl_users where id = :id";
  $paramData = 9;

  // OR array of parameter values
  $sql = "SELECT * FROM tbl_users where id = :id or username = :username";
  $paramData = array ( 9, "raven" );

  // OR, set an associative array for one, or more parameter values that forces datatypes on each parameter:
  // @formatter:off
  $paramData = [
      ":id" => [ 9, PDO::PARAM_INT ],
      ":username" => [ "raven", PDO::PARAM_STR ],
  ];
  // @formatter:on

  // Another way of setting $paramData dynamically...
  $paramData = [ ];
  $paramData += [  ":id" => [  9, PDO::PARAM_INT ] ];
  $paramData += [  ":username" => [  "raven", PDO::PARAM_STR ] ];

  // get the data and print
  $data = $mydb->readData($sql, $paramData, null, null, false, true);

  echo "Username = " . $data['username'] . "<br/>";
  echo "Mail = " . $data['email'] . "<br/><br/>";

  // ---------
  // return multiple rows example

  // multiple sql parameters / multiple values...
  $sql = "SELECT * FROM tbl_users where is_active = :active";
  $paramData = 1;

  // get and print the data
  $data = $mydb->readData($sql, $paramData, null, null, true, true);

  foreach ( $data as $row )
  {
    echo "Username = " . $row['username'] . "<br/>";
    echo "Mail = " . $row['email'] . "<br/><br/>";
  }

  // get the number of rows found
  echo "Rows found = " . $mydb->getAffectedRows() . "<br/><br/>";

  // ---------
  // heck let's store the data directly into a custom object!
  class User
  {
    // custom object code...
  }

  $sql = "SELECT * FROM tbl_users where id = :id";

  // either pass along new Object() into the fetchMode args, or pass it an already existing object to prefill
  $user = $mydb->readData($sql, 1, null, array ( PDO::FETCH_INTO, new User() ), false, true);

  echo "Username = " . $user->username . "<br/>";
  echo "Mail = " . $user->email . "<br/><br/>";
}
catch ( DBWrapperException $ex )
{
  echo $ex->getMessage_toWeb();
}

/*****************************************************
 * Read columns metadata
 ****************************************************/
try
{
  echo "<h3>READ COLUMNS META</h3>";

  $mydb = new DBWrapper($debug, $project);

  $cols = $mydb->readColumnsMeta("tbl_users", null, true);

  // print columns metadata (names, etc)
  for ( $i = 0; $i < count($cols); $i ++ )
  {
    echo $cols[$i]['Field'] . "<br/>";
  }

  echo "<br/>Number of columns = " . $mydb->getAffectedRows();
}
catch ( DBWrapperException $ex )
{
  echo $ex->getMessage_toWeb();
}

/*****************************************************
 * delete data
 ****************************************************/
try
{
  echo "<h3>DELETE DATA</h3>";

  $mydb = new DBWrapper($debug, $project);

  // delete with a LIKE statement
  $sql = "DELETE FROM tbl_users WHERE username LIKE :username";
  $paramData = "pat%";

  $affectedRows = $mydb->storeData($sql, $paramData, null, false, true);
  echo "Successful deletes = " . $affectedRows . "<br/><br/>";
}
catch ( DBWrapperException $ex )
{
  // MySQL error code check:
  // check code to see if we're say trying to delete an entry who's got references to in another table (Cannot delete or update a parent row: a foreign key constraint fails
  if ( $ex->getDbCode() == 1451 ) echo "Can't delete that user! They've got contracts attached to them...<br><br>";

  echo $ex->getMessage_toWeb();
}

/*****************************************************
 * insert single row data
 ****************************************************/
try
{
  echo "<h3>INSERT SINGLE ROW</h3>";

  $mydb = new DBWrapper($debug, $project);

  $sql = "INSERT INTO tbl_users (username, email, password) VALUES (:username, :email, :passwd)";

  // force value / value type on parameters
  // @formatter:off
  $paramData = [
      ":username" => [ "patroy", PDO::PARAM_STR ],
      ":email" => [ "raven@gmail.com", PDO::PARAM_STR ],
      ":passwd" => [ password_hash("abc123", PASSWORD_DEFAULT), PDO::PARAM_STR ],
  ];
  //@formatter:on

  // OR, data is just an array of parameter values and DBWrapper will take care of finding datatypes based on the values
  $paramData = array ( "patroy", "raven@gmail.com", password_hash("abc123", PASSWORD_DEFAULT) );

  // insert...
  $affectedRows = $mydb->storeData($sql, $paramData, null, false, false);
  echo "Successful inserts = " . $affectedRows . "<br/>";
  echo "Row ID of last insert = " . $mydb->getLastInsertId() . "<br/><br/>";
}
catch ( DBWrapperException $ex )
{
  // MySQL error code check:
  // check error code to see if trying to use insert a duplicate entry in the database  (Integrity constraint violation: 1062 Duplicate entry...)
  if ( $ex->getDbCode() == 1062 ) echo "That username is already taken!<br><br>";

  echo $ex->getMessage_toWeb();
}

/*****************************************************
 * insert multiple row data (this could be an update, delete, etc)
 ****************************************************/
try
{
  echo "<h3>INSERT MULTIPLE ROWS</h3>";

  $mydb = new DBWrapper($debug, $project);

  $sql = "INSERT INTO tbl_users (username, email, password) VALUES (:username, :email, :passwd)";

  //@formatter:off
  $data = array (
      array ( "jdoe1", "jdoe1@email.ca", "abc123"),
      array ( "jdoe2", "jdoe2@email.ca", "abc123"),
      array ( "jdoe3", "jdoe3@email.ca", "abc123"),
      array ( "jdoe4", "jdoe4@email.ca", "abc123"),
      array ( "jdoe5", "jdoe5@email.ca", "abc123"),
  );

  // parameter configuration (totally optional!! if null, then DBWrapper will determine itself datatypes for each params)
  $paramConfigs = [
      ':username' =>  PDO::PARAM_STR ,
      ':email' =>  PDO::PARAM_STR ,
      ':passwd' =>  PDO::PARAM_STR ,
  ];
  //@formatter:on

  $affectedRows = $mydb->storeData($sql, $data, $paramConfigs, true, true);
  echo "Successful multi-inserts = " . $affectedRows . "<br/>";

  $errMsg = null;
}
catch ( DBWrapperException $ex )
{
  $errMsg = "Successful multi-inserts = " . $mydb->getAffectedRows() . "<br/>";
  $errMsg .= "Failed multi-inserts = " . $mydb->getFailedRows() . "<br/><br/>";
  $errMsg .= $ex->getMessage_toWeb();
  if ( $ex->getErrorList() ) $errMsg .= "<br/>" . $ex->getErrorList_toWeb(true) . "<br/>";
}
finally {
  if ( $mydb->getLastInsertId() ) echo "Row ID of last insert = " . $mydb->getLastInsertId() . "<br/><br/>";
  if ( isset($errMsg) ) echo $errMsg;
}

?>
