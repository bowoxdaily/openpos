

(function($) {
    var total_item = 0;
    let lastCheck = 0;
    var view_type = 'items';
    let client_time_offset = new Date().getTimezoneOffset();
    let last_html_str = '';
    var table_column = 4;
    var table_row = 2;
    var table_width = 0;
    var table_height = 0;
    var total_page = 1;
    var current_page = 1;
    var current_orders = [];
    var last_version = 0;
    var hide_orders = {};
    const ready_template = wp.template('ready-order'); 
    const pending_template = wp.template('pending-order'); 

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
                    //$('#kitchen-table-body').empty();
                    if(!response.code )
                    {
                        
                        var _index = 1;
                        let selected_view_type = $('input[name="display"]').val();
                        let selected_area = $('select[name="type"]').val();
                        let data_response = response['orders']['all'];
                     
                        
                        var pending_html = '';
                        var ready_html = '';

                        console.log(data_response);

                        for(var i in data_response)
                        {
                            
                           
                            
                            var row_data = data_response[i];
                            var label = row_data.label ? row_data.label : row_data.desk.name;
                            var ready_count = 0;
                            var item_count = row_data.items.length;
                            
                            for(let j=0;j<item_count;j++)
                            {
                                if(row_data.items[j].done == 'ready')
                                {
                                    ready_count++;
                                }
                            }
                            if(ready_count == item_count)
                            {
                                const html = ready_template({
                                    label: label
                                 });
                                 ready_html += html;
                            }else{
                                const html = pending_template({
                                    label: label,
                                    percentage: Math.ceil((ready_count/item_count)*100),
                                 });
                                 pending_html += html;
                            }
                             
                             
                            _index++;
                        }
                        if(ready_html == '' )
                        {
                                ready_html = '<div class="no-orders">'+kitchen_no_orders+'</div>';
                        }
                        if(pending_html == '' )
                        {
                                pending_html = '<div class="no-orders">'+kitchen_no_orders+'</div>';
                        }
                        $('#ready-orders').html(ready_html);
                        $('#processing-orders').html(pending_html);
                        
                        
                        
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

   
   

    $(document).ready(function(){
      
        getData();

    });


    
    var is_nosleep = false;
    document.body.addEventListener("click", function () {
      if(!is_nosleep)
      {
           var noSleep = new NoSleep();
           noSleep.enable();  
           is_nosleep = true;
           console.log('start no sleep');
      }
   });


}(jQuery));