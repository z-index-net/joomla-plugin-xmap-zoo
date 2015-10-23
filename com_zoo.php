<?php
/**
 * Updated for SEF by Matt Faulds (Trafalgar Design)
 *
 */

/**
 * @version 2.1.1TD
 * @package Joomla
 * @subpackage Xmap - Zoo Plugin
 * @copyright (C) 2010-2011 WDEVP
 * @license GNU/GPL
 */

defined('_JEXEC') or die;

class xmap_com_zoo {

	protected static $_menu_items;

	function prepareMenuItem(&$node) {
		$link_query = parse_url( $node->link );
		parse_str( html_entity_decode($link_query['query']), $link_vars);
		$component = JArrayHelper::getValue($link_vars, 'option', '');
		$view = JArrayHelper::getValue($link_vars,'view','');


		if ($component == 'com_zoo' && $view == 'frontpage' ) {
			$id = intval(JArrayHelper::getValue($link_vars,'id',0));
			if ( $id != 0 ) {
				$node->uid = 'zoo'.$id;
				$node->expandible = false;
			}
		}
	}

	function getTree( &$xmap, &$parent, &$params) {

		$link_query = parse_url( $parent->link );
		parse_str( html_entity_decode($link_query['query']), $link_vars );
		$view = JArrayHelper::getValue($link_vars,'view',0);

		$include_categories = JArrayHelper::getValue( $params, 'include_categories',1,'' );
		$include_categories = ( $include_categories == 1
				  || ( $include_categories == 2 && $xmap->view == 'xml')
				  || ( $include_categories == 3 && $xmap->view == 'html')
				  ||   $xmap->view == 'navigator');
		$params['include_categories'] = $include_categories;

		$include_items = JArrayHelper::getValue( $params, 'include_items',1,'' );
		$include_items = ( $include_items == 1
				  || ( $include_items == 2 && $xmap->view == 'xml')
				  || ( $include_items == 3 && $xmap->view == 'html')
				  ||   $xmap->view == 'navigator');
		$params['include_items'] = $include_items;

		$priority = JArrayHelper::getValue($params,'cat_priority',$parent->priority,'');
		$changefreq = JArrayHelper::getValue($params,'cat_changefreq',$parent->changefreq,'');
		if ($priority  == '-1')
			$priority = $parent->priority;
		if ($changefreq  == '-1')
			$changefreq = $parent->changefreq;

		$params['cat_priority'] = $priority;
		$params['cat_changefreq'] = $changefreq;

		$priority = JArrayHelper::getValue($params,'item_priority',$parent->priority,'');
		$changefreq = JArrayHelper::getValue($params,'item_changefreq',$parent->changefreq,'');
		if ($priority  == '-1')
			$priority = $parent->priority;

		if ($changefreq  == '-1')
			$changefreq = $parent->changefreq;

		$params['item_priority'] = $priority;
		$params['item_changefreq'] = $changefreq;

		self::getCategoryTree($xmap, $parent, $params);

	}

	function getCategoryTree ( &$xmap, &$parent, &$params) {
		$db = &JFactory::getDBO();

		// first we fetch what application we are talking about

		$menu =& JSite::getMenu();
		$menuparams = $menu->getParams($parent->id);
		$appid =  intval($menuparams->get('application', 0));

		// if selected, we print title category
		if ($params['include_categories']) {

			// we print title if there is any
		// commented out as non-functioning - Matt Faulds
		//	if ($params['categories_title'] != "" && $xmap->view == 'html') {
		//		echo "<".$params['categories_title_tag'].">".$params['categories_title']."</".$params['categories_title_tag'].">";
		//	}
			// get categories info from database
			$queryc = 'SELECT c.id, c.name '.
					 'FROM #__zoo_category c '.
					 ' WHERE c.application_id = '.$appid.' AND c.published=1 '.
					 ' ORDER by c.ordering';

			$db->setQuery($queryc);
			$cats = $db->loadObjectList();

			// now we print categories
			$xmap->changeLevel(1);
			foreach($cats as $cat) {

				// Added by Matt Faulds to allow SEF urls
				if(!($Itemid = self::_find('frontpage',$appid)->id)) {
					$Itemid = self::_find('category',$appid)->id;
				}

				$node = new stdclass;
				$node->id   = $parent->id;
				$node->uid  = $parent->uid .'c'.$cat->id;
				$node->name = $cat->name;
				$node->link = 'index.php?option=com_zoo&amp;task=category&amp;category_id='.$cat->id.'&amp;Itemid='.$Itemid;
				$node->priority   = $params['cat_priority'];
				$node->changefreq = $params['cat_changefreq'];
				$node->expandible = true;
				$xmap->printNode($node);
			}
			$xmap->changeLevel(-1);
		}

		if ($params['include_items'] ){

		// commented out as non-functioning - Matt Faulds
		//	if ($params['items_title'] != "" && $xmap->view == 'html') {
		//		echo "<".$params['items_title_tag'].">".$params['items_title']."</".$params['items_title_tag'].">";
		//	}

			// get items info from database
			// basically it select those items that are published now (publish_up is less then now, meaning it's in past)
			// and not unpublished yet (either not have publish_down date set, or that date is in future)
			$queryi =  'SELECT i.id, i.name, i.publish_up ,i.application_id'.
							' FROM #__zoo_item i'.
							' WHERE i.application_id= '.$appid.
							' AND DATEDIFF( i.publish_up, NOW( ) ) <=0'.
							' AND IF( i.publish_down >0, DATEDIFF( i.publish_down, NOW( ) ) >0, true )'.
							' ORDER BY i.publish_up';
			$db->setQuery($queryi);
			$items = $db->loadObjectList();

			// now we print items
			$xmap->changeLevel(1);
			foreach($items as $item) {

				// Added by Matt Faulds to allow SEF urls
				if(!($Itemid = self::_find('frontpage',$appid)->id) AND !($Itemid = self::_find('category',$appid)->id)) {
					$Itemid = self::_find('item',$appid)->id;
				}

				// if we are making news map, we should ignore items older then 3 days
				if ($xmap->isNews && strtotime($item->publish_up) < ($xmap->now - (3 * 86400))) {
                    continue;
                }
				$node = new stdclass;
				$node->id   = $parent->id;
				$node->uid  = $parent->uid .'i'.$item->id;
				$node->name = $item->name;
				$node->link = 'index.php?option=com_zoo&amp;task=item&amp;item_id='.$item->id.'&amp;Itemid='.$Itemid;
				$node->priority   = $params['item_priority'];
				$node->changefreq = $params['item_changefreq'];
				$node->expandible = true;
				$node->modified = strtotime($item->publish_up);
				$node->newsItem = 1; // if we are making news map and it get this far, it's news
				$xmap->printNode($node);

			}
			$xmap->changeLevel(-1);
		}
	}

	// Adapted from ZOO 2.5.10
	// Added by Matt Faulds to allow SEF urls
	protected function _find($type, $id) {

		// load config
		require_once(JPATH_ADMINISTRATOR.'/components/com_zoo/config.php');

		// get ZOO app
		$app = App::getInstance('zoo');

		if (self::$_menu_items == null) {
			$component_id = $app->joomla->isVersion('1.5') ? 'componentid' : 'component_id';
			$menu_items	= $app->object->create('JSite')->getMenu()->getItems($component_id, JComponentHelper::getComponent('com_zoo')->id);
			$menu_items = $menu_items ? $menu_items : array();

			self::$_menu_items = array_fill_keys(array('category', 'frontpage', 'item'), array());
			foreach($menu_items as $menu_item) {
				switch (@$menu_item->query['view']) {
					case 'frontpage':
						self::$_menu_items['frontpage'][$app->parameter->create($menu_item->params)->get('application')] = $menu_item;
						break;
					case 'category':
						self::$_menu_items['category'][$app->parameter->create($menu_item->params)->get('category')] = $menu_item;
						break;
					case 'item':
						self::$_menu_items['item'][$app->parameter->create($menu_item->params)->get('item_id')] = $menu_item;
						break;
				}
			}
		}

		return @self::$_menu_items[$type][$id];
	}

}
