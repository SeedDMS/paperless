<?php
$EXT_CONF['paperless'] = array(
	'title' => 'Extents the RestAPI with method for Paperless',
	'description' => 'This extension adds additional routes to make it behave like a paperless server',
	'disable' => false,
	'version' => '1.0.0',
	'releasedate' => '2022-12-07',
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
		'inboxtags' => array(
			'title'=>'Categories treated as inbox tag',
			'help'=>'These categories are marked as inbox tag when the list of tags is retrieved.',
			'type'=>'select',
			'internal'=>'categories',
		),
	),
	'constraints' => array(
		'depends' => array('php' => '5.6.40-', 'seeddms' => array('5.1.29-5.1.99', '6.0.22-6.0.99')),
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
