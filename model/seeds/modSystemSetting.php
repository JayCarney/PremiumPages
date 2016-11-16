<?php
/*-----------------------------------------------------------------
 * Lexicon keys for System Settings follows this format:
 * Name: setting_ + $key
 * Description: setting_ + $key + _desc
 -----------------------------------------------------------------*/
return array(

    array(
        'key'  		=>     'premiumpages.templates',
		'value'		=>     '',
		'xtype'		=>     'textfield',
		'namespace' => 'premiumpages',
		'area' 		=> 'premiumpages:default'
    ),
    array(
        'key'  		=>     'premiumpages.admins',
        'value'		=>     '1',
        'xtype'		=>     'textfield',
        'namespace' => 'premiumpages',
        'area' 		=> 'premiumpages:default'
    )
);
/*EOF*/