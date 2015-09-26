<?php

namespace Almiro\Wordpress\NextCellent\MediaLibraryAddon;

/**
 * This is the main plugin class. It is responsible for loading everything
 * else and setting up everything with WordPress hooks and filters.
 *
 * @package Niknetniko\NextCellent\MediaLibraryAddon
 */
class Bootstrap
{
    private $url;

    public function __construct( $url )
    {
        $this->url = $url . '/src';
    }

    /**
     * Start the plugin.
     */
    public function start()
    {
        //If NextCellent is not active, do nothing.
        if ( ! $this->checkForNextCellent()) {
            return;
        }

        add_action( 'admin_enqueue_scripts', [ $this, 'add_scripts' ] );

        include_once( NGGALLERY_ABSPATH . '/admin/functions.php' );
        include_once( 'Library.php' );
        include_once( 'MediaAddonTab.php' );

        $tab = new MediaAddonTab();
        $tab->load();

        // Register nggmla_media_tags taxonomy
        add_action( 'init', 'Almiro\Wordpress\NextCellent\MediaLibraryAddon\Library::register_taxonomy' );
        // Add nggmla_media_tags in image search
        add_action( 'pre_get_posts', 'Almiro\Wordpress\NextCellent\MediaLibraryAddon\Library::search_media_tags', 999 );
    }

    public function add_scripts()
    {
        if ( ! $this->endsWith( get_current_screen()->id, '_page_nggallery-add-gallery' )) {
            return;
        }
        wp_register_script( 'nggmla-script', $this->url . '/js/admin.js', [ 'jquery' ], '2.0.0' );
        wp_register_style( 'nggmla-style', $this->url . '/css/admin.css', [ ], '2.0.0' );

        wp_enqueue_media();
        wp_enqueue_script( 'nggmla-script' );
        wp_enqueue_style( 'nggmla-style' );

        wp_localize_script( 'nggmla-script', 'nggmla', [
            'ajax_nonce'   => wp_create_nonce( 'lib-to-ngg-nonce' ),
            'preview_txt'  => __( 'Preview', 'nextcellent-gallery-media-addon' ),
            'choose_image' => __( 'Choose Image', 'nextcellent-gallery-media-addon' ),
            'title_from'   => __( 'Import title from:', 'nextcellent-gallery-media-addon' ),
            'desc_from'    => __( 'Import description from:', 'nextcellent-gallery-media-addon' ),
            'label'        => [
                'caption' => __( 'Caption', 'nextcellent-gallery-media-addon' ),
                'alt'     => __( 'Alternative Text', 'nextcellent-gallery-media-addon' ),
                'desc'    => __( 'Description', 'nextcellent-gallery-media-addon' )
            ]
        ] );
    }

    /**
     * Checks if NextCellent is activated or not.
     *
     * @todo Once there is another constant in NextCellent, switch to that one.
     *
     * @return bool True if NextCellent is active, otherwise false.
     */
    private function checkForNextCellent()
    {
        return defined( 'NGGFOLDER' );
    }

    private function endsWith( $str, $end )
    {
        return substr_compare( $str, $end, strlen( $str ) - strlen( $end ), strlen( $end ) ) === 0;
    }
}