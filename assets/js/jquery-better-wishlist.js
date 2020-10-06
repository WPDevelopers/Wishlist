(function($) {

    $(document).ready(function() {
        $(document).on('click', '.add_to_wishlist_button', function(e) {
            
            var $this = $(this),
                product_id = $this.attr( 'data-product-id' ),
                product_wrap = $('.add-to-wishlist-' + product_id),
                data = {
                    action: BETTER_WISHLIST_SCRIPTS.actions.add_to_wishlist_action,
                    context: 'frontend',
                    add_to_wishlist: product_id,
                    product_type: $this.data('product-type'),
                    wishlist_id: $this.data('wishlist-id' ),
                    fragments: product_wrap.data('fragment-options')
                };

                e.preventDefault();

                $.ajax({
                    type: 'POST',
                    url: BETTER_WISHLIST_SCRIPTS.ajax_url,
                    data: data,
                    success: function( response ) {
                        if(response.success) {
                            $this.replaceWith(data.fragments.already_in_wishlist_text + ' <a href="'+data.fragments.wishlist_url+'">'+data.fragments.browse_wishlist_text+'</a>');
                        }
                    },
                    error: function( response ) {
                        console.log(response);
                    }
                });
                
            


        });
    });
    
})(jQuery);