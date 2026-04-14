(function ($) {
    $.extend({
        playSound: function () {
            return $(
                '<audio class="sound-player" autoplay="autoplay" style="display:none;">'
                + '<source src="' + arguments[0] + '" />'
                + '<embed src="' + arguments[0] + '" hidden="true" autostart="true" loop="false"/>'
                + '</audio>'
            ).appendTo('body');
        },
        stopSound: function () {
            $(".sound-player").remove();
        },
        generateItemView: function (data) { 
            // const productTemplate = wp.template('item');
            // console.log(productTemplate.html());
             data['time_ago'] = $.timeago(data['order_timestamp']);
            var template = ejs.compile($('#tmpl-item').html(), {});
            var html = template(data);
            return html;
        },
        generateOrderView: function (data) {
            data['time_ago'] = $.timeago(data['order_timestamp']);
            var template = ejs.compile($('#tmpl-order').html(), {});
            var html = template(data);
            return html;
        }
    });
})(jQuery);

(function($) {
    var total_item = 0;
    let lastCheck = 0;
    let allow_sound = false;
    var view_type = 'orders';
    let client_time_offset = new Date().getTimezoneOffset();
    let last_html_str = '';
    var table_column = 4;
    var table_row = 2;
    var table_width = 0;
    var table_height = 0;
    var total_page = 1;
    var current_page = 1;
    var current_orders = [];
    var current_items = {};
    var last_version = 0;
    var hide_orders = {};
    var hiddend_items = new Array();
    var item_keys = {};



    function getDataInit(callback){

        //var time_data_url = data_url + (data_url.indexOf('?') !== -1 ? '&' : '?') + 't=' + Date.now();
        var time_data_url = data_url+'/' +lastCheck;
        
        if($('body').hasClass('processing'))
        {
            callback();
        }else {
            
            $.ajax({
                url : time_data_url,
                type: 'get',
                dataType: 'json',
                beforeSend:function(){
                    $('body').addClass('processing');
                },
                success: function(response){
                    
                    if(!response.code )
                    {
                        var list_html = '';    
                        var _index = 1;
                        let selected_view_type = view_type;// $('input[name="display"]').val();
                        let selected_area = $('select[name="type"]').val();
                        let data_response = [];
                        let old_order_length = Object.values(current_items).length;
                        if(response['items'])
                        {
                            current_items =  response['items'][selected_area];
                           
                        }
                        if(response['orders'])
                        {
                            current_orders =  response['orders'][selected_area];
                        }
                        let new_order_length = Object.values(current_items).length;
                        if(selected_view_type == 'items' && response['items'])
                        {
                            data_response = response['items'][selected_area];
                        }
                        if(selected_view_type == 'orders' && response['orders'])
                        {
                            data_response = response['orders'][selected_area];
                            current_orders = data_response;
                        }
                        let max_version = 0;
                        for(var i in data_response)
                        {
                            var row_data = data_response[i];
                            if(selected_view_type == 'orders')
                            {
                                if(row_data['ver'] > max_version)
                                {
                                    max_version = row_data['ver'];
                                }
                                var order_id = row_data['id'];
                                var order_version = row_data['ver'];
                                if(hide_orders[order_id] && hide_orders[order_id] >= order_version)
                                {

                                    
                                    continue;
                                }
                                
                            }
    
                            let order_time = row_data['order_timestamp'];
                            row_data['time_ago'] = $.timeago(order_time);
    
                            row_data['index'] = _index;
                            var in_process = readied_items.indexOf(row_data['id']);
                            
                            if(in_process >= 0)
                            {
                                row_data['done'] = 'ready';
                            }
                            _index++;
                        }
                        
                        if(new_order_length > old_order_length)
                        {
                            $('body').trigger('new-dish-come');

                        }
                        total_item = _index;
                        let has_update = false;
                        
                        if(selected_view_type == 'orders' && last_version > max_version)
                        {
                            has_update = true;
                            max_version = last_version;
                        }else{
                            if(last_html_str == '' || last_html_str != list_html)
                            {
                                
                                has_update = true;
                            }
                        }
                         if(selected_view_type == 'items')
                         {
                            has_update = true;
                         }
                        
                        if(has_update)
                        {
                            viewInit();
                            order_item_size();
                        }
                        
                    }
                    lastCheck = Math.floor(Date.now() / 1000); 
                    $('body').removeClass('processing');
                    callback();
                },
                error: function(){
                    $('body').removeClass('processing');
                    callback();
                }
            });
        }

    }
    function getData(){
        getDataInit(function(){

            setTimeout(function() {
                getData();
            }, kitchen_frequency_time);

        });
    }

    function screenSizeInit(){
        let offset  = $('#header-container').offset();
        let window_height = $(window).height();
        let window_width = $(window).width();
        let header_height = $('#header-container').outerHeight();
        
        let padding_bottom = 10;
        let kitchen_content_height = window_height - offset.top - header_height - padding_bottom;
        let kitchen_content_width = $('#bill-content').outerWidth();
        $('#bill-content').css('height',kitchen_content_height+'px');
        table_width = Math.floor(kitchen_content_width / table_column);
        table_height = Math.floor(kitchen_content_height / table_row);
        $('body').css('width',window_width);
        $('body').css('overflow','hidden');

    }
    function order_item_size(){
        
        $('body').find('.kitchen-order').each(function(){
            $(this).css('width',table_width+'px');
            $(this).css('height',table_height+'px');
        });
        total_page = Math.ceil($('body').find('.kitchen-order').length / (table_column * table_row));
        let page_html = '';
        for(i=0; i<total_page; i++)
        {
            if((i+1) == current_page)
            {
                page_html += '<a href="javascript:void(0)" data-page='+(i+1)+' class="page-item current">'+(i+1)+'</a>';
            }else{
                page_html += '<a href="javascript:void(0)" data-page='+(i+1)+' class="page-item">'+(i+1)+'</a>';
            }
            

        }
        if(total_page > 2)
        {
            page_html += '<a href="javascript:void(0)"  class="page-item prev"><span class="glyphicon glyphicon-triangle-top" aria-hidden="true"></span></a>';
            page_html += '<a href="javascript:void(0)"  class="page-item next"><span class="glyphicon glyphicon-triangle-bottom" aria-hidden="true"></span></a>';
        }
        if(total_page > 1)
        {

            $('#bill-pagination').html(page_html);
        }
       
    }
    function increaseAction(type){
        if(type == 'column')
        {
            table_column += 1;
            $('#input-column').val(table_column);
        }
        if(type == 'row')
        {
            table_row += 1;
            $('#input-row').val(table_row);
        }
       
        
    }
    function reductAction(type){
        if(type == 'column')
        {
            if(table_column >1)
            {
                table_column -= 1;
            }
            $('#input-column').val(table_column);
        }
        if(type == 'row')
        {
            if(table_row >1)
            {
                table_row -= 1;
            }
            $('#input-row').val(table_row);
        }
       
        
        
    }



    function viewInit(){
        $('body').removeClass('body-orders');
        $('body').removeClass('body-items');
        $('body').addClass('body-'+view_type);
        if(view_type == 'items')
        {
            let item_pending_html = '';
            let item_ready_html = '';
            for(current_item in current_items)
            {
                let item = current_items[current_item];
                let html = $.generateItemView(item);
            
                if(item['done'] == 'ready')
                {
                    item_ready_html += html;
                }else{
                    item_pending_html += html;
                }
                
                
            }
            

            
            let item_list_pending_html = '<div class="item-list-container pending-list">';
            item_list_pending_html += '<div class="container-fluid">';
    
            item_list_pending_html += '<div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 item-list">';
            item_list_pending_html += '<div class="container">';
            item_list_pending_html += item_pending_html;
            item_list_pending_html += '</div>';
            item_list_pending_html += '</div>';
            item_list_pending_html += '</div>';
            item_list_pending_html += '</div>';

            let item_list_ready_html = '<div class="item-list-container ready-list">';
            item_list_ready_html += '<div class="container-fluid">';
            item_list_ready_html += '<div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 item-group-list">';
            item_list_ready_html += '<div class="container">';
            item_list_ready_html += item_ready_html;
            item_list_ready_html += '</div>';
            item_list_ready_html += '</div>';
            item_list_ready_html += '</div>';
            item_list_ready_html += '</div>';
            $('#kitchen-table-body').html(item_list_pending_html + item_list_ready_html);
        }else{
            let order_html = '<div id="bill-content-orders" class="bill-content-container"><div id="kitchen-table-body">';
            for(current_order in current_orders)
            {
                var _order = current_orders[current_order];
                var order_version = _order['ver'];
                var order_id =  _order['id'];
                if(hide_orders[order_id] && hide_orders[order_id] >= order_version)
                {
                    continue;
                }
                let html = $.generateOrderView(_order);
                order_html += html;
            }
            order_html += '</div>';
            order_html += '</div>';
           
            $('#kitchen-table-body').html(order_html);
            order_item_size();
        }
       
       
    }

    $(document).ready(function(){
        screenSizeInit();
        $('select[name="type"]').on('change',function(){
            //window.location.href = $(this).val();
            $('form#kitchen-form').submit();
        });

        getData();

        $(document).on('click','.item-action-click',function(){
            var current = $(this);
            var ready_id = $(this).data('id');
            var ready_action = $(this).data('action');

            var time_data_url = kitchen_action_url + '?t='+ Date.now();
            let client_time_offset = new Date().getTimezoneOffset();
            $.ajax({
                url : time_data_url,
                type: 'post',
                dataType: 'json',
                data: {action: 'custom_action',custom_action: ready_action,id: ready_id, type: kitchen_type,client_time_offset: client_time_offset},
                beforeSend:function(){
                    $('body').addClass('processing');
                    current.hide();
                },
                success: function(response){
                    $('body').removeClass('processing');
                    if(ready_action != 'delete')
                    {
                        current.show();
                    }
                },
                error: function(){
                    $('body').removeClass('processing');
                    if(ready_action != 'delete')
                    {
                        current.show();
                    }
                }
            });

        })

        $(document).on('click','.is_cook_ready',function(){
            var current = $(this);
            var ready_id = $(this).data('id');
            var time_data_url = kitchen_action_url + '?t='+ Date.now();
            let client_time_offset = new Date().getTimezoneOffset();
            $.ajax({
                url : time_data_url,
                type: 'post',
                dataType: 'json',
                data: {action: 'update_ready',id: ready_id, type: kitchen_type,client_time_offset: client_time_offset},
                beforeSend:function(){
                    $('body').addClass('processing');
                    current.hide();
                },
                success: function(response){
                    $('body').removeClass('processing');
                    readied_items.push(ready_id);

                },
                error: function(){
                    $('body').removeClass('processing');
                }
            });
        });

        $(document).on('click','#refresh-kitchen',function(){
            if(confirm('Flush all abandoned data. Are you sure ?')){
                var time_data_url = kitchen_action_url + '?t='+ Date.now();
                $.ajax({
                    url : time_data_url,
                    type: 'post',
                    dataType: 'json',
                    data: {action: 'clear_data',warehouse: data_warehouse_id,type: kitchen_type},
                    beforeSend:function(){
                        $('body').addClass('processing');
                    },
                    success: function(response){
                        $('body').removeClass('processing');
                    },
                    error: function(){
                        $('body').removeClass('processing');
                    }
                });
            }
            
        });
        $(document).on('click','.grid-view',function(){
            view_type = $(this).data('id');
            $('.grid-view').removeClass('selected');
            $(this).addClass('selected');
            viewInit();
            //$('input[name="display"]').val(view_type);
            //$('form#kitchen-form').submit();
        });
        $(document).on('click','.page-item',function(){
            let page = current_page;
            if($(this).data('page'))
            {
                 page = $(this).data('page');
            }else{
                if($(this).hasClass('next'))
                {
                    page = current_page + 1;
                    if(page > total_page)
                    {
                        page = total_page;
                    }
                }
                if($(this).hasClass('prev'))
                {
                    page = current_page - 1;
                    if(page < 1)
                    {
                        page = 1;
                    }
                }
                
            }
            current_page = page;
            
            $(document).find('.page-item').each(function(){
                if($(this).data('page') &&  $(this).data('page') == current_page)
                {
                    $(this).addClass('current');
                }else{
                    $(this).removeClass('current');
                }
                
            })
            
            
            let top_offset = 0 - (table_height * table_row) * (page - 1);
            
            $('#bill-content-orders').css('top',top_offset+'px');
        });

        $(document).on('click','#page-menu-arrow',function(){
            
            if($('#bill-content-page-container').hasClass('is-open'))
            {
                $('#bill-content-page-container').removeClass('is-open');
                $('body').removeClass('menu-is-open');
            }else{
                $('#bill-content-page-container').addClass('is-open');
                $('body').addClass('menu-is-open');
            }
        });
        $(document).on('click','.page-menu',function(){
            let action = $(this).data('action');
            switch(action)
            {
                 case 'setting':
                    $('#input-column').val(table_column);
                    $('#input-row').val(table_row);
                    $('#myModal').modal('show');
                    break;
                 case 'refresh':
                    last_version = 0
                    hide_orders = {};
                    let parent = $(this).find('span');
                    parent.addClass('fa-spin');
                    getDataInit(function(){
                        parent.removeClass('fa-spin');
                    });
                    break;
            }
            
        });
        $(document).on('click','.grid-setting-action',function(e){
            let action = $(this).data('action');
            let type = $(this).data('type');
            console.log(action);
            switch(action)
            {
                case 'increase':
                    increaseAction(type);
                    break;
                case 'reduct':
                    reductAction(type);
                    break;
                case 'darkmode':
                    let is_checked = e.target.checked;
                    console.log(e);
                    is_checked ?  $('body').addClass('dark-mode') : $('body').removeClass('dark-mode'); 
                    break;
            }
           
            screenSizeInit();
            order_item_size();
        });
        $(document).on('click','.order-action-click',function(){
            var action = $(this).data('action');
            if(action == 'hide')
            {
                var id = $(this).data('id');
                var version = $(this).data('ver');
                hide_orders[id] = version;
                $(this).closest('.kitchen-order').hide();
            }
            
        });
    });
    var is_nosleep = false;
    document.body.addEventListener("click", function () {
      if(!is_nosleep)
      {
           var noSleep = new NoSleep();
           noSleep.enable();  
           is_nosleep = true;
           $('body').append('<img style="position: absolute;top: 5px;left: 5px;width: 10px;" src="data:image/png;base64,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"/>');
           console.log('start no sleep');
      }
   });


}(jQuery));