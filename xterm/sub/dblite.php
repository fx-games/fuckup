<?php
/* *** dblite - (Simple PHP based SQL Abstraction Layer) v0.4 ***
This Script was written in 2010 by
          _____
        _/ ____\__  ___
        \   __\\  \/  /
         |  |   >    <
         |__|  /__/\_ \
                     \/  Felix Ruzzoli

Current Development:
 - adding Full Text Search through inverted index table, need to enable multiple columns as indexable
 - make KVP db, MySQL compatible, also the FTS

Version History:
v.0.4
 * bugfix: SQLite unique now working

v.0.3
 * added MySQL support

v.0.2
 * improved readability and introduced search via LIKE in the queries..
   * select($query, $exactmatch = true, $conjunctive = true) is the new prototype for select function
   * LIKE is used on the query if $exactmatch is false, if $conjunctive is false the WHERE clause will be concatenated with OR

v.0.1
 * implemented the abstraction layer for PHP PDO SQLite v3
 * added key value simplifications

*** Documentation ***
* Initialization *
for SQLite: 

// constructor parameter defaults to 'lite.db' (filename of SQLite db)
$db = new DbLite('myfile.db');

// define table structure
$db->tables['musictable'] = array
(
	'title', 'artist', 'release_year'
);

for MySQL:

// specify credentials for the MySQL DB
$dbcreds = array
(
	'hostname' => 'localhost',
	'dbname' => 'development',
	'username' => 'developer',
	'password' => 'devel'
);

// create db object, passing credentials
$db = new DbLite($dbcreds);

// define table structure
$db->tables['musictable'] = array
(
	'title' => 'varchar(255)',
	'artist' => 'varchar(255)',
	'release_year' => 'int(4)'
));

* Usage *
1. Select the active table:

$db->activetable = 'musictable';

2. (optional) Create the table (must have rights):

$db->createTable();

3. issue queries:
insert:

$db->insert(array
(
	'title' => 'Rock X-Mas',
	'artist' => 'Kelly Family',
	'release_year' => 1994
);

select:

// returns numbered array of associative arrays of the active table..
$result = $db->selectAll(); // selects whole table
echo($result[0]['rowid'].' - '.$result[0]['title']);

$result = $db->select(array('release_year' => 1994)); // selects all entries where release_year = 1994
echo($result[0]['rowid'].' - '.$result[0]['title']);

$result = $db->selectone(array('rowid' => 1)); // selects all rows where the rowid is 1 (there can be only one)
echo($result['title'].' - '.$result['artist']);

delete:

$db->delete(array('rowid' => 1));

update:

$db->update(
array('rowid' => 1),
array
(
	'title' => 'Rock X-Mas',
	'artist' => 'Kelly Family',
	'release_year' => 1985
));

*** End of Documentation ***


Development Notes:
 * sqlite data types: integer, real, text, blob

*/

function backtrace()
{
    echo('<p>Backtrace:</p><pre>');
    debug_print_backtrace();
    echo('</pre>');
}

class DbLite
{
    private $db;
    public $tables;
    public $activetable;
    public $dbname;
    public $debug;
    public $ftscols;
    public $kvpneeded;
    public $lite;
    public $cfg;

    /* KVP =  Key Value Pairs, FTS = Full Text Search */
    function __construct($credentials = 'lite.db', $kvpneeded = false, $ftscolumns = array())
    {
        $this->debug = false;
        $this->ftscols = $ftscolumns;
        $this->kvpneeded = $kvpneeded;
        $this->lite = !is_array($credentials);
        if ($this->lite) {
            $this->db = new PDO('sqlite:' . $credentials);
            $this->cfg['allcols'] = 'rowid,*';
            $this->cfg['tablequotes'] = "'";
            $this->dbname = '';
        } else {
            $this->db = new PDO
            (
                'mysql:host=' . $credentials['hostname'] . ';dbname=' . $credentials['dbname'],
                $credentials['username'],
                $credentials['password'],
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
            );
            $this->dbname = $credentials['dbname'];
            $this->cfg['allcols'] = '*';
            $this->cfg['tablequotes'] = '`';
        }
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->kvpneeded) {
            if ($this->hasTable('kvp'))
                $this->initKVP(false);
            else
                $this->initKVP(true);
        }

        if (count($this->ftscols) > 0 && $this->lite) {
            if ($this->hasTable('invertedindex'))
                $this->initFTS(false);
            else
                $this->initFTS(true);
        }
        /*if ($this->debug)
        {
            // do selftest..
            $ok = $this->everythingIsOK();
            if ($ok !== true)
            {
                echo('<pre>');
                var_dump($ok);
                echo('</pre>');
            }
        }*/
    }

    public function initFTS($createtable)
    {

        $this->tables['invertedindex'] = array('token', 'ids');
        if ($createtable) {
            $previoustable = $this->activetable;
            $this->activetable = 'invertedindex';
            $this->createTable(array('token')); // make token an unique column, thus creating an sqlite index
            $this->activetable = $previoustable;
        }
    }

    public function initKVP($createtable)
    {

        $this->tables['kvp'] = array('key', 'value');
        if ($createtable) {
            $previoustable = $this->activetable;
            $this->activetable = 'kvp';
            $this->createTable();
            $this->activetable = $previoustable;
        }
    }


    public function set($name, $value)
    {
        $previoustable = $this->activetable;
        $this->activetable = 'kvp';
        //echo('<p>here set:</p>');
        $query = $this->select(array('key' => $name));
        //var_dump($query);

        if (!$this->select(array('key' => $name))) {
            // new entry
            $this->insert(array('key' => $name, 'value' => $value));
        } else {
            // exisiting entry
            $this->update(array('key' => $name), array('value' => $value));
        }
        $this->activetable = $previoustable;
    }

    public function get($name)
    {
        $previoustable = $this->activetable;
        $this->activetable = 'kvp';
        $result = $this->select(array('key' => $name));
        $this->activetable = $previoustable;
        return $result[0]['value'];
    }

    public function hasTable($tablename)
    {
        $result = $this->lite ?
            $this->query("SELECT * FROM SQLITE_MASTER WHERE type='table' AND tbl_name='" . $tablename . "'") :
            $this->query("SHOW TABLES WHERE `Tables_in_" . $this->dbname . "`='" . $tablename . "'");
        //var_dump($result);
        return !empty($result);
    }

    function changeTable()
    {
        $this->db->beginTransaction();
        $this->db->exec('CREATE TEMPORARY TABLE diffs_backup(pagename,timestamp,diff,revision)');
        $this->db->exec('INSERT INTO diffs_backup SELECT pagename,timestamp,diff,revision FROM diffs');
        $this->db->exec('DROP TABLE diffs');
        $this->db->exec('CREATE TABLE diffs(itemname,timestamp,author,diff,revision)');
        $this->db->exec('INSERT INTO diffs SELECT pagename as itemname,timestamp,diff,revision FROM diffs_backup');
        $this->db->exec('DROP TABLE diffs_backup');
        $this->db->commit();
    }

    function createTable($unique = array())
    {
        $create_statement = "CREATE TABLE " . $this->cfg['tablequotes'] . $this->activetable . $this->cfg['tablequotes'] . "\n";
        if ($this->lite) {
            $create_statement .= $this->getSQLiteCreateStatement($unique);
        } else {
            $create_statement .= $this->getMySQLCreateStatement($unique);
        }
        $result = $this->db->query($create_statement);
        /*
        echo('<pre>');
		var_dump($create_statement);
		echo('</pre>');
        */
        if ($result === false) {
            $err = $this->db->errorInfo();
            $errmsg = $err[2];
            $sqleng = $this->lite ? 'SQLite' : 'MySQL';
            echo('<p class="error">' . $sqleng . ' Error while creating table: ' . $errmsg . '</p>');
            echo('<p class="error">Query: ' . $create_statement . '</p>');
            false;
        }
    }

    function deleteTable($tablename)
    {
        $sql = "DROP TABLE " . $this->cfg['tablequotes'] . $tablename . $this->cfg['tablequotes'];
        $result = $this->db->query($sql);
        if ($result === false) {
            $err = $this->db->errorInfo();
            $errmsg = $err[2];
            $sqleng = $this->lite ? 'SQLite' : 'MySQL';
            echo('<p class="error">' . $sqleng . ' Error while creating table: ' . $errmsg . '</p>');
            echo('<p class="error">Query: ' . $sql . '</p>');
            false;
        }
    }

    private function getMySQLCreateStatement($unique)
    {
        $uniques = '';
        $create_statement = "(\n `rowid` int(32) NOT NULL AUTO_INCREMENT,\n";
        foreach ($this->tables[$this->activetable] as $colname => $type) {
            if ($type === 'file') {
                $type = 'VARCHAR(255)';
            }
            $create_statement .= $colname . ' ' . $type . ",\n";
            if (in_array($colname, $unique)) {
                $uniques .= 'UNIQUE KEY(' . $colname . "),\n";
            }
        }
        $ftsline = (count($this->ftscols) > 0) ? 'FULLTEXT (' . implode(', ', $this->ftscols) . '), ' : '';
        $create_statement .= $uniques . " " . $ftsline . "PRIMARY KEY (`rowid`)\n) DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ENGINE=MYISAM";
        return $create_statement;
    }

    private function getSQLiteCreateStatement($unique)
    {
        $create_statement = "(\n";
        foreach ($this->tables[$this->activetable] as $colname) {
            if (in_array($colname, $unique)) {
                $create_statement .= $colname . ' UNIQUE, ';
            } else {
                $create_statement .= $colname . ', ';
            }
        }
        $create_statement = substr($create_statement, 0, -2) . "\n)";
        return $create_statement;
    }

    //private function fullTextSearch($searchstring)
    //{
    //$this->ftscol;
    // TODO: multiple search words, boolean operators, phrase search..
    //$entries = $this->lookupToken($searchstring);
    //return $entries;
    //}

    private function lookupToken($token)
    {
        $previoustable = $this->activetable;
        $this->activetable = 'invertedindex';
        $indexentry = $this->select(array('token' => $token));
        $this->activetable = $previoustable;
        if (is_array($indexentry)) {
            $entryids = explode(',', $indexentry[0]['ids']);

            //$entries = $this->select(array('rowid' => $entryids));
            return $entryids;
        } else {
            return false;
        }
    }

    private function updateFTSIndex($id, $content)
    {
        $previoustable = $this->activetable;
        $this->activetable = 'invertedindex';
        // tokenize input
        // add $id to every token row
        $tokens = $this->simpleTokenizer($content);
        foreach ($tokens as $token) {
            $indexentry = $this->select(array('token' => $token));
            if (is_array($indexentry)) {
                // word exists, update
                $indexentry = $indexentry[0];
                $currentids = explode(',', $indexentry['ids']);
                $currentids[] = $id; // add id..
                $newids = implode(',', array_unique($currentids));
                $this->update($indexentry['rowid'], array('ids' => $newids));
            } else {
                // create entry
                $this->insert(array('token' => $token, 'ids' => $id));
            }
        }
        $this->activetable = $previoustable;
    }

    private function simpleTokenizer($content)
    {
        $blindchars = array('.', ',', '/', '"', '\'', '*', '\\', '%', '#', '!', '?', '`', '@', '[', ']', '(', ')', '{', '}', '|', '^', '&', '<', '>', ':', ';', '+', '=');

        $cleansed = str_replace($blindchars, ' ', trim(strip_tags($content)));
        $cleansed = preg_replace('(\s+)', ' ', $cleansed);
        $tokens = array_unique(explode(' ', $cleansed));
        return $tokens;
    }

    private function getConstraint($query, $exactmatch = true, $conjunctive = true)
    {
        $constraintparts = array();
        $constraint = ' WHERE ';
        $logicop = $conjunctive ? ' AND ' : ' OR ';
        $compareop = $exactmatch ? " = " : " LIKE ";
        foreach ($query as $colname => $value) {
            if (is_array($value)) {
                foreach ($value as $colval) {
                    $constraintparts[] .= $this->cfg['tablequotes'];
                    $colname . $this->cfg['tablequotes'] . $compareop . ":" . $colname;
                }
            } else {
                $constraintparts[] .= $colname . $compareop . ":" . $colname;
            }
        }
        $constraint .= implode($logicop, $constraintparts);
        return $constraint;
    }

    function aggregate($function, $column)
    {
        $aggname = $function . '(' . $column . ')';
        $sql_query = "SELECT " . $aggname . " FROM " . $this->cfg['tablequotes'] . $this->activetable . $this->cfg['tablequotes'];
        //$result = $this->db->query($sql_query);
        $result = $this->preparedQuery($sql_query, array());
        if ($result === false) {
            if ($this->debug === true) {
                $err = $this->db->errorInfo();
                $errmsg = $err[2];
                $sqleng = $this->lite ? 'SQLite' : 'MySQL';
                echo('<p class="error">' . $sqleng . ' Error while aggregating entries: ' . $errmsg . '</p>');
                echo('<p class="error">Query: ' . $sql_query . '</p><p>Backtrace:</p>');
                backtrace();
            }
            return false;
        }
        return $result[0][$aggname];
    }

    function query($sqlquery)
    {
        $result = $this->db->query($sqlquery);
        //echo('<p class="error">Query: '.$sqlquery.'</p><p>Backtrace:</p>');
        if ($result === false) {
            if ($this->debug === true) {
                $err = $this->db->errorInfo();
                $errmsg = $err[2];
                $sqleng = $this->lite ? 'SQLite' : 'MySQL';
                echo('<p class="error">' . $sqleng . ' Error while executing raw query: ' . $errmsg . '</p>');
                echo('<p class="error">Query: ' . $sqlquery . '</p><p>Backtrace:</p>');
                backtrace();
            }
            return false;
        }
        $output = $result->fetchAll(PDO::FETCH_ASSOC);
        //var_dump($output);
        return $output;
    }

    private function prepareStatement($sqlquery, $params)
    {
        $statement = $this->db->prepare($sqlquery);
        //var_dump($sqlquery);
        /*
        echo('<pre>');
		var_dump($params);
		echo('</pre>');
        */
        //die();
        /*
        $err = $this->db->errorInfo();
        $errmsg = $err[2];
        $sqleng = $this->lite ? 'SQLite' : 'MySQL';
        echo('<p class="error">'.$sqleng.' Error while executing raw query: '.$errmsg.'</p>');
        echo('<p class="error">Query: '.$sqlquery.'</p><p>Backtrace:</p>');
        backtrace();
        echo('<pre>');
        var_dump($sqlquery);
        echo('</pre>');
        echo('<pre>');
        var_dump($params);
        echo('</pre>');
        */
        foreach ($params as $name => $value) {
            if (isset($value)) {
                if (is_int($value)) {
                    //echo('<p>--- binding '.$value.' as '.$name.' with type INTEGER</p>');
                    $statement->bindValue($name, $value, PDO::PARAM_INT);
                    //var_dump($value);
                } else if (is_bool($value)) {
                    //echo('<p>--- binding '.$value.' as '.$name.' with type BOOL</p>');
                    $statement->bindValue($name, $value, PDO::PARAM_BOOL);
                } else {
                    //echo('<p>--- binding '.$value.' as '.$name.' with type STRING</p>');
                    $statement->bindValue($name, $value, PDO::PARAM_STR);
                }
            }
        }
        return $statement;
    }

    private function preparedQuery($sqlquery, $params)
    {
        //echo('<p>'.$sqlquery.'</p>');

        $statement = $this->prepareStatement($sqlquery, $params);
        try
        {
            $success = $statement->execute();
            $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        }
        catch (PDOException $e)
        {
            $success = false;
            $result = false;
        }

        if ($success === false) {
            if ($this->debug === true) {
                $err = $this->db->errorInfo();
                $errmsg = $err[2];
                $sqleng = $this->lite ? 'SQLite' : 'MySQL';
                echo('<p class="error">' . $sqleng . ' Error while executing prepared query: ' . $errmsg . '</p>');
                echo('<p class="error">Query: ' . $sqlquery . '</p><p>Backtrace:</p>');
                backtrace();
            }
            return false;
        }
        if ($success === true && !$result)
        {
            return true;
        }
        //var_dump($output);
        return $result;
    }

    function selectone($query, $exactmatch = true, $conjunctive = true)
    {
        if (is_array($query) && !empty($query)) {
            $output = array();
            $sql_query = "SELECT " . $this->cfg['allcols'] . " FROM " . $this->cfg['tablequotes'] . $this->activetable . $this->cfg['tablequotes'];
            $sql_query .= $this->getConstraint($query, $exactmatch, $conjunctive) . " LIMIT 1";
            $result = $this->preparedQuery($sql_query, $query);
            if (is_array($result) && $result) { // preparedQuery returns true for "no rows"
                return $result[0];
            }
        }
        //$entry = $this->select($query, $exactmatch,$conjunctive);
        //var_dump($entry);
        //$entry = (is_array($entry) && !empty($entry)) ? $entry[0] : $entry;
        return false;
    }

    function select($query, $exactmatch = true, $conjunctive = true)
    {
        if (is_array($query) && !empty($query)) {
            $indexedcols = array_intersect($this->ftscols, array_keys($query));
            if (count($indexedcols) > 0) {
                // if we query for the FTS indexed column alone, use the index..
                $entryids = $this->lookupToken($query[$indexedcols[0]]); // query all of them?
                unset($query[$this->ftscol]); //unset all of them
                $query = array_merge($query, array('rowid' => $entryids));
            }
            $output = array();
            $sql_query = "SELECT " . $this->cfg['allcols'] . " FROM " . $this->cfg['tablequotes'] . $this->activetable . $this->cfg['tablequotes'];
            $sql_query .= $this->getConstraint($query, $exactmatch, $conjunctive);
            //var_dump($sql_query);
            $result = $this->preparedQuery($sql_query, $query);
            return $result;
        }
        return false;
    }

    function selectAll($orderby = '', $asc = 'ASC')
    {
        $output = array();
        $order = ($orderby === '') ? '' : ' ORDER BY ' . $orderby . ' ' . $asc;
        $sql_query = "SELECT " . $this->cfg['allcols'] . " FROM " . $this->cfg['tablequotes'] . $this->activetable . $this->cfg['tablequotes'] . $order;
        $result = $this->preparedQuery($sql_query, array());
        return $result;
    }

    function selectPaged($pagenum, $rows, $orderby = '', $asc = 'ASC')
    {
        $output = array();
        //$sortorder = ($asc === 'asc')
        $offset = ($pagenum - 1) * $rows;
        $order = ($orderby === '') ? '' : ' ORDER BY ' . $orderby . ' ' . $asc;
        $sql_query = "SELECT " . $this->cfg['allcols'] . " FROM " . $this->cfg['tablequotes'] . $this->activetable . $this->cfg['tablequotes'] . $order . ' LIMIT ' . $offset . ', ' . $rows;
        //var_dump($sql_query);
        $result = $this->preparedQuery($sql_query, array());
        return $result;
    }

    function delete($query)
    {
        $sql_query = "DELETE FROM " . $this->cfg['tablequotes'] . $this->activetable . $this->cfg['tablequotes'];
        $sql_query .= $this->getConstraint($query);
        $result = $this->preparedQuery($sql_query, $query);
        if ($result === false) {
            $err = $this->db->errorInfo();
            $errmsg = $err[2];
            $sqleng = $this->lite ? 'SQLite' : 'MySQL';
            echo('<p class="error">' . $sqleng . ' Error while deleting values: ' . $errmsg . '</p>');
            return false;
        }
    }

    function update($query, $datadict)
    {
        $newftscontent = '';
        if (is_array($datadict) && !empty($datadict)) {
            $cols = "(";
            $vals = "(";
            $updates = array();
            $columns = $this->lite ? $this->tables[$this->activetable] : array_keys($this->tables[$this->activetable]);
            foreach ($columns as $desc) {
                if (array_key_exists($desc, $datadict) && isset($datadict[$desc])) {
                    if (in_array($desc, $this->ftscols)) {
                        $newftscontent .= trim($datadict[$desc]) . ' ';
                    }
                    $updates[] = $this->cfg['tablequotes'] . $desc . $this->cfg['tablequotes'] . " = :" . $desc;
                }
            }
            $updates = implode(', ', $updates);
            $sql_query = "UPDATE " . $this->cfg['tablequotes'] . $this->activetable . $this->cfg['tablequotes'] . " SET " . $updates;
            $sql_query .= $this->getConstraint($query);
            //var_dump($datadict);
            $statement = $this->prepareStatement($sql_query, array_merge($query, $datadict));
            $result = $statement->execute();
            //echo('<p class="error">QUERY: '.$sql_query.'</p>');
            if ($result === false) {
                $err = $this->db->errorInfo();
                $errmsg = $err[2];
                $sqleng = $this->lite ? 'SQLite' : 'MySQL';
                echo('<p class="error">' . $sqleng . ' Error while updating values in table(' . $this->activetable . '): ' . $errmsg . '</p>');
                return false;
            }
            if ($newftscontent != '') {
                $this->updateFTSIndex($this->getID($query), $newftscontent);
            }
        }
        return true;
    }

    function insert($datadict)
    {
        $newftscontent = '';
        if (is_array($datadict) && !empty($datadict)) {
            $cols = "(";
            $vals = "(";
            //var_dump($this->tables);
            $columns = $this->lite ? $this->tables[$this->activetable] : array_keys($this->tables[$this->activetable]);
            foreach ($columns as $desc) {
                if (array_key_exists($desc, $datadict) && isset($datadict[$desc])) {
                    if ($desc == $this->ftscols) {
                        $newftscontent = $datadict[$desc];
                    }
                    $cols .= $this->cfg['tablequotes'] . $desc . $this->cfg['tablequotes'] . ", ";
                    $vals .= ":" . $desc . ", ";
                }
            }
            $cols = substr($cols, 0, -2) . ")";
            $vals = substr($vals, 0, -2) . ")";
            $sql_query = "INSERT INTO " . $this->cfg['tablequotes'] . $this->activetable . $this->cfg['tablequotes'] . " " . $cols . " VALUES " . $vals;
            $result = $this->preparedQuery($sql_query, $datadict);
            //echo('<p class="error">QUERY: '.$sql_query.'</p>');
            if ($result === false) {
                if ($this->debug)
                {
                    $err = $this->db->errorInfo();
                    $errmsg = $err[2];
                    $sqleng = $this->lite ? 'SQLite' : 'MySQL';
                    echo('<p class="error">' . $sqleng . ' Error while inserting values into table (' . $this->activetable . '): ' . $errmsg . '</p>');
                    echo('<p class="error">QUERY: ' . $sql_query . '</p>');
                }
                return false;
            }
            $newid = $this->db->lastInsertId();
            if ($newftscontent != '') {
                $this->updateFTSIndex($newid, $newftscontent);
            }
            return intval($newid);
        }
        return false;
    }

    function getID($query)
    {
        $result = $this->select($query);
        //var_dump($result);
        if ($result)
            return intval($result[0]['rowid']);
        return false;
    }

    // even more convenience..:
    function getEntryByID($id)
    {
        //$this->db->activetable = 'user';
        $entries = $this->select(array('rowid' => $id));
        if (empty($entries))
            return false;
        return $entries[0];
    }


    /// returns an array of strings describing the errors.. if everything is cool returns true..
    function everythingIsOK()
    {
        $previoustable = $this->activetable;
        $errors = array();
        $this->activetable = 'selftest';
        $this->tables['selftest'] = $this->lite ?
            array
            (
                'title', 'artist', 'releaseyear'
            )
            : array
            (
                'title' => 'varchar(255)',
                'artist' => 'varchar(255)',
                'releaseyear' => 'int(4)'
            );

        // table creation
        $this->createTable();
        if (!$this->hasTable('selftest')) {
            $errors[] = 'table creation failed';
        }

        // data insertion
        $insertid = $this->insert(array
        (
            'title' => 'Rock X-Mas',
            'artist' => 'Kelly Family',
            'releaseyear' => 1994
        ));
        //var_dump($insertid);
        $entries = $this->select(array('rowid' => $insertid));
        //var_dump($entry);
        if ($entries[0]['title'] != 'Rock X-Mas' or $entries[0]['artist'] != 'Kelly Family' or $entries[0]['releaseyear'] != 1994) {
            $errors[] = 'data insertion and/or querying failed';
        }

        // data updates
        $this->update(array('rowid' => $insertid), array('releaseyear' => 2010));
        $entries = $this->select(array('rowid' => $insertid));
        //var_dump($entries);
        if ($entries[0]['releaseyear'] != 2010) {
            $errors[] = 'data updating and/or querying failed';
        }

        // data deletion

        $this->delete(array('rowid' => $insertid));
        $entries = $this->select(array('rowid' => $insertid));
        if (!empty($entries)) {
            $errors[] = 'data deletion failed';
        }


        // table deletion

        $this->deleteTable('selftest');
        if ($this->hasTable('selftest')) {
            $errors[] = 'table deletion failed';
        }


        $this->activetable = $previoustable;

        if (empty($errors))
            return true;
        return $errors;
    }
}

?>
