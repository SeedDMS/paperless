<?php
$EXT_CONF['paperless'] = array(
	'title' => 'Paperless RestAPI',
	'description' => 'This extension adds additional rest api routes to make it behave like a paperless server. Just use the regular paperless apps, .e.g paperless mobile to access SeedDMS.',
	'disable' => false,
	'version' => '1.1.2',
	'releasedate' => '2023-05-11',
	'author' => array('name'=>'Uwe Steinmann', 'email'=>'uwe@steinmann.cx', 'company'=>'MMK GmbH'),
	'config' => array(
		'rootfolder' => array(
			'title'=>'Folder used as root folder',
			'help'=>'This is the folder used as the base folder. Documens not below this folder will not be shown by the papeerless mobile app. Uploaded documents will be saved into this folder, unless the dedicated upload folder is set.',
			'type'=>'select',
			'internal'=>'folders',
		),
		'usehomefolder' => array(
			'title'=>'Use the home folder as root folder',
			'type'=>'checkbox',
			'help'=>"Enable, if the user's home folder shall be used instead of the configured root folder.",
		),
		'uploadfolder' => array(
			'title'=>'Folder where new documents are uploaded',
			'help'=>'This is the folder where new documents will be uploaded by the paperless mobile app.',
			'type'=>'select',
			'internal'=>'folders',
		),
		'jwtsecret' => array(
			'title'=>'Secret for JSON Web Token',
			'help'=>'This is used for creating a secret which is needed to authenticate by token',
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
		'autocompletefield' => array(
			'title'=>'Field used for autocompletion',
			'help'=>'The terms in this field will be used when the autocomplete method is called.',
			'type'=>'select',
			'multiple'=>false,
			'options'=>['title'=>'Title', 'content'=>'Content'],
			'allow_empty'=>true,
		),
		'correspondentsattr' => array(
			'title'=>'Attribute for storing the correspondent',
			'help'=>'This attribute stores the correspondent of a document and must have a list of correspondents.',
			'type'=>'select',
			'internal'=>'attributedefinitions',
			'objtype'=>'2',
			'allow_empty'=>true,
		),
		'documenttypesattr' => array(
			'title'=>'Attribute for storing the document type',
			'help'=>'This attribute stores the document type of a document and must have a list of types.',
			'type'=>'select',
			'internal'=>'attributedefinitions',
			'objtype'=>'2',
			'allow_empty'=>true,
		),
	),
	'constraints' => array(
		'depends' => array('php' => '7.4.0-', 'seeddms' => array('5.1.31-5.1.99', '6.0.24-6.0.99')),
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
