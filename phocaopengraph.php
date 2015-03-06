<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
jimport( 'joomla.plugin.plugin' );

class plgContentPhocaOpenGraph extends JPlugin
{
	public $pluginNr = 0;
	
	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
		$this->loadLanguage();
		
	}
	
	public function onContentAfterDisplay($context, &$row, &$params, $page=0) {
		
		$app 	= JFactory::getApplication();
		$view	= JRequest::getCmd('view');// article, category, featured
		
		if ($app->getName() != 'site') { return;}
		if ($view == 'featured' && $this->params->get('displayf', 1) == 0) { return; }
		if ($view == 'category' && $this->params->get('displayc', 1) == 0) { return; }
		if ((int)$this->pluginNr > 0) { return; } // Second instance in featured view or category view 
		
		$document 	= JFactory::getDocument();
		$config 	= JFactory::getConfig();
		
		// We need help variables as we cannot change the $row variable - such then will influence global settings
		$thisDesc 	= $row->metadesc;
		$thisTitle	= $row->title;
		
		
		if ($view == 'featured' && $this->pluginNr == 0) {
			$suffix 		= 'f';// Data from first article will be set
			$this->pluginNr = 1;
		} else if ($view == 'category' && $this->pluginNr == 0) {
			$suffix 		= 'c';// Data from first article will be set
			if (isset($row->catid) && (int)$row->catid > 0) {
				$db = JFactory::getDBO();
				$query = ' SELECT c.metadesc, c.title FROM #__categories AS c'
			    .' WHERE c.id = '.(int) $row->catid . ' LIMIT 1';
				$db->setQuery($query);
				$cItem = $db->loadObjectList();
				
				if (isset($cItem[0]->metadesc) && $cItem[0]->metadesc != '') {
					//$row->metadesc 	= $cItem[0]->metadesc; We cannot influence global variable
					$thisDesc		= $cItem[0]->metadesc;
				}
				if (isset($cItem[0]->title) && $cItem[0]->title != '') {
					//$row->title 	= $cItem[0]->title; We cannot influence global variable
					$thisTitle		= $cItem[0]->title;
				}
			}
			$this->pluginNr = 1;
		} else {
			$suffix 		= '';
		}
		
		// Title
		if ($this->params->get('title'.$suffix, '') != '') {
			$document->setMetadata('og:title', htmlspecialchars($this->params->get('title'.$suffix, '')));
		} else if ($row->title != '') {
			$document->setMetadata('og:title', htmlspecialchars($thisTitle));
		}
		
		// Type
		$document->setMetadata('og:type', $this->params->get('type'.$suffix, 'article'));
		
		// Image
		if ($this->params->get('image'.$suffix, '') != '') {
			$document->setMetadata('og:image', JURI::base(false).htmlspecialchars($this->params->get('image'.$suffix, '')));
		} else {
			// Try to find image in article
			$img = 0;
			$fulltext = '';
			if (isset($row->fulltext) && $row->fulltext != '') {
				$fulltext = $row->fulltext;
			}
			$introtext = '';
			if (isset($row->introtext) && $row->introtext != '') {
				$introtext = $row->introtext;
			}
			$content = $introtext . $fulltext;
			preg_match('/< *img[^>]*src *= *["\']?([^"\']*)/i', $content, $src);
			if (isset($src[1]) && $src[1] != '') {
				$document->setMetadata('og:image', JURI::base(false).htmlspecialchars($src[1]));
				$img = 1;
			}
			
			// Try to find image in images/phocaopengraph folder
			if ($img == 0) {
				if (isset($row->id) && (int)$row->id > 0) {
					
					jimport( 'joomla.filesystem.file' );
					$imgPath	= '';
					$path 		= JPATH_ROOT . DS . 'images' . DS . 'phocaopengraph' . DS;
					if (JFile::exists($path . DS . (int)$row->id.'.jpg')) {
						$imgPath = JURI::base(false) . 'images/phocaopengraph/'.(int)$row->id.'.jpg';
					} else if (JFile::exists($path . DS . (int)$row->id.'.png')) {
						$imgPath = JURI::base(false) . 'images/phocaopengraph/'.(int)$row->id.'.png';
					} else if (JFile::exists($path . DS . (int)$row->id.'.gif')) {
						$imgPath = JURI::base(false) . 'images/phocaopengraph/'.(int)$row->id.'.gif';
					}
					
					if ($imgPath != '') {
						$document->setMetadata('og:image', $imgPath);
					}
				}
			}
		}
		
		//URL
		if ($this->params->get('url'.$suffix, '') != '') {
			$document->setMetadata('og:url', htmlspecialchars($this->params->get('url'.$suffix, '')));
		} else {	
			//} else if ((int)$row->id > 0) {
			//$url = ContentHelperRoute::getArticleRoute($row->id);
			//$document->setMetadata('og:url', JRoute::_($url));
			$uri 	= JFactory::getURI();
			$document->setMetadata('og:url', $uri->toString());
		}
		
		
		// Site Name
		if ($this->params->get('site_name'.$suffix, '') != '') {
			$document->setMetadata('og:site_name', htmlspecialchars($this->params->get('site_name'.$suffix, '')));
		} else if ($thisTitle != '') {
			$document->setMetadata('og:site_name', htmlspecialchars($config->get('sitename')));
		}
		
		// Description
		if ($this->params->get('description'.$suffix, '') != '') {
			$document->setMetadata('og:description', htmlspecialchars($this->params->get('description'.$suffix, '')));
		} else if (isset($thisDesc) && $thisDesc != '') {
			$document->setMetadata('og:description', htmlspecialchars($thisDesc));
		}  else if ($thisTitle != '') {
			$document->setMetadata('og:description', htmlspecialchars($config->get('MetaDesc')));
		}
		
		// FB App ID - COMMON
		if ($this->params->get('app_id', '') != '') {
			$document->setMetadata('fb:app_id', htmlspecialchars($this->params->get('app_id', '')));
		}
		
		// Other
		if ($this->params->get('other', '') != '') {
			$other = explode (';', $this->params->get('other', ''));
			if (!empty($other)) {
				foreach ($other as $v) {
					if ($v != '') {
						$vother = explode ('=', $v);
						if(!empty($vother)) {
							if (isset($vother[0]) && isset($vother[1])) {
								$document->setMetadata(htmlspecialchars(strip_tags($vother[0])), htmlspecialchars($vother[1]));
							}
						}
					}
				
				}
			}
		}

	}
}
?>