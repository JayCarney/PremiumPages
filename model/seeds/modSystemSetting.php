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
    ),
    array(
        'key'  		=>     'premiumpages.clients',
        'value'		=>     '',
        'xtype'		=>     'textfield',
        'namespace' => 'premiumpages',
        'area' 		=> 'premiumpages:default'
    ),
    array(
        'key'  		=>     'premiumpages.payment_gateway',
        'value'		=>     'stripe',
        'xtype'		=>     'textfield',
        'namespace' => 'premiumpages',
        'area' 		=> 'premiumpages:default'
    ),
    array(
        'key'  		=>     'premiumpages.stripe_secret_key',
        'value'		=>     '',
        'xtype'		=>     'textfield',
        'namespace' => 'premiumpages',
        'area' 		=> 'premiumpages:stripe'
    ),
    array(
        'key'  		=>     'premiumpages.stripe_public_key',
        'value'		=>     '',
        'xtype'		=>     'textfield',
        'namespace' => 'premiumpages',
        'area' 		=> 'premiumpages:stripe'
    )
);
/*EOF*/