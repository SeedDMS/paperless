<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2013 Uwe Steinmann <uwe@steinmann.cx>
*  All rights reserved
*
*  This script is part of the SeedDMS project. The SeedDMS project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * Paperless extension
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  example
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
	} /* }}} */

	function main() { /* {{{ */
	} /* }}} */
} /* }}} */

use Psr\Container\ContainerInterface;

class SeedDMS_ExtPaperless_RestAPI_Controller { /* {{{ */
	protected $container;

	protected function __getDocumentData($document) { /* {{{ */
		$fulltextservice = $this->container->fulltextservice;

		$content = '';
		$index = $fulltextservice->Indexer();
		if($index) {
			$lucenesearch = $fulltextservice->Search();
			if($searchhit = $lucenesearch->getDocument($document->getID())) {
				$idoc = $searchhit->getDocument();
				try {
					$content = htmlspecialchars(mb_strimwidth($idoc->getFieldValue('content'), 0, 1000, '...'));
				} catch (Exception $e) {
				}
			}
		}

		$lc = $document->getLatestContent();
		$cats = $document->getCategories();
		$tags = array();
		foreach($cats as $cat)
			$tags[] = (int) $cat->getId();
		$data = array(
			'id'=>(int)$document->getId(),
			'correspondent'=>null,
			'document_type'=>null,
			'storage_path'=>null,
			'title'=>$document->getName(),
			'content'=>$content,
			'tags'=>$tags,
			'checksum'=>$lc->getChecksum(),
			'created'=>date('Y-m-d\TH:i:s+02:00', $document->getDate()),
			'created_date'=>date('Y-m-d', $document->getDate()),
			'modified'=>date('Y-m-d\TH:i:s+02:00', $document->getDate()),
			'added'=>date('Y-m-d\TH:i:s+02:00', $document->getDate()),
			'archive_serial_number'=>null,
			'original_file_name'=>$lc->getOriginalFileName(),
			'archived_file_name'=>$lc->getOriginalFileName()
		);
		return $data;
	} /* }}} */

	public function getContrastColor($hexcolor) {
		$r = hexdec(substr($hexcolor, 1, 2));
		$g = hexdec(substr($hexcolor, 3, 2));
		$b = hexdec(substr($hexcolor, 5, 2));
		$yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
		return ($yiq >= 148) ? '000000' : 'ffffff';
	}

	protected function __getCategoryData($category, $inboxtags) { /* {{{ */
		$color = substr(md5($category->getName()), 0, 6);
		$data = [
			'id'=>(int)$category->getId(),
			'slug'=>strtolower($category->getName()),
			'name'=>$category->getName(),
			'colour'=>'#'.$color, //'#50b02c',
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
				return $response->withJson(array('token'=>''), 403);
			else {
				if(!empty($settings->_extensions['paperless']['jwtsecret'])) {
					$token = new SeedDMS_JwtToken($settings->_extensions['paperless']['jwtsecret']);
					if(!empty($settings->_extensions['paperless']['tokenlivetime']))
						$days = (int) $settings->_extensions['paperless']['tokenlivetime'];
					else
						$days = 1000;
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
			$searchresult = $lucenesearch->search('', array('record_type'=>['document'], 'user'=>[$userobj->getLogin()], 'startFolder'=>$startfolder, 'rootFolder'=>$startfolder), array('limit'=>20), array());
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

	function correspondents($request, $response) { /* {{{ */
		//file_put_contents("php://stdout", var_dump($request, true));

		$correspondents = array(
		);
		return $response->withJson(array('count'=>count($correspondents), 'next'=>null, 'previous'=>null, 'results'=>$correspondents), 200);
	} /* }}} */

	function document_types($request, $response) { /* {{{ */
		//file_put_contents("php://stdout", var_dump($request, true));

		$types = array(
		);
		return $response->withJson(array('count'=>count($types), 'next'=>null, 'previous'=>null, 'results'=>$types), 200);
	} /* }}} */

	function saved_views($request, $response) { /* {{{ */
		//file_put_contents("php://stdout", var_dump($request, true));

		$views = array(
		);
		return $response->withJson(array('count'=>count($views), 'next'=>null, 'previous'=>null, 'results'=>$views), 200);
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
		$logger->log(var_export($params, true), PEAR_LOG_INFO);

		if(!empty($settings->_extensions['paperless']['usehomefolder'])) {
			if(!($startfolder = $dms->getFolder((int) $userobj->getHomeFolder())))
				$startfolder = $dms->getFolder($settings->_rootFolderID);
		} elseif(!isset($settings->_extensions['paperless']['rootfolder']) || !($startfolder = $dms->getFolder($settings->_extensions['paperless']['rootfolder'])))
			$startfolder = $dms->getFolder($settings->_rootFolderID);

		$logger->log('Searching for documents in folder '.$startfolder->getId(), PEAR_LOG_DEBUG);

		$fullsearch = true;
		if($fullsearch) {
			if (isset($params["query"]) && is_string($params["query"])) {
				$query = $params["query"];
			} elseif (isset($params["title_content"]) && is_string($params["title_content"])) {
				$query = $params['title_content'];
			} elseif (isset($params["title__icontains"]) && is_string($params["title__icontains"])) {
				$query = $params['title__icontains'];
			} else {
				$query = "";
			}

			$order = [];
			if (isset($params["ordering"]) && is_string($params["ordering"])) {
				if($params["ordering"][0] == '-') {
					$order['dir'] = 'asc';
					$orderfield = substr($params["ordering"], 1);
				} else {
					$order['dir'] = 'desc';
					$orderfield = $params["ordering"];
				}
				if(in_array($orderfield, ['created', 'title']))
					$order['by'] = $orderfield;
				elseif($orderfield == 'added')
					$order['by'] = 'created';
			}

			// category
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
			}

			/* The start and end date for e.g. 2012-12-10 is
			 * 2022-12-09 and 2022-12-11
			 * Because makeTsFromDate() returns the start of the day
			 * one day has to be added.
			 */
			$astart = 0;
			if(isset($params['added__date__gt'])) {
				$astart = (int) makeTsFromDate($params['added__date__gt'])+86400;
			}
			$aend = 0;
			if(isset($params['added__date__lt'])) {
				$aend = (int) makeTsFromDate($params['added__date__lt']);
			}

			$index = $fulltextservice->Indexer();
			if($index) {
				$limit = isset($params['page_size']) ? (int) $params['page_size'] : 25;
				$offset = (isset($params['page']) && $params['page'] > 0) ? ($params['page']-1)*$limit : 0;
				$lucenesearch = $fulltextservice->Search();
				$searchresult = $lucenesearch->search($query, array('record_type'=>['document'], 'user'=>[$userobj->getLogin()], 'category'=>$categorynames, 'created_start'=>$astart, 'created_end'=>$aend, 'startFolder'=>$startfolder, 'rootFolder'=>$startfolder), array('limit'=>$limit, 'offset'=>$offset), $order);
				if($searchresult) {
					$recs = array();
					$facets = $searchresult['facets'];
					$dcount = 0;
					$fcount = 0;
					if($searchresult) {
						foreach($searchresult['hits'] as $hit) {
							if($hit['document_id'][0] == 'D') {
								if($tmp = $dms->getDocument(substr($hit['document_id'], 1))) {
	//								if($tmp->getAccessMode($user) >= M_READ) {
										$tmp->verifyLastestContentExpriry();
										$recs[] = $this->__getDocumentData($tmp);
	//								}
								}
							}
						}
					}
					$curpage = $params['page'];
					if($offset + $limit < $searchresult['count']) {
						$params['page'] = $curpage+1;
						$next = $request->getUri()->getBasePath().'/api/documents?'.http_build_query($params);
					} else
						$next = null;
					if($offset > 0) {
						$params['page'] = $curpage-1;
						$prev = $request->getUri()->getBasePath().'/api/documents?'.http_build_query($params);
					} else
						$prev = null;
					return $response->withJson(array('count'=>$searchresult['count'], 'next'=>$next, 'previous'=>$prev, 'offset'=>$offset, 'limit'=>$limit, 'results'=>$recs), 200);
				}
			}
		}
		return $response->withJson('Error', 500);

	} /* }}} */

	function autocomplete($request, $response) { /* {{{ */
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$fulltextservice = $this->container->fulltextservice;
		$logger = $this->container->logger;

		$params = $request->getQueryParams();
		$query = $params['term'];
		$logger->log(var_export($params, true), PEAR_LOG_INFO);

		$list = [];
		$index = $fulltextservice->Indexer();
		if($index) {
			if($terms = $index->terms($query, 'title')) {
				foreach($terms as $term)
					$list[] = $term->text;
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
			'settings'=>array('update_checking'=>array('backend_setting'=>'default')),
		);
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
			$searchresult = $lucenesearch->search('', array('record_type'=>['document'], 'user'=>[$userobj->getLogin()], 'startFolder'=>$startfolder, 'rootFolder'=>$startfolder), array('limit'=>20), array());
			if($searchresult === false) {
				return $response->withStatus(500);
			} else {
				$recs = array();
				$facets = $searchresult['facets'];
				$logger->log(var_export($facets, true), PEAR_LOG_INFO);
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
		require_once "SeedDMS/Preview.php";

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

	function documents_download($request, $response, $args) { /* {{{ */
		require_once "SeedDMS/Preview.php";
		$dms = $this->container->dms;
		$userobj = $this->container->userobj;
		$settings = $this->container->config;
		$conversionmgr = $this->container->conversionmgr;
		$logger = $this->container->logger;

		if (!isset($args['id']) || !$args['id'])
			return $response->withStatus(404);
	
		$logger->log('Download doc '.$args['id'], PEAR_LOG_INFO);
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

		if(!empty($settings->_extensions['paperless']['usehomefolder'])) {
			if(!($mfolder = $dms->getFolder((int) $userobj->getHomeFolder())))
				$mfolder = $dms->getFolder($settings->_rootFolderID);
		} elseif(!isset($settings->_extensions['paperless']['rootfolder']) || !($mfolder = $dms->getFolder($settings->_extensions['paperless']['rootfolder'])))
			$mfolder = $dms->getFolder($settings->_rootFolderID);
		if($mfolder) {
			if($mfolder->getAccessMode($userobj) < M_READWRITE)
				return $response->withStatus(403);

			$data = $request->getParsedBody();
//			$logger->log(var_export($data, true), PEAR_LOG_INFO);
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
				$logger->log('Upload failed: '.$errmsg, PEAR_LOG_NOTICE);
				return $response->withJson(getMLText('paperless_upload_failed'), 500);
			} else {
				$logger->log('Upload succeeded', PEAR_LOG_NOTICE);
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
			require_once("SeedDMS/Preview.php");
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
								$logger->log("Login with apikey '".$tmp[1]."' failed", PEAR_LOG_INFO);
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
						$logger->log("Login with basic authentication for '".$kk[0]."' failed", PEAR_LOG_INFO);
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
		$app->get('/api/documents/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':documents');
		$app->get('/api/correspondents/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':correspondents');
		$app->get('/api/document_types/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':document_types');
		$app->get('/api/saved_views/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':saved_views');
		$app->get('/api/storage_paths/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':storage_paths');
		$app->post('/api/documents/post_document/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':post_document');
		$app->get('/api/documents/{id}/preview/', \SeedDMS_ExtPaperless_RestAPI_Controller::class.':documents_download');
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

		return null;
	} /* }}} */

} /* }}} */

