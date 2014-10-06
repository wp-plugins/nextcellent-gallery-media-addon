<?php
/*
Plugin Name: NextCellent Gallery Media Library Addon
Plugin URI: https://bitbucket.org/niknetniko/nextcellent-media-addon
Description: An addon to NextCellent.
Version: 1.0.1
Author: niknetniko
 */
/*
This file is part of NextCellent Gallery Media Library Addon.
NextCellent Gallery Media Library Addon is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
NextCellent Gallery Media Library Addon is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
You should have received a copy of the GNU General Public License
along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
*/
if ( !defined( 'ABSPATH' ) || preg_match( '#' . basename( __FILE__ ) . '#', $_SERVER['PHP_SELF'] ) ) {
	die( "You are not allowed to call this page directly." );
}

/**
 * Get some constants
 */
define( 'NGGMLA_PLUGIN_FILE', __FILE__ );
define( 'NGGMLA_DOMAIN', basename( dirname( __FILE__ ) ) );
define( 'NGGMLA_MEDIA_TAGS_QUERYVAR', 'sngg_media_tags' );
define( 'NGG_PLUGIN_NAME', 'NextCellent Gallery' );

//The main class
if ( !class_exists( 'NextCellentMediaLibraryAddon' ) ) {
	class NextCellentMediaLibraryAddon {

		protected static $instance;

		/**
		 * The list of galleries
		 *
		 * @var array
		 */
		private $gallery_list;

		/**
		 * Gallery default path
		 *
		 * @var string
		 */
		private $default_path;

		/**
		 * Get NextCellentMediaLibraryAddon instance.
		 *
		 * @return NextCellentMediaLibraryAddon instance
		 */
		public static function get_instance() {
			null === self::$instance && self::$instance = new self;

			return self::$instance;
		}

		public function init() {
			// Load addon if NextGEN Gallery plugin is installed/activated.
			if ( $this->check_ngg() ) {
				$this->init_includes();
				$this->init_properties();
				$this->init_hooks();
			}
		}

		/**
		 * Checks if required plugin is activated or not.
		 *
		 * @return boolean True, if required plugin is activated, false otherwise.
		 */
		public function check_ngg() {
			if ( !defined('NGGFOLDER') ) {
				if ( is_multisite() ) {
					add_action( 'all_admin_notices', array( &$this, 'required_plugin_notice' ) );
				} else {
					add_action( 'admin_notices', array( &$this, 'required_plugin_notice' ) );
				}

				return false;
			}

			return true;
		}

		/**
		 * Display admin notice/s if required plugin is not activated.
		 */
		public function required_plugin_notice() {
			echo sprintf( __( '%s' . 'This plugin requires ' . NGG_PLUGIN_NAME . ' to be installed and activated first before it can work properly.%s', NGGMLA_DOMAIN ), "<div id='message' class='error'><p>", "</p></div>");
		}

		/**
		 * Loads additional scripts
		 */
		public function admin_addl_scripts() {
			if ( get_current_screen()->id == 'galleries_page_nggallery-add-gallery' ) {
				wp_enqueue_media();
				wp_register_style( 'nggmla-styles', plugins_url( 'css/nggmla-styles.css', NGGMLA_PLUGIN_FILE ) );
				wp_enqueue_style( 'nggmla-styles' );
				add_action( 'admin_footer', array( &$this, 'medialibrary_js' ) );
			}
		}

		/**
		 * Appends additional tab/s.
		 *
		 * @param array $tabs Default tabs from NextGen Gallery.
		 *
		 * @return array The modified array of $tabs
		 */
		public function add_ngg_tab( $tabs ) {
			$tabs['frommedialibrary'] = __( 'Media Library', NGGMLA_DOMAIN );

			return $tabs;
		}

		/**
		 * Registers custom taxonomy for use as media tags
		 */
		public function register_taxonomy() {
			$labels = array(
				'name'                       => _x( 'Media Tags', 'taxonomy general name' ),
				'singular_name'              => _x( 'Media Tag', 'taxonomy singular name' ),
				'search_items'               => __( 'Search Media Tags' ),
				'popular_items'              => __( 'Popular Media Tags' ),
				'all_items'                  => __( 'All Media Tags' ),
				'parent_item'                => null,
				'parent_item_colon'          => null,
				'edit_item'                  => __( 'Edit Media Tag' ),
				'update_item'                => __( 'Update Media Tag' ),
				'add_new_item'               => __( 'Add New Media Tag' ),
				'new_item_name'              => __( 'New Media Tag Name' ),
				'separate_items_with_commas' => __( 'Separate media tags with commas' ),
				'add_or_remove_items'        => __( 'Add or remove media tags' ),
				'choose_from_most_used'      => __( 'Choose from the most used media tags' ),
				'not_found'                  => __( 'No media tags found.' ),
				'menu_name'                  => __( 'Media Tags' )
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
			register_taxonomy( NGGMLA_MEDIA_TAGS_QUERYVAR, 'attachment', $args );
		}

		public function search_media_tags( $query ) {
			global $current_screen;
			if ( !is_admin() ) {
				return;
			}

			if ( empty( $current_screen ) && $query->query['post_type'] == 'attachment' && $query->is_search ) {
				$args  = array(
					'fields' => 'names',
					'search' => $query->get( 's' )
				);
				$terms = get_terms( array( NGGMLA_MEDIA_TAGS_QUERYVAR ), $args );
				if ( is_wp_error( $terms ) ) {
					return;
				}
				$tax_query = array(
					'relation' => 'OR',
					array(
						'taxonomy' => NGGMLA_MEDIA_TAGS_QUERYVAR,
						'field'    => 'slug',
						'terms'    => $terms
					)
				);
				$query->set( 'tax_query', $tax_query );

				add_filter( 'posts_where', array( &$this, 'nggmla_mediatag_search_where' ) );
			}

			return $query;
		}

		public function nggmla_mediatag_search_where( $where ) {
			$where = preg_replace( '/AND \(\(\(/', 'OR (((', $where );

			return $where;
		}

		/**
		 * The "Add Images From Media Library" content.
		 */
		public function tab_frommedialibrary() {
			?>
			<h3><?php _e( 'Add images from the media library', NGGMLA_DOMAIN ); ?></h3>
			<form id="nggmla-selected-images-form" action="" method="POST">
				<div id="select-gallery"><label for=""><?php _e( 'Add images to:', NGGMLA_DOMAIN );?></label>
					<select id="togallery" name="togallery">
						<option value="0"><?php _e( 'Choose gallery', 'nggallery' ); ?>
						</option>
						<option value="new"><?php _e( 'New gallery', NGGMLA_DOMAIN ); ?></option>
						<?php
						foreach ( $this->gallery_list as $gallery ) {
							//special case : we check if a user has this cap, then we override the second cap check
							if ( !current_user_can( 'NextGEN Upload in all galleries' ) ) {
								if ( !nggAdmin::can_manage_this_gallery( $gallery->author ) ) {
									continue;
								}
							}
							$name = ( empty( $gallery->title ) )
								? $gallery->name
								: $gallery->title;
							echo '<option value="' . $gallery->gid . '" >' . $gallery->gid . ' - ' . esc_attr( $name ) . '</option>' . "\n";
						}
						?>
					</select>
					<input id="togallery_name" name="togallery_name" type="text" size="30" value=""
					       style="display: none;" />
					<span id="gallery-error" class="nggmla-error"></span>
				</div>
				
				<p><a id="nggmla-select-images" class="button-secondary" href="#"><?php _e( 'Select Images', NGGMLA_DOMAIN); ?></a> <span id="image-error" class="nggmla-error"></span>
				</p>

				<div id="nggmla-images-preview"></div>

				<div id="nggmla-selected-images"></div>
				<p style="clear: both;">
					<input id="nggmla-submit-images" class="button-primary" type="submit" value="Add to Gallery" />
                <span id="copying" style="display: none;"><?php echo $copying = __( 'Copying...', NGGMLA_DOMAIN ); ?>
	                <img src="<?php echo NGGALLERY_URLPATH . 'images/ajax-loader.gif'; ?>"
	                     alt="<?php echo esc_attr( $copying ); ?>" /></span>
				</p>
			</form>
		<?php
		}

		/**
		 * Ajax handler.
		 * Sends request for creating a gallery and or
		 * adding images to a gallery from media library
		 */
		public function ajax_lib_to_ngg() {
			check_ajax_referer( 'lib-to-ngg-nonce', 'nggmla_nonce' );
			$msg        = new stdClass();
			$msg->error = false;
			if ( !isset( $_POST['togallery'] ) || $_POST['togallery'] === '0' ) {
				$msg->error         = true;
				$msg->error_code    = 'gallery_error';
				$msg->error_message = __( 'No gallery selected!', NGGMLA_DOMAIN );
				echo json_encode( $msg );
				die();
			} else {
				if ( !empty( $_POST['togallery'] ) && $_POST['togallery'] === 'new' && empty( $_POST['togallery_name'] )
				) {
					$msg->error         = true;
					$msg->error_code    = 'gallery_error';
					$msg->error_message = __( 'Enter a name for the new gallery!', NGGMLA_DOMAIN );
					echo json_encode( $msg );
					die();
				} else {
					if ( empty( $_POST['imagefiles'] ) ) {
						$msg->error         = true;
						$msg->error_code    = 'image_error';
						$msg->error_message = __( 'No images selected!', NGGMLA_DOMAIN );
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
						$msg->error_message = __( 'No cheating!', NGGMLA_DOMAIN );
						echo json_encode( $msg );
						die();
					} else {
						$newgallery = $_POST['togallery_name'];
						if ( !empty( $newgallery ) ) {
							$galleryID = nggAdmin::create_gallery( $newgallery, $this->default_path, false );
						}
					}
				} else {
					$galleryID = (int) $_POST['togallery'];
				}
				$imagefiles = array();
				parse_str( $_POST['imagefiles'], $imagefiles );
				extract( $imagefiles );

				foreach ( $imagefiles as $img ) {
					$this->add_to_superglobal_files( $img, $img[$img['title_as']], $img[$img['desc_as']] );
				}

				echo json_encode( $this->transfer_images_from_library_to_ngg( $galleryID ) );
				die();
			}
			$msg->error           = true;
			$msg->error_code[]    = 'upload_error';
			$msg->error_message[] = __( 'Image upload error!', NGGMLA_DOMAIN );
			echo json_encode( $msg );
			die();
		}

		/**
		 * Add to $_FILES from external url
		 *
		 * @param array  $image Image data
		 * @param string $title Image title
		 * @param string $desc  Image description
		 *
		 * @internal param string $url sample http://some.tld/path/to/file.ext
		 */
		public function add_to_superglobal_files( $image, $title, $desc ) {
			$url           = urldecode( $image['url'] );
			$temp_name     = tempnam( sys_get_temp_dir(), 'nggmla' );
			$original_name = basename( parse_url( $url, PHP_URL_PATH ) );
			$response      = wp_remote_get( $url );
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
		public function transfer_images_from_library_to_ngg( $galleryID ) {
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
				$msg->error_message[] = __( 'Failure in database, no gallery path set !', 'nggallery' );

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
								esc_html( $value['name'] ) . __( ' is not an image.', NGGMLA_DOMAIN );
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
								             'nggallery'
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
							                            'nggallery'
							                        ) . esc_html( $dest_file );
							$safemode             = $this->check_safemode( $gallery->abspath );
							if ( $safemode ) {
								$msg->error           = true;
								$msg->error_code[]    = $safemode->error_code;
								$msg->error_message[] = $safemode->error_message;
							}
							continue;
						}
						if ( !nggAdmin::chmod( $dest_file ) ) {
							$msg->error           = true;
							$msg->error_code[]    = 'set_permissions_error';
							$msg->error_message[] = __( 'Error, the file permissions could not be set',
							                            'nggallery'
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
				$image_ids = $this->add_images( $galleryID, $imageslist );

				//create thumbnails
				foreach ( $image_ids as $image_id ) {
					nggAdmin::create_thumbnail( $image_id );
				}
				$msg->success         = true;
				$msg->success_message = count( $image_ids ) . __( ' image(s) successfully added', 'nggallery' );
				$msg->success_message .= " to $gallery->title";
				$msg->gallery_id = $galleryID;

				return $msg;
			}
			$msg->error           = true;
			$msg->error_code[]    = 'transfer_error';
			$msg->error_message[] = sprintf( 'Error in transferring selected %s.',
			                                 ( $imagefiles_count > 1 )
				                                 ? 'images'
				                                 : 'image'
			);

			return $msg;
		}

		/**
		 * Check UID in folder and Script
		 * (Adapted from NGG)
		 * Read http://www.php.net/manual/en/features.safe-mode.php to understand safe_mode
		 *
		 * @param string $foldername The name of the folder
		 *
		 * @return bool $result True if in safemode, False otherwise.
		 */
		public function check_safemode( $foldername ) {
			$msg        = new stdClass();
			$msg->error = false;
			if ( SAFE_MODE ) {
				$script_uid = ( ini_get( 'safe_mode_gid' ) )
					? getmygid()
					: getmyuid();
				$folder_uid = fileowner( $foldername );
				if ( $script_uid != $folder_uid ) {
					$message =
						sprintf( __( 'SAFE MODE Restriction in effect! You need to create the folder <strong>%s</strong> manually', 'nggallery' ), esc_html( $foldername ) );
					$message .=
						'<br />' .
						sprintf( __( 'When safe_mode is on, PHP checks to see if the owner (%s) of the current script matches the owner (%s) of the file to be operated on by a file function or its directory', 'nggallery' ), $script_uid, $folder_uid );
					$msg->error         = true;
					$msg->error_code    = 'safe_mode_error';
					$msg->error_message = $message;

					return $msg;
				}
			}

			return false;
		}

		/**
		 * Script that launches/processes the media library dialog.
		 */
		public function medialibrary_js() {
			$nggmla = array(
				'ajax_nonce'   => wp_create_nonce( 'lib-to-ngg-nonce' ),
				'preview_txt'  => __( 'Preview', NGGMLA_DOMAIN ),
				'choose_image' => __( 'Choose Image', NGGMLA_DOMAIN ),
				'title_from'   => __( 'Import title from:', NGGMLA_DOMAIN ),
				'desc_from'    => __( 'Import description from:', NGGMLA_DOMAIN ),
				'label'        => array(
					'caption' => __( 'Caption', NGGMLA_DOMAIN ),
					'alt'     => __( 'Alternative Text', NGGMLA_DOMAIN ),
					'desc'    => __( 'Description', NGGMLA_DOMAIN )
				)
			);
			?>
			<script>
				jQuery( document ).ready( function ( $ ) {
					var custom_uploader,
						togallery = $( '#togallery' ),
						nggmla = <?php echo json_encode( $nggmla ); ?>;

					$( '#nggmla-select-images' ).click( function ( e ) {
						e.preventDefault();
						if ( custom_uploader ) {
							custom_uploader.open();
							return;
						}

						//Extend the wp.media object
						custom_uploader = wp.media.frames.file_frame = wp.media( {
							                                                         title   : nggmla.choose_image,
							                                                         button  : {
								                                                         text: nggmla.choose_image
							                                                         },
							                                                         library : {
								                                                         type: 'image'
							                                                         },
							                                                         multiple: true
						                                                         } );

						//When a file is selected, grab the URL and set it as the text field's value
						custom_uploader.on( 'select', function () {
							var selection = custom_uploader.state().get( 'selection' );

							var images_preview = '<p class="label"><label>' + nggmla.preview_txt + '</label>'
									+ '</p><ul>',
								image_ids = '',
								radio_opts = ['caption', 'alt', 'desc'],
								i = 0;

							selection.map( function ( attachment ) {
								attachment = attachment.toJSON();

								images_preview += "<li><img src='" + attachment.sizes.thumbnail.url
									+ "' alt='' /><p>";

								var meta_func = function ( option, key ) {
									var label = nggmla.label[option];

									images_preview += "<input type='radio' name='imagefiles["
										                  + i + "][" + key + "]' value='" + option + "' /> "
										                  + label + '<br>';
								};

								images_preview += nggmla.title_from + '<br>';
								radio_opts.forEach( function ( option ) {
									meta_func( option, 'title_as' );
								} );
								images_preview += '</p><p>';
								images_preview += nggmla.desc_from + '<br>';
								radio_opts.forEach( function ( option ) {
									meta_func( option, 'desc_as' );
								} );

								images_preview += "</p></li>";
								image_ids += "<input data-imgid='" + attachment.id + "' type='hidden' name='imagefiles["
									             + i + "][url]' value='"
									             + encodeURIComponent( attachment.sizes.full.url ) + "' />"
									             + "<input type='hidden' name='imagefiles["
									             + i + "][caption]' value='"
									             + _.escape( attachment.caption ) + "'>"
									             + "<input type='hidden' name='imagefiles["
									             + i + "][alt]' value='"
									             + _.escape( attachment.alt ) + "'>"
									             + "<input type='hidden' name='imagefiles["
									             + i + "][desc]' value='"
									             + _.escape( attachment.description ) + "'>"
								;

								i++;
							} );

							images_preview += '</ul>';
							$( '#nggmla-selected-images' ).html( image_ids );
							$( '#nggmla-images-preview' ).html( images_preview );
						} );

						// Check already selected images when form opens
						custom_uploader.on( 'open', function () {
							var selection = custom_uploader.state().get( 'selection' );
							var ids = $( 'input', '#nggmla-selected-images' ).map(function () {
								return $( this ).attr( 'data-imgid' );
							} ).get();
							ids.forEach( function ( id ) {
								var attachment = wp.media.attachment( id );

								selection.add( attachment ? [attachment] : [] );
							} );
						} );

						//Open the uploader dialog
						custom_uploader.open();
					} );

					// Show gallery name input if 'new' is currently selected
					if ( togallery.val() == 'new' ) {
						$( '#togallery_name' ).show();
					}

					togallery.on( 'change', function () {
						var $this = $( this );
						if ( $this.val() == 'new' ) {
							$( '#togallery_name' ).show();
						} else {
							$( '#togallery_name' ).hide();
						}
					} );

					// Ajax POST
					$( '#nggmla-selected-images-form' ).submit( function ( e ) {
						e.preventDefault();
						var copying = $( '#copying' ),
							togallery = $( this ).find( '#togallery' ),
							togallery_name = $( this ).find( '#togallery_name' ),
							screen_meta = $( '#screen-meta', '#wpbody-content' );

						screen_meta.next().remove( 'div.wrap' );
						var data = {
							action      : 'lib_to_ngg',
							nggmla_nonce: nggmla.ajax_nonce,
							togallery   : togallery.val(),
							imagefiles  : $( this ).find( 'input[name^="imagefiles"]' ).serialize()
						};
						if ( togallery.val() == 'new' ) {
							data['togallery_name'] = togallery_name.val();
						}
						copying.show();
						$.post( ajaxurl,
						        data,
						        function ( response ) {
							        response = JSON.parse( response );
							        copying.hide();

							        if ( response.error ) {
								        if ( response.error_code == 'gallery_error' ) {
									        $( '#gallery-error' )
										        .html( response.error_message )
										        .show();
									        setTimeout( function () {
										        $( '#gallery-error' )
											        .fadeOut( 'slow', function () {
												                  $( this ).html( '' );
											                  } );
									        }, 3000 );
								        } else if ( response.error_code == 'image_error' ) {
									        $( '#image-error' )
										        .html( response.error_message )
										        .show();
									        setTimeout( function () {
										        $( '#image-error' )
											        .fadeOut( 'slow', function () {
												                  $( this ).html( '' );
											                  } );
									        }, 3000 );
								        } else {
									        var error_message = '';
									        if ( $.isArray( response.error_message ) ) {
										        $.each( response.error_message, function ( index, value ) {
											        error_message += '<p>' + value + '</p>';
										        } );
									        } else {
										        error_message += '<p>' + response.error_message + '</p>';
									        }
									        screen_meta.after( '<div class="wrap"><h2></h2><div id="error" class="error below-h2">' + error_message + '</div></div>' );
								        }
							        } else {
								        screen_meta.after( '<div class="wrap"><h2></h2><div class="updated fade" id="message"><p>' + response.success_message + '</p></div></div>' );

								        if ( togallery_name.val().length !== 0 ) {
									        togallery.find( 'option[value="new"]' ).after( '<option value="' + response.gallery_id + '">' + response.gallery_id + ' - ' + data.togallery_name + '</option>' );
									        togallery_name.val( '' );
								        }
							        }
						        } );
					} );
				} );
			</script>
		<?php
		}

		/*
		  |-------------------------------------------
		  |               PRIVATE ACCESS
		  |-------------------------------------------
		 */
		/**
		 * Add filters/actions
		 */
		private function init_hooks() {
			// Add new tab/s in nggallery-add-gallery page
			add_filter( 'ngg_addgallery_tabs', array( &$this, 'add_ngg_tab' ) );
			// Add tab/s call back
			add_action( 'ngg_tab_content_frommedialibrary', array( &$this, 'tab_frommedialibrary' ) );
			// Add additional script/s
			add_action( 'admin_enqueue_scripts', array( &$this, 'admin_addl_scripts' ) );
			// Add ajax handler
			add_action( 'wp_ajax_lib_to_ngg', array( &$this, 'ajax_lib_to_ngg' ) );
			// Register nggmla_media_tags taxonomy
			add_action( 'init', array( &$this, 'register_taxonomy' ) );
			// Add nggmla_media_tags in image search
			add_action( 'pre_get_posts', array( &$this, 'search_media_tags' ), 999 );
		}

		/**
		 * Initialize class members/properties.
		 *
		 * @global object $ngg   NextGen Gallery loader object.
		 * @global object $nggdb NextGen Gallery database object.
		 */
		private function init_properties() {
			global $ngg, $nggdb;
			$this->gallery_list = $nggdb->find_all_galleries( 'gid', 'DESC' );
			$this->default_path = $ngg->options['gallerypath'];
		}

		/**
		 * Require files from NGG
		 */
		private function init_includes() {
			require_once( NGGALLERY_ABSPATH . '/admin/functions.php' );

			load_plugin_textdomain( NGGMLA_DOMAIN, false, NGGMLA_DOMAIN . '/languages' );
		}

		/**
		 * Similar to WP's is_plugin_active function.
		 * Check whether the plugin is active by checking the active_plugins list.
		 *
		 * @param string $plugin Base plugin path from plugins directory.
		 *
		 * @return bool True, if in the active plugins list. False, not in the list.
		 */
		private function is_plugin_active( $plugin ) {
			return in_array( $plugin,
			                 (array) get_option( 'active_plugins', array() )
			       ) || $this->is_plugin_active_for_network( $plugin );
		}

		/**
		 * Similar to WP's is_plugin_active_for_network function.
		 * Check whether the plugin is active for the entire network.
		 *
		 * @param string $plugin Base plugin path from plugins directory.
		 *
		 * @return boolean True, if active for the network, otherwise false.
		 */
		private function is_plugin_active_for_network( $plugin ) {
			if ( !is_multisite() ) {
				return false;
			}
			$plugins = get_site_option( 'active_sitewide_plugins' );
			if ( isset( $plugins[$plugin] ) ) {
				return true;
			}

			return false;
		}

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
		private function add_images( $galleryID, $imageslist ) {
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
	}
}

add_action( 'plugins_loaded', array( NextCellentMediaLibraryAddon::get_instance(), 'init' ) );