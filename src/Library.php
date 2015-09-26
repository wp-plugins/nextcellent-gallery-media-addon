<?php

namespace Almiro\Wordpress\NextCellent\MediaLibraryAddon;


use nggAdmin;
use nggdb;
use nggGallery;
use stdClass;

/**
 * Contains various helper methods.
 *
 * @package Niknetniko\NextCellent\MediaLibraryAddon
 */
class Library
{
    const MEDIA_TAGS_QUERYVAR = 'sngg_media_tags';

    /**
     * Modified version of nggAdmin::add_Images method
     *
     * @see nggAdmin::add_Images
     *
     * @param int   $galleryID  The Gallery ID which the images will be added to
     * @param array $imageslist The image files array
     *
     * @return array Image IDs array
     */
    public static function add_images( $galleryID, $imageslist ) {
        global $ngg;

        $image_ids = array();

        if ( is_array( $imageslist ) ) {
            foreach ( $imageslist as $key => $val ) {
                $picture = $val['filename'];
                // filter function to rename/change/modify image before
                $picture = apply_filters( 'ngg_pre_add_new_image', $picture, $galleryID );

                // strip off the extension of the filename
                $path_parts = pathinfo( $picture );
                if ( !empty( $val['title'] ) ) {
                    $alttext = $val['title'];
                } else {
                    $alttext = ( !isset( $path_parts['filename'] ) )
                        ? substr( $path_parts['basename'], 0, strpos( $path_parts['basename'], '.' ) )
                        : $path_parts['filename'];
                }

                $desc = ( !empty( $val['desc'] ) )
                    ? $val['desc']
                    : '';

                // save it to the database
                $pic_id = nggdb::add_image( $galleryID, $picture, $desc, $alttext );

                if ( !empty( $pic_id ) ) {
                    $image_ids[] = $pic_id;
                }

                // add the metadata
                nggAdmin::import_MetaData( $pic_id );

                // auto rotate
                nggAdmin::rotate_image( $pic_id );

                // Autoresize image if required
                if ( $ngg->options['imgAutoResize'] ) {
                    $imagetmp  = nggdb::find_image( $pic_id );
                    $sizetmp   = @getimagesize( $imagetmp->imagePath );
                    $widthtmp  = $ngg->options['imgWidth'];
                    $heighttmp = $ngg->options['imgHeight'];
                    if ( ( $sizetmp[0] > $widthtmp && $widthtmp ) || ( $sizetmp[1] > $heighttmp && $heighttmp ) ) {
                        nggAdmin::resize_image( $pic_id );
                    }
                }

                // action hook for post process after the image is added to the database
                $image = array( 'id' => $pic_id, 'filename' => $picture, 'galleryID' => $galleryID );
                do_action( 'ngg_added_new_image', $image );

            }
        } // is_array

        // delete dirsize after adding new images
        delete_transient( 'dirsize_cache' );

        do_action( 'ngg_after_new_images_added', $galleryID, $image_ids );

        return $image_ids;
    }

    /**
     * Add to $_FILES from external url
     *
     * @param array  $image Image data
     * @param string $title Image title
     * @param string $desc  Image description
     */
    public static function add_to_superglobal_files( $image, $title, $desc ) {
        $url           = urldecode( $image['url'] );
        // fixes error with ssl and self signed certificates
        $url		   = str_replace( 'https:', 'http:', $url );
        $temp_name     = tempnam( sys_get_temp_dir(), 'nggmla' );
        $original_name = basename( parse_url( $url, PHP_URL_PATH ) );
        $response      = wp_remote_get( $url, array( 'sslverify' => false ) );
        $img_raw_data  = wp_remote_retrieve_body( $response );
        file_put_contents( $temp_name, $img_raw_data );
        $type                   = wp_check_filetype_and_ext( $temp_name, $original_name );
        $_FILES['imagefiles'][] = array(
            'name'     => $original_name,
            'type'     => $type['type'],
            'tmp_name' => $temp_name,
            'error'    => 0,
            'size'     => strlen( $img_raw_data ),
            'title'    => $title,
            'desc'     => $desc
        );
    }

    /**
     * Processes adding of images to a gallery.
     *
     * @param int $galleryID The gallery id to add images to.
     *
     * @return stdClass Response message when transferring
     * images from library to NGG
     */
    public static function transfer_images_from_library_to_ngg( $galleryID ) {
        /**
         * @var nggdb $nggdb;
         */
        global $nggdb;
        $msg        = new stdClass();
        $msg->error = false;
        // Images must be an array
        $imageslist = array();
        // get the path to the gallery
        $gallery = $nggdb->find_gallery( $galleryID );
        if ( empty( $gallery->path ) ) {
            $msg->error           = true;
            $msg->error_code[]    = 'gallery_path_error';
            $msg->error_message[] = __( 'Failure in database, no gallery path set !', 'nextcellent-gallery-media-addon' );

            return $msg;
        }
        // read list of images
        $dirlist    = nggAdmin::scandir( $gallery->abspath );
        $imagefiles = $_FILES['imagefiles'];

        $imagefiles_count = 0;
        if ( is_array( $imagefiles ) ) {
            foreach ( $imagefiles as $key => $value ) {
                // look only for uploded files
                if ( $value['error'] == 0 ) {
                    $temp_file = $value['tmp_name'];
                    //clean filename and extract extension
                    $filepart = nggGallery::fileinfo( $value['name'] );
                    $filename = $filepart['basename'];
                    // check for allowed extension and if it's an image file
                    $ext = array( 'jpg', 'png', 'gif' );
                    if ( !in_array( $filepart['extension'],
                            $ext
                        ) || !@getimagesize( $temp_file )
                    ) {
                        $msg->error           = true;
                        $msg->error_code[]    = 'not_an_image_error';
                        $msg->error_message[] =
                            esc_html( $value['name'] ) . __( ' is not an image.', 'nextcellent-gallery-media-addon' );
                        continue;
                    }
                    // check if this filename already exist in the folder
                    $i = 0;
                    while ( in_array( $filename, $dirlist ) ) {
                        $filename = $filepart['filename'] . '_' . $i++ . '.' . $filepart['extension'];
                    }
                    $dest_file = $gallery->abspath . '/' . $filename;
                    //check for folder permission
                    if ( !is_writeable( $gallery->abspath ) ) {
                        $message              =
                            sprintf( __( 'Unable to write to directory %s. Is this directory writable by the server?',
                                'nextcellent-gallery-media-addon'
                            ),
                                esc_html( $gallery->abspath )
                            );
                        $msg->error           = true;
                        $msg->error_code[]    = 'write_permission_error';
                        $msg->error_message[] = $message;

                        return $msg;
                    }
                    // save temp file to gallery
                    if ( !copy( $temp_file, $dest_file ) ) {
                        $msg->error           = true;
                        $msg->error_code[]    = 'not_an_image_error';
                        $msg->error_message[] = __( 'Error, the file could not be moved to : ',
                                'nextcellent-gallery-media-addon'
                                                ) . esc_html( $dest_file );
                        continue;
                    }
                    if ( !nggAdmin::chmod( $dest_file ) ) {
                        $msg->error           = true;
                        $msg->error_code[]    = 'set_permissions_error';
                        $msg->error_message[] = __( 'Error, the file permissions could not be set',
                            'nextcellent-gallery-media-addon'
                        );
                        continue;
                    }
                    // add to imagelist & dirlist
                    $imageslist[$key]['filename'] = $filename;
                    $imageslist[$key]['title']    = !empty( $value['title'] )
                        ? $value['title']
                        : '';
                    $imageslist[$key]['desc']     = !empty( $value['desc'] )
                        ? $value['desc']
                        : '';
                    $dirlist[]                  = $filename;
                }
                $imagefiles_count++;
            }
        }
        if ( count( $imageslist ) > 0 ) {
            // add images to database
            $image_ids = self::add_images( $galleryID, $imageslist );

            //create thumbnails
            foreach ( $image_ids as $image_id ) {
                nggAdmin::create_thumbnail( $image_id );
            }
            $msg->success         = true;
            $imagesNumber = count( $image_ids );
            $msg->success_message = $imagesNumber . ' ' . _nx( 'image', 'images', $imagesNumber, 'n image(s) successfully added', 'nextcellent-gallery-media-addon' ) . ' ';
            $msg->success_message .= __( 'successfully added to', 'nextcellent-gallery-media-addon' ) . ' ' . $gallery->title;
            $msg->gallery_id = $galleryID;

            return $msg;
        }
        $msg->error           = true;
        $msg->error_code[]    = 'transfer_error';
        $msg->error_message[] =	__( 'Error in transferring', 'nextcellent-gallery-media-addon' ) . ' ' . $imagefiles_count . ' ' .
                                   _nx( 'selected', 'selected', $imagefiles_count, 'Error in transferring n selected image(s)', 'nextcellent-gallery-media-addon' ) . ' ' .
                                   _nx( 'image', 'images', $imagefiles_count, 'Error in transferring n selected image(s)', 'nextcellent-gallery-media-addon' ) . '.';

        return $msg;
    }

    /**
     * Registers custom taxonomy for use as media tags
     */
    public static function register_taxonomy() {
        $labels = array(
            'name'                       => _x( 'Media Tags', 'taxonomy general name', 'nextcellent-gallery-media-addon' ),
            'singular_name'              => _x( 'Media Tag', 'taxonomy singular name', 'nextcellent-gallery-media-addon' ),
            'search_items'               => __( 'Search Media Tags', 'nextcellent-gallery-media-addon' ),
            'popular_items'              => __( 'Popular Media Tags', 'nextcellent-gallery-media-addon' ),
            'all_items'                  => __( 'All Media Tags', 'nextcellent-gallery-media-addon' ),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __( 'Edit Media Tag', 'nextcellent-gallery-media-addon' ),
            'update_item'                => __( 'Update Media Tag', 'nextcellent-gallery-media-addon' ),
            'add_new_item'               => __( 'Add New Media Tag', 'nextcellent-gallery-media-addon' ),
            'new_item_name'              => __( 'New Media Tag Name', 'nextcellent-gallery-media-addon' ),
            'separate_items_with_commas' => __( 'Separate media tags with commas', 'nextcellent-gallery-media-addon' ),
            'add_or_remove_items'        => __( 'Add or remove media tags', 'nextcellent-gallery-media-addon' ),
            'choose_from_most_used'      => __( 'Choose from the most used media tags', 'nextcellent-gallery-media-addon' ),
            'not_found'                  => __( 'No media tags found.','nextcellent-gallery-media-addon' ),
            'menu_name'                  => __( 'Media Tags', 'nextcellent-gallery-media-addon' )
        );
        $args   = array(
            'hierarchical'          => false,
            'labels'                => $labels,
            'public'                => true,
            'show_ui'               => true,
            'show_in_nav_menus'     => true,
            'show_tagcloud'         => true,
            'show_admin_column'     => true,
            'update_count_callback' => '_update_generic_term_count',
            'query_var'             => 'nggmla-media-tags',
            'rewrite'               => array( 'slug' => 'nggmla-media-tags' )
        );
        register_taxonomy( self::MEDIA_TAGS_QUERYVAR, 'attachment', $args );
    }

    public static function search_media_tags( $query ) {
        global $current_screen;
        if ( !is_admin() ) {
            return;
        }

        if ( empty( $current_screen ) && $query->query['post_type'] == 'attachment' && $query->is_search ) {
            $args  = array(
                'fields' => 'names',
                'search' => $query->get( 's' )
            );
            $terms = get_terms( [ self::MEDIA_TAGS_QUERYVAR ], $args );
            if ( is_wp_error( $terms ) ) {
                return;
            }
            $tax_query = array(
                'relation' => 'OR',
                array(
                    'taxonomy' => self::MEDIA_TAGS_QUERYVAR,
                    'field'    => 'slug',
                    'terms'    => $terms
                )
            );
            $query->set( 'tax_query', $tax_query );

            add_filter( 'posts_where', [ 'Niknetniko\\NextCellent\\MediaLibraryAddon\\Library', 'tag_search_where' ] );
        }

        return $query;
    }

    public static function tag_search_where( $where ) {
        $where = preg_replace( '/AND \(\(\(/', 'OR (((', $where );

        return $where;
    }

}