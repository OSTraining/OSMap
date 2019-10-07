<?php
/**
 * @package   OSMap
 * @copyright 2007-2014 XMap - Joomla! Vargas - Guillermo Vargas. All rights reserved.
 * @copyright 2016-2019 Joomlashack.com. All rights reserved.
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 */

namespace Alledia\OSMap\Sitemap;

defined('_JEXEC') or die();

interface SitemapInterface
{
    /**
     * Traverse the sitemap items recursively and call the given callback,
     * passing each node as parameter.
     *
     * @param callable $callback
     *
     * @return void
     */
    public function traverse($callback);
}
