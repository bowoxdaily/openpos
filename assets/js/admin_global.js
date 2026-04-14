
(function($) {




$(document).ready(function(){
    $( 'input#_manage_stock' ).on( 'click',function(){
        if($(this).prop('checked'))
        {
           $('._op_stock_field').removeClass('op-no-display');
        }else{
           $('._op_stock_field').addClass('op-no-display');
        }
        
    } );
})



}(jQuery));