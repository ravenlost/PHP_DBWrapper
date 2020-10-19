# CorbeauPerdu\Database\DBWrapper Class
Database Wrapper (DBAL) class that wrap's around PDO to handle any database opening, closing, reading and storing data, while also protecting against SQL Injections. It was written using a MySQL database, but should also work for Oracle, etc. Further testing would be required!

The simplest possible usage example:
<pre>
$mydb = new DBWrapper();
$data = $mydb->readData('select * from users where id=:id', 5); // returns an array holding row data where user id = 5
</pre>

<a href="https://github.com/ravenlost/CorbeauPerdu/blob/master/PHP/Database/UsageExamples/DBWrapperUsageExamples.php">**See: DBWrapperUsageExamples.php**</a>

Constructor expects either to use DBWrapperConfig.php to get the database configuration, <br/>
or to be passed all database configuration as arguments! Read the documentation!
<pre>
$mydb = new DBWrapper(true,'MYPROJECT');

$mydb->OpenDB();                // open DB connection...
$mydb->CloseDB();               // close DB connection...
$con = &$mydb->getConnection(); // opens DB connection and returns a PDO connection object; do as you will with it afterwards

$data = $mydb->readData(...);        // get data from database
$cols = $mydb->readColumnsMeta(...); // get table columns metadata
$mydb->storeData(...);               // store, update or delete data from database
</pre>
Notes: <br/>
 - _You do not need to explicitaly call OpenDB / CloseDB, since the read*() and storeData() will take care of that!_
 - *The parameters in read/storeData() SQLs are expected to be named parameters (:user, :country, etc!)*
 - *Regarding storeData(): if you want to insert, update or delete many rows of data, use its $commitOnEachExec argument to tell it to commit on each inserts, updates,... allowing data to still be updated if one of the rows fails. Otherwise, if just one of the rows fails, everything is rolled back!*

But really... just read the documentation, and look at the *DBWrapperUsageExamples.php*!

<br/>

Other useful functions from DBWrapper:
<pre>
// get the number of affected rows (number of rows return from SELECTS, also number of successful DELETES, INSERTS, etc)
$mydb->getAffectedRows();

// get the number of failed rows when doing a multi-execute statement
$mydb->getFailedRows();

// get row ID of the last succesful INSERT
$mydb->getLastInsertId();

// get execution time of query  
$mydb->getRuntime();

// get MySQL warnings
$mydb->getMySQLWarnings();

// throw an exception when no data found on SQL Selects instead of returning NULL ?
$mydb->setThrowExOnNoData(bool)
</pre>

<br/>

**DBWrapperException** additional functions:

<pre>
$ex->getMessage()       // returns error message
$ex->getMessage_toWeb() // returns error message in a web readable format (i.e. replaces '\n' with '&lt;br/>'
$ex->getCode()          // custom exception code
$ex->getDbCode()        // database driver error code (i.e. error codes returned by MySQL, and not PDO)
$ex->getPdoCode()       // SQLSTATE / PDOException error code
$ex->getErrorList()     // if we ran a multi-row insert/delete/update, and we've commited everyrow, the ones that failed will be in this retured array, along with the error messages
$ex->getErrorList_toString() // prints errorList array content with linebreaks (\n)
$ex->getErrorList_toWeb()    // prints errorList array content, formatted for the web (&lt;br/>)
</pre>

*$ex->getDbCode()* can be quite handy for let's say we insert a new user, and want to catch if the user already exists.<br/>
In such a case, MySQL / getDbCode() will return code '1062': *Integrity constraint violation: 1062 Duplicate entry...*

Another case scenario is if we try to delete say a user, but this user has data from another table attached to him, and MySQL is setup to prevent deletion of such a user:

In such a case, MySQL / getDbCode() will return code '1451': *Cannot delete or update a parent row: a foreign key constraint fails*

We can then act upon this, say by showing an error to the webuser saying "hey! that user already exists!" or "hey! can't delete that user! he's got existing contracts attached to him!"

Other things:<br/>
- If you create the DBWrapper object with it's $debug set to true, error messages in exceptions will show
  stacktrace information as well as output DB activity to system log
- The readData() has $fetchMode argument to pass to \PDOStatement::setFetchMode() function (i.e. PDO::FETCH_ASSOC, array(PDO::FETCH_INTO, $myobj), etc.) to determine how the data is returned.
   If null, then default PDO Fetch Style (PDO::ATTR_DEFAULT_FETCH_MODE) is used.
 - Additionnaly, readData() has a $fetchAllRows boolean to return an array of rows using fetchAll(), or to return a single row of data using fetch().
- The storeData() and read*() functions have a $closeDB parameter.<br/>
  So, to speed things up, if you have multiple different statement to run one after the other (select, then update, etc.),
  run the first statement with $closeDB set to FALSE, and proceed with the remaining statements, using the same DBWrapper object.<br/>
  Finally, set $closeDB back to TRUE on the last statement, or simply unset your DBWrapper object / let your script exit,
  which will eventually call the DBWrapper destruct method (thus closing the DB connection)
