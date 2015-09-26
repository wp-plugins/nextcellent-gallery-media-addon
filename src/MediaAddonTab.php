<?php

namespace Almiro\Wordpress\NextCellent\MediaLibraryAddon;


use nggAdmin;
use nggGallery;
use stdClass;

class MediaAddonTab
{
    /**
     * @var array
     */
    private $galleries;

    public function __construct()
    {
        /**
         * @var \nggdb $nggdb
         */
        global $nggdb;

        $this->galleries = $nggdb->find_all_galleries('gid', 'DESC');
    }

    /**
     * Add this page to the NextCellent upload tabs.
     */
    public function load()
    {
        // Add the new tab
        add_filter( 'ngg_addgallery_tabs', [ $this, 'add_ngg_tab' ] );
        // Add content to the tab
        add_action( 'ngg_tab_content_media_library', [ $this, 'tab_content' ] );
        // Add ajax handler
        add_action( 'wp_ajax_lib_to_ngg', [ $this, 'add_ajax' ] );
    }

    /**
     * Appends additional tabs.
     *
     * @param array $tabs Default tabs from NextGen Gallery.
     *
     * @return array The modified array of $tabs
     */
    public function add_ngg_tab( $tabs ) {
        $tabs['media_library'] = __( 'Media Library', 'nextcellent-gallery-media-addon' );
        return $tabs;
    }

    public function tab_content()
    {
        ?>
        <div class="error nggla-notice" id="nggla-error"></div>
        <div class="updated nggla-notice" id="nggla-ok"></div>
        <h3><?php _e( 'Add images from the media library', 'nextcellent-gallery-media-addon' ); ?></h3>
        <form id="nggmla-selected-images-form" action="" method="POST">
            <div id="select-gallery">
                <label for="togallery"><?php _e( 'Add images to:', 'nextcellent-gallery-media-addon' );?></label>
                <select id="togallery" name="togallery">
                    <option value="0"><?php _e( 'Choose gallery', 'nextcellent-gallery-media-addon' ); ?></option>
                    <option value="new"><?php _e( 'New gallery', 'nextcellent-gallery-media-addon' ); ?></option>
                    <?php $this->print_galleries(); ?>
                </select>
                <input id="togallery_name" name="togallery_name" type="text" size="30" value="">
            </div>

            <p>
                <a id="nggmla-select-images" class="button-secondary" href="#">
                    <?php _e( 'Select Images', 'nextcellent-gallery-media-addon'); ?>
                </a>
            </p>

            <div id="nggmla-images-preview"></div>

            <div id="nggmla-selected-images"></div>
            <p style="clear: both;">
                <input id="nggmla-submit-images" class="button-primary" type="submit" value="<?php _e( 'Add to Gallery', 'nextcellent-gallery-media-addon' ); ?>" />
                <span id="copying" style="display: none;"><?php echo $copying = __( 'Copying...', 'nextcellent-gallery-media-addon' ); ?>
                    <img src="<?php echo admin_url( "images/spinner.gif" ); ?>"
                         alt="<?php echo esc_attr( $copying ); ?>" /></span>
            </p>
        </form>
        <?php
    }

    /**
     * Ajax handler.
     */
    public function add_ajax()
    {
        check_ajax_referer( 'lib-to-ngg-nonce', 'nggmla_nonce' );
        $msg        = new stdClass();
        $msg->error = false;
        if ( !isset( $_POST['togallery'] ) || $_POST['togallery'] === '0' ) {
            $msg->error         = true;
            $msg->error_code    = 'gallery_error';
            $msg->error_message = __( 'No gallery selected!', 'nextcellent-gallery-media-addon' );
            echo json_encode( $msg );
            die();
        } else {
            if ( !empty( $_POST['togallery'] ) && $_POST['togallery'] === 'new' && empty( $_POST['togallery_name'] )
            ) {
                $msg->error         = true;
                $msg->error_code    = 'gallery_error';
                $msg->error_message = __( 'Enter a name for the new gallery!', 'nextcellent-gallery-media-addon' );
                echo json_encode( $msg );
                die();
            } else {
                if ( empty( $_POST['imagefiles'] ) ) {
                    $msg->error         = true;
                    $msg->error_code    = 'image_error';
                    $msg->error_message = __( 'No images selected!', 'nextcellent-gallery-media-addon' );
                    echo json_encode( $msg );
                    die();
                }
            }
        }
        if ( isset( $_POST['imagefiles'] ) ) {

            $galleryID = 0;
            if ( $_POST['togallery'] == 'new' ) {
                if ( !nggGallery::current_user_can( 'NextGEN Add new gallery' ) ) {
                    $msg->error         = true;
                    $msg->error_code    = 'ngg_error';
                    $msg->error_message = __( 'No cheating!', 'nextcellent-gallery-media-addon' );
                    echo json_encode( $msg );
                    die();
                } else {
                    $newgallery = $_POST['togallery_name'];
                    if ( !empty( $newgallery ) ) {
                        $options = get_option('ngg_options');
                        $defaultPath = $options['gallerypath'];
                        $galleryID = nggAdmin::create_gallery( $newgallery, $defaultPath, false );
                    }
                }
            } else {
                $galleryID = (int) $_POST['togallery'];
            }
            $imagefiles = array();
            parse_str( $_POST['imagefiles'], $imagefiles );
            extract( $imagefiles );

            foreach ( $imagefiles as $img ) {

                $title = isset($img['title_as']) ? $img[$img['title_as']] : '';
                $desc = isset($img['desc_as']) ? $img[$img['desc_as']] : '';

                Library::add_to_superglobal_files( $img, $title, $desc);
            }

            echo json_encode( Library::transfer_images_from_library_to_ngg( $galleryID ) );
            die();
        }
        $msg->error           = true;
        $msg->error_code[]    = 'upload_error';
        $msg->error_message[] = __( 'Image upload error!', 'nextcellent-gallery-media-addon' );
        echo json_encode( $msg );
        die();
    }

    /**
     * Taken from NextCellent.
     *
     * @todo If this becomes public in NextCellent, we can use that function.
     */
    private function print_galleries() {
        foreach($this->galleries as $gallery) {
            if ( current_user_can( 'NextGEN Upload in all galleries' ) || nggAdmin::can_manage_this_gallery($gallery->author) ) {
                $name = ( empty( $gallery->title ) ) ? $gallery->name : $gallery->title;
                echo '<option value="' . $gallery->gid . '" >' . $gallery->gid . ' - ' . esc_attr( $name ) . '</option>';
            }
        }
    }
}