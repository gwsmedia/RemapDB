<?php

$tables = array(
	'st' => array(
		'name' => 'source_table',
		'conditions' => 'st.type = "page"'
	),
	'stl' => array(
		'name' => 'source_table_link',
		'conditions' => 'st.id = stl.id'
	)
);

$mapping = array(
	array(
		'table' => 'table1',
		'control_table' => 'source_table',
		'columns' => array(
				'id' => 'AUTO',
				'column1' => 'UUID()',
				'column2' => 12,
				'column3' => array(
                    'table' => 'source_table',
                    'value' => 'FUNC_combine_fields(title, subtitle, "abc", 10)'
                ),
				'column4' => 'FUNC_combine_fields(column2, RAW_st_title, "abc", 10)',
				'column5' => 'Hello world',
				'column6' => 2.5
		)
	),
	array(
		'table' => 'table2',
		'control_table' => 'source_table_link',
		'columns' => array(
			'column1' => array(
				'table' => 'DEST_table1',
				'value' => 'FUNC_combine_fields("This column ", "will say ", "Hello world: ", column5)'
			)
		)
	)
);

function combine_fields($a, $b, $c, $d) {
	return "$a $b $c $d";
}
