jQuery(document).ready(function($) {
    let selectedImages = [];

    $('#select-images').click(function(e) {
        e.preventDefault();

        const frame = wp.media({
            title: 'Select Images',
            button: {
                text: 'Use selected images'
            },
            multiple: true,
            library: {
                type: 'image'
            }
        });

        frame.on('select', function() {
            const attachments = frame.state().get('selection').toJSON();
            selectedImages = attachments.map(att => att.id);
            
            $('#selected-images').html(
                `<p>Selected ${selectedImages.length} images</p>`
            );
            
            if (selectedImages.length > 0) {
                $('#create-posts').show();
            }
        });

        frame.open();
    });

    $('#create-posts').click(function(e) {
        e.preventDefault();
        
        const $status = $('#conversion-status');
        $status.html('<p>Creating posts...</p>');

        $.ajax({
            url: imageToPost.ajaxurl,
            type: 'POST',
            data: {
                action: 'bulk_create_posts',
                nonce: imageToPost.nonce,
                image_ids: selectedImages
            },
            success: function(response) {
                if (response.success) {
                    const posts = response.data;
                    $status.html(
                        `<p>Successfully created ${posts.length} posts.</p>`
                    );
                } else {
                    $status.html('<p>Error creating posts.</p>');
                }
            },
            error: function() {
                $status.html('<p>Error creating posts.</p>');
            }
        });
    });
});