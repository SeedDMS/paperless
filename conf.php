<?php
$EXT_CONF['paperless'] = array(
	'title' => 'Extents the RestAPI with method for Paperless',
	'description' => 'This extension adds additional routes to make it behave like a paperless server',
	'disable' => false,
	'version' => '1.0.0',
	'releasedate' => '2023-01-05',
	'author' => array('name'=>'Uwe Steinmann', 'email'=>'uwe@steinmann.cx', 'company'=>'MMK GmbH'),
	'config' => array(
		'rootfolder' => array(
			'title'=>'Folder used as root folder',
			'help'=>'This is the folder used as the base folder. Uploaded documents will be saved in this folder and all documents listed will result in fulltext search below this folder.',
			'type'=>'select',
			'internal'=>'folders',
		),
		'usehomefolder' => array(
			'title'=>'Use the home folder as root folder',
			'type'=>'checkbox',
			'help'=>"Enable, if the user's home folder shall be used instead of the configured root folder.",
		),
		'jwtsecret' => array(
			'title'=>'Secret for JSON Web Token',
			'help'=>'This is used for creating a token which is needed to authenticate by token',
			'type'=>'password',
		),
		'tokenlivetime' => array(
			'title'=>'Days before token expires',
			'help'=>'If token based authentication is used, this is the time before the token expires. Once it has expired the user has to log in again.',
			'type'=>'numeric',
		),
		'inboxtags' => array(
			'title'=>'Categories treated as inbox tag',
			'help'=>'These categories are marked as inbox tag when the list of tags is retrieved.',
			'type'=>'select',
			'multiple'=>true,
			'internal'=>'categories',
			'allow_empty'=>true,
		),
	),
	'constraints' => array(
		'depends' => array('php' => '7.4.0-', 'seeddms' => array('5.1.29-5.1.99', '6.0.22-6.0.99')),
	),
	'icon' => 'icon.svg',
	'changelog' => 'changelog.md',
	'class' => array(
		'file' => 'class.paperless.php',
		'name' => 'SeedDMS_ExtPaperless'
	),
	'language' => array(
		'file' => 'lang.php',
	),
);
?>
