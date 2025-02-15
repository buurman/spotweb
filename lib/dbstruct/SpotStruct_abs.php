<?php

abstract class SpotStruct_abs
{
    protected $_dbcon;

    public function __construct(dbeng_abs $dbEng)
    {
        $this->_dbcon = $dbEng;
    }

    // __construct

    /*
     * Factory for dbstruct
     */
    public static function factory($dbEngine, $dbCon)
    {
        // Instantieeer een struct object
        switch ($dbEngine) {
            case 'mysql':
            case 'pdo_mysql': return new SpotStruct_mysql($dbCon); break;

            case 'pdo_pgsql': return new SpotStruct_pgsql($dbCon); break;

            case 'pdo_sqlite': return new SpotStruct_sqlite($dbCon); break;

            default: throw new Exception("Unknown database engine '".$dbEngine."'");
        } // switch
    }

    // factory

    /*
     * Optimize / analyze (database specific) a number of hightraffic
     * tables.
     * This function does not modify any schema or data
     */
    abstract public function analyze();

    /*
     * This will reset the following table's: spots, spotsfull, spotsposted,
     * spotstatelist, commentsfull, commentsposted, commentsxover, moderatedringbuffer,
     * reportsposted, reportsxover, usenetstate, cache
     */
    abstract public function resetdb();

    /*
     * Clearcache / clear the database table cache.
     */
    abstract public function clearcache();

    /*
     * Converts a 'spotweb' internal datatype to a
     * database specific datatype
     */
    abstract public function swDtToNative($colType);

    /*
     * Converts a boolean type to database native representation
     */
    abstract public function bool2dt($b);

    /*
     * Converts a database native datatype to a spotweb native
     * datatype
     */
    abstract public function nativeDtToSw($colInfo);

    /*
     * Adds an index, but first checks if the index doesn't
     * exist already.
     *
     * $idxType can be either 'UNIQUE', '' or 'FULLTEXT'
     */
    abstract public function addIndex($idxname, $idxType, $tablename, $colList);

    /* drops an index if it exists */
    abstract public function dropIndex($idxname, $tablename);

    /* adds a column if the column doesn't exist yet */
    abstract public function addColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation);

    /* alters a column - does not check if the column doesn't adhere to the given definition */
    abstract public function modifyColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation, $what);

    /* drops a column (dbms allowing) */
    abstract public function dropColumn($colName, $tablename);

    /* checks if an index exists */
    abstract public function indexExists($idxname, $tablename);

    /* checks if a column exists */
    abstract public function columnExists($tablename, $colname);

    /* checks if a table exists */
    abstract public function tableExists($tablename);

    /* checks if a fts text index exists */
    abstract public function ftsExists($ftsname, $tablename, $colList);

    /* creates a full text index */
    abstract public function createFts($ftsname, $tablename, $colList);

    /* drops a fulltext index */
    abstract public function dropFts($ftsname, $tablename, $colList);

    /* returns FTS info  */
    abstract public function getFtsInfo($ftsname, $tablename, $colList);

    /* creates an empty table with onl an ID field. Collation should be either UTF8 or ASCII */
    abstract public function createTable($tablename, $collation);

    /* creates a foreign key constraint */
    abstract public function addForeignKey($tablename, $colname, $reftable, $refcolumn, $action);

    /* drops a foreign key constraint */
    abstract public function dropForeignKey($tablename, $colname, $reftable, $refcolumn, $action);

    /* alters a storage engine (only mysql knows something about store engines, but well  :P ) */
    abstract public function alterStorageEngine($tablename, $engine);

    /* drop a table */
    abstract public function dropTable($tablename);

    /* rename a table */
    abstract public function renameTable($tablename, $newTableName);

    /* Returns in a fixed format, index information */
    abstract public function getIndexInfo($idxname, $tablename);

    /* Returns in a fixed format, column information */
    abstract public function getColumnInfo($tablename, $colname);

    /* Checks if a index structure is the same as the requested one. Recreats if not */
    public function validateIndex($idxname, $type, $tablename, $colList)
    {
        echo "\tValidating index ".$idxname.PHP_EOL;

        if (!$this->compareIndex($idxname, $type, $tablename, $colList)) {
            // Drop the index
            if ($this->indexExists($idxname, $tablename)) {
                echo "\t\tDropping index ".$idxname.PHP_EOL;
                $this->dropIndex($idxname, $tablename);
            } // if

            echo "\t\tAdding index ".$idxname.PHP_EOL;

            // and recreate the index
            $this->addIndex($idxname, $type, $tablename, $colList);
        } // if
    }

    // validateIndex

    /* Checks if a fulltext structure matches the required one. Recreates if not */
    public function validateFts($ftsname, $tablename, $colList)
    {
        echo "\tValidating FTS ".$ftsname.PHP_EOL;

        if (!$this->compareFts($ftsname, $tablename, $colList)) {
            // Drop de FTS
            if ($this->ftsExists($ftsname, $tablename, $colList)) {
                echo "\t\tDropping FTS ".$ftsname.PHP_EOL;
                $this->dropFts($ftsname, $tablename, $colList);
            } // if

            echo "\t\tAdding FTS ".$ftsname.PHP_EOL;

            // and recreate the index
            $this->createFts($ftsname, $tablename, $colList);
        } // if
    }

    // validateFts

    /* Checks if a column definition is the same as the requested one. Recreats if not */
    public function validateColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation)
    {
        echo "\tValidating ".$tablename.'('.$colName.')'.PHP_EOL;

        $compResult = $this->compareColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation);
        if ($compResult !== true) {
            if ($this->columnExists($tablename, $colName)) {
                echo "\t\tModifying column ".$colName.' ('.$compResult.') on '.$tablename.PHP_EOL;
                $this->modifyColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation, $compResult);
            } else {
                echo "\t\tAdding column ".$colName.'('.$colType.') to '.$tablename.PHP_EOL;
                $this->addColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation);
            } // else
        } // if
    }

    // validateColumn

    /* Compares a columns' definition */
    public function compareColumn($colName, $tablename, $colType, $colDefault, $notNull, $collation)
    {
        // Retrieve the column information
        $q = $this->getColumnInfo($tablename, $colName);

        // if column is not found at all, it's easy
        if (empty($q)) {
            return false;
        } // if

        // Check data type
        if (strtolower($q['COLUMN_TYPE']) != strtolower($this->swDtToNative($colType))) {
            //var_dump($q);
            //var_dump($colType);
            //var_dump($this->swDtToNative($colType));
            //die();
            return 'type';
        } // if

        // check default value
        if (is_null($q['COLUMN_DEFAULT'])) {
            $q['COLUMN_DEFAULT'] = 'NULL';
        }
        if (is_null($colDefault)) {
            $colDefault = 'NULL';
        }
        if (strtolower($q['COLUMN_DEFAULT']) != strtolower($colDefault)) {
            return 'default';
        } // if

        // check the NOT NULL setting
        if (strtolower($q['NOTNULL']) != $notNull) {
            return 'not null';
        } // if

        // check COLLATION_NAME
        if (($q['COLLATION_NAME'] != null) && (strtolower($q['COLLATION_NAME']) != $collation)) {
            return 'charset';
        } // if

        return true;
    }

    // compareColumn

    /* Compares an index with the requested structure */
    public function compareIndex($idxname, $type, $tablename, $colList)
    {
        // Retrieve index information
        $q = $this->getIndexInfo($idxname, $tablename);

        // If the amount of columns in the index don't match...
        if (count($q) != count($colList)) {
            return false;
        } // if

        /*
         * We iterate throuhg each column and compare the index order,
         * and properties of each index.
         */
        for ($i = 0; $i < count($colList); $i++) {
            $same = true;
            $qUpper = array_change_key_case($q[$i], CASE_UPPER);
            if ($colList[$i] != $qUpper['COLUMN_NAME']) {
                $same = false;
            } // if

            if ($same) {
                switch (strtolower($type)) {
                    case 'fulltext': $same = (strtolower($q[$i]['index_type']) == 'fulltext'); break;
                    case 'unique': $same = ($qUpper['NON_UNIQUE'] == 0); break;
                    case '': $same = (strtolower($q[$i]['index_type']) != 'fulltext') && ($qUpper['NON_UNIQUE'] == 1);
                } // switch
            } // if

            if (!$same) {
                //var_dump($q[$i]);
                //var_dump($type);
                //var_dump($colList);
                //die();
                return false;
            } // if
        } // for

        return true;
    }

    // compareIndex

    /* Compares an FTS index with the desired definition */
    public function compareFts($ftsname, $tablename, $colList)
    {
        // Retrieves FTS information
        $q = $this->getFtsInfo($ftsname, $tablename, $colList);

        // If the amount of columns in the index don't match...
        if (count($q) != count($colList)) {
            return false;
        } // if

        /*
         * We iterate throuhg each column and compare the index order,
         * and properties of each index.
         */
        for ($i = 0; $i < count($colList); $i++) {
            $qUpper = array_change_key_case($q[$i], CASE_UPPER);
            if ($colList[$i + 1] != $qUpper['COLUMN_NAME']) {
                return false;
            } // if
        } // for

        return true;
    }

    // compareFts

    public function updateSchema()
    {
        // Drop any older (not used anymore) FTS indexes on the spots full table
        $this->dropIndex('idx_spotsfull_fts_1', 'spotsfull');
        $this->dropIndex('idx_spotsfull_fts_2', 'spotsfull');
        $this->dropIndex('idx_spotsfull_fts_3', 'spotsfull');
        $this->dropIndex('idx_spotsfull_2', 'spotsfull'); // Index on userid
        $this->dropIndex('idx_nntp_2', 'nntp');
        $this->dropIndex('idx_nntp_3', 'nntp');
        $this->dropIndex('idx_spotteridblacklist_3', 'spotteridblacklist');

        // Drop any non-valid FK relations
        $this->dropForeignKey('spotsfull', 'messageid', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->dropForeignKey('spotstatelist', 'messageid', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->dropForeignKey('commentsposted', 'inreplyto', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->dropForeignKey('commentsposted', 'messageid', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->dropForeignKey('commentsxover', 'messageid', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->dropForeignKey('commentsfull', 'messageid', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->dropForeignKey('reportsposted', 'inreplyto', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->dropForeignKey('reportsposted', 'messageid', 'spots', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->dropForeignKey('sessions', 'userid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');

        //#############################################################################################
        // Cleaning up data ###########################################################################
        //#############################################################################################
        if (($this instanceof SpotStruct_mysql) && (false)) {
            echo 'Cleaning up old data...'.PHP_EOL;
            if ($this->tableExists('usersettings') && $this->tableExists('users')) {
                $this->_dbcon->rawExec('DELETE usersettings FROM usersettings LEFT JOIN users ON usersettings.userid=users.id WHERE users.id IS NULL');
            } // if
            if ($this->tableExists('sessions') && $this->tableExists('users')) {
                $this->_dbcon->rawExec('DELETE sessions FROM sessions LEFT JOIN users ON sessions.userid=users.id WHERE users.id IS NULL');
            } // if
            if ($this->tableExists('spotstatelist') && $this->tableExists('users')) {
                $this->_dbcon->rawExec('DELETE spotstatelist FROM spotstatelist LEFT JOIN users ON spotstatelist.ouruserid=users.id WHERE users.id IS NULL');
            } // if
            if ($this->tableExists('usergroups') && $this->tableExists('users')) {
                $this->_dbcon->rawExec('DELETE usergroups FROM usergroups LEFT JOIN users ON usergroups.userid=users.id WHERE users.id IS NULL');
            } // if
            if ($this->tableExists('usergroups') && $this->tableExists('securitygroups')) {
                $this->_dbcon->rawExec('DELETE usergroups FROM usergroups LEFT JOIN securitygroups ON usergroups.groupid=securitygroups.id WHERE securitygroups.id IS NULL');
            } // if
            if ($this->tableExists('grouppermissions') && $this->tableExists('securitygroups')) {
                $this->_dbcon->rawExec('DELETE grouppermissions FROM grouppermissions LEFT JOIN securitygroups ON grouppermissions.groupid=securitygroups.id WHERE securitygroups.id IS NULL');
            } // if
            if ($this->tableExists('commentsposted') && $this->tableExists('users')) {
                $this->_dbcon->rawExec('DELETE commentsposted FROM commentsposted LEFT JOIN users ON commentsposted.ouruserid=users.id WHERE users.id IS NULL');
            } // if
            if ($this->tableExists('commentsposted') && $this->tableExists('spots')) {
                $this->_dbcon->rawExec('DELETE commentsposted FROM commentsposted LEFT JOIN spots ON commentsposted.inreplyto=spots.messageid WHERE spots.messageid IS NULL');
            } // if
            if ($this->tableExists('commentsfull') && $this->tableExists('commentsxover')) {
                $this->_dbcon->rawExec('DELETE commentsfull FROM commentsfull LEFT JOIN commentsxover ON commentsfull.messageid=commentsxover.messageid WHERE commentsxover.messageid IS NULL');
            } // if
            if ($this->tableExists('spotsfull') && $this->tableExists('spots')) {
                $this->_dbcon->rawExec('DELETE spotsfull FROM spotsfull LEFT JOIN spots ON spotsfull.messageid=spots.messageid WHERE spots.messageid IS NULL');
            } // if
            if ($this->tableExists('spotstatelist') && $this->tableExists('spots')) {
                $this->_dbcon->rawExec('DELETE spotstatelist FROM spotstatelist LEFT JOIN spots ON spotstatelist.messageid=spots.messageid WHERE spots.messageid IS NULL');
            } // if
            if ($this->tableExists('reportsposted') && $this->tableExists('users')) {
                $this->_dbcon->rawExec('DELETE reportsposted FROM reportsposted LEFT JOIN users ON reportsposted.ouruserid=users.id WHERE users.id IS NULL');
            } // if
            if ($this->tableExists('reportsposted') && $this->tableExists('spots')) {
                $this->_dbcon->rawExec('DELETE reportsposted FROM reportsposted LEFT JOIN spots ON reportsposted.inreplyto=spots.messageid WHERE spots.messageid IS NULL');
            } // if
        } // if

        // ---- spots table ---- #
        $this->createTable('spots', 'utf8');
        $this->validateColumn('messageid', 'spots', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('poster', 'spots', 'VARCHAR(128)', null, false, 'utf8');
        $this->validateColumn('title', 'spots', 'VARCHAR(128)', null, false, 'utf8');
        $this->validateColumn('tag', 'spots', 'VARCHAR(128)', null, false, 'utf8');
        $this->validateColumn('category', 'spots', 'INTEGER', null, false, '');
        $this->validateColumn('subcata', 'spots', 'VARCHAR(64)', null, false, 'ascii');
        $this->validateColumn('subcatb', 'spots', 'VARCHAR(64)', null, false, 'ascii');
        $this->validateColumn('subcatc', 'spots', 'VARCHAR(64)', null, false, 'ascii');
        $this->validateColumn('subcatd', 'spots', 'VARCHAR(64)', null, false, 'ascii');
        $this->validateColumn('subcatz', 'spots', 'VARCHAR(64)', null, false, 'ascii');
        $this->validateColumn('stamp', 'spots', 'INTEGER UNSIGNED', null, false, '');
        $this->validateColumn('reversestamp', 'spots', 'INTEGER', '0', false, '');
        $this->validateColumn('filesize', 'spots', 'BIGINTEGER UNSIGNED', '0', true, '');
        $this->validateColumn('moderated', 'spots', 'BOOLEAN', null, false, '');
        $this->validateColumn('commentcount', 'spots', 'INTEGER', '0', false, '');
        $this->validateColumn('spotrating', 'spots', 'INTEGER', '0', false, '');
        $this->validateColumn('reportcount', 'spots', 'INTEGER', '0', false, '');
        $this->validateColumn('spotterid', 'spots', 'VARCHAR(32)', null, false, 'ascii_bin');
        $this->validateColumn('editstamp', 'spots', 'INTEGER UNSIGNED', null, false, '');
        $this->validateColumn('editor', 'spots', 'VARCHAR(128)', null, false, 'utf8');
        $this->alterStorageEngine('spots', 'MyISAM');

        // ---- spotsfull table ---- #
        $this->createTable('spotsfull', 'utf8');
        $this->validateColumn('messageid', 'spotsfull', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('verified', 'spotsfull', 'BOOLEAN', null, false, '');
        $this->validateColumn('usersignature', 'spotsfull', 'VARCHAR(255)', null, false, 'ascii');
        $this->validateColumn('userkey', 'spotsfull', 'VARCHAR(512)', null, false, 'ascii');
        $this->validateColumn('xmlsignature', 'spotsfull', 'VARCHAR(255)', null, false, 'ascii');
        $this->validateColumn('fullxml', 'spotsfull', 'TEXT', null, false, 'utf8');
        $this->alterStorageEngine('spotsfull', 'InnoDB');

        // ---- uspstate table ---- #
        $this->createTable('usenetstate', 'utf8');
        $this->validateColumn('infotype', 'usenetstate', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('curarticlenr', 'usenetstate', 'INTEGER', '0', false, '');
        $this->validateColumn('curmessageid', 'usenetstate', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('lastretrieved', 'usenetstate', 'INTEGER', '0', false, '');
        $this->validateColumn('nowrunning', 'usenetstate', 'INTEGER', '0', false, '');
        $this->alterStorageEngine('usenetstate', 'InnoDB');

        // ---- commentsxover table ---- #
        $this->createTable('commentsxover', 'ascii');
        $this->validateColumn('messageid', 'commentsxover', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('nntpref', 'commentsxover', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('spotrating', 'commentsxover', 'INTEGER', '0', false, '');
        $this->validateColumn('moderated', 'commentsxover', 'BOOLEAN', null, false, '');
        $this->validateColumn('stamp', 'commentsxover', 'INTEGER UNSIGNED', null, false, '');
        $this->alterStorageEngine('commentsxover', 'InnoDB');

        // ---- reportsxover table ---- #
        $this->createTable('reportsxover', 'ascii');
        $this->validateColumn('messageid', 'reportsxover', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('fromhdr', 'reportsxover', 'VARCHAR(256)', "''", true, 'utf8');
        $this->validateColumn('keyword', 'reportsxover', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('nntpref', 'reportsxover', 'VARCHAR(128)', "''", true, 'ascii');
        $this->alterStorageEngine('reportsxover', 'InnoDB');

        // ---- spotstatelist table ---- #
        $this->createTable('spotstatelist', 'ascii');
        $this->validateColumn('messageid', 'spotstatelist', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('ouruserid', 'spotstatelist', 'INTEGER', '0', false, '');
        $this->validateColumn('download', 'spotstatelist', 'INTEGER', null, false, '');
        $this->validateColumn('watch', 'spotstatelist', 'INTEGER', null, false, '');
        $this->validateColumn('seen', 'spotstatelist', 'INTEGER', null, false, '');
        $this->alterStorageEngine('spotstatelist', 'InnoDB');

        // ---- commentsfull table ---- #
        $this->createTable('commentsfull', 'ascii');
        $this->validateColumn('messageid', 'commentsfull', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('fromhdr', 'commentsfull', 'VARCHAR(128)', null, false, 'utf8');
        $this->validateColumn('stamp', 'commentsfull', 'INTEGER', null, false, '');
        $this->validateColumn('usersignature', 'commentsfull', 'VARCHAR(255)', null, false, 'ascii');
        $this->validateColumn('userkey', 'commentsfull', 'VARCHAR(512)', null, false, 'ascii');
        $this->validateColumn('spotterid', 'commentsfull', 'VARCHAR(32)', null, false, 'ascii_bin');
        $this->validateColumn('hashcash', 'commentsfull', 'VARCHAR(255)', null, false, 'ascii');
        $this->validateColumn('body', 'commentsfull', 'TEXT', null, false, 'utf8');
        $this->validateColumn('verified', 'commentsfull', 'BOOLEAN', null, false, '');
        $this->validateColumn('avatar', 'commentsfull', 'TEXT', null, false, 'ascii');
        $this->alterStorageEngine('commentsfull', 'InnoDB');

        // ---- settings table ---- #
        $this->createTable('settings', 'ascii');
        $this->validateColumn('name', 'settings', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('value', 'settings', 'TEXT', null, false, 'utf8');
        $this->validateColumn('serialized', 'settings', 'boolean', null, false, '');
        $this->alterStorageEngine('settings', 'InnoDB');

        // ---- commentsposted table ---- #
        $this->createTable('commentsposted', 'ascii');
        $this->validateColumn('ouruserid', 'commentsposted', 'INTEGER', '0', true, '');
        $this->validateColumn('messageid', 'commentsposted', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('inreplyto', 'commentsposted', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('randompart', 'commentsposted', 'VARCHAR(32)', "''", true, 'ascii');
        $this->validateColumn('rating', 'commentsposted', 'INTEGER', 0, true, '');
        $this->validateColumn('body', 'commentsposted', 'TEXT', null, false, 'utf8');
        $this->validateColumn('stamp', 'commentsposted', 'INTEGER', '0', true, '');
        $this->alterStorageEngine('commentsposted', 'InnoDB');

        // ---- spotsposted table ---- #
        $this->createTable('spotsposted', 'utf8');
        $this->validateColumn('messageid', 'spotsposted', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('ouruserid', 'spotsposted', 'INTEGER', '0', true, '');
        $this->validateColumn('stamp', 'spotsposted', 'INTEGER UNSIGNED', null, false, '');
        $this->validateColumn('title', 'spotsposted', 'VARCHAR(128)', null, false, 'utf8');
        $this->validateColumn('tag', 'spotsposted', 'VARCHAR(128)', null, false, 'utf8');
        $this->validateColumn('category', 'spotsposted', 'INTEGER', null, false, '');
        $this->validateColumn('subcats', 'spotsposted', 'VARCHAR(255)', null, false, 'ascii');
        $this->validateColumn('filesize', 'spotsposted', 'BIGINTEGER UNSIGNED', '0', true, '');
        $this->validateColumn('fullxml', 'spotsposted', 'TEXT', null, false, 'utf8');
        $this->alterStorageEngine('spotsposted', 'InnoDB');

        // ---- reportsposted table ---- #
        $this->createTable('reportsposted', 'ascii');
        $this->validateColumn('ouruserid', 'reportsposted', 'INTEGER', '0', true, '');
        $this->validateColumn('messageid', 'reportsposted', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('inreplyto', 'reportsposted', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('randompart', 'reportsposted', 'VARCHAR(32)', "''", true, 'ascii');
        $this->validateColumn('body', 'reportsposted', 'TEXT', null, false, 'utf8');
        $this->validateColumn('stamp', 'reportsposted', 'INTEGER', '0', true, '');
        $this->alterStorageEngine('reportsposted', 'InnoDB');

        // ---- usersettings table ---- #
        $this->createTable('usersettings', 'utf8');
        $this->validateColumn('userid', 'usersettings', 'INTEGER', '0', true, '');
        $this->validateColumn('privatekey', 'usersettings', 'TEXT', null, false, 'ascii');
        $this->validateColumn('publickey', 'usersettings', 'TEXT', null, false, 'ascii');
        $this->validateColumn('avatar', 'usersettings', 'TEXT', null, false, 'ascii');
        $this->validateColumn('otherprefs', 'usersettings', 'TEXT', null, false, 'utf8');
        $this->alterStorageEngine('usersettings', 'InnoDB');

        // ---- users table ---- #
        $this->createTable('users', 'utf8');
        $this->validateColumn('username', 'users', 'VARCHAR(128)', "''", true, 'utf8');
        $this->validateColumn('firstname', 'users', 'VARCHAR(128)', "''", true, 'utf8');
        $this->validateColumn('passhash', 'users', 'VARCHAR(40)', "''", true, 'ascii');
        $this->validateColumn('lastname', 'users', 'VARCHAR(128)', "''", true, 'utf8');
        $this->validateColumn('mail', 'users', 'VARCHAR(128)', "''", true, 'utf8');
        $this->validateColumn('apikey', 'users', 'VARCHAR(32)', "''", true, 'ascii');
        $this->validateColumn('lastlogin', 'users', 'INTEGER', '0', true, '');
        $this->validateColumn('lastvisit', 'users', 'INTEGER', '0', true, '');
        $this->validateColumn('lastread', 'users', 'INTEGER', '0', true, '');
        $this->validateColumn('lastapiusage', 'users', 'INTEGER', '0', true, '');
        $this->validateColumn('deleted', 'users', 'BOOLEAN', $this->bool2dt(false), true, '');
        $this->alterStorageEngine('users', 'InnoDB');

        // ---- sessions ---- #
        $this->createTable('sessions', 'ascii');
        $this->validateColumn('sessionid', 'sessions', 'VARCHAR(128)', null, false, 'ascii');
        $this->validateColumn('userid', 'sessions', 'INTEGER', null, false, '');
        $this->validateColumn('hitcount', 'sessions', 'INTEGER', null, false, '');
        $this->validateColumn('lasthit', 'sessions', 'INTEGER', null, false, '');
        $this->validateColumn('ipaddr', 'sessions', 'VARCHAR(45)', "''", true, 'ascii');
        $this->validateColumn('devicetype', 'sessions', 'VARCHAR(8)', "''", true, 'ascii');
        $this->alterStorageEngine('sessions', 'MyISAM');

        // ---- securitygroups ----
        $this->createTable('securitygroups', 'ascii');
        $this->validateColumn('name', 'securitygroups', 'VARCHAR(128)', null, false, 'ascii');
        $this->alterStorageEngine('securitygroups', 'InnoDB');

        // ---- grouppermissions ----
        $this->createTable('grouppermissions', 'ascii');
        $this->validateColumn('groupid', 'grouppermissions', 'INTEGER', '0', true, '');
        $this->validateColumn('permissionid', 'grouppermissions', 'INTEGER', '0', true, '');
        $this->validateColumn('objectid', 'grouppermissions', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('deny', 'grouppermissions', 'BOOLEAN', $this->bool2dt(false), true, '');
        $this->alterStorageEngine('grouppermissions', 'InnoDB');

        // ---- usergroups ----
        $this->createTable('usergroups', 'ascii');
        $this->validateColumn('userid', 'usergroups', 'INTEGER', '0', true, '');
        $this->validateColumn('groupid', 'usergroups', 'INTEGER', '0', true, '');
        $this->validateColumn('prio', 'usergroups', 'INTEGER', '1', true, '');
        $this->alterStorageEngine('usergroups', 'InnoDB');

        // ---- notifications ----
        $this->createTable('notifications', 'ascii');
        $this->validateColumn('userid', 'notifications', 'INTEGER', '0', true, '');
        $this->validateColumn('stamp', 'notifications', 'INTEGER', '0', true, '');
        $this->validateColumn('objectid', 'notifications', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('type', 'notifications', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('title', 'notifications', 'VARCHAR(128)', "''", true, 'utf8');
        $this->validateColumn('body', 'notifications', 'TEXT', null, false, 'utf8');
        $this->validateColumn('sent', 'notifications', 'BOOLEAN', $this->bool2dt(false), true, '');
        $this->alterStorageEngine('notifications', 'InnoDB');

        // ---- filters ----
        $this->createTable('filters', 'utf8');
        $this->validateColumn('userid', 'filters', 'INTEGER', '0', true, '');
        $this->validateColumn('filtertype', 'filters', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('title', 'filters', 'VARCHAR(128)', "''", true, 'utf8');
        $this->validateColumn('icon', 'filters', 'VARCHAR(128)', "''", true, 'utf8');
        $this->validateColumn('torder', 'filters', 'INTEGER', '0', true, '');
        $this->validateColumn('tparent', 'filters', 'INTEGER', '0', true, '');
        $this->validateColumn('tree', 'filters', 'TEXT', null, false, 'ascii');
        $this->validateColumn('valuelist', 'filters', 'TEXT', null, false, 'utf8');
        $this->validateColumn('sorton', 'filters', 'VARCHAR(128)', null, false, 'ascii');
        $this->validateColumn('sortorder', 'filters', 'VARCHAR(128)', null, false, 'ascii');
        $this->validateColumn('enablenotify', 'filters', 'BOOLEAN', $this->bool2dt(false), true, '');
        $this->alterStorageEngine('filters', 'InnoDB');

        // ---- filtercounts ----
        $this->createTable('filtercounts', 'utf8');
        $this->validateColumn('userid', 'filtercounts', 'INTEGER', '0', true, '');
        $this->validateColumn('filterhash', 'filtercounts', 'VARCHAR(40)', "''", true, 'ascii');
        $this->validateColumn('currentspotcount', 'filtercounts', 'INTEGER', '0', true, '');
        $this->validateColumn('lastvisitspotcount', 'filtercounts', 'INTEGER', '0', true, '');
        $this->validateColumn('lastupdate', 'filtercounts', 'INTEGER', '0', true, '');
        $this->alterStorageEngine('filtercounts', 'InnoDB');

        // ---- spotteridblacklist table ---- #
        $this->createTable('spotteridblacklist', 'utf8');
        $this->validateColumn('spotterid', 'spotteridblacklist', 'VARCHAR(32)', null, false, 'ascii_bin');
        $this->validateColumn('ouruserid', 'spotteridblacklist', 'INTEGER', '0', true, '');
        $this->validateColumn('idtype', 'spotteridblacklist', 'INTEGER', '0', true, '');
        $this->validateColumn('origin', 'spotteridblacklist', 'VARCHAR(255)', null, false, 'ascii');
        $this->validateColumn('doubled', 'spotteridblacklist', 'BOOLEAN', $this->bool2dt(false), true, '');
        $this->alterStorageEngine('spotteridblacklist', 'InnoDB');

        // Update old blacklisttable
        $schemaVer = $this->_dbcon->singleQuery("SELECT value FROM settings WHERE name = 'schemaversion'", []);
        if ($schemaVer < 0.56) {
            $this->_dbcon->rawExec('UPDATE spotteridblacklist SET idtype = 1');
        }

        // Drop old cache -- converting is too error prone
        if ($schemaVer > 0.00 && ($schemaVer < 0.60)) {
            /*
             * If the current cache table is too large, we basically recommend the user
             * to run the seperate cache-migration script. If it contains less than 501 records
             *  we just drop it. This number is chosen arbitrarily.
             */
            $cacheCount = $this->_dbcon->singleQuery('SELECT COUNT(1) FROM cache WHERE content IS NOT NULL', []);
            if ($cacheCount > 500) {
                throw new CacheMustBeMigratedException();
            } else {
                if ($this->columnExists('cache', 'content')) {
                    $this->dropTable('cache');
                } // if
            } // else
        } // if

        if ($schemaVer > 0.60 && ($schemaVer < 0.63)) {
            throw new CacheMustBeMigrated2Exception();
        } // new storage format

        // Convert incorrect stored book subtitles
        if ($schemaVer > 0.00 && ($schemaVer < 0.65)) {
            /*
             * A lot of posters use the old category mapping of Spotweb for epubs, but this
             * is wrong and we don't want to propagate this error. Hence we fix the categories,
             * in the database. The Spot parsers also fixup the categories.
             */
            echo 'Performing mass book category mapping update '.PHP_EOL;
            $this->_dbcon->exec("UPDATE spots SET subcatc = REPLACE(REPLACE(REPLACE(subcatc, 'c2|', 'c11|'), 'c2|', 'c11|'), 'c6|', 'c11|')
                                    WHERE
                                            subcatz = 'z2|'
                                            AND ((subcatc LIKE '%c1|%') OR (subcatc LIKE '%c2|%') OR ('subcatc' LIKE '%c6|%'))
                                            AND (NOT subcatc LIKE '%c11|%')
                                ");

            $this->_dbcon->exec("UPDATE spots SET subcatc = REPLACE(REPLACE(REPLACE(subcatc, 'c3|', 'c10|'), 'c4|', 'c10|'), 'c7|', 'c10|')
                                    WHERE
                                            subcatz = 'z2|'
                                            AND ((subcatc LIKE '%c3|%') OR (subcatc LIKE '%c4|%') OR ('subcatc' LIKE '%c7|%'))
                                            AND (NOT subcatc LIKE '%c10|%')
                                ");
        } // if

        /*
         * In version 0.65 we made a misttake in inserting categories, so delete the last 10.000 spots
         * and let them be retrieved again
         */
        if ($schemaVer == 0.65) {
            $maxSpotsId = $this->_dbcon->singleQuery('SELECT MAX(id) FROM spots');
            $this->_dbcon->rawExec('DELETE FROM spots WHERE id > '.(int) ($maxSpotsId - 15000));

            // invalidate usenetstate so we re-retrieve the spots
            $this->_dbcon->rawExec("UPDATE usenetstate SET curmessageid = '' WHERE infotype = 'Spots'");
        } // if

        // ---- cache table ---- #
        $this->createTable('cache', 'ascii');
        $this->validateColumn('resourceid', 'cache', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('cachetype', 'cache', 'INTEGER', '0', true, '');
        $this->validateColumn('stamp', 'cache', 'INTEGER', '0', true, '');
        $this->validateColumn('metadata', 'cache', 'TEXT', null, false, 'ascii');
        $this->validateColumn('ttl', 'cache', 'INTEGER', '0', true, '');
        $this->alterStorageEngine('cache', 'InnoDB');

        // ---- moderated ring buffer table ---- #
        $this->createTable('moderatedringbuffer', 'ascii');
        $this->validateColumn('messageid', 'moderatedringbuffer', 'VARCHAR(128)', "''", true, 'ascii');
        $this->alterStorageEngine('moderatedringbuffer', 'InnoDB');

        // ---- permaudit table ---- #
        $this->createTable('permaudit', 'ascii');
        $this->validateColumn('stamp', 'permaudit', 'INTEGER', '0', true, '');
        $this->validateColumn('userid', 'permaudit', 'INTEGER', '0', true, '');
        $this->validateColumn('permissionid', 'permaudit', 'INTEGER', '0', true, '');
        $this->validateColumn('objectid', 'permaudit', 'VARCHAR(128)', "''", true, 'ascii');
        $this->validateColumn('result', 'permaudit', 'BOOLEAN', $this->bool2dt(true), true, '');
        $this->validateColumn('ipaddr', 'permaudit', 'VARCHAR(45)', "''", true, 'ascii');
        $this->alterStorageEngine('permaudit', 'InnoDB');

        //#############################################################################################
        //## Remove old sessions ######################################################################
        //#############################################################################################
        // Remove sessions with only one hit, older than one day
        //
        $this->_dbcon->rawExec('DELETE FROM sessions WHERE lasthit < '.(time() - (60 * 60 * 60 * 24)).' AND hitcount = 1');
        //
        // and remove sessions older than 180 days
        //
        $this->_dbcon->rawExec('DELETE FROM sessions WHERE lasthit < '.(time() - (60 * 60 * 60 * 24) * 180));

        //#############################################################################################
        //## deprecation of old Spotweb versions ######################################################
        //#############################################################################################
        if ($schemaVer > 0.00 && ($schemaVer < 0.51)) {
            if ($schemaVer < 0.30) {
                throw new SpotwebCannotBeUpgradedTooOldException('da6ba29071c49ae88823cccfefc39375b37e9bee');
            } // if

            if (($schemaVer < 0.34) && ($this->tableExists('spottexts'))) {
                throw new SpotwebCannotBeUpgradedTooOldException('48bc94a63f94959f9fe6b2372b312e35a4d09997');
            } // if

            if ($schemaVer < 0.48) {
                throw new SpotwebCannotBeUpgradedTooOldException('4c874ec24a28d5ee81218271dc584a858f6916af');
            } // if

            if (($schemaVer < 0.51) && ($this->tableExists('cachetmp'))) {
                throw new SpotwebCannotBeUpgradedTooOldException('4c874ec24a28d5ee81218271dc584a858f6916af');
            } // if
        } // if

        // drop PostGreSQL FTS indices
        if ($schemaVer < 0.68) {
            if ($this instanceof SpotStruct_pgsql) {
                $this->dropFts('idx_fts_spots', 'spots', [1 => 'poster',
                    2                                       => 'title',
                    3                                       => 'tag', ]);
            }
        }

        // Create several indexes
        // ---- Indexes on spots -----
        $this->validateIndex('idx_spots_1', 'UNIQUE', 'spots', ['messageid']);
        $this->validateIndex('idx_spots_2', '', 'spots', ['stamp']);
        $this->validateIndex('idx_spots_3', '', 'spots', ['reversestamp']);
        $this->validateIndex('idx_spots_4', '', 'spots', ['category', 'subcata', 'subcatb', 'subcatc', 'subcatd', 'subcatz']);
        $this->validateIndex('idx_spots_5', '', 'spots', ['spotterid']);
        $this->validateFts('idx_fts_spots', 'spots', [1 => 'poster',
            2                                           => 'title',
            3                                           => 'tag', ]);

        // ---- Indexes on nntp ----
        $this->validateIndex('idx_usenetstate_1', 'UNIQUE', 'usenetstate', ['infotype']);

        // ---- Indexes on spotsfull ----
        $this->validateIndex('idx_spotsfull_1', 'UNIQUE', 'spotsfull', ['messageid']);

        // ---- Indexes on commentsfull ----
        $this->validateIndex('idx_commentsfull_1', 'UNIQUE', 'commentsfull', ['messageid']);

        // ---- Indexes on commentsxover ----
        $this->validateIndex('idx_commentsxover_1', 'UNIQUE', 'commentsxover', ['messageid']);
        $this->validateIndex('idx_commentsxover_2', '', 'commentsxover', ['nntpref']);

        // ---- Indexes on reportsxover ----
        $this->validateIndex('idx_reportsxover_1', 'UNIQUE', 'reportsxover', ['messageid']);
        $this->validateIndex('idx_reportsxover_2', '', 'reportsxover', ['nntpref']);

        // ---- Indexes on reportsposted ----
        $this->validateIndex('idx_reportsposted_1', 'UNIQUE', 'reportsposted', ['messageid']);
        $this->validateIndex('idx_reportsposted_2', 'UNIQUE', 'reportsposted', ['inreplyto', 'ouruserid']);
        $this->validateIndex('idx_reportspostedrel_1', '', 'reportsposted', ['ouruserid']);

        // ---- Indexes on commentsposted ----
        $this->validateIndex('idx_commentsposted_1', 'UNIQUE', 'commentsposted', ['messageid']);
        $this->validateIndex('idx_commentspostedrel_1', '', 'commentsposted', ['ouruserid']);

        // ---- Indexes on spotsposted ----
        $this->validateIndex('idx_spotsposted_1', 'UNIQUE', 'spotsposted', ['messageid']);
        $this->validateIndex('idx_spotspostedrel_1', '', 'spotsposted', ['ouruserid']);

        // ---- Indexes on settings ----
        $this->validateIndex('idx_settings_1', 'UNIQUE', 'settings', ['name']);

        // ---- Indexes on usersettings ----
        $this->validateIndex('idx_usersettings_1', 'UNIQUE', 'usersettings', ['userid']);

        // ---- Indexes on users ----
        $this->validateIndex('idx_users_1', 'UNIQUE', 'users', ['username']);
        $this->validateIndex('idx_users_2', 'UNIQUE', 'users', ['mail']);
        $this->validateIndex('idx_users_3', '', 'users', ['deleted']);
        $this->validateIndex('idx_users_4', 'UNIQUE', 'users', ['apikey']);

        // ---- Indexes on sessions
        $this->validateIndex('idx_sessions_1', 'UNIQUE', 'sessions', ['sessionid']);
        $this->validateIndex('idx_sessions_2', '', 'sessions', ['lasthit']);
        $this->validateIndex('idx_sessions_3', '', 'sessions', ['sessionid', 'userid']);
        $this->validateIndex('idx_sessionsrel_1', '', 'sessions', ['userid']);

        // ---- Indexes on spotstatelist ----
        $this->validateIndex('idx_spotstatelist_1', 'UNIQUE', 'spotstatelist', ['messageid', 'ouruserid']);
        $this->validateIndex('idx_spotstatelistrel_1', '', 'spotstatelist', ['ouruserid']);

        // ---- Indexes on securitygroups ----
        $this->validateIndex('idx_securitygroups_1', 'UNIQUE', 'securitygroups', ['name']);

        // ---- Indexes on grouppermissions ----
        $this->validateIndex('idx_grouppermissions_1', 'UNIQUE', 'grouppermissions', ['groupid', 'permissionid', 'objectid']);

        // ---- Indexes on usergroups ----
        $this->validateIndex('idx_usergroups_1', 'UNIQUE', 'usergroups', ['userid', 'groupid']);
        $this->validateIndex('idx_usergroupsrel_1', '', 'usergroups', ['groupid']);

        // ---- Indexes on notifications ----
        $this->validateIndex('idx_notifications_1', '', 'notifications', ['userid']);
        $this->validateIndex('idx_notifications_2', '', 'notifications', ['sent']);

        // ---- Indexes on filters ----
        $this->validateIndex('idx_filters_1', '', 'filters', ['userid', 'filtertype', 'tparent', 'torder']);

        // ---- Indexes on filtercounts ----
        $this->validateIndex('idx_filtercounts_1', 'UNIQUE', 'filtercounts', ['userid', 'filterhash']);

        // ---- Indexes on spotteridblacklist ----
        $this->validateIndex('idx_spotteridblacklist_1', 'UNIQUE', 'spotteridblacklist', ['spotterid', 'ouruserid', 'idtype']);
        $this->validateIndex('idx_spotteridblacklist_2', '', 'spotteridblacklist', ['ouruserid']);

        // ---- Indexes on cache ----
        $this->validateIndex('idx_cache_1', 'UNIQUE', 'cache', ['resourceid', 'cachetype']);
        $this->validateIndex('idx_cache_2', '', 'cache', ['cachetype', 'stamp']);

        // ---- Indexes on ring buffer of moderated messageids ----
        $this->validateIndex('idx_moderatedringbuffer_1', 'UNIQUE', 'moderatedringbuffer', ['messageid']);

        // Create foreign keys where possible
        $this->addForeignKey('usersettings', 'userid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->addForeignKey('spotstatelist', 'ouruserid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->addForeignKey('usergroups', 'userid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->addForeignKey('usergroups', 'groupid', 'securitygroups', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->addForeignKey('grouppermissions', 'groupid', 'securitygroups', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->addForeignKey('commentsfull', 'messageid', 'commentsxover', 'messageid', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->addForeignKey('notifications', 'userid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->addForeignKey('commentsposted', 'ouruserid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->addForeignKey('reportsposted', 'ouruserid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->addForeignKey('filters', 'userid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');
        $this->addForeignKey('spotsposted', 'ouruserid', 'users', 'id', 'ON DELETE CASCADE ON UPDATE CASCADE');

        //#############################################################################################
        // Drop old columns ###########################################################################
        //#############################################################################################
        $this->dropColumn('filesize', 'spotsfull');
        $this->dropColumn('userid', 'spotsfull');
        $this->dropColumn('userid', 'spotteridblacklist');
        $this->dropColumn('userid', 'commentsfull');
        $this->dropColumn('serialized', 'cache');
        $this->dropColumn('content', 'cache');

        //#############################################################################################
        // Drop old tables ############################################################################
        //#############################################################################################
        $this->dropTable('nntp');
        $this->dropTable('debuglog');

        // update the database with this specific schemaversion
        $this->_dbcon->rawExec("DELETE FROM settings WHERE name = 'schemaversion'", []);
        $this->_dbcon->rawExec("INSERT INTO settings(name, value) VALUES('schemaversion', '".SPOTDB_SCHEMA_VERSION."')");
    }

    // updateSchema
} // class
