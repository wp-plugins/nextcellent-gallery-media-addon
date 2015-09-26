<?php
/**
 * --COPYRIGHT NOTICE------------------------------------------------------------------------------
 *
 * This file is part of NextCellent Media Library Addon.
 *
 * NextCellent Media Library Addon is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * NextCellent Media Library Addon is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NextCellent Media Library Addon.  If not, see <http://www.gnu.org/licenses/>.
 *
 * ------------------------------------------------------------------------------------------------
 *
 * @wordpress-plugin
 * Plugin Name:     NextCellent Media Library Addon
 * Plugin URI:      https://bitbucket.org/niknetniko/nextcellent-media-addon
 * Description:     Add WP media library integration to NextCellent.
 * Version:         2.0.0
 * Author:          niknetniko
 * Text Domain:     nextcellent-gallery-media-addon
 * Domain Path:     /lang
 * License:         GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' )) {
    die;
}

function nextcellent_gallery_media_library_addon()
{
    //Check the PHP version.
    if (version_compare( PHP_VERSION, '5.4', '<' )) {
        wp_die( 'You need at least PHP version 5.4 for this plugin.' );
    }

    $url = plugins_url(dirname(plugin_basename(__FILE__)));

    //After the plugin is loaded, we load translations
    load_plugin_textdomain( 'nextcellent-gallery-media-addon', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

    include_once( 'src/Bootstrap.php' );

    $bootstrap = new \Almiro\Wordpress\NextCellent\MediaLibraryAddon\Bootstrap($url);
    $bootstrap->start();
}

add_action( 'plugins_loaded', 'nextcellent_gallery_media_library_addon' );
