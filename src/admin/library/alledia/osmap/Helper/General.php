<?php
/**
 * @package   OSMap
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2007-2014 XMap - Joomla! Vargas - Guillermo Vargas. All rights reserved.
 * @copyright 2016-2021 Joomlashack.com. All rights reserved.
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 *
 * This file is part of OSMap.
 *
 * OSMap is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * OSMap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OSMap.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Alledia\OSMap\Helper;

use Alledia\OSMap;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

defined('_JEXEC') or die();

abstract class General
{
    protected static $plugins = [];

    /**
     * Build the submenu in admin if needed. Triggers the
     * onAdminSubmenu event for component addons to attach
     * their own admin screens.
     *
     * The expected response must be an array
     * [
     *    "text" => Static language string,
     *    "link" => Link to the screen
     *    "view" => unique view name
     * ]
     *
     * @param string $viewName
     *
     * @return void
     * @throws \Exception
     */
    public static function addSubmenu($viewName)
    {
        $submenus = [
            [
                'text' => 'COM_OSMAP_SUBMENU_SITEMAPS',
                'link' => 'index.php?option=com_osmap&view=sitemaps',
                'view' => 'sitemaps'
            ],
            [
                'text' => 'COM_OSMAP_SUBMENU_EXTENSIONS',
                'link' => 'index.php?option=com_plugins&view=plugins&filter_folder=osmap',
                'view' => 'extensions'
            ]
        ];

        $events = OSMap\Factory::getContainer()->getEvents();
        $events->trigger('onOSMapAddAdminSubmenu', [&$submenus]);

        if (!empty($submenus)) {
            foreach ($submenus as $submenu) {
                if (is_array($submenu)) {
                    \JHtmlSidebar::addEntry(
                        Text::_($submenu['text']),
                        $submenu['link'],
                        $viewName == $submenu['view']
                    );
                }
            }
        }
    }

    /**
     * Returns the sitemap type checking the input.
     * The expected types:
     *   - standard
     *   - images
     *   - news
     *
     * @return string
     * @throws \Exception
     */
    public static function getSitemapTypeFromInput()
    {
        $input = OSMap\Factory::getContainer()->input;

        if ((bool)$input->getString('images', 0)) {
            return 'images';
        }

        if ((bool)$input->getString('news', 0)) {
            return 'news';
        }

        return 'standard';
    }

    /**
     * Returns a list of plugins from the database. Legacy plugins from XMap
     * will be returned first, than, OSMap plugins. Always respecting the
     * ordering.
     *
     * @return array
     * @throws \Exception
     */
    public static function getPluginsFromDatabase()
    {
        $db = OSMap\Factory::getContainer()->db;

        // Get all the OSMap and XMap plugins. Get first the XMap plugins and
        // than OSMap. Always respecting the ordering.
        $query = $db->getQuery(true)
            ->select([
                'folder',
                'params',
                'element'
            ])
            ->from('#__extensions')
            ->where('type = ' . $db->quote('plugin'))
            ->where('folder IN (' . $db->quote('osmap') . ',' . $db->quote('xmap') . ')')
            ->where('enabled = 1')
            ->order('folder DESC, ordering');

        return $db->setQuery($query)->loadObjectList();
    }

    /**
     * Returns true if the plugin is compatible with the given option
     *
     * @param object $plugin
     * @param string $option
     *
     * @return bool
     */
    protected static function checkPluginCompatibilityWithOption($plugin, $option)
    {
        if (empty($option)) {
            return false;
        }

        $path       = JPATH_PLUGINS . '/' . $plugin->folder . '/' . $plugin->element . '/' . $plugin->element . '.php';
        $compatible = false;

        // Check if the plugin file exists
        if (File::exists($path)) {
            // Legacy plugins have plugins element equals the option. But it still doesn't mean is compatible with
            // the current content/option
            $isLegacy = $plugin->element === $option;

            $className = $isLegacy ? ($plugin->folder . '_' . $option) :
                ('Plg' . ucfirst($plugin->folder) . ucfirst($plugin->element));

            // If the class wasn't loaded yet, load it.
            if (!class_exists($className)) {
                require_once $path;
            }

            // Instantiate the plugin if the class exists
            if (class_exists($className)) {
                $dispatcher = \JEventDispatcher::getInstance();
                $instance   = method_exists($className, 'getInstance') ?
                    $className::getInstance() : new $className($dispatcher);

                // If is legacy, we know it is compatible since the element and option were already validated
                $compatible = $isLegacy
                    || (
                        method_exists($instance, 'getComponentElement')
                        && $instance->getComponentElement() === $option
                    );

                if ($compatible) {
                    $plugin->instance  = $instance;
                    $plugin->className = $className;
                    $plugin->isLegacy  = $isLegacy;
                    $plugin->params    = new Registry($plugin->params);
                }
            }
        }

        return $compatible;
    }

    /**
     * Gets the plugins according to the given option/component
     *
     * @param string $option
     *
     * @return object
     * @throws \Exception
     */
    public static function getPluginsForComponent($option)
    {
        // Check if there is a cached list of plugins for this option
        if (!empty(static::$plugins) && isset(static::$plugins[$option])) {
            return static::$plugins[$option];
        }

        $compatiblePlugins = [];

        $plugins = static::getPluginsFromDatabase();

        if (!empty($plugins)) {
            jimport('joomla.filesystem.file');

            foreach ($plugins as $plugin) {
                if (static::checkPluginCompatibilityWithOption($plugin, $option)) {
                    $compatiblePlugins[] = $plugin;
                }
            }
        }

        static::$plugins[$option] = $compatiblePlugins;

        return static::$plugins[$option];
    }

    /**
     * Extracts pagebreaks from the given text. Returns a list of subnodes
     * related to each pagebreak.
     *
     * @param string $text
     * @param string $baseLink
     * @param string $uid
     *
     * @return array
     */
    public static function getPagebreaks($text, $baseLink, $uid = '')
    {
        $matches = $subnodes = [];

        if (preg_match_all(
            '/<hr\s*[^>]*?(?:(?:\s*alt="(?P<alt>[^"]+)")|(?:\s*title="(?P<title>[^"]+)"))+[^>]*>/i',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            $i = 2;
            foreach ($matches as $match) {
                if (strpos($match[0], 'class="system-pagebreak"') !== false) {
                    $link = $baseLink . '&limitstart=' . ($i - 1);

                    if (@$match['alt']) {
                        $title = stripslashes($match['alt']);
                    } elseif (@$match['title']) {
                        $title = stripslashes($match['title']);
                    } else {
                        $title = Text::sprintf('Page #', $i);
                    }

                    $subnode             = new \stdClass();
                    $subnode->name       = $title;
                    $subnode->uid        = $uid . '.' . ($i - 1);
                    $subnode->expandible = false;
                    $subnode->link       = $link;

                    $subnodes[] = $subnode;

                    $i++;
                }
            }

        }

        return $subnodes;
    }

    /**
     * Returns true if the given date is empty, considering not only as string,
     * but integer, boolean or date.
     *
     * @param string $date
     *
     * @return bool
     * @throws \Exception
     */
    public static function isEmptyDate($date)
    {
        $db = OSMap\Factory::getContainer()->db;

        $invalidDates = [
            '',
            null,
            false,
            0,
            '0',
            -1,
            '-1',
            $db->getNullDate(),
            '0000-00-00'
        ];

        return in_array($date, $invalidDates);
    }

    /**
     * Returns an array or string with the authorised view levels for public or
     * guest users. If the param $asString is true, it returns a string as CSV.
     * If false, an array. If the current view was called by the admin to edit
     * the sitemap links, we returns all access levels to give access for everything.
     *
     * @param bool $asString
     *
     * @return mixed
     * @throws \Exception
     */
    public static function getAuthorisedViewLevels($asString = true)
    {
        $container = OSMap\Factory::getContainer();
        $levels    = [];

        // Check if we need to return all levels, if it was called from the admin to edit the link list
        if ($container->input->get('view') === 'adminsitemapitems') {
            $db = OSMap\Factory::getDbo();

            // Get all access levels
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__viewlevels'));
            $db->setQuery($query);
            $rows = $db->loadRowList();

            foreach ($rows as $row) {
                $levels[] = $row[0];
            }
        } else {
            // Only shows returns the level for the current user
            $levels = (array)OSMap\Factory::getUser()->getAuthorisedViewLevels();
        }

        if ($asString) {
            $levels = implode(',', $levels);
        }

        return $levels;
    }

    /**
     * Make sure the appropriate component language files are loaded
     *
     * @param string $option
     * @param string $adminPath
     * @param string $sitePath
     *
     * @return void
     * @throws \Exception
     */
    public static function loadOptionLanguage($option, $adminPath, $sitePath)
    {
        $app = OSMap\Factory::getApplication();

        switch ($app->getName()) {
            case 'administrator':
                OSMap\Factory::getLanguage()->load($option, $adminPath);
                break;

            case 'site':
                OSMap\Factory::getLanguage()->load($option, $sitePath);
                break;
        }
    }

    /**
     * Check if the needed method is static or no and call it in the proper
     * way, avoiding Strict warnings in 3rd party plugins. It returns the
     * result of the called method.
     *
     * @param string $class
     * @param object $instance
     * @param string $method
     * @param array  $params
     *
     * @return mixed
     * @throws \Exception
     */
    public static function callUserFunc($class, $instance, $method, array $params = [])
    {
        $reflection = new \ReflectionMethod($class, $method);
        $result     = null;

        if ($reflection->isStatic()) {
            $result = call_user_func_array([$class, $method], $params);
        } else {
            $result = call_user_func_array([$instance, $method], $params);
        }

        return $result;
    }
}
