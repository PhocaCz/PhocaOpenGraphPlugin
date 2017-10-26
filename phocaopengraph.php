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
	public $pluginNr 		= 0;
	public $twitterEnable 	= 0;
	
	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}
	
	public function renderTag($name, $value, $type = 1) {
		
		$document 	= JFactory::getDocument();
		
		// OG
		if ($type == 1) {
			$document->setMetadata(htmlspecialchars($name), htmlspecialchars($value));
		} else {
			$document->addCustomTag('<meta property="'.htmlspecialchars($name).'" content="' . htmlspecialchars($value) . '" />');
		}
		
		// Tweet with cards
		if ($this->twitterEnable == 1) {
			if ($name == 'og:title') {
				$document->setMetadata('twitter:title', htmlspecialchars($value));
			}
			if ($name == 'og:description') {
				$document->setMetadata('twitter:description', htmlspecialchars($value));
			}
			if ($name == 'og:image') {
				$document->setMetadata('twitter:image', htmlspecialchars($value));
			}
		}
	}
	
	public function onContentAfterDisplay($context, &$row, &$params, $page=0) {
		
		$app 	= JFactory::getApplication();
		$view	= $app->input->get('view');// article, category, featured
		$option	= $app->input->get('option');// article, category, featured
		$itemid	= $app->input->get('Itemid');

		
		/*if ($view == 'article' && $app->input->get('id') != $row->id) {
			// Page displays article so we want to set metadata for main content article only
			return;
		}*/
		if ($app->getName() != 'site') { return;}
		if ($view == 'tag') { return; }
		if ($view == 'featured' && $this->params->get('displayf', 1) == 0) { return; }
		if ($view == 'category' && $this->params->get('displayc', 1) == 0) { return; }
		
		
		if ((int)$this->pluginNr > 0) { return; } // Second instance in featured view or category view 
	
		$itemids 				= $this->params->get('disable_menu_items', '');
		$options 				= $this->params->get('disable_options', '');
		$views 					= $this->params->get('disable_views', '');
		$rSD 					= $this->params->get('remove_strings_description', '');
		$parameterImage 		= $this->params->get('parameter_image', 1);
		$this->twitterEnable 	= $this->params->get('twitter_enable', 0);
		$twitterCard 			= $this->params->get('twitter_card', 'summary_large_image');
		
		
		if ($this->twitterEnable == 1) {
			$this->renderTag('twitter:card', htmlspecialchars($twitterCard), 1);
			
			if ($this->params->get('twitter_site', '') != '') {
				$this->renderTag('twitter:site', $this->params->get('twitter_site', ''), 1);
			}
			
			if ($this->params->get('twitter_site', '') != '') {
				$this->renderTag('twitter:creator', $this->params->get('twitter_creator', ''), 1);
			}	
		}
		
		if ($itemids != '') {
			$itemidsA =  explode(',', $itemids);
			if (!empty($itemidsA)) {
				foreach ($itemidsA as $k => $v) {
					if ($itemid == $v) {
						return;// don't apply it in this view
					}
				}
			}
		}
		
		if ($options != '') {
			$optionsA =  explode(',', $options);
			if (!empty($optionsA)) {
				foreach ($optionsA as $k => $v) {
					if ($option == $v) {
						return;// don't apply it in this view
					}
				}
			}
		}
		
		if ($views != '') {
			$viewsA =  explode(',', $views);
			if (!empty($viewsA)) {
				foreach ($viewsA as $k => $v) {
					if ($view == $v) {
						return;// don't apply it in this view
					}
				}
			}
		}
		
		$document 	= JFactory::getDocument();
		$config 	= JFactory::getConfig();
		$type		= $this->params->get('render_type', 1);
		$desc_intro	= $this->params->get('desc_intro', 0);
		
		// We need help variables as we cannot change the $row variable - such then will influence global settings
		$thisDesc 	= '';
		$thisTitle	= '';
		$thisKey	= '';
		$thisImg	= '';
		
		if (isset($row->metadesc)) {
			$thisDesc 	= $row->metadesc;
		}
		if (isset($row->title)) {
			$thisTitle	= $row->title;
		}
		if (isset($row->metakey)) {
			$thisKey	= $row->metakey;
		}

		
		if ($view == 'featured' && $this->pluginNr == 0) {
			$suffix 		= 'f';// Data from first article will be set
			$this->pluginNr = 1;
		} else if ($view == 'category' && $this->pluginNr == 0) {
			$suffix 		= 'c';// Data from first article will be set
			if (isset($row->catid) && (int)$row->catid > 0) {
				$db = JFactory::getDBO();
				$query = ' SELECT c.metadesc, c.metakey, c.params, c.title FROM #__categories AS c'
			    .' WHERE c.id = '.(int) $row->catid . ' LIMIT 1';
				$db->setQuery($query);
				$cItem = $db->loadObjectList();
				
				if (!empty($cItem[0]->params)) {
					$registry = new JRegistry;
					$registry->loadString($cItem[0]->params);
					$pC = $registry->toArray();
					if (isset($pC['image']) && $pC['image'] != '') {
						$thisImg =  $pC['image'];
					}
					
				}
				
				if (isset($cItem[0]->metadesc) && $cItem[0]->metadesc != '') {
					//$row->metadesc 	= $cItem[0]->metadesc; We cannot influence global variable
					$thisDesc		= $cItem[0]->metadesc;
				}
				if (isset($cItem[0]->title) && $cItem[0]->title != '') {
					//$row->title 	= $cItem[0]->title; We cannot influence global variable
					$thisTitle		= $cItem[0]->title;
				}
				if (isset($cItem[0]->metakey) && $cItem[0]->metakey != '') {
					//$row->title 	= $cItem[0]->title; We cannot influence global variable
					$thisKey		= $cItem[0]->metakey;
				}
			}
			$this->pluginNr = 1;
		} else {
			$suffix 		= '';
		}
		
		// Title
		if ($this->params->get('title'.$suffix, '') != '') {
			$this->renderTag('og:title', $this->params->get('title'.$suffix, ''), $type);
		} else if (isset($row->title) && $row->title != '') {
			$this->renderTag('og:title', $thisTitle, $type);
		}
		
		// Type
		$this->renderTag('og:type', $this->params->get('type'.$suffix, 'article'), $type);
		
		// Image
		$pictures = '';
		if (isset($row->images)) {
			$pictures = json_decode($row->images);
		}

		$imgSet = 0;
		
		if ($this->params->get('image'.$suffix, '') != '' && $parameterImage == 1) {
			$this->renderTag('og:image', JURI::base(false).$this->params->get('image'.$suffix, ''), $type);
			$imgSet = 1;
		} else if ($thisImg != ''){
			$this->renderTag('og:image', JURI::base(false).$thisImg, $type);
			$imgSet = 1;
		} else if (isset($pictures->{'image_intro'}) && $pictures->{'image_intro'} != '') {
			$this->renderTag('og:image', JURI::base(false).$pictures->{'image_intro'}, $type);
			$imgSet = 1;
		} else if (isset($pictures->{'image_fulltext'}) && $pictures->{'image_fulltext'} != '') {
			$this->renderTag('og:image', JURI::base(false).$pictures->{'image_fulltext'}, $type);
			$imgSet = 1;
		} else {
			// Try to find image in article
			
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
				
				$absU = 0;
				// Test if this link is absolute http:// then do not change it
				$pos1 			= strpos($src[1], 'http://');
				if ($pos1 === false) {
				} else {
					$absU = 1;
				}
				
				// Test if this link is absolute https:// then do not change it
				$pos2 			= strpos($src[1], 'https://');
				if ($pos2 === false) {
				} else {
					$absU = 1;
				}

				
				if ($absU == 1) {
					$linkImg = $src[1];
				} else {
					$linkImg = JURI::base(false).$src[1];
					if ($src[1][0] == '/') {
						$myURI = new \Joomla\Uri\Uri(JURI::base(false));
						$myURI->setPath($src[1]);
						$linkImg = $myURI->toString();
					} else {
						$linkImg = JURI::base(false).$src[1];
					}
				}
				
				$this->renderTag('og:image', $linkImg, $type);
				//$this->renderTag('og:image', JURI::base(false).$src[1], $type);
				$imgSet = 1;
			}
			
			// Try to find image in images/phocaopengraph folder
			if ($imgSet == 0) {
				if (isset($row->id) && (int)$row->id > 0) {
					
					jimport( 'joomla.filesystem.file' );
					$imgPath	= '';
					$path 		= JPATH_ROOT . '/images/phocaopengraph/';
					if (JFile::exists($path . '/' . (int)$row->id.'.jpg')) {
						$imgPath = JURI::base(false) . 'images/phocaopengraph/'.(int)$row->id.'.jpg';
					} else if (JFile::exists($path . '/' . (int)$row->id.'.png')) {
						$imgPath = JURI::base(false) . 'images/phocaopengraph/'.(int)$row->id.'.png';
					} else if (JFile::exists($path . '/' . (int)$row->id.'.gif')) {
						$imgPath = JURI::base(false) . 'images/phocaopengraph/'.(int)$row->id.'.gif';
					}
					
					if ($imgPath != '') {
						$this->renderTag('og:image', $imgPath, $type);
						$imgSet = 1;
					}
				}
			}
		}

		// If still image not set and parameter Image is set as last, then try to add the parameter image
		if ($imgSet == 0 && $this->params->get('image'.$suffix, '') != '' && $parameterImage == 0) {
			$this->renderTag('og:image', JURI::base(false).$this->params->get('image'.$suffix, ''), $type);
		}
		
		// END IMAGE
		
		//URL
		if ($this->params->get('url'.$suffix, '') != '') {
			$this->renderTag('og:url', $this->params->get('url'.$suffix, ''), $type);
		} else {	
			//} else if ((int)$row->id > 0) {
			//$url = ContentHelperRoute::getArticleRoute($row->id);
			//$document->setMetadata('og:url', JRoute::_($url));
			$uri 	= JFactory::getURI();
			$this->renderTag('og:url', $uri->toString(), $type);
		}
		
		
		// Site Name
		if ($this->params->get('site_name'.$suffix, '') != '') {
			$this->renderTag('og:site_name', $this->params->get('site_name'.$suffix, ''), $type);
		} else if ($thisTitle != '') {
			$this->renderTag('og:site_name', $config->get('sitename'), $type);
		}
		
		
		// Description

		if ($this->params->get('description'.$suffix, '') != '') { // description in params
			$this->renderTag('og:description', $this->params->get('description'.$suffix, ''), $type);
		} else if (isset($thisDesc) && $thisDesc != '') { // article meta description
			$this->renderTag('og:description', $thisDesc, $type);
		} else if ($this->params->get('menu-meta_description') != '') { // menu link meta description
			$this->renderTag('og:description', $this->params->get('menu-meta_description'), $type);
		} else if (isset($row->introtext) && $row->introtext != '' && $desc_intro == 1) { // artcle introtext
			
			$iTD = $row->introtext;
			$iTD = preg_replace('#(<code.*?>).*?(</code>)#', '$1$2', $iTD);
			$iTD = preg_replace('#(<pre.*?>).*?(</pre>)#', '$1$2', $iTD);
			$iTD = preg_replace('#<br.*?>#', ' ', $iTD);
			$iTD = strip_tags($iTD);
			$iTD = str_replace("\r\n", ' ', $iTD);
			$iTD = str_replace("\n", ' ', $iTD);
			$iTD = str_replace("\n", ' ', $iTD);
			
			// Remove every possible plugin code
			$iTD = preg_replace("/\{[^}]+\}/","",$iTD);
			$iTD = preg_replace("/\[[^]]+\]/","",$iTD);
			$iTD = preg_replace("/\([^)]+\)/","",$iTD);
			
			if ($rSD != '') {
			$rSDA =  explode(',', $rSD);
			if (!empty($rSDA)) {
				foreach ($rSDA as $k => $v) {
					$iTD = str_replace($v, "", $iTD);
				}
			}
		}
			
			$this->renderTag('og:description', $iTD, $type);
		} else if ($config->get('MetaDesc') != '') { // site meta desc
			$this->renderTag('og:description', $config->get('MetaDesc'), $type);
		}
		
		// FB App ID - COMMON
		if ($this->params->get('app_id', '') != '') {
			$this->renderTag('fb:app_id', $this->params->get('app_id', ''), $type);
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
								$this->renderTag(strip_tags($vother[0]), $vother[1], $type);
							}
						}
					}
				
				}
			}
		}

	}
}
?>