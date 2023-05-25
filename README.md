# RemapDB

**Please note: this package has not been tested with PHP 8. It could also do with some refactoring. Both of these things are planned.**

A tool for fairly complex DB migration and remapping with configuration files.

You will need to setup a config and migration file. See the documentation below. 

**Contents**
- [Config file](#config)
	- [DEBUG](#config-debug)
	- [DELETE_EXISTING_ROWS](#config-delete)
	- [SOURCE_DB_HOST](#config-source-db-host)
	- [SOURCE_DB_USER](#config-source-db-user)
	- [SOURCE_DB_PASS](#config-source-db-pass)
	- [SOURCE_DB_NAME](#config-source-db-name)
	- [DEST_DB_HOST](#config-dest-db-host)
	- [DEST_DB_USER](#config-dest-db-user)
	- [DEST_DB_PASS](#config-dest-db-pass)
	- [DEST_DB_NAME](#config-dest-db-name)
- [Migration files](#migration)
	- [$tables](#migration-tables)
		- [Item key](#migration-tables-key)
		- [name](#migration-tables-name)
		- [conditions](#migration-tables-conditions)
	- [$mapping](#migration-mapping)
		- [table](#migration-mapping-table)
		- [control_table](#migration-mapping-control-table)
		- [columns](#migration-mapping-columns)
		- [condition](#migration-mapping-condition)
- [Usage](#usage)
	- [CLI](#usage-cli)
	- [VirtualHost](#usage-virtualhost)
- [To-do](#todo)
	
## <span id="config">Config file</span>
`config.php` contains your configuration and database details and will be the first thing you need to set up. Copy `config-template.php` to `config.php` and update the sample values.

### <span id="config-debug">`DEBUG`</span>
- Boolean
- This determines whether the script will do a dry-run or not.
- If `true` all the MySQL queries will be echoed to the page instead of being execued.
- If some values rely on results from previous queries (inserted ID, MySQL functions), there may be some Notices thrown but these should
not be present on the real run.
    - `5` is used as a dummy insert ID
	- Dummy MySQL function results have not been included in debug mode yet

### <span id="config-delete">`DELETE_EXISTING_ROWS`</span>
- Boolean
- This determines whether all the rows in a destination table are deleted before executing any insert queries
- This can be useful for rerunning migrations, but be careful!

### <span id="config-source-db-host">`SOURCE_DB_HOST`</span>
- String
- The host of the source database, EG `localhost`
### <span id="config-source-db-user">`SOURCE_DB_USER`</span>
- String
- The user of the source database
### <span id="config-source-db-pass">`SOURCE_DB_PASSWORD`</span>
- String
- The password of the source database
### <span id="config-source-db-name">`SOURCE_DB_NAME`</span>
- String
- The name of the source database
### <span id="config-dest-db-host">`DEST_DB_HOST`</span>
- String
- The host of the destination database, EG `localhost`
### <span id="config-dest-db-user">`DEST_DB_USER`</span>
- String
- The user of the destination database
### <span id="config-dest-db-pass">`DEST_DB_PASSWORD`</span>
- String
- The password of the destination database
### <span id="config-dest-db-name">`DEST_DB_NAME`</span>
- String
- The name of the destination database

## <span id="migration">Migration files</span>
You can name your migration file `{anything}.php` and store it in the `migrations` directory.

Each migration file (`migrations/\*.php`) require both of the following arrays to be defined within:
- [$tables](#migration-tables)
- [$mapping](#migration-mapping)

All the source data is pulled from the source tables (defined in `$tables`).

Then for each destination table defined in `$mapping`, the data is mapped across into the defined formats.

Check out `migrations/template.php` for an example.

### <span id="migration-tables">`$tables`</span>
An array of the source tables to be migrated. All tables following the first will be `LEFT JOIN`ed, which means for those the `conditions` value will become an `ON` clause instead of a `WHERE` clause.

Each item should be in the following format:

```
'TABLE_ALIAS' => array(
    'name' => 'TABLE_NAME',
	'conditions' => 'WHERE_CLAUSE'
)
```
#### <span id="migration-tables-key">Item key</span>
- Required
- The table alias, can be used in later `conditions` items

#### <span id="migration-tables-name">`name`</span>
- Required
- The table name

#### <span id="migration-tables-conditions">`conditions`</span>
- Required
- The WHERE clause to pull the data you need from the source tables. This can reference table aliases from current or previous array items.
- **OR** the key (table alias) of a previous item to copy the WHERE clause from. This will replace all references of the the given table alias with the current table alias so that the clause applies to this table instead of the referenced one.

### <span id="migration-mapping">`$mapping`</span>
An array of the details of the destination tables and how the data should be mapped across. No key needs to be given.

#### <span id="migration-mapping-table">`table`</span>
- Required
- The name of the destination table

#### <span id="migration-mapping-control-table">`control_table`</span>
- Required
- The name of the main table the data is being migrated from. 
- The number of rows from this table retrieved with the condtions defined in `$tables` is the number of times this `$mapping` item is looped over and a row inserted. 
- The data inserted into `table` does *not* need to be from `control_table`. See `columns` for more details.

#### <span id="migration-mapping-columns">`columns`</span>
- Required
- An array of the columns in the target table and what value should be used.
- If a column has a DEFAULT_VALUE then it is not necessary to define it in this array.
- For each column:
	- **Item key**: The target column name
	- Any of the follow values are accepted:
	    - Integer, float, boolean
	    - An array containing details of the source value, containing the following items:
			- `table`: the source table to use from either `$table` or `$mapping`. In the latter case `DEST_` must be prepended, and the table must have become before this one in the array.
			- `value`: the column name.
				- If `table` is in the source DB, this will use the row currently being looped over (as defined by `control_table`).
				- This works hierarchically so that even if `control_table` is one of the joined tables, `table` can still refer to the master table.
				- Likewise if a `DEST_` table is used, this will use the last row inserted into that table
		  		- This can also be a custom user function as described in 'String values' below. Any field paramaters will use the defined `table`.
		- String values
			- `'AUTO'`: use this on auto incremented columns. This is only necessary to include if the column is going to be referenced by another table later.
			- A MySQL function such as `'UNIX_TIMESTAMP()'` or `'UUID()'`
			- A custom user function can be used by calling the function with `'FUNC_'` prepended.
				- For example: `'FUNC_process_type(status, "example")'`
				- The function itself can then be added to the bottom of the file
				- 1 or more parameters can be used
				- The parameters can be:
					- A string
					- A boolean
					- An integer
					- A field name
				- The function should return the value to be inserted into the database
				- The function can use field parameters from the current table row if they come before this column in the array
					- In other words if you had a table called `people` which had columns called
					`title`, `first_name` and `surname`, defined previously in the array, and you wanted to combine them in a column called `full_name`
					  you could use `'FUNC_create_name(title, first_name, surname)'`
					- Defining a function within an `array('table': '', 'value': '')` as described above in Array Values uses the last row from that table instead
					- Combining fields parameters from multiple different tables can be done by using a parameter like so: `RAW_{table_alias}_{column_name}`, where `{table_alias}` is the alias defined in `$tables` and `{column_name}` is column_name.
						- For example: `'FUNC_add_totals(RAW_a_book_count, RAW_b_dvd_count)'`
			- Failing all of the above the string will be taken literally
#### <span id="migration-mapping-condition">`condition`</span>
- Optional
- Evaluates to a boolean to determine if the row should be added or not
- Experimental. Initially only tested with user functions and `RAW_` parameters
- I would advise not to use this yet
- For example: `FUNC_row_is_active(RAW_n_status)`


## <span id="usage">Usage</span>
Once you have your config and migration file set up you can run your script one of two ways:

### <span id="usage-cli">CLI</span>
Ensure you have given the directory and files sufficient permissions (I would suggest
755 for directories and 644 for files, or similar).

Then execute:  
`php run.php -m your_migration_name`

If you are in debug mode you may want to output stdout to a file:  
`php run.php -m your_migration_name > migration_debug.txt`

### <span id="usage-virtualhost">VirtualHost</span>
You may want to set this project up as a VirtualHost on your local Apache server, and then
you can go to http://yoursite.local/your_migration_name to run the script


## <span id="todo">To-do</span>
- [ ] Improve documentation, particularly `$mapping['columns']`
- [ ] Rewrite into OOP
- [ ] Integrate with composer
- [ ] Better error handling / logging
- [ ] Go through to-dos in code
- [ ] Remove legacy / unused / test code
- [ ] Implement better way to flag certain values instead of `FUNC_` etc.
