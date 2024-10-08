<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

defined( '_JEXEC' ) or die( 'Restricted access' );
jimport( 'joomla.plugin.plugin' );


class plgContentPhocaOpenGraph extends CMSPlugin
{
	public $pluginNr 		= 0;
	public $twitterEnable 	= 0;

	public function __construct(& $subject, $config) {

		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	// https://github.com/joomla/joomla-cms/issues/35871
	function realCleanImageUrl($img) {

		$imgClean = HTMLHelper::cleanImageURL($img);
		if ($imgClean->url != '') {
			$img =  $imgClean->url;
		}
		return $img;
	}

	public function setImage($image) {



		$change_svg_to_png 		= $this->params->get('change_svg_to_png', 0);
		$linkImg 				= $image;

		$absU = 0;
		// Test if this link is absolute http:// then do not change it
		$pos1 			= strpos($image, 'http://');
		if ($pos1 === false) {
		} else {
			$absU = 1;
		}

		// Test if this link is absolute https:// then do not change it
		$pos2 			= strpos($image, 'https://');
		if ($pos2 === false) {
		} else {
			$absU = 1;
		}


		if ($absU == 1) {
			$linkImg = $image;
		} else {
			$linkImg = Uri::base(false).$image;

			if ($image[0] == '/') {
				$myURI = new Uri(Uri::base(false));
				$myURI->setPath($image);
				$linkImg = $myURI->toString();

			} else {
				$linkImg = Uri::base(false).$image;
			}

			if ($change_svg_to_png == 1) {
				$pathInfo 	= pathinfo($linkImg);
				if (isset($pathInfo['extension']) && $pathInfo['extension'] == 'svg') {
					$linkImg 	= $pathInfo['dirname'] .'/'. $pathInfo['filename'] . '.png';
				}
			}
		}

		return $linkImg;
	}

	public function renderTag($name, $value, $type = 1) {

		$document 				= Factory::getDocument();

		$display_itemprop_image 				= $this->params->get('display_itemprop_image', 0);

		// Encoded html tags can still be rendered, decode and strip tags first.
		$value                  = strip_tags(html_entity_decode($value));

		// OG
		$attributes = '';
		if ($name == 'og:image' && $display_itemprop_image == 1) {
			$attributes = ' itemprop="image"';
		}
		$typeString = 'name';
		if ($type != 1) {
			$typeString = 'property';
		}
		if ($attributes != '') {
			$document->addCustomTag('<meta '.$typeString.'="'.htmlspecialchars($name, ENT_COMPAT, 'UTF-8').'"'.$attributes.' content="' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '" />');
		} else {
			$document->setMetadata(htmlspecialchars($name, ENT_COMPAT, 'UTF-8'), htmlspecialchars($value, ENT_COMPAT, 'UTF-8'), $typeString);
		}

		// Tweet with cards
		if ($this->twitterEnable == 1) {
			if ($name == 'og:title') {
				$document->setMetadata('twitter:title', htmlspecialchars($value, ENT_COMPAT , 'UTF-8'));
			}
			if ($name == 'og:description') {
				$document->setMetadata('twitter:description', htmlspecialchars($value, ENT_COMPAT, 'UTF-8'));
			}
			if ($name == 'og:image') {
				$document->setMetadata('twitter:image', htmlspecialchars($value, ENT_COMPAT, 'UTF-8'));
			}
		}
	}

	public function onContentAfterDisplay($context, &$row, &$params, $page=0) {

		$app 	= Factory::getApplication();
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
		$test_article_layout_param = $this->params->get('test_article_layout_param', 0);

		// Prevent confusing open graph tags by some modules
		if ($test_article_layout_param == 1) {
			$articleLayout = $params->get('article_layout', '');
			if ($articleLayout == '') {
				return;
			}

		}


		if ($this->twitterEnable == 1) {
			$this->renderTag('twitter:card', $twitterCard, 1);

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

		$document 	= Factory::getDocument();
		$config 	= Factory::getConfig();
		$type		= $this->params->get('render_type', 2);
		$desc_intro	= $this->params->get('desc_intro', 0);
		$title_type	= $this->params->get('title_type', 1);
		$title_type_featured	= $this->params->get('title_type_featured', 3);
		$title_type_category	= $this->params->get('title_type_category', 1);

		$desc_type_featured	= $this->params->get('desc_type_featured', 3);
		$desc_type_category	= $this->params->get('desc_type_category', 1);

		$active = $app->getMenu()->getActive();





		// We need help variables as we cannot change the $row variable - such then will influence global settings
		$thisDesc 	= '';
		$thisTitle	= '';
		$thisKey	= '';
		$thisImg	= '';

		// Attributes
		$attribs = '';
		if (isset($row->attribs)) {
			//$attribs = json_decode($row->attribs);
			$attribs = (is_string($row->attribs) ? json_decode($row->attribs) : $row->attribs);
		}

		// will be set for each type
		/*if (isset($row->metadesc)) {
			$thisDesc 	= $row->metadesc;
		}*/

		if (isset($row->metakey)) {
			$thisKey	= $row->metakey;
		}


		// ARTICLE, FEATURED, CATEGORY
		if ($view == 'featured' && $this->pluginNr == 0) {

			// FEATURED
			// 3 MENU LINK TITLE
			// 2 NOTHING
			// 1 FIRST ARTICLE TITLE
			if ($title_type_featured == 2) {
				$thisTitle = '';
			} else if ($title_type_featured == 1) {
				if (isset($row->title)) {
					$thisTitle	= $row->title;
				}
			} else if ($title_type_featured == 3) {
				if (isset($document->title) && $document->title != '') {
					$thisTitle	= $document->title;
				}
			}

			if ($desc_type_featured == 2) {
				$thisDesc = -1;
			} else if ($desc_type_featured == 1) {
				if (isset($row->metadesc)) {
					$thisDesc	= $row->metadesc;
				}
			} else if ($desc_type_featured == 3) {
				if (isset($active->params) && !empty($active->params->get('menu-meta_description')) && $active->params->get('menu-meta_description') != '') {
					$thisDesc	= $active->params->get('menu-meta_description');
				}
			}

			$suffix 		= 'f';// Data from first article will be set
			$this->pluginNr = 1;
		} else if ($view == 'category' && $this->pluginNr == 0) {
			$suffix 		= 'c';// Data from first article will be set


			if (isset($row->catid) && (int)$row->catid > 0) {
				$db = Factory::getDBO();
				$query = ' SELECT c.metadesc, c.metakey, c.params, c.title FROM #__categories AS c'
			    .' WHERE c.id = '.(int) $row->catid . ' LIMIT 1';
				$db->setQuery($query);
				$cItem = $db->loadObjectList();

				if (!empty($cItem[0]->params)) {
					$registry = new Registry;
					$registry->loadString($cItem[0]->params);
					$pC = $registry->toArray();
					if (isset($pC['image']) && $pC['image'] != '') {
						$thisImg = $this->realCleanImageURL($pC['image']);
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

			// C A T E G O R Y
			// 3 MENU LINK TITLE
			// 2 CATEGORY PAGE TITLE NOT EXIST
			// 1 IF NOT 3 THEN CATEGORY TITLE
			if ($title_type_category == 3 || $thisTitle == '') {
				if (isset($document->title) && $document->title != '') {
					$thisTitle	= $document->title;
				}
			}

			if ($desc_type_category == 3 || $thisDesc == '') {
				if (isset($active->params) && !empty($active->params->get('menu-meta_description')) && $active->params->get('menu-meta_description') != '') {
					$thisDesc	= $active->params->get('menu-meta_description');
				}
			}

			$this->pluginNr = 1;
		} else {

			// A R T I C L E
			// 3 MENU LINK TITLE
			// 2 ARTICLE PAGE TITLE
			// 1 ARTICLE TITLE
			if ($title_type == 3) {
				if (isset($document->title) && $document->title != '') {
					$thisTitle	= $document->title;
				} else {
					// Fallback to standard title
					if (isset($row->title)) {
						$thisTitle	= $row->title;
					}
				}
			} else if ($title_type == 2) {
				if (isset($attribs->article_page_title) && $attribs->article_page_title != '') {
					$thisTitle	= $attribs->article_page_title;
				} else {
					// Fallback to standard title
					if (isset($row->title)) {
						$thisTitle	= $row->title;
					}
				}
			} else {
				if (isset($row->title)) {
					$thisTitle	= $row->title;
				}
			}

			$suffix 		= '';

			if (isset($row->metadesc)) {
				$thisDesc 	= $row->metadesc;
			}
		}


		// TITLE
		if ($this->params->get('title'.$suffix, '') != '') {
			$this->renderTag('og:title', $this->params->get('title'.$suffix, ''), $type);
		} else if (isset($thisTitle) && (int)$thisTitle == -1) {
			// FORCE NOTHING - FEATURED VIEW
		} else if (isset($thisTitle) && $thisTitle != '') {
			$this->renderTag('og:title', $thisTitle, $type);
		}

		// Type
		$this->renderTag('og:type', $this->params->get('type'.$suffix, 'article'), $type);

		// Image
		$pictures = '';
		if (isset($row->images)) {
			//$pictures = json_decode($row->images);
			$pictures = (is_string($row->images) ? json_decode($row->images) : $row->images);
		}



		$imgSet = 0;


		if ($this->params->get('image'.$suffix, '') != '' && $parameterImage == 1) {
			$this->renderTag('og:image', $this->setImage($this->realCleanImageURL($this->params->get('image'.$suffix, ''))), $type);
			$imgSet = 1;
		} else if ($thisImg != ''){
			$this->renderTag('og:image', $this->setImage($thisImg), $type);
			$imgSet = 1;
		} else if (isset($pictures->{'image_intro'}) && $pictures->{'image_intro'} != '') {
			$this->renderTag('og:image', $this->setImage($this->realCleanImageURL($pictures->{'image_intro'})), $type);
			$imgSet = 1;
		} else if (isset($pictures->{'image_fulltext'}) && $pictures->{'image_fulltext'} != '') {
			$this->renderTag('og:image', $this->setImage($this->realCleanImageURL($pictures->{'image_fulltext'})), $type);
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
				$this->renderTag('og:image', $this->setImage($this->realCleanImageURL($src[1])), $type);
				//$this->renderTag('og:image', Uri::base(false).$src[1], $type);
				$imgSet = 1;
			}

			// Try to find image in images/phocaopengraph folder
			if ($imgSet == 0) {
				if (isset($row->id) && (int)$row->id > 0) {

					jimport( 'joomla.filesystem.file' );
					$imgPath	= '';
					$path 		= JPATH_ROOT . '/images/phocaopengraph/';
					if (File::exists($path . '/' . (int)$row->id.'.jpg')) {
						$imgPath = 'images/phocaopengraph/'.(int)$row->id.'.jpg';
					} else if (File::exists($path . '/' . (int)$row->id.'.png')) {
						$imgPath = 'images/phocaopengraph/'.(int)$row->id.'.png';
					} else if (File::exists($path . '/' . (int)$row->id.'.gif')) {
						$imgPath = 'images/phocaopengraph/'.(int)$row->id.'.gif';
					}

					if ($imgPath != '') {
						$this->renderTag('og:image', $this->setImage($imgPath), $type);
						$imgSet = 1;
					}
				}
			}
		}

		// If still image not set and parameter Image is set as last, then try to add the parameter image
		if ($imgSet == 0 && $this->params->get('image'.$suffix, '') != '' && $parameterImage == 0) {
			$this->renderTag('og:image', $this->setImage($this->realCleanImageURL($this->params->get('image'.$suffix, ''))), $type);
		}

		// END IMAGE

		//URL
		if ($this->params->get('url'.$suffix, '') != '') {
			$this->renderTag('og:url', $this->params->get('url'.$suffix, ''), $type);
		} else {
			//} else if ((int)$row->id > 0) {
			//$url = ContentHelperRoute::getArticleRoute($row->id);
			//$document->setMetadata('og:url', JRoute::_($url));
			$uri = Uri::getInstance();
			$this->renderTag('og:url', $uri->toString(), $type);
		}


		// Site Name
		if ($this->params->get('site_name'.$suffix, '') != '') {
			$this->renderTag('og:site_name', $this->params->get('site_name'.$suffix, ''), $type);
		} else if ($thisTitle != '') {
			$this->renderTag('og:site_name', $config->get('sitename'), $type);
		}


		// Description - works for article only (category and featured includes $thisDesc by conditions set previously)
		if ($this->params->get('description'.$suffix, '') != '') { // description in params
			$this->renderTag('og:description', $this->params->get('description'.$suffix, ''), $type);
		} else if (isset($thisDesc) && (int)$thisDesc == -1) {
			// FORCE NOTHING - FEATURED VIEW
		} else if (isset($thisDesc) && $thisDesc != '') { // article meta description
			$this->renderTag('og:description', $thisDesc, $type);
		} else if (isset($active->params) && !empty($active->params->get('menu-meta_description')) && $active->params->get('menu-meta_description') != '') {// menu link meta description
			$this->renderTag('og:description', $active->params->get('menu-meta_description'), $type);

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

	/*
	 * Extra features
	 */

	public function onContentBeforeDisplay($context, &$row, &$params, $page=0) {


		$article_display_category_image = $this->params->get('article_display_category_image', 0);

		if ((int)$article_display_category_image > 0) {


			$app = Factory::getApplication();
			if ($app->isClient('site')) {

				$categoryImage = '';
				$categoryImageAlt = '';

				if (isset($row->catid) && (int)$row->catid > 0) {
					$db = Factory::getDBO();
					$query = ' SELECT c.params FROM #__categories AS c'
						. ' WHERE c.id = ' . (int)$row->catid . ' LIMIT 1';
					$db->setQuery($query);
					$cItem = $db->loadObjectList();

					if (!empty($cItem[0]->params)) {
						//$registry = new JRegistry;
						//$registry->loadString($cItem[0]->params);
						//$pC = $registry->toArray();
						$pC = json_decode($cItem[0]->params);

						if (isset($pC->image) && $pC->image != '') {
							$categoryImage = $this->realCleanImageURL($pC->image);
							if (isset($pC->image_alt) && $pC->image_alt != '') {
								$categoryImageAlt = $pC->image_alt;
							}
						}

					}
				}

				$images = new stdClass();

				if (isset($row->images)) {
					$images = json_decode($row->images);
				}

				if (!is_object($images)) {
					$images = new stdClass();
				}

				if ((int)$article_display_category_image == 1 || (int)$article_display_category_image == 3) {

					if ((!isset($images->image_fulltext) || (isset($images->image_fulltext) && $images->image_fulltext == '')) && $categoryImage != '') {
						$images->image_fulltext = $categoryImage;
						$images->image_fulltext_alt = $categoryImageAlt;

						$row->images = json_encode($images);
					}
				}

				if ((int)$article_display_category_image == 2 || (int)$article_display_category_image == 3) {
					if ((!isset($images->image_intro) || (isset($images->image_intro) && $images->image_intro == '')) && $categoryImage != '') {
						$images->image_intro 		= $categoryImage;
						$images->image_intro_alt 	= $categoryImageAlt;

						$row->images = json_encode($images);
					}
				}
			}
		}
	}
}
?>
