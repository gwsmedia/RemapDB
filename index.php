<?php

$start = microtime(true);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if(isset($_GET['migration'])) {
  $migration = $_GET['migration'];
  $migration = strtolower(str_replace('-', '_', $migration));
}

if(!isset($_GET['migration']) || !is_file("migrations/$migration.php")) {
  http_response_code(404);
  die('[404] That page could not be found.');
}

require_once('config.php');
require_once('inc/db.php');

global $source_db;
$source_db = new DB('mysql', SOURCE_DB_HOST, SOURCE_DB_NAME, SOURCE_DB_USER, SOURCE_DB_PASSWORD);
$dest_db = new DB('mysql', DEST_DB_HOST, DEST_DB_NAME, DEST_DB_USER, DEST_DB_PASSWORD);

//$a = $dest_db->query(" INSERT INTO commerce_product ( `type`, `uuid`, `langcode`) VALUES (1, 3, 3)");
//$a = $dest_db->query(" INSERT INTO commerce_number_pattern_sequence (`entity_id`, `store_id`, `number`, `generated`) VALUES ('def', 'def', 2, 3)");
//Logger::vdp($a, true);

require_once("migrations/$migration.php");

run_migration($source_db, $dest_db, $tables, $mapping);

echo "<br>Took " . (microtime(true) - $start) . " seconds";


function run_migration($source_db, $dest_db, $tables, $mapping) {
//  Logger::vdp(build_query($tables, $mapping), true);
  if(DELETE_EXISTING_ROWS) {
    foreach($mapping as $destTable) {
      $dest_db->query("DELETE FROM {$destTable['table']};");
      $dest_db->query("ALTER TABLE {$destTable['table']} AUTO_INCREMENT = 1;");
    }
  }

  global $destInsertVals;
  $insertedRows = array();
  $sourceData = $source_db->query(build_query($tables, $mapping));
  $tableAliases = collect_tables_aliases($tables);

  foreach($sourceData as $sourceRow) {
    $destInsertVals = array();
    foreach($mapping as $destTableData) {
      $values = array();
      $table = $destTableData['table'];
      $columns = $destTableData['columns'];
      $primaryKey = get_primary_key_value($sourceRow, $tableAliases, $destTableData);

      foreach($columns as $destCol => $sourceValue) {
        if(!evaluate_value_mapping($values, $destCol, $sourceValue, $sourceRow, $tables, $tableAliases)) continue 2;
      }

      if(count($values) > 0 && (!isset($insertedRows[$table]) || !in_array($primaryKey, $insertedRows[$table]))) {
        $destInsertVals[$table][] = $values;
        $colClause = implode(', ', array_keys($values));

        $valClause = '';
        $queryParams = array();
        foreach($values as $val) {
          if(preg_match('/^[A-Z_]+\(\)$/', $val)) {
            $valClause .= $val . ", ";
          } else {
            $valClause .= "?, ";
            $queryParams[] = $val;
          }
        }
        $valClause = substr($valClause, 0, -2);
        $query = "INSERT INTO {$table} ($colClause) VALUES ($valClause)";
        $id = $dest_db->query($query, $queryParams);
        if($id === false) {
          // TODO: Throw error
          echo "PROBLEM QUERY: " . $query;
          Logger::vdp($values, true);
          return;
        } else {
          $insertedRows[$table][] = $primaryKey;
        }

        $colsByVal = array_flip(array_filter($columns, 'is_string'));
        if (isset($colsByVal['AUTO'])) {
          $idCol = $colsByVal['AUTO'];
          $lastRowIndex = count($destInsertVals[$table]) - 1;
          $destInsertVals[$table][$lastRowIndex][$idCol] = $id;
        }
      }
    }
  }

  foreach($insertedRows as $table => $inserted) {
    echo "$table: inserted " . count($inserted) . " rows<br>";
  }
}


function get_primary_key_value($sourceRow, $tableAliases, $destTableData) {
  global $primaryKeys;
  $controlTable = $destTableData['control_table'];
  $alias = $tableAliases[$controlTable];
  $keyCols = $primaryKeys[$alias];
  $keyValues = array();
  foreach($keyCols as $keyCol) $keyValues[] = $sourceRow["{$alias}_{$keyCol}"];
  return implode("__", $keyValues);
}


function evaluate_if_user_func(&$values, $mappingValue, $destCol, $sourceRow, $alias = null) {
  if(strpos($mappingValue, 'FUNC_') === 0) {
    preg_match('/^FUNC_(.*?)\((.*?)\)$/', $mappingValue, $matches);

    if(is_null($alias)) {
      $colName = $matches[2];
      if(isset($values[$colName])) {
        $param = $values[$colName];
      } else {
        // TODO: THROW ERROR
      }
    } else {
      $colName = "{$alias}_{$matches[2]}";
      if (isset($sourceRow[$colName])) {
        $param = $sourceRow[$colName];
      } else {
        // TODO: THROW ERROR
      }
    }

    $values[$destCol] = call_user_func($matches[1], $param);
    return true;
  }

  return false;
}


function joined_row_exists($alias, $sourceRow, $tables) {
  $condColumn = $alias . '_' . get_table_condition_column($alias, $tables);
  $test = !is_null($sourceRow[$condColumn]);

  return $test;
}


function evaluate_value_mapping(&$values, $destCol, $sourceValue, $sourceRow, $tables, $tableAliases) {
  global $destInsertVals;
  if(is_array($sourceValue)) {
    if(preg_match('/^DEST_(.*)$/', $sourceValue['table'], $matches)) {
      $tableName = $matches[1];
      if(isset($destInsertVals[$tableName])) {
        $lastRowIndex = count($destInsertVals[$tableName]) - 1;
        $val = $destInsertVals[$tableName][$lastRowIndex][$sourceValue['value']];
      } else {
        // echo "ERROR: Dest col not found";
        // TODO: THROW ERROR ?
        return false;
      }
    } else {
      $alias = $tableAliases[$sourceValue['table']];
      if (joined_row_exists($alias, $sourceRow, $tables)) {
        if (!evaluate_if_user_func($values, $sourceValue['value'], $destCol, $sourceRow, $alias)) {
          $colName = "{$alias}_{$sourceValue['value']}";
          if (!isset($sourceRow[$colName])) {
            return true;
          } else {
            $val = $sourceRow[$colName];
          }
        }
      } else {
        return false;
      }
    }
  } else if(evaluate_if_user_func($values, $sourceValue, $destCol, $sourceRow)) {
    return true;
  } else if(strpos($sourceValue, ')') == strlen($sourceValue) - 1 || $sourceValue != 'AUTO') {
    $val = $sourceValue;
  } else return true;

  $values[$destCol] = $val;

  return true;
}


function collect_tables_aliases($tables) {
  return array_filter(array_combine(array_column($tables, 'name'), array_keys($tables)));
}


function prepare_columns_for_query($colsByTableAliases) {
  $colClauses = array();
  foreach($colsByTableAliases as $alias => $cols) {
    foreach($cols as $col) {
      $colClauses[] = "$alias.$col as {$alias}_{$col}";
    }
  }
  return $colClauses;
}


function collect_columns($tables, $mapping) {
  $colsToPrepare = array();
  $tableAliases = collect_tables_aliases($tables);
  $columns = prepare_columns_for_query(collect_primary_key_columns($mapping, $tableAliases));

  foreach(array_column($mapping, 'columns') as $destData) {
    foreach($destData as $sourceData) {
      if (is_array($sourceData) && isset($tableAliases[$sourceData['table']])) {
        preg_match('/^(?:FUNC_.+?\()?(.+?)\)?$/', trim($sourceData['value']), $matches);
        $alias = $tableAliases[$sourceData['table']];
        $colsToAdd = array($matches[1], get_table_condition_column($alias, $tables));

        foreach($colsToAdd as $colToAdd) {
          $colsToPrepare[$alias][] = $colToAdd;
        }
      }
    }
  }

  $columns = array_merge($columns, prepare_columns_for_query($colsToPrepare));
  $columns = array_unique($columns);

  return implode(', ', $columns);
}


// TODO: All control tables require primary key?
function collect_primary_key_columns($mapping, $tableAliases) {
  global $source_db, $primaryKeys;
  $keys = array();
  $controlTables = array_unique(array_column($mapping, 'control_table'));

  foreach($controlTables as $controlTable) {
    $keyData = $source_db->query("SHOW KEYS FROM $controlTable WHERE Key_name = 'PRIMARY'");
    $tableKeys = array_column($keyData, 'Column_name');
    $tableAlias = $tableAliases[$controlTable];
    $keys[$tableAlias] = $tableKeys;
  }

  $primaryKeys = $keys;
  return $keys;
}


function get_table_condition_column($alias, $tables) {
  $condition = evaluate_table_condition($alias, $tables);
  // TODO: Only ASCII column names allowed atm. See evaluate_table_condition() too
  if(preg_match('/(?:^|[^0-9,a-z,A-Z$_])'.$alias.'\.([^\s<>=!]+)/', $condition, $matches)) {
    return $matches[1];
  } else {
    // TODO: Throw error
  }
}


function evaluate_table_condition($alias, $tables) {
  $condition = $tables[$alias]['conditions'];
  if(preg_match('/^[0-9,a-z,A-Z$_]+$/', trim($condition))) {
    $condition = preg_replace('/([\^=<>\s])'.$condition.'./', '$1'.$alias.'.', $tables[$condition]['conditions']);
  }

  return $condition;
}


function build_query($tables, $mapping) {
  $columns = collect_columns($tables, $mapping);
  $query = "SELECT $columns FROM ";
  $i = 1;

  foreach($tables as $alias => $tableData) {
    $tableClause = "{$tableData['name']} $alias";
    $tableData['conditions'] = evaluate_table_condition($alias, $tables);

    if($i++ > 1) {
      $query .= "LEFT JOIN $tableClause ON {$tableData['conditions']} ";
    } else {
      $query .= "$tableClause ";
      $whereClause = "WHERE {$tableData['conditions']}";
    }
  }

  $query .= $whereClause;
  return $query;
}
