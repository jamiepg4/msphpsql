--TEST--
Fetch Scrollabale Data Test
--DESCRIPTION--
Verifies data retrieval with scrollable result sets.
--ENV--
PHPT_EXEC=true
--SKIPIF--
<?php require('skipif.inc'); ?>
--FILE--
<?php
include 'MsCommon.inc';

function FetchRow($noRows)
{
    include 'MsSetup.inc';

    $testName = "Fetch - Scrollable";
    StartTest($testName);

    Setup();
    if (! IsWindows())
        $conn1 = ConnectUTF8();
    else 
        $conn1 = Connect();
    CreateTable($conn1, $tableName);

    $noRowsInserted = InsertRows($conn1, $tableName, $noRows);

    $actual = null;
    $expected = null;

    // fetch array (to retrieve reference values)
    $stmt1 = SelectFromTable($conn1, $tableName);
    $numFields = sqlsrv_num_fields($stmt1);
    $expected = FetchArray($stmt1, $noRowsInserted, $numFields);
    sqlsrv_free_stmt($stmt1);

    $query = "SELECT * FROM [$tableName]";

    // fetch object - FORWARD cursor
    $options = array('Scrollable' => SQLSRV_CURSOR_FORWARD);
    $stmt2 = SelectQueryEx($conn1, $query, $options);
    $actual = FetchObject($stmt2, $noRowsInserted, $numFields, SQLSRV_SCROLL_NEXT);
    sqlsrv_free_stmt($stmt2);
    CheckData($noRowsInserted, $numFields, $actual, $expected);

    // fetch object - STATIC cursor
    $options = array('Scrollable' => SQLSRV_CURSOR_STATIC);
    $stmt2 = SelectQueryEx($conn1, $query, $options);
    $actual = FetchObject($stmt2, $noRowsInserted, $numFields, SQLSRV_SCROLL_RELATIVE);
    sqlsrv_free_stmt($stmt2);
    CheckData($noRowsInserted, $numFields, $actual, $expected);

    // fetch object - DYNAMIC cursor
    $options = array('Scrollable' => SQLSRV_CURSOR_DYNAMIC);
    $stmt2 = SelectQueryEx($conn1, $query, $options);
    $actual = FetchObject($stmt2, $noRowsInserted, $numFields, SQLSRV_SCROLL_ABSOLUTE);
    sqlsrv_free_stmt($stmt2);
    CheckData($noRowsInserted, $numFields, $actual, $expected);

    // fetch object - KEYSET cursor
    $options = array('Scrollable' => SQLSRV_CURSOR_KEYSET);
    $stmt2 = SelectQueryEx($conn1, $query, $options);
    $actual = FetchObject($stmt2, $noRowsInserted, $numFields, SQLSRV_SCROLL_PRIOR, 0);
    sqlsrv_free_stmt($stmt2);
    CheckData($noRowsInserted, $numFields, $actual, $expected);

    DropTable($conn1, $tableName);	
    
    sqlsrv_close($conn1);

    EndTest($testName);	
}


function FetchArray($stmt, $rows, $fields)
{
    $values = array();
    for ($i = 0; $i < $rows; $i++)
    {
        $row = sqlsrv_fetch_array($stmt);
        if ($row === false)
        {
            FatalError("Row $i is missing");
        }
        $values[$i] = $row;
    }
    return ($values);
}


function FetchObject($stmt, $rows, $fields, $dir)
{
    Trace("\tRetrieving $rows objects with $fields fields each ...\n");
    $values = array();
    for ($i = 0; $i < $rows; $i++)
    {
        if ($dir == SQLSRV_SCROLL_NEXT)
        {
            $obj = sqlsrv_fetch_object($stmt, null, null, $dir);
        }
        else if ($dir == SQLSRV_SCROLL_PRIOR)
        {
            if ($i == 0)
            {
                $obj = sqlsrv_fetch_object($stmt, null, null, SQLSRV_SCROLL_LAST);
            }
            else
            {
                $obj = sqlsrv_fetch_object($stmt, null, null, $dir);
            }
        }
        else if ($dir == SQLSRV_SCROLL_ABSOLUTE)
        {
            $obj = sqlsrv_fetch_object($stmt, null, null, $dir, $i);
        }
        else if ($dir == SQLSRV_SCROLL_RELATIVE)
        {
            $obj = sqlsrv_fetch_object($stmt, null, null, $dir, 1);
        }
        if ($obj === false)
        {
            FatalError("Row $i is missing");
        }
        if ($dir == SQLSRV_SCROLL_PRIOR)
        {
            $values[$rows - $i - 1] = $obj;
        }
        else
        {
            $values[$i] = $obj;
        }
    }
    return ($values);
}

function CheckData($rows, $fields, $actualValues, $expectedValues)
{
    if (($actualValues != null) && ($expectedValues != null))
    {
        for ($i = 0; $i < $rows; $i++)
        {
            for ($j = 0; $j < $fields; $j++)
            {
                $colName = GetColName($j + 1);
                $actual = $actualValues[$i]->$colName;
                $expected = $expectedValues[$i][$colName];
                if ($actual != $expected)
                {
                    die("Data corruption on row ".($i + 1)." column ".($j + 1).": $expected => $actual");
                }
            }
        }
    }
}


//--------------------------------------------------------------------
// Repro
//
//--------------------------------------------------------------------
function Repro()
{
    try
    {
        FetchRow(10);
    }
    catch (Exception $e)
    {
        echo $e->getMessage();
    }
}

Repro();

?>
--EXPECT--
Test "Fetch - Scrollable" completed successfully.
