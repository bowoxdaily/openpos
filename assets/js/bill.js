(function($) {
    var last_item = 0;
    var last_data_str = '';
    /* Get into full screen */
    function GoInFullscreen(element) {
        screenfull.request();
    }

    /* Get out of full screen */
    function GoOutFullscreen() {
        screenfull.request();
    }

    /* Is currently in full screen or not */
    function IsFullScreenCurrently() {
        if (screenfull.enabled) {
            return false;
        }
        // If no element is in full-screen

        return true;
    }

    function getDataInit(callback){
        var time_data_url = data_url + '?t='+ Date.now();
        $.ajax({
            url : time_data_url,
            type: 'get',
            dataType: 'json',
            success: function(response){
                
                response.lang = lang_obj;
                
                var template = ejs.compile(data_template['template'], {});
                var html = template(response);
                if(last_data_str.length == 0 || last_data_str != html)
                {
                    last_data_str = html;
                   
                    $('#bill-content').html(html);
                }

                let _last_item = $(document).find('#bill-footer').data('last_item');
                if(last_item != _last_item && _last_item != undefined)
                {
                    
                    last_item = _last_item;
                    if($("#item-"+last_item).length)
                    {
                        $(document).find('#bill-products').animate({
                            scrollTop: $("#item-"+last_item).offset().top
                        }, 1000);
                    }
                    
                }
                
                
                callback();
            },
            error: function(){
                callback();
            }
        });
    }
    function getData(){
        getDataInit(function(){
            setTimeout(function() {
                getData();
            }, bill_frequency_time);
        });
    }
    $(document).ready(function(){
        var noSleep = new NoSleep();
        getData();

        $(document).on('click','#go-button', function() {

            if ($('body').hasClass('on-full')) {
                $('body').removeClass('on-full');
                noSleep.disable();
                screenfull.exit();
            } else {

                $('body').addClass('on-full');
                noSleep.enable();
                screenfull.request();
            }
        });

    });



}(jQuery));