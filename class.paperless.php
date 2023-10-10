<?php
/**
 * Paperless extension
 *
 * @author     Uwe Steinmann <uwe@steinmann.cx>
 * @package    SeedDMS
 * @subpackage paperless
 * @license    GPL3
 * @copyright  Copyright (C) 2023 Uwe Steinmann
 */
class SeedDMS_ExtPaperless extends SeedDMS_ExtBase { /* {{{ */

	/**
	 * Initialization
	 *
	 * Use this method to do some initialization like setting up the hooks
	 * You have access to the following global variables:
	 * $GLOBALS['settings'] : current global configuration
	 * $GLOBALS['settings']->_extensions['example'] : configuration of this extension
	 * $GLOBALS['LANG'] : the language array with translations for all languages
	 * $GLOBALS['SEEDDMS_HOOKS'] : all hooks added so far
	 */
	function init() { /* {{{ */
		$GLOBALS['SEEDDMS_HOOKS']['initRestAPI'][] = new SeedDMS_ExtPaperless_RestAPI;
		$GLOBALS['SEEDDMS_HOOKS']['view']['settings'][] = new SeedDMS_ExtPaperless_Settings;
	} /* }}} */

	function main() { /* {{{ */
	} /* }}} */
} /* }}} */

/**
 * Class containing method for checking the configuration
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage	paperless
 */
class SeedDMS_ExtPaperless_Settings { /* {{{ */
	/**
	 * Hook for checking the configuration
	 *
	 * This hook is not called if the extension isn't enabled
	 */
	function checkConfig($view, $extname, $conf) {
		$settings = $view->getParam('settings');
		if($extname != 'paperless')
			return;
		if(empty($settings->_extensions['paperless']['jwtsecret'])) {
			echo $view->contentSubHeading(getMLText($extname));
			echo $view->warningMsg(getMLText('paperless_jwtsecret_not_set'));
		}
	}
} /* }}} */

use Psr\Container\ContainerInterface;

class SeedDMS_ExtPaperless_RestAPI_Controller { /* {{{ */
	protected $container;

	static public function mb_word_count($string, $mode = MB_CASE_TITLE, $characters = null) { /* {{{ */
		$string = mb_convert_case($string, $mode, "UTF-8");
		$addChars = $characters ? preg_quote($characters, '~') : "";
//		$regEx = "~[^\p{L}".$addChars."]+~u";
		$regEx = "~[^\p{L}".$addChars."]+~u";
		return array_count_values(preg_split($regEx,$string, -1, PREG_SPLIT_NO_EMPTY));
	} /* }}} */

	protected function __getDocumentData($document, $truncate_content=false) { /* {{{ */
		$fulltextservice = $this->container->fulltextservice;
		$settings = $this->container->config;
		$conversionmgr = $this->container->conversionmgr;

		$lc = $document->getLatestContent();
		$dms = $document->getDMS();

		$content = '';
		/* The plain text can either be created by the text previewer
		 * or taken from the fulltext index. The text from the fulltext index
		 * does not have stop words anymore if a stop words file was
		 * configured during indexing.
		 */
		if(1) {
			$txtpreviewer = new SeedDMS_Preview_TxtPreviewer($settings->_cacheDir, $settings->_cmdTimeout, $settings->_enableXsendfile);
			$txtpreviewer->setConversionMgr($conversionmgr);
			if(!$txtpreviewer->hasPreview($lc))
				$txtpreviewer->createPreview($lc);

			$file = $txtpreviewer->getFileName($lc).".txt";
			if(file_exists($file))
				$content = file_get_contents($file);
		} else {
			$index = $fulltextservice->Indexer();
			if($index) {
				$lucenesearch = $fulltextservice->Search();
				if($searchhit = $lucenesearch->getDocument($document->getID())) {
					$idoc = $searchhit->getDocument();
					try {
						if($truncate_content)
							$content = htmlspecialchars(mb_strimwidth($idoc->getFieldValue('content'), 0, 500, '...'));
						else
							$content = htmlspecialchars($idoc->getFieldValue('content'));
					} catch (Exception $e) {
					}
				}
			}
		}

		$cats = $document->getCategories();
		$tags = array();
		foreach($cats as $cat)
			$tags[] = (int) $cat->getId();

		$correspondent = null;
		if(!empty($settings->_extensions['paperless']['correspondentsattr']) && $attrdef = $dms->getAttributeDefinition($settings->_extensions['paperless']['correspondentsattr'])) {
			if($attr = $document->getAttribute($attrdef)) {
				$valueset = $attrdef->getValueSetAsArray();
				$i = array_search($attr->getValue(), $valueset);
				if($i !== false)
					$correspondent = $i+1;
			}
		}

		$documenttype = null;
		if(!empty($settings->_extensions['paperless']['documenttypesattr']) && $attrdef = $dms->getAttributeDefinition($settings->_extensions['paperless']['documenttypesattr'])) {
			if($attr = $document->getAttribute($attrdef)) {
				$valueset = $attrdef->getValueSetAsArray();
				$i = array_search($attr->getValue(), $valueset);
				if($i !== false)
					$documenttype = $i+1;
			}
		}
		$data = array(
			'id'=>(int)$document->getId(),
			'correspondent'=>$correspondent,
			'document_type'=>$documenttype,
			'storage_path'=>null,
			'title'=>$document->getName(),
			'content'=>$content,
			'tags'=>$tags,
			'checksum'=>$lc->getChecksum(),
			'created'=>date('Y-m-d\TH:i:s+02:00', $document->getDate()),
			'created_date'=>date('Y-m-d', $document->getDate()),
			'modified'=>date('Y-m-d\TH:i:s+02:00', $lc->getDate()),
			'added'=>date('Y-m-d\TH:i:s+02:00', $document->getDate()),
			'archive_serial_number'=> (int) $document->getId(), // was null
			'original_file_name'=>$lc->getOriginalFileName(),
			'archived_file_name'=>$lc->getOriginalFileName()
		);
		return $data;
	} /* }}} */

	public function getContrastColor($hexcolor) { /* {{{ */
		$r = hexdec(substr($hexcolor, 1, 2));
		$g = hexdec(substr($hexcolor, 3, 2));
		$b = hexdec(substr($hexcolor, 5, 2));
		$yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
		return ($yiq >= 148) ? '000000' : 'ffffff';
	} /* }}} */

	protected function __getCategoryData($category, $inboxtags) { /* {{{ */
		$color = substr(md5($category->getName()), 0, 6);
		$data = [
			'id'=>(int)$category->getId(),
			'slug'=>strtolower($category->getName()),
			'name'=>$category->getName(),
			'color'=>'#'.$color, //'#50b02c',
			'text_color'=>'#'.$this->getContrastColor($color),
			'match'=>'',
			'matching_algorithm'=>6,
			'is_insensitive'=>true,
			'is_inbox_tag'=>in_array($category->getId(), $inboxtags),
			'document_count'=>0
		];
		return $data;
	} /* }}} */

	// constructor receives container instance
	public function __construct(ContainerInterface $container) { /* {{{ */
		$this->container = $container;
	} /* }}} */

	function api($request, $response) { /* {{{ */
		$data = array(
			'correspondents'=>$request->getUri().'correspondents/',
			'document_types'=>$request->getUri().'document_types/',
			'documents'=>$request->getUri().'documents/',
			'logs'=>$request->getUri().'logs/',
			'tags'=>$request->getUri().'tags/',
			'saved_views'=>$request->getUri().'saved_views/',
			'storage_paths'=>$request->getUri().'storage_paths/',
			'tasks'=>$request->getUri().'tasks/',
			'mail_accounts'=>$request->getUri().'mail_accounts/',
			'mail_rule'=>$request->getUri().'mail_rule/',
		);

		return $response->withJson($data, 200);
	} /* }}} */

	function token($request, $response) { /* {{{ */
		$settings = $this->container->config;
		$authenticator = $this->container->authenticator;
		$logger = $this->container->logger;

		$data = $request->getParsedBody();
		if(empty($data['username'])) {
			$body = $request->getBody();
			$data = json_decode($body, true);
		}
		if($data) {
			$userobj = $authenticator->authenticate($data['username'], $data['password']);
			if(!$userobj)
				return $response->withJson(array('non_field_errors'=>['Unable to log in with provided credentials.']), 403);
			else {
				if(!empty($settings->_extensions['paperless']['jwtsecret'])) {
					$token = new SeedDMS_JwtToken($settings->_extensions['paperless']['jwtsecret']);
					if(!empty($settings->_extensions['paperless']['tokenlivetime']))
						$days = (int) $settings->_extensions['paperless']['tokenlivetime'];
					else
						$days = 365;
					if(!$tokenstr = $token->jwtEncode($userobj->getId().':'.(time()+$days*84600))) {
						return $response->withStatus(403);
					}
					return $response->withJson(array('token'=>$tokenstr), 200);
				} else {
					return $response->withJson(array('token'=>$settings->_apiKey), 200);
				}
			}
		}
		return $response->withStatus(403);
	} /* }}} */

	function tags($request, $response) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$fulltextservice = $this->container->fulltextservice;
		$logger = $this->container->logger;

		if(false === ($categories = $dms->getDocumentCategories())) {
			return $response->withJson(array('results'=>null), 500);
		}

		if(!empty($settings->_extensions['paperless']['usehomefolder'])) {
			if(!($startfolder = $dms->getFolder((int) $userobj->getHomeFolder())))
				$startfolder = $dms->getFolder($settings->_rootFolderID);
		} elseif(!isset($settings->_extensions['paperless']['rootfolder']) || !($startfolder = $dms->getFolder($settings->_extensions['paperless']['rootfolder'])))
			$startfolder = $dms->getFolder($settings->_rootFolderID);

		$index = $fulltextservice->Indexer();
		if($index) {
			$lucenesearch = $fulltextservice->Search();
			$searchresult = $lucenesearch->search('', array('record_type'=>['document'], 'status'=>[2], 'user'=>[$userobj->getLogin()], 'startFolder'=>$startfolder, 'rootFolder'=>$startfolder), array('limit'=>20), array());
			if($searchresult === false) {
				return $response->withStatus(500);
			} else {
				$recs = array();
				$facets = $searchresult['facets'];
			}
		}

		$data = [];
		$inboxtags = [];
		if(!empty($settings->_extensions['paperless']['inboxtags']))
		 	$inboxtags = explode(',', $settings->_extensions['paperless']['inboxtags']);
		foreach($categories as $category) {
			$tmp = $this->__getCategoryData($category, $inboxtags);
			if(isset($facets['category'][$category->getName()]))
				$tmp['document_count'] = (int) $facets['category'][$category->getName()];
			$data[] = $tmp;
		}
		return $response->withJson(array('count'=>count($data), 'next'=>null, 'previous'=>null, 'results'=>$data), 200);
	} /* }}} */

	function post_tag($request, $response) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$logger = $this->container->logger;
		$fulltextservice = $this->container->fulltextservice;
		$notifier = $this->container->notifier;

		if(!$userobj->isAdmin())
			return $response->withStatus(404);

		$data = $request->getParsedBody();
		$oldcat = $dms->getDocumentCategoryByName($data['name']);
		if (is_object($oldcat)) {
			return $response->withJson(getMLText('paperless_tag_already_exists'), 400);
		}
		$newCategory = $dms->addDocumentCategory($data['name']);
		if (!$newCategory)
			return $response->withJson(getMLText('paperless_could_not_create_tag'), 400);

		return $response->withJson($this->__getCategoryData($newCategory, []), 201);
	} /* }}} */

	function delete_tag($request, $response, $args) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$conversionmgr = $this->container->conversionmgr;
		$logger = $this->container->logger;
		$notifier = $this->container->notifier;
		$fulltextservice = $this->container->fulltextservice;

		if(!$userobj->isAdmin())
			return $response->withStatus(404);

		if (!isset($args['id']) || !$args['id'])
			return $response->withStatus(404);

		$cat = $dms->getDocumentCategory($args['id']);
		if($cat) {
			$documents = $cat->getDocumentsByCategory(10);
			if($documents) {
				$logger->log('Will not remove because cat has documents', PEAR_LOG_WARNING);
				return $response->withStatus(400);
			} else {
				$logger->log('remove categorie', PEAR_LOG_INFO);
				$cat->remove();
			}
		}
		return $response->withStatus(204);
	} /* }}} */

	/* FIXME: This method does not take the document status into account
	 * It might be better to create a facet from the correspondant field
	 * instead of calling getStatistics()
	 */
	function correspondents($request, $response) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$logger = $this->container->logger;

		//file_put_contents("php://stdout", var_dump($request, true));

		$correspondents = array();
		if(!empty($settings->_extensions['paperless']['correspondentsattr']) && $attrdef = $dms->getAttributeDefinition($settings->_extensions['paperless']['correspondentsattr'])) {
			$res = $attrdef->getStatistics(30);
//			print_r($res['frequencies']);
			$valueset = $attrdef->getValueSetAsArray();
			foreach($valueset as $id=>$val) {
				$c = isset($res['frequencies']['document'][md5($val)]) ? $res['frequencies']['document'][md5($val)]['c'] : 0;
				$correspondents[] = array(
					'id'=>$id+1,
					'slug'=>strtolower($val),
					'name'=>$val,
					'match'=>'',
					'matching_algorithm'=>1,
					'is_insensitive'=>true,
					'document_count'=>$c,
					'last_correspondence'=>null
				);
			}
		}
		return $response->withJson(array('count'=>count($correspondents), 'next'=>null, 'previous'=>null, 'results'=>$correspondents), 200);
	} /* }}} */

	function document_types($request, $response) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$logger = $this->container->logger;

		$types = array();
		if(!empty($settings->_extensions['paperless']['documenttypesattr']) && $attrdef = $dms->getAttributeDefinition($settings->_extensions['paperless']['documenttypesattr'])) {
			$res = $attrdef->getStatistics(30);
			$valueset = $attrdef->getValueSetAsArray();
			foreach($valueset as $id=>$val) {
				$c = isset($res['frequencies']['document'][md5($val)]) ? $res['frequencies']['document'][md5($val)]['c'] : 0;
				$types[] = array(
					'id'=>$id+1,
					'slug'=>strtolower($val),
					'name'=>$val,
					'match'=>'',
					'matching_algorithm'=>1,
					'is_insensitive'=>true,
					'document_count'=>$c
				);
			}
		}
		return $response->withJson(array('count'=>count($types), 'next'=>null, 'previous'=>null, 'results'=>$types), 200);
	} /* }}} */

	function saved_views($request, $response) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$logger = $this->container->logger;

		require_once('class.PaperlessView.php');

		$views = SeedDMS_PaperlessView::getAllInstances($userobj, $dms);

		$data = [];
		foreach($views as $view) {
			$tmp = $view->getView();
			$data[] = $tmp;
		}
		return $response->withJson(array('count'=>count($data), 'next'=>null, 'previous'=>null, 'results'=>$data), 200);
	} /* }}} */

	function post_saved_views($request, $response) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$logger = $this->container->logger;
		$fulltextservice = $this->container->fulltextservice;
		$notifier = $this->container->notifier;

		require_once('class.PaperlessView.php');

		$data = $request->getParsedBody();
		$logger->log(var_export($data, true), PEAR_LOG_DEBUG);

		$view = new SeedDMS_PaperlessView($data['id'], $userobj, $data);
		$view->setDMS($dms);
		if($newview = $view->save()) {
//		$logger->log(var_export($newview, true), PEAR_LOG_DEBUG);
			return $response->withJson($newview->getView(), 201);
		} else {
			return $response->withJson('', 501);
		}

	} /* }}} */

	function delete_saved_views($request, $response, $args) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$conversionmgr = $this->container->conversionmgr;
		$logger = $this->container->logger;
		$notifier = $this->container->notifier;

		if (!isset($args['id']) || !$args['id'])
			return $response->withStatus(404);

		require_once('class.PaperlessView.php');

		$view = SeedDMS_PaperlessView::getInstance($args['id'], $dms);
		if($view) {
			$logger->log('remove saved view', PEAR_LOG_INFO);
			$view->remove();
		}
		return $response->withStatus(204);
	} /* }}} */

	function storage_paths($request, $response) { /* {{{ */
		//file_put_contents("php://stdout", var_dump($request, true));

		$paths = array(
		);
		return $response->withJson(array('count'=>count($paths), 'next'=>null, 'previous'=>null, 'results'=>$paths), 200);
	} /* }}} */

	function documents($request, $response) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$fulltextservice = $this->container->fulltextservice;
		$logger = $this->container->logger;

		$params = $request->getQueryParams();
		$logger->log(var_export($params, true), PEAR_LOG_DEBUG);

		if(!empty($settings->_extensions['paperless']['usehomefolder'])) {
			if(!($rootfolder = $dms->getFolder((int) $userobj->getHomeFolder())))
				$rootfolder = $dms->getFolder($settings->_rootFolderID);
		} elseif(!isset($settings->_extensions['paperless']['rootfolder']) || !($rootfolder = $dms->getFolder($settings->_extensions['paperless']['rootfolder'])))
			$rootfolder = $dms->getFolder($settings->_rootFolderID);
		$startfolder = $rootfolder;

		$logger->log('Searching for documents in folder '.$rootfolder->getId(), PEAR_LOG_DEBUG);

		$fullsearch = true;
		$query = '';
		$astart = $mstart = 0;
		$aend = $mend = 0;
		if($fullsearch) {
			if (isset($params["query"]) && is_string($params["query"])) {
				$queryparts = explode(',', $params["query"]);
				foreach($queryparts as $querypart) {
					/* 'added' is time when a document was added. This is 'created'
					 * in the fulltext index.
					 */
					if(substr($querypart, 0, 7) == 'added:[') {
						$q = substr($querypart, 7, -1);
						if($t = explode(' to ', $q, 2)) {
							$astart = strtotime($t[0]);
							$aend = strtotime($t[1])+86400;
	//						echo "astart: ".date('Y-m-d', $astart)."\n";
	//						echo "aend: ".date('Y-m-d', $aend);
						}
					}
					/* 'created' is the time when a document was actually created.
					 * There is no equivalent in the fulltext index.
					 * Currently is identical to 'added'.
					 */
					elseif(substr($querypart, 0, 9) == 'created:[') {
						$q = substr($querypart, 9, -1);
						if($t = explode(' to ', $q, 2)) {
							$astart = strtotime($t[0]);
							$aend = strtotime($t[1])+86400;
	//						echo "astart: ".date('Y-m-d', $astart)."\n";
	//						echo "aend: ".date('Y-m-d', $aend);
						}
					}
					/* 'modified' is the time when a document was last modified.
					 * This is 'modified' in the fulltext index.
					 */
					elseif(substr($querypart, 0, 10) == 'modified:[') {
						$q = substr($querypart, 10, -1);
						if($t = explode(' to ', $q, 2)) {
							$mstart = strtotime($t[0]);
							$mend = strtotime($t[1])+86400;
	//						echo "mstart: ".date('Y-m-d', $mstart)."\n";
	//						echo "mend: ".date('Y-m-d', $mend);
						}
					} else {
						$query = $querypart;
					}
				}
			} elseif (isset($params["title_content"]) && is_string($params["title_content"])) {
				$query = $params['title_content'];
			} elseif (isset($params["title__icontains"]) && is_string($params["title__icontains"])) {
				$query = $params['title__icontains'];
			}

			$limit = isset($params['page_size']) ? (int) $params['page_size'] : 25;
			$page = (isset($params['page']) && $params['page'] > 0) ? (int) $params['page'] : 1;
			$offset = ($page-1)*$limit;
			/* Truncate content if requested
			 * See https://github.com/paperless-ngx/paperless-ngx/blob/main/src/documents/serialisers.py
			 */
			$truncate_content = isset($params['truncate_content']) && ($params['truncate_content'] == 'true');

			$order = [];
			if (isset($params["ordering"]) && is_string($params["ordering"])) {
				if($params["ordering"][0] == '-') {
					$order['dir'] = 'desc';
					$orderfield = substr($params["ordering"], 1);
				} else {
					$order['dir'] = 'asc';
					$orderfield = $params["ordering"];
				}
				if(in_array($orderfield, ['modified', 'created', 'title']))
					$order['by'] = $orderfield;
				elseif($orderfield == 'added')
					$order['by'] = 'created';
				elseif($orderfield == 'archive_serial_number')
					$order['by'] = 'id';
				elseif($orderfield == 'correspondent__name') {
					if(!empty($settings->_extensions['paperless']['correspondentsattr']) && $attrdef = $dms->getAttributeDefinition($settings->_extensions['paperless']['correspondentsattr'])) {
						$order['by'] = 'attr_'.$attrdef->getId();
					}
				}
			}

			/* Searching for tags (category) {{{ */
			$categories = array();
			$categorynames = array();
			if(isset($params['tags__id'])) {
				$catid = (int) $params['tags__id'];
				if($catid) {
					if($cat = $dms->getDocumentCategory($catid)) {
						$categories[] = $cat;
						$categorynames[] = $cat->getName();
					}
				}
			}
			/* tags__id__in is used when searching for documents by id */
			if(isset($params['tags__id__all'])) {
				$catids = explode(',', $params['tags__id__all']);
				foreach($catids as $catid)
					if($catid) {
						if($cat = $dms->getDocumentCategory($catid)) {
							$categories[] = $cat;
							$categorynames[] = $cat->getName();
						}
					}
			}
			/* tags__id__in is used when getting the documents of the inbox */
			if(isset($params['tags__id__in'])) {
				$catids = explode(',', $params['tags__id__in']);
				if($catids) {
					foreach($catids as $catid)
						if($cat = $dms->getDocumentCategory($catid)) {
							$categories[] = $cat;
							$categorynames[] = $cat->getName();
						}
				}
			} elseif(isset($params['is_tagged']) && $params['is_tagged'] == '1') {
				$categorynames[] = '*';
			}
			/* }}} */

			/* more_like_id is set to find similar documents {{{ */
			if(isset($params['more_like_id'])) {

				$index = $fulltextservice->Indexer();
				$lucenesearch = $fulltextservice->Search();
				if($searchhit = $lucenesearch->getDocument((int) $params['more_like_id'])) {
					$idoc = $searchhit->getDocument();
					if($idoc) {
						try {
							$fullcontent = $idoc->getFieldValue('content');
						} catch (Exception $e) {
							$fullcontent = '';
						}
						$wcl = 2000;
						$shortcontent = mb_strimwidth($fullcontent, 0, $wcl);

						/* Create a list of words and its occurences to be passed
						 * to the classification.
						 * The '.' is added as valid character in a word, because solr's
						 * standard tokenizer treats it as a valid char as well.
						 * But sqlitefts treats '.' as a separator
						 */
						$wordcount = self::mb_word_count($shortcontent, MB_CASE_LOWER, '');
						arsort($wordcount);
						$newquery = [];
						foreach($wordcount as $word=>$n) {
							if(mb_strlen($word) > 4 && ($n > 2 || count($newquery) < 5))
								$newquery[] = $word;
						}
//						echo implode(' ', $newquery);
						$logger->log("Query for '".implode(' ', $newquery)."'", PEAR_LOG_DEBUG);
						/* $newquery is empty if the document doesn't have a fulltext.
						 * In that case it makes no sense to search for similar documents
						 * Otherwise search for documents with newquery, but if doesn't yield
						 * a result, short the newquery by the last term and try again until
						 * newquery is void
						 */
						while($newquery) {
							$searchresult = $lucenesearch->search(implode(' ', $newquery), array('record_type'=>['document'], 'status'=>[2], 'user'=>[$userobj->getLogin()], 'startFolder'=>$startfolder, 'rootFolder'=>$rootfolder), array('limit'=>$limit, 'offset'=>$offset), $order);
							if($searchresult) {
								$recs = array();
								if($searchresult['hits']) {
									$allids = '';
									foreach($searchresult['hits'] as $hit) {
										if(($hit['document_id'][0] == 'D') && ($hit['document_id'] != 'D'.((int)$params['more_like_id']))) {
											if($tmp = $dms->getDocument((int) substr($hit['document_id'], 1))) {
												$allids .= $hit['document_id'].' ';
												$recs[] = $this->__getDocumentData($tmp, $truncate_content);
											}
										} else {
											$searchresult['count']--;
										}
									}
									$logger->log('Result is '.$allids, PEAR_LOG_DEBUG);
									if($recs)
										return $response->withJson(array('count'=>$searchresult['count'], 'next'=>null, 'previous'=>null, 'offset'=>$offset, 'limit'=>$limit, 'results'=>$recs), 200);
									else {
										/* Still nothing found, so try a shorter query */
										array_pop($newquery);
									}
								} else {
									/* Still nothing found, so try a shorter query */
									array_pop($newquery);
								}
							} else {
								/* Quit the while loop right away, if the search failed */
								$newquery = false;
							}
						}
					}
				}

				return $response->withJson(array('count'=>0, 'next'=>null, 'previous'=>null, 'offset'=>0, 'limit'=>$limit, 'results'=>[]), 200);
				/* Get all documents in the same folder and subfolders
				$likeid = (int) $params['more_like_id'];
				if($likeid && $likedoc = $dms->getDocument($likeid)) {
					$startfolder = $likedoc->getFolder();
				}
				 */
			} /* }}} */

			/* Search for correspondent {{{ */
			$cattrs = [];
			$correspondent = null;
			if(isset($params['correspondent__id']) && $params['correspondent__id']>0) {
				if(!empty($settings->_extensions['paperless']['correspondentsattr']) && $attrdef = $dms->getAttributeDefinition($settings->_extensions['paperless']['correspondentsattr'])) {
					$valueset = $attrdef->getValueSetAsArray();
					if(isset($valueset[$params['correspondent__id']-1])) {
						$correspondent = $valueset[$params['correspondent__id']-1];
						$cattrs['attr_'.$attrdef->getId()] = $correspondent;
					}
				}
			}
			/* Search for any correspondent (correspondent__isnull = 0) */
			/* Search for no correspondent (correspondent__isnull = 1) */
			/* }}} */

			/* Search form document type {{{ */
			$documenttype = null;
			if(isset($params['document_type__id']) && $params['document_type__id']>0) {
				if(!empty($settings->_extensions['paperless']['documenttypesattr']) && $attrdef = $dms->getAttributeDefinition($settings->_extensions['paperless']['documenttypesattr'])) {
					$valueset = $attrdef->getValueSetAsArray();
					if(isset($valueset[$params['document_type__id']-1])) {
						$documenttype = $valueset[$params['document_type__id']-1];
						$cattrs['attr_'.$attrdef->getId()] = $documenttype;
					}
				}
			}
			/* Search for any document_type (document_type__isnull = 0) */
			/* Search for no document_type (document_type__isnull = 1) */
			/* }}} */

			/* The start and end date for e.g. 2012-12-10 is
			 * 2022-12-09 and 2022-12-11
			 * Because makeTsFromDate() returns the start of the day
			 * one day has to be added.
			 */
			if(isset($params['added__date__gt'])) {
				$astart = (int) makeTsFromDate($params['added__date__gt'])+86400;
			}
			if(isset($params['added__date__lt'])) {
				$aend = (int) makeTsFromDate($params['added__date__lt']);
			}

			if(isset($params['created__date__gt'])) {
				$astart = (int) makeTsFromDate($params['created__date__gt'])+86400;
			}
			if(isset($params['created__date__lt'])) {
				$aend = (int) makeTsFromDate($params['created__date__lt']);
			}

			$index = $fulltextservice->Indexer();
			if($index) {
				$logger->log('Query is '.$query, PEAR_LOG_DEBUG);
				/*
				$logger->log('User is '.$userobj->getLogin(), PEAR_LOG_DEBUG);
				$logger->log('created_start is '.$astart, PEAR_LOG_DEBUG);
				$logger->log('created_end is '.$aend, PEAR_LOG_DEBUG);
				$logger->log('modified_start is '.$mstart, PEAR_LOG_DEBUG);
				$logger->log('modified_end is '.$mend, PEAR_LOG_DEBUG);
				$logger->log('startfolder is '.$startfolder->getId(), PEAR_LOG_DEBUG);
				$logger->log('rootfolder is '.$rootfolder->getId(), PEAR_LOG_DEBUG);
				$logger->log('limit is '.$limit, PEAR_LOG_DEBUG);
				$logger->log('offset is '.$offset, PEAR_LOG_DEBUG);
				 */
				$lucenesearch = $fulltextservice->Search();
				$searchresult = $lucenesearch->search($query, array('record_type'=>['document'], 'status'=>[2], 'user'=>[$userobj->getLogin()], 'category'=>$categorynames, 'created_start'=>$astart, 'created_end'=>$aend, 'modified_start'=>$mstart, 'modified_end'=>$mend, 'startFolder'=>$startfolder, 'rootFolder'=>$rootfolder, 'attributes'=>$cattrs), array('limit'=>$limit, 'offset'=>$offset), $order);
				if($searchresult) {
					$recs = array();
					$facets = $searchresult['facets'];
					$dcount = 0;
					$fcount = 0;
					if($searchresult['hits']) {
						$allids = '';
						foreach($searchresult['hits'] as $hit) {
							if($hit['document_id'][0] == 'D') {
								if($tmp = $dms->getDocument((int) substr($hit['document_id'], 1))) {
									$allids .= $hit['document_id'].' ';
	//								if($tmp->getAccessMode($user) >= M_READ) {
//										$tmp->verifyLastestContentExpriry();
										$recs[] = $this->__getDocumentData($tmp, $truncate_content);
	//								}
								}
							}
						}
						$logger->log('Result is '.$allids, PEAR_LOG_DEBUG);
					}
					if($offset + $limit < $searchresult['count']) {
						$params['page'] = $page+1;
						$next = $request->getUri()->getBasePath().'/api/documents?'.http_build_query($params);
					} else
						$next = null;
					if($offset > 0) {
						$params['page'] = $page-1;
						$prev = $request->getUri()->getBasePath().'/api/documents?'.http_build_query($params);
					} else
						$prev = null;
					return $response->withJson(array('count'=>$searchresult['count'], 'next'=>$next, 'previous'=>$prev, 'offset'=>$offset, 'limit'=>$limit, 'results'=>$recs), 200);
				}
			}
		}
		return $response->withJson('Error', 500);

	} /* }}} */

	/**
	 * autocompletion is done on the last term of a list of comma separated
	 * terms. The returned value is then a list of the first n-1 terms
	 * concatenated with the completed terms, e.g.
	 * 'term1 ter' will be auto completed to 'term1 term2', 'term1 term3',
	 * etc.
	 */
	function autocomplete($request, $response) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$fulltextservice = $this->container->fulltextservice;
		$logger = $this->container->logger;

		if(!empty($settings->_extensions['paperless']['autocompletefield']))
			$field = $settings->_extensions['paperless']['autocompletefield'];
		else
			$field = 'title';
		$params = $request->getQueryParams();
		$allterms = explode(' ', $params['term']);
		$query = trim(array_pop($allterms));
		$logger->log(var_export($params, true), PEAR_LOG_DEBUG);

		$list = [];
		$index = $fulltextservice->Indexer();
		if($index) {
			if($terms = $index->terms($query, $field)) {
				foreach($terms as $term)
					$list[] = implode(' ', $allterms).' '.$term->text;
			}
		}


		return $response->withJson($list, 200);
	} /* }}} */

	function ui_settings($request, $response) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$logger = $this->container->logger;

		$data = array(
			'user_id'=>$userobj->getId(),
			'username'=>$userobj->getLogin(),
			'display_name'=>$userobj->getFullName(),
			'settings'=>array(
				'update_checking'=>array(
					'enabled'=>false,
					'backend_setting'=>'default'
				),
				'bulk_edit'=>array(
					'apply_on_close'=>false,
					'confirmation_dialogs'=>true
				),
				'documentListSize'=>50,
				'slim_sidebar'=>false,
				'dark_mode'=>array(
					'use_system'=>true,
					'enabled'=>false, // paperless-ngx 1.13.0 returns a string
					'thumb_inverted'=>true, // paperless-ngx 1.13.0 returns a string
				),
				'theme'=>array(
					'color'=>'',
				),
				'document_details'=>array(
					'native_pdf_viewer'=>false,
				),
				'date_display'=>array(
					'date_local'=>'',
					'date_format'=>'mediumDate',
				),
				'notifications'=>array(
					'consumer_new_documents'=>true,
					'consumer_success'=>true,
					'consumer_failed'=>true,
					'consumer_suppress_on_dashboard'=>true,
				),
				'comments_enabled'=>true,
				'language'=>'en-gb',
			),
		);
		/*
		$data = array(
			'user'=>[
				'id'=>$userobj->getId(),
				'username'=>$userobj->getLogin(),
				'is_superuser'=>$userobj->isAdmin(),
				'groups'=>[]
			],
			'settings'=>array('update_checking'=>array('backend_setting'=>'default')),
		);
		*/
		return $response->withJson($data, 200);
	} /* }}} */

	function statstotal($request, $response) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$fulltextservice = $this->container->fulltextservice;
		$logger = $this->container->logger;

		if(false === ($categories = $dms->getDocumentCategories())) {
			return $response->withJson(array('results'=>null), 500);
		}

		if(!empty($settings->_extensions['paperless']['usehomefolder'])) {
			if(!($startfolder = $dms->getFolder((int) $userobj->getHomeFolder())))
				$startfolder = $dms->getFolder($settings->_rootFolderID);
		} elseif(!isset($settings->_extensions['paperless']['rootfolder']) || !($startfolder = $dms->getFolder($settings->_extensions['paperless']['rootfolder'])))
			$startfolder = $dms->getFolder($settings->_rootFolderID);

		$index = $fulltextservice->Indexer();
		if($index) {
			$lucenesearch = $fulltextservice->Search();
			$searchresult = $lucenesearch->search('', array('record_type'=>['document'], 'status'=>[2], 'user'=>[$userobj->getLogin()], 'startFolder'=>$startfolder, 'rootFolder'=>$startfolder), array('limit'=>20), array());
			if($searchresult === false) {
				return $response->withStatus(500);
			} else {
				$recs = array();
				$facets = $searchresult['facets'];
//				$logger->log(var_export($facets, true), PEAR_LOG_DEBUG);
			}
		}

		$data = array(
			'documents_total'=>$searchresult['count'],
			'document_inbox'=>0,
		);
		$inboxtags = [];
		if(!empty($settings->_extensions['paperless']['inboxtags']) && $inboxtags = explode(',', $settings->_extensions['paperless']['inboxtags'])) {
			foreach($inboxtags as $inboxtagid)
				if($inboxtag = $dms->getDocumentCategory((int) $inboxtagid))
					$data['document_inbox'] += (int) $facets['category'][$inboxtag->getName()];
		}

		return $response->withJson($data, 200);
	} /* }}} */

	function fetch_thumb($request, $response, $args) { /* {{{ */
		return $response->withRedirect($request->getUri()->getBasePath().'/api/documents/'.$args['id'].'/thumb/', 302);
	} /* }}} */

	function documents_thumb($request, $response, $args) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$conversionmgr = $this->container->conversionmgr;
		$logger = $this->container->logger;

		if (!isset($args['id']) || !$args['id'])
			return $response->withStatus(404);
	
		$document = $dms->getDocument($args['id']);
		if($document) {
			if($document->getAccessMode($userobj) >= M_READ) {
				$object = $document->getLatestContent();
				$width = 400;
				$previewer = new SeedDMS_Preview_Previewer($settings->_cacheDir, $width);
				if($conversionmgr)
					$previewer->setConversionMgr($conversionmgr);
				else
					$previewer->setConverters($settings->_converters['preview']);
				if(!$previewer->hasPreview($object))
					$previewer->createPreview($object);

				$file = $previewer->getFileName($object, $width).".png";
				if(!($fh = @fopen($file, 'rb'))) {
					return $response->withJson(array('success'=>false, 'message'=>'', 'data'=>''), 500);
				}
				$stream = new \Slim\Http\Stream($fh); // create a stream instance for the response body

				return $response->withHeader('Content-Type', 'image/png')
						->withHeader('Content-Description', 'File Transfer')
						->withHeader('Content-Transfer-Encoding', 'binary')
						->withHeader('Content-Disposition', 'attachment; filename="preview-' . $document->getID() . "-" . $object->getVersion() . "-" . $width . ".png" . '"')
						->withHeader('Content-Length', $previewer->getFilesize($object))
						->withBody($stream);
			}
		}
		return $response->withStatus(403);
	} /* }}} */

	function fetch_doc($request, $response, $args) { /* {{{ */
		$logger = $this->container->logger;
		$logger->log('Fetch doc '.$args['id'], PEAR_LOG_INFO);
		return $response->withRedirect($request->getUri()->getBasePath().'/api/documents/'.$args['id'].'/download/', 302);
	} /* }}} */

	/**
	 * documents_preview works like documents_download but converts
	 * documents which are not pdf already into pdf
	 */
	function documents_preview($request, $response, $args) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$conversionmgr = $this->container->conversionmgr;
		$logger = $this->container->logger;

		if (!isset($args['id']) || !$args['id'])
			return $response->withStatus(404);
	
		$logger->log('Get preview of doc '.$args['id'], PEAR_LOG_INFO);
		$document = $dms->getDocument($args['id']);
		if($document) {
			if($document->getAccessMode($userobj) >= M_READ) {
				$lc = $document->getLatestContent();
				if($lc) {
					if($lc->getMimeType() == 'application/pdf') {
						if (pathinfo($document->getName(), PATHINFO_EXTENSION) == $lc->getFileType())
							$filename = $document->getName();
						else
							$filename = $document->getName().$lc->getFileType();
						$file = $dms->contentDir . $lc->getPath();
						if(!($fh = @fopen($file, 'rb'))) {
							return $response->withJson(array('success'=>false, 'message'=>'', 'data'=>''), 500);
						}
						$filesize = filesize($dms->contentDir . $lc->getPath());
					} else {
						$previewer = new SeedDMS_Preview_PdfPreviewer($settings->_cacheDir);
						if($conversionmgr)
							$previewer->setConversionMgr($conversionmgr);
						else
							$previewer->setConverters(isset($settings->_converters['pdf']) ? $settings->_converters['pdf'] : array());
						if(!$previewer->hasPreview($lc))
							$previewer->createPreview($lc);
						if(!$previewer->hasPreview($lc)) {
							$logger->log('Creating pdf preview failed', PEAR_LOG_ERR);
							return $response->withJson('', 500);
						} else {
							$filename = $document->getName().".pdf";
							$file = $previewer->getFileName($lc).".pdf";
							$filesize = filesize($file);
							if(!($fh = @fopen($file, 'rb'))) {
								$logger->log('Creating pdf preview failed', PEAR_LOG_ERR);
								return $response->withJson('', 500);
							}
						}
					}
					$stream = new \Slim\Http\Stream($fh); // create a stream instance for the response body

					return $response->withHeader('Content-Type', $lc->getMimeType())
						->withHeader('Content-Description', 'File Transfer')
						->withHeader('Content-Transfer-Encoding', 'binary')
						->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
						->withHeader('Content-Length', $filesize)
						->withHeader('Expires', '0')
						->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
						->withHeader('Pragma', 'no-cache')
						->withBody($stream);
				} else {
					return $response->withStatus(403);
				}
			} else
				return $response->withStatus(404);
		} else {
			return $response->withStatus(500);
		}
	} /* }}} */

	function documents_download($request, $response, $args) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$conversionmgr = $this->container->conversionmgr;
		$logger = $this->container->logger;

		if (!isset($args['id']) || !$args['id'])
			return $response->withStatus(404);

		$params = $request->getQueryParams();
		$logger->log(var_export($params, true), PEAR_LOG_DEBUG);

		$logger->log('Download doc '.$args['id'], PEAR_LOG_INFO);
		$document = $dms->getDocument($args['id']);
		if($document) {
			if($document->getAccessMode($userobj) >= M_READ) {
				$lc = $document->getLatestContent();
				if($lc) {
					/* Used to check if empty($settings->_extensions['paperless']['converttopdf'])
					 * but that makes no sense any more, because paperless mobile sets
					 * the parameter 'original=true' if the original document shall be
					 * downloaded.
					 */
					if((isset($params['original']) && $params['original'] == 'true') || $lc->getMimeType() == 'application/pdf') {
						if (pathinfo($document->getName(), PATHINFO_EXTENSION) == $lc->getFileType())
							$filename = $document->getName();
						else
							$filename = $document->getName().$lc->getFileType();
						$file = $dms->contentDir . $lc->getPath();

						if(!($fh = @fopen($file, 'rb'))) {
							return $response->withJson(array('success'=>false, 'message'=>'', 'data'=>''), 500);
						}
						$filesize = filesize($dms->contentDir . $lc->getPath());
					} else {
						$previewer = new SeedDMS_Preview_PdfPreviewer($settings->_cacheDir);
						if($conversionmgr)
							$previewer->setConversionMgr($conversionmgr);
						else
							$previewer->setConverters(isset($settings->_converters['pdf']) ? $settings->_converters['pdf'] : array());
						if(!$previewer->hasPreview($lc))
							$previewer->createPreview($lc);
						if(!$previewer->hasPreview($lc)) {
							$logger->log('Creating pdf preview failed', PEAR_LOG_ERR);
							return $response->withJson('', 500);
						} else {
							$filename = $document->getName().".pdf";
							$file = $previewer->getFileName($lc).".pdf";
							$filesize = filesize($file);
							if(!($fh = @fopen($file, 'rb'))) {
								$logger->log('Creating pdf preview failed', PEAR_LOG_ERR);
								return $response->withJson('', 500);
							}
						}
					}
					$stream = new \Slim\Http\Stream($fh); // create a stream instance for the response body

					return $response->withHeader('Content-Type', $lc->getMimeType())
						->withHeader('Content-Description', 'File Transfer')
						->withHeader('Content-Transfer-Encoding', 'binary')
						->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
						->withHeader('Content-Length', $filesize)
						->withHeader('Expires', '0')
						->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
						->withHeader('Pragma', 'no-cache')
						->withBody($stream);
				} else {
					return $response->withStatus(403);
				}
			} else
				return $response->withStatus(404);
		} else {
			return $response->withStatus(500);
		}
	} /* }}} */

	function documents_metadata($request, $response, $args) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$conversionmgr = $this->container->conversionmgr;
		$logger = $this->container->logger;

		if (!isset($args['id']) || !$args['id'])
			return $response->withStatus(404);
	
		$document = $dms->getDocument($args['id']);
		if($document) {
			if($document->getAccessMode($userobj) >= M_READ) {
				$lc = $document->getLatestContent();
				if($lc) {
					return $response->withJson(array(
						'original_checksum'=>$lc->getChecksum(),
						'original_size'=>(int) $lc->getFilesize(),
						'original_mime_type'=>$lc->getMimeType(),
						'media_filename'=>$lc->getOriginalFileName(),
						'has_archive_version'=>false,
						'original_metadata'=>[],
						'archive_checksum'=>$lc->getChecksum(),
						'archive_media_filename'=>$lc->getOriginalFileName(),
						'original_filename'=>$lc->getOriginalFileName(),
						'archive_size'=>(int) $lc->getFilesize(),
						'archive_metadata'=>[],
					), 200);
				} else {
					return $response->withStatus(403);
				}
			} else
				return $response->withStatus(404);
		} else {
			return $response->withStatus(500);
		}
	} /* }}} */

	function post_document($request, $response) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$logger = $this->container->logger;
		$fulltextservice = $this->container->fulltextservice;
		$notifier = $this->container->notifier;

		if(isset($settings->_extensions['paperless']['uploadfolder']))
		 	$mfolder = $dms->getFolder($settings->_extensions['paperless']['uploadfolder']);

		if(!$mfolder) {
			if(!empty($settings->_extensions['paperless']['usehomefolder'])) {
				if(!($mfolder = $dms->getFolder((int) $userobj->getHomeFolder())))
					$mfolder = $dms->getFolder($settings->_rootFolderID);
			} elseif(!isset($settings->_extensions['paperless']['rootfolder']) || !($mfolder = $dms->getFolder($settings->_extensions['paperless']['rootfolder'])))
				$mfolder = $dms->getFolder($settings->_rootFolderID);
		}

		if($mfolder) {
			if($mfolder->getAccessMode($userobj) < M_READWRITE) {
				$logger->log('No write access on folder '.$mfolder->getId(), PEAR_LOG_ERR);
				return $response->withStatus(403);
			}

			$data = $request->getParsedBody();
//			$logger->log(var_export($data, true), PEAR_LOG_DEBUG);
			$uploadedFiles = $request->getUploadedFiles();
			if (count($uploadedFiles) == 0) {
				$logger->log('No files uploaded', PEAR_LOG_ERR);
				return $response->withJson(getMLText("paperless_no_files_uploaded"), 400);
			}

			$file_info = array_pop($uploadedFiles);

			$maxuploadsize = SeedDMS_Core_File::parse_filesize($settings->_maxUploadSize);
			if ($maxuploadsize && $file_info->getSize() > $maxuploadsize) {
				$logger->log('File too large ('.$file_info->getSize().' > '.$maxuploadsize.')', PEAR_LOG_ERR);
				return $response->withJson(getMLText("paperless_upload_maxsize"), 400);
			}

			$origfilename = null;
			if ($origfilename == null)
				$origfilename = $file_info->getClientFilename();
			if(!empty($data['title']))
				$docname = $data['title'];
			else
				$docname = $origfilename;

			/* Check if name already exists in the folder */
			if(!$settings->_enableDuplicateDocNames) {
				if($mfolder->hasDocumentByName($docname)) {
					$logger->log('Duplicate document name '.$docname, PEAR_LOG_ERR);
					return $response->withJson(getMLText("document_duplicate_name"), 409);
				}
			}
			/* If several tags are set, they will all be saved individually in
			 * a parameter named 'tags'. This cannot be handled by php. It would
			 * require to use 'tags[]'. Hence, only the last tag will be taken into
			 * account.
			 */
			$cats = [];
			if(!empty($data['tags'])) {
				if($cat = $dms->getDocumentCategory((int) $data['tags']))
					$cats[] = $cat;
			}

			$userfiletmp = $file_info->file;
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$userfiletype = finfo_file($finfo, $userfiletmp);
			$fileType = ".".pathinfo($origfilename, PATHINFO_EXTENSION);
			finfo_close($finfo);

			$reviewers = array();
			$approvers = array();
			$reviewers["i"] = array();
			$reviewers["g"] = array();
			$approvers["i"] = array();
			$approvers["g"] = array();
			$workflow = null;

			if($settings->_workflowMode == 'traditional' || $settings->_workflowMode == 'traditional_only_approval') {
				// add mandatory reviewers/approvers
				if($settings->_workflowMode == 'traditional') {
					$mreviewers = getMandatoryReviewers($mfolder, $userobj);
					if($mreviewers['i'])
						$reviewers['i'] = array_merge($reviewers['i'], $mreviewers['i']);
					if($mreviewers['g'])
						$reviewers['g'] = array_merge($reviewers['g'], $mreviewers['g']);
				}
				$mapprovers = getMandatoryApprovers($mfolder, $userobj);
				if($mapprovers['i'])
					$approvers['i'] = array_merge($approvers['i'], $mapprovers['i']);
				if($mapprovers['g'])
					$approvers['g'] = array_merge($approvers['g'], $mapprovers['g']);
			} elseif($settings->_workflowMode == 'advanced') {
				if($workflows = $userobj->getMandatoryWorkflows()) {
					$workflow = array_shift($workflows);
				}
			}

			$comment = '';
			$expires = null;
			$owner = null;
			$keywords = '';
			$sequence = 1;
			$reqversion = null;
			$version_comment = '';
			$attributes = array();
			$attributes_version = array();
			$notusers = array();
			$notgroups = array();

			$controller = Controller::factory('AddDocument', array('dms'=>$dms, 'user'=>$userobj));
			$controller->setParam('documentsource', 'paperless');
			$controller->setParam('folder', $mfolder);
			$controller->setParam('fulltextservice', $fulltextservice);
			$controller->setParam('name', $docname);
			$controller->setParam('comment', $comment);
			$controller->setParam('expires', $expires);
			$controller->setParam('keywords', $keywords);
			$controller->setParam('categories', $cats);
			$controller->setParam('owner', $userobj);
			$controller->setParam('userfiletmp', $userfiletmp);
			$controller->setParam('userfilename', $origfilename ? $origfilename : basename($userfiletmp));
			$controller->setParam('filetype', $fileType);
			$controller->setParam('userfiletype', $userfiletype);
			$controller->setParam('sequence', $sequence);
			$controller->setParam('reviewers', $reviewers);
			$controller->setParam('approvers', $approvers);
			$controller->setParam('reqversion', $reqversion);
			$controller->setParam('versioncomment', $version_comment);
			$controller->setParam('attributes', $attributes);
			$controller->setParam('attributesversion', $attributes_version);
			$controller->setParam('workflow', $workflow);
			$controller->setParam('notificationgroups', $notgroups);
			$controller->setParam('notificationusers', $notusers);
			$controller->setParam('maxsizeforfulltext', $settings->_maxSizeForFullText);
			$controller->setParam('defaultaccessdocs', $settings->_defaultAccessDocs);
			if(!$document = $controller()) {
				$err = $controller->getErrorMsg();
				if(is_string($err))
					$errmsg = getMLText($err);
				elseif(is_array($err)) {
					$errmsg = getMLText($err[0], $err[1]);
				} else {
					$errmsg = $err;
				}
				$logger->log('Upload failed: '.$errmsg, PEAR_LOG_ERR);
				return $response->withJson(getMLText('paperless_upload_failed'), 500);
			} else {
				$logger->log('Upload succeeded', PEAR_LOG_INFO);
				/* Turn off for now, because file_info is not an array
				if($controller->hasHook('cleanUpDocument')) {
					$controller->callHook('cleanUpDocument', $document, $file_info);
				}
				*/
				// Send notification to subscribers of folder.
				if($notifier) {
					$notifier->sendNewDocumentMail($document, $userobj);
				}
				if($settings->_removeFromDropFolder) {
					if(file_exists($userfiletmp)) {
						unlink($userfiletmp);
					}
				}
				return $response->withJson('OK', 200);
			}
		}
		return $response->withJson(getMLText('paperless_missing_target_folder'), 400);
	} /* }}} */

	function patch_documents($request, $response, $args) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$conversionmgr = $this->container->conversionmgr;
		$logger = $this->container->logger;
		$fulltextservice = $this->container->fulltextservice;

		if (!isset($args['id']) || !$args['id'])
			return $response->withStatus(404);

		$document = $dms->getDocument($args['id']);
		if($document) {
			$body = $request->getBody();
			if($data = json_decode($body, true)) {
				if(isset($data['tags'])) {
					$cats = [];
					foreach($data['tags'] as $tagid) {
						if($cat = $dms->getDocumentCategory($tagid)) {
							$cats[] = $cat;
						}
					}
					if(!$document->setCategories($cats))
						return $response->withStatus(500);
					if($fulltextservice && ($index = $fulltextservice->Indexer())) {
						$idoc = $fulltextservice->IndexedDocument($document);
//						if(false !== $this->callHook('preIndexDocument', $document, $idoc)) {
							$lucenesearch = $fulltextservice->Search();
							if($hit = $lucenesearch->getDocument((int) $document->getId())) {
								$index->delete($hit->id);
							}
							$index->addDocument($idoc);
							$index->commit();
//						}
					}
				}
			} 
		}
		return $response->withStatus(204);
	} /* }}} */

	/**
	 * Currently just sets tags but receives all kind of data, which
	 * is still disregarded.
	 */
	function put_documents($request, $response, $args) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$conversionmgr = $this->container->conversionmgr;
		$logger = $this->container->logger;
		$fulltextservice = $this->container->fulltextservice;

		if (!isset($args['id']) || !$args['id'])
			return $response->withStatus(404);

		$document = $dms->getDocument($args['id']);
		if($document) {
			$body = $request->getBody();
			if($data = json_decode($body, true)) {
				$logger->log(var_export($data, true), PEAR_LOG_DEBUG);
				if(isset($data['tags'])) {
					$cats = [];
					foreach($data['tags'] as $tagid) {
						if($cat = $dms->getDocumentCategory($tagid)) {
							$cats[] = $cat;
						}
					}
					if(!$document->setCategories($cats))
						return $response->withStatus(500);
					if($fulltextservice && ($index = $fulltextservice->Indexer())) {
						$idoc = $fulltextservice->IndexedDocument($document);
//						if(false !== $this->callHook('preIndexDocument', $document, $idoc)) {
							$lucenesearch = $fulltextservice->Search();
							if($hit = $lucenesearch->getDocument((int) $document->getId())) {
								$index->delete($hit->id);
							}
							$index->addDocument($idoc);
							$index->commit();
//						}
					}
				}
			} 
		}
		return $response->withJson($this->__getDocumentData($document), 200);
	} /* }}} */

	function delete_documents($request, $response, $args) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$conversionmgr = $this->container->conversionmgr;
		$logger = $this->container->logger;
		$notifier = $this->container->notifier;
		$fulltextservice = $this->container->fulltextservice;

		if (!isset($args['id']) || !$args['id'])
			return $response->withStatus(404);

		$document = $dms->getDocument($args['id']);
		if($document) {
			$folder = $document->getFolder();
			/* Remove all preview images. */
			$previewer = new SeedDMS_Preview_Previewer($settings->_cacheDir);
			$previewer->deleteDocumentPreviews($document);

			/* Get the notify list before removing the document
			 * Also inform the users/groups of the parent folder
			 * Getting the list now will keep them in the document object
			 * even after the document has been deleted.
			 */
			$dnl =	$document->getNotifyList();
			$fnl =	$folder->getNotifyList();
			$docname = $document->getName();

			$controller = Controller::factory('RemoveDocument', array('dms'=>$dms, 'user'=>$userobj));
			$controller->setParam('document', $document);
			$controller->setParam('fulltextservice', $fulltextservice);
			if(!$controller()) {
				$logger->log($controller->getErrorMsg(), PEAR_LOG_ERR);
				return $response->withStatus(500);
			}
			$logger->log('Document deleted', PEAR_LOG_INFO);
			if ($notifier){
				/* $document still has the data from the just deleted document,
				 * which is just enough to send the email.
				 */
				$notifier->sendDeleteDocumentMail($document, $userobj);
			}
		}
		return $response->withStatus(204);
	} /* }}} */

} /* }}} */

class SeedDMS_ExtPaperless_RestAPI_Auth { /* {{{ */

	private $container;

	public function __construct($container) {
		$this->container = $container;
	}

	/**
	 * Example middleware invokable class
	 *
	 * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
	 * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
	 * @param  callable                                 $next     Next middleware
	 *
	 * @return \Psr\Http\Message\ResponseInterface
	 */
	public function __invoke($request, $response, $next) { /* {{{ */
		// $this->container has the DI
		$dms = $this->container->dms;
		$settings = $this->container->config;
		$logger = $this->container->logger;

		/* Skip this middleware if the authentication was already successful */
		$userobj = null;
		if($this->container->has('userobj'))
		  $userobj = $this->container->userobj;

		if($userobj) {
		  $response = $next($request, $response);
		  return $response;
		}

		/* Pretent to be paperless ngx 1.10.0 with api version 2 */
		$response = $response->withHeader('x-api-version', '2')->withHeader('x-version', '1.10.0');

		$logger->log("Invoke paperless middleware for method ".$request->getMethod()." on '".$request->getUri()->getPath()."'", PEAR_LOG_INFO);
		if(!in_array($request->getUri()->getPath(), array('api/token/', 'api/'))) {
			$userobj = null;
			if(!empty($this->container->environment['HTTP_AUTHORIZATION'])) {
				$tmp = explode(' ', $this->container->environment['HTTP_AUTHORIZATION'], 2);
				switch($tmp[0]) {
				case 'Token':
					/* if jwtsecret is set, the token is expected to be a jwt */
					if(!empty($settings->_extensions['paperless']['jwtsecret'])) {
						$token = new SeedDMS_JwtToken($settings->_extensions['paperless']['jwtsecret']);
						if(!$tokenstr = $token->jwtDecode($tmp[1])) {
							$logger->log("Could not decode jwt", PEAR_LOG_ERR);
							return $response->withJson("Invalid token", 403);
						}
						$tmp = explode(':', json_decode($tokenstr, true));
						if($tmp[1] < time()) {
							$logger->log("Jwt has expired at ".date('Y-m-d H:i:s', $tmp[1]), PEAR_LOG_ERR);
							return $response->withJson(getMLText('paperless_token_has_expired'), 403);
						} else {
							$logger->log("Token is valid till ".date('Y-m-d H:i:s', $tmp[1]), PEAR_LOG_DEBUG);
						}
						if(!($userobj = $dms->getUser((int) $tmp[0]))) {
							$logger->log("No such user ".$tmp[0], PEAR_LOG_ERR);
							return $response->withJson("No such user", 403);
						}
						$dms->setUser($userobj);
						$this->container['userobj'] = $userobj;
						$logger->log("Login with jwt as '".$userobj->getLogin()."' successful", PEAR_LOG_INFO);
					} else {
						if(!empty($settings->_apiKey) && !empty($settings->_apiUserId)) {
							if($settings->_apiKey == $tmp[1]) {
								if(!($userobj = $dms->getUser($settings->_apiUserId))) {
									return $response->withStatus(403);
								}
							} else {
								$logger->log("Login with apikey '".$tmp[1]."' failed", PEAR_LOG_ERR);
								return $response->withStatus(403);
							}
							$dms->setUser($userobj);
							$this->container['userobj'] = $userobj;
							$logger->log("Login with apikey as '".$userobj->getLogin()."' successful", PEAR_LOG_INFO);
						}
					}
					break;
				case 'Basic':
					$authenticator = $this->container->authenticator;
					$kk = explode(':', base64_decode($tmp[1]));
					$userobj = $authenticator->authenticate($kk[0], $kk[1]);
					if(!$userobj) {
						$logger->log("Login with basic authentication for '".$kk[0]."' failed", PEAR_LOG_ERR);
						return $response->withStatus(403);
					}
					$dms->setUser($userobj);
					$this->container['userobj'] = $userobj;
					$logger->log("Login with basic authentication as '".$userobj->getLogin()."' successful", PEAR_LOG_INFO);
					break;
				}
			}
		} else {
			/* Set userobj to keep other middlewares for authentication from running */
			$this->container['userobj'] = true;
		}
		$response = $next($request, $response);
		return $response;
	} /* }}} */
} /* }}} */

/**
 * Class containing methods which adds additional routes to the RestAPI
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  paperless
 */
class SeedDMS_ExtPaperless_RestAPI { /* {{{ */

	/**
	 * Hook for adding additional routes to the RestAPI
	 *
	 * @param object $app instance of \Slim\App
	 * @return void
	 */
	public function addMiddleware($app) { /* {{{ */
		$container = $app->getContainer();
		$app->add(new SeedDMS_ExtPaperless_RestAPI_Auth($container));
	} /* }}} */

	/**
	 * Hook for adding additional routes to the RestAPI
	 *
	 * @param object $app instance of \Slim\App
	 * @return void
	 */
	public function addRoute($app) { /* {{{ */
		$app->get('/api/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':api');
		/* /api/token/ is actually a get, but paperless_app calls it to check for ngx */
		$app->post('/api/token/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':token');
		$app->get('/api/token/', function($request, $response) use ($app) {
				return $response->withStatus(405);
		});
		$app->get('/api/tags/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':tags');
		$app->post('/api/tags/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':post_tag');
		$app->delete('/api/tags/{id}/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':delete_tag');
		$app->get('/api/documents/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':documents');
		$app->get('/api/correspondents/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':correspondents');
		$app->get('/api/document_types/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':document_types');
		$app->get('/api/saved_views/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':saved_views');
		$app->post('/api/saved_views/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':post_saved_views');
		$app->delete('/api/saved_views/{id}/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':delete_saved_views');
		$app->get('/api/storage_paths/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':storage_paths');
		$app->post('/api/documents/post_document/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':post_document');
		$app->get('/api/documents/{id}/preview/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':documents_preview');
		$app->get('/api/documents/{id}/thumb/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':documents_thumb');
		$app->get('/fetch/thumb/{id}', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':fetch_thumb');
		$app->get('/api/documents/{id}/download/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':documents_download');
		$app->get('/api/documents/{id}/metadata/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':documents_metadata');
		$app->get('/fetch/doc/{id}', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':fetch_doc');
		$app->patch('/api/documents/{id}/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':patch_documents');
		$app->put('/api/documents/{id}/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':put_documents');
		$app->delete('/api/documents/{id}/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':delete_documents');
		$app->get('/api/search/autocomplete/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':autocomplete');
		$app->get('/api/ui_settings/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':ui_settings');
		$app->get('/api/statstotal/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':statstotal');
		$app->get('/api/statistics/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':statstotal');

		return null;
	} /* }}} */

} /* }}} */

