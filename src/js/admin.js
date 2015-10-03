/**
 * This is the JavaScript for the admin section.
 */
jQuery(document).ready(function($) {

    "use strict";

    var custom_uploader;
    var toGallery = $('#togallery');

    $('#nggmla-select-images').click(function(e) {
        e.preventDefault();

        if (custom_uploader) {
            custom_uploader.open();
            return;
        }

        //Extend the wp.media object
        custom_uploader = wp.media.frames.file_frame = wp.media({
            title: nggmla.choose_image,
            button: {
                text: nggmla.choose_image
            },
            library: {
                type: 'image'
            },
            multiple: true
        });

        //When a file is selected, grab the URL and set it as the text field's value
        custom_uploader.on('select', function() {
            var selection = custom_uploader.state().get('selection');

            var images_preview = '<p class="label"><label>' + nggmla.preview_txt + '</label></p><ul>';
            var image_ids = '';
            var radio_opts = ['caption', 'alt', 'desc'];
            var i = 0;

            selection.map(function(attachment) {
                attachment = attachment.toJSON();

                images_preview += "<li><img src='" + attachment.sizes.thumbnail.url
                    + "' alt='' /><p>";

                var meta_func = function(option, key) {
                    var label = nggmla.label[option];

                    images_preview += "<input type='radio' name='imagefiles["
                        + i + "][" + key + "]' value='" + option + "' /> "
                        + label + '<br>';
                };

                images_preview += nggmla.title_from + '<br>';
                radio_opts.forEach(function(option) {
                    meta_func(option, 'title_as');
                });
                images_preview += '</p><p>';
                images_preview += nggmla.desc_from + '<br>';
                radio_opts.forEach(function(option) {
                    meta_func(option, 'desc_as');
                });

                images_preview += "</p></li>";
                image_ids += "<input data-imgid='" + attachment.id + "' type='hidden' name='imagefiles["
                    + i + "][url]' value='"
                    + encodeURIComponent(attachment.sizes.full.url) + "' />"
                    + "<input type='hidden' name='imagefiles["
                    + i + "][caption]' value='"
                    + _.escape(attachment.caption) + "'>"
                    + "<input type='hidden' name='imagefiles["
                    + i + "][alt]' value='"
                    + _.escape(attachment.alt) + "'>"
                    + "<input type='hidden' name='imagefiles["
                    + i + "][desc]' value='"
                    + _.escape(attachment.description) + "'>"
                ;

                i++;
            });

            images_preview += '</ul>';
            $('#nggmla-selected-images').html(image_ids);
            $('#nggmla-images-preview').html(images_preview);
        });

        // Check already selected images when form opens
        custom_uploader.on('open', function() {
            var selection = custom_uploader.state().get('selection');
            var ids = $('input', '#nggmla-selected-images').map(function() {
                return $(this).attr('data-imgid');
            }).get();
            ids.forEach(function(id) {
                var attachment = wp.media.attachment(id);

                selection.add(attachment ? [attachment] : []);
            });
        });

        //Open the uploader dialog
        custom_uploader.open();
    });

    // Show gallery name input if 'new' is currently selected
    if (toGallery.val() == 'new') {
        $('#togallery_name').show();
    }

    toGallery.on('change', function() {
        var $this = $(this);
        if ($this.val() == 'new') {
            $('#togallery_name').show();
        } else {
            $('#togallery_name').hide();
        }
    });

    // Ajax POST
    $('#nggmla-selected-images-form').submit(function(e) {
        e.preventDefault();
        var copying = $('#copying');
        var togallery = $(this).find('#togallery');
        var togallery_name = $(this).find('#togallery_name');
        var screen_meta = $('#screen-meta', '#wpbody-content');

        screen_meta.next().remove('div.wrap');
        var data = {
            action: 'lib_to_ngg',
            nggmla_nonce: nggmla.ajax_nonce,
            togallery: togallery.val(),
            imagefiles: $(this).find('input[name^="imagefiles"]').serialize()
        };
        if (togallery.val() == 'new') {
            data['togallery_name'] = togallery_name.val();
        }
        copying.show();
        $.post(ajaxurl, data, function(response) {

            //Parse the response
            response = JSON.parse(response);

            //Hide the spinner
            copying.hide();

            if (response.error) {
                var error_message = '';
                if ($.isArray(response.error_message)) {
                    $.each(response.error_message, function(index, value) {
                        error_message += value + '<br>';
                    });
                } else {
                    error_message = response.error_message;
                }
                displayError(error_message);
            } else {
                displayOK(response.success_message);

                if (togallery_name.val().length !== 0) {
                    togallery.find('option[value="new"]').after('<option value="' + response.gallery_id + '">' + response.gallery_id + ' - ' + data.togallery_name + '</option>');
                    togallery_name.val('');
                }
            }
        });
    });

    /**
     * Display a WordPress admin error message.
     *
     * @param message The message to display.
     */
    function displayError(message) {
        var $normalError = $('#nggla-error');
        $normalError.html('<p>' + message + '</p>').show();
        setTimeout(function() {
            $normalError.fadeOut('slow', function() {
                $(this).html('');
            });
        }, 3000);
    }

    /**
     * Display a succeeded WordPress admin message.
     *
     * @param message The message to display.
     */
    function displayOK(message) {
        $('#nggla-ok').html('<p>' + message + '</p>').show();
    }
});
