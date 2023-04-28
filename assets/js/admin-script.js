
jQuery(document).ready(function ($) {
    // open image on click it in orders table
    $(document).on('click', '.supg-image-popup', function(){
        // get image url
        var img_url = $(this).find('img').attr('src');
        // append container on body to view image
        var wrapper = $('<div class="supg-image-viewer"><img src="'+img_url+'"></div>');
        $('body').append(wrapper);

        // close on click
        wrapper.click(function(){
            wrapper.remove();
        });
    });
});