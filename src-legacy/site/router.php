<?php
/**
 * @package   OSMap
 * @copyright 2007-2014 XMap - Joomla! Vargas. All rights reserved.
 * @copyright 2016 Open Source Training, LLC. All rights reserved..
 * @author    Guillermo Vargas <guille@vargas.co.cr>
 * @author    Alledia <support@alledia.com>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * This file is part of OSMap.
 *
 * OSMap is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * OSMap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OSMap. If not, see <http://www.gnu.org/licenses/>.
 */

defined('_JEXEC') or die('Restricted access');

/**
 * Build the route for the com_content component
 *
 * @param    array    An array of URL arguments
 *
 * @return    array    The URL arguments to use to assemble the subsequent URL.
 */
function OSMapBuildRoute(&$query)
{
    $segments = array();

    // Get a menu item based on Itemid or currently active
    $menu = JFactory::getApplication()->getMenu();

    if (empty($query['Itemid'])) {
        $menuItem = $menu->getActive();
    } else {
        $menuItem = $menu->getItem($query['Itemid']);
    }

    $itemId = empty($menuItem->query['id']) ? null : $menuItem->query['id'];

    if (!empty($query['Itemid'])) {
        unset($query['view']);
        unset($query['id']);
    } else {
        if (!empty($query['view'])) {
            $segments[] = $query['view'];
        }
    }

    if (isset($query['id'])) {
        if (empty($query['Itemid'])) {
            $segments[] = $query['id'];
        } else {
            if (isset($menuItem->query['id'])) {
                if ($query['id'] != $itemId) {
                    $segments[] = $query['id'];
                }
            } else {
                $segments[] = $query['id'];
            }
        }

        unset($query['id']);
    }

    if (isset($query['layout'])) {
        if (!empty($query['Itemid']) && isset($menuItem->query['layout'])) {
            if ($query['layout'] == $menuItem->query['layout']) {
                unset($query['layout']);
            }
        } else {
            if ($query['layout'] == 'default') {
                unset($query['layout']);
            }
        }
    }

    return $segments;
}

/**
 * Parse the segments of a URL.
 *
 * @param    array    The segments of the URL to parse.
 *
 * @return    array    The URL attributes to be used by the application.
 */
function OSMapParseRoute($segments)
{
    $vars = array();

    //Get the active menu item.
    $item = JFactory::getApplication()->getMenu()->getActive();

    // Count route segments
    $count = count($segments);

    // Standard routing for articles.
    if (!isset($item)) {
        $vars['view'] = $segments[0];
        $vars['id']   = $segments[$count - 1];

        return $vars;
    }

    $vars['view'] = $item->query['view'];
    $vars['id']   = $item->query['id'];

    return $vars;
}