(function($) {

   // alert(ajaxurl);
    $(document).on('click','#generate-pin-link',function(){
        let user_id = $(this).data('id');
        let op_nonce = $(this).data('op_nonce');
        $.ajax({
            url: ajaxurl,
            data: {user_id: user_id,op_nonce:op_nonce,action: 'op_generate_pin'},
            dataType: 'json',
            type: 'post',
            beforeSend: function(){
                $('#generate-pin-link').prop('disabled',true);
            },
            success: function(response){
                $('#generate-pin-link').prop('disabled',false);
                if(response.status == 1)
                {
                    $('#user-pint-description').text(response.message);
                }else{

                }
            },
            error: function(){
                $('#generate-pin-link').prop('disabled',false);
            }
        })
    });


}(jQuery));