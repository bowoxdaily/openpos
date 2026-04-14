
<script type="text/html" id="tmpl-item">
    <div class="item-row row">
        <div class="col-md-10 col-sm-10 col-xs-10 item-index">
            <p class="item-name"><span class="item-qty"> <%= qty %> </span> x <span class="dining <%- dining %>"><%- dining %></span><%= item %></p>
            <p class="item-note"><%- note %></p>
            <% if( typeof order_note != "undefined" && order_note.length > 0 ){ %>
            <p class="order-note"><%- order_note %></p>
            <% } %>
            <p class="order-time"><%- table %> / <%- time_ago %></p>
        </div>
        <div class="col-md-2 col-sm-2 col-xs-2 text-center item-action"> 
            <% if (allow_action.length == 0 ) { %> 
                <% if (done != "ready" && done != "done" ) { %> 
                    <a data-id="<%- id %>" href="javascript:void(0);" class="is_cook_ready"> <span class="glyphicon glyphicon-bell" aria-hidden="true"></span> </a> 
                <% } else { %> 
                    <span class="glyphicon glyphicon-ok" aria-hidden="true"></span> 
                <% } %>
            <% }else{ %>
                <% allow_action.forEach(function(action){ %>
                    <% if (action == "delete" ) { %> 
                        <a data-id="<%- id %>" data-action="<%= action %>" href="javascript:void(0);" class="item-action-click"> <span class="glyphicon glyphicon-trash" aria-hidden="true"></span> </a> 
                    <% } else { %> 
                        <a data-id="<%- id %>" data-action="<%= action %>" href="javascript:void(0);" class="item-action-click"><%= action %> </a> 
                    <% } %>
                <% }) %>
            <% } %>
           
        </div>
    </div>
</script>
<script type="text/template" id="tmpl-order">
    <% var items_id = []; %>
    <% var total_qty = 0; %>
    <% var serverd_qty = 0; %>
    <div class="kitchen-order order-type-<%- desk.type %>" id="order-<%- id %>">
        <div class="order-container">
            <div class="order-header">
                <h3>
                <%- desk.name %>
                <% if(customer != undefined && customer['name'] && customer['name'].length > 0){ %>
                
                                <span class="order-customer-name"><%- customer.name %></span>
                <% } %> 
                </h3>
                <span class="order-time-ago"><%= time_ago %></span>
            </div>
            <div class="order-items">
                <ul>
                    <% if(note.length > 0){ %>
                        <li class="order-note">
                        <p><%- note  %></p>
                        </li>
                    <% } %>
                    <% items.forEach(function(item){ %>
                        <% items_id.push(item.id );%>
                        <li class="dining <%- item.dining %> <%- item.done %> ">
                        <p>
                        <% if (item.done != "ready" && item.done != "done" && item.done != "done_all" ) { %> 
                            <a data-id="<%- item.id %>" href="javascript:void(0);" class="is_cook_ready"> <span class="glyphicon glyphicon-ok-circle" aria-hidden="true"></span> </a> 
                        <% }else{ %>
                            <% serverd_qty += item.qty; %>
                        <% } %> 
                    <% total_qty += item.qty; %>
                    <span class="item-qty"><%= item.qty %></span> x <%= item.item %>
                        <% if(item.dining == 'takeaway'){ %>
                            <span class="dining-takeaway">takeway</span>
                        <% }; %>
                        <% if(item.note.length > 0){ %>
                            <br/>
                            <span class="option-item"><i><%- item.note  %></i></span>
                        <% }; %>
                        </p>
                        <% if(item.seller_name.length > 0){ %>
                            
                            <span class="item-seller"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> <%- item.seller_name  %></span>
                        <% }; %>
                        <% var item_date = new Date(item.order_timestamp);  %>
                        <span class="item-order-time"><%- item_date.getHours() < 10 ? '0'+item_date.getHours() : item_date.getHours() %>:<%- item_date.getMinutes() < 10 ? '0'+item_date.getMinutes() : item_date.getMinutes() %></span>
                        </li>
                    <% }); %>
                    
                </ul>
            </div>
            
            <div class="order-action container-fluid">
            
            <div class="order-action-btn row">
                    <div class="col-md-4 col-sm-4 col-xs-6">
                        <span class="<%= serverd_qty == total_qty ? "all-servered" : "" %>"><%= serverd_qty %> / <%= total_qty %></span>
                    </div>
                    <div class="col-md-8 col-sm-8 col-xs-6">
                        <% if (allow_action.length == 0 ) { %> 
                                <% if(serverd_qty != total_qty){ %>
                                <a data-id="<%- items_id.join(',') %>" href="javascript:void(0);" class="is_cook_ready"> <span class="glyphicon glyphicon-ok-sign" aria-hidden="true"></span> </a> 
                                <% }else{ %>
                                <a data-id="<%- id %>" data-ver="<%- ver %>" href="javascript:void(0);" class="order-action-click" data-action="hide"> <span class="glyphicon glyphicon-eye-close" aria-hidden="true"></span> </a> 
                                <% } %>
                        <% }else{ %>
                            <% allow_action.forEach(function(action){ %>
                                <% if (action == "delete" ) { %> 
                                    <a data-id="<%- items_id.join(',') %>" data-action="<%= action %>" href="javascript:void(0);" class="item-action-click"> <span class="glyphicon glyphicon-trash" aria-hidden="true"></span> </a> 
                                <% } else { %> 
                                    <a data-id="<%- items_id.join(',') %>" data-action="<%= action %>" href="javascript:void(0);" class="item-action-click"><%= action %> </a> 
                                <% } %>
                            <% }) %>
                        <% } %>
                    </div>
                
            
            </div>
            </div>
        </div>

    </div>
</script>
<div class="container">
    <div class="header-container" id="header-container">
        <div class="row">
            <div class="col-md-12 text-center">
                <h3><?php echo __('Kitchen View','openpos'); ?></h3>
            </div>
        </div>
        <div class="row kitchen-control-container">
            <div class="col-sm-4 col-md-4col-xs-12 pull-left grid-view-control" >
                    <p>
                        <a href="javascript:void(0);" data-id="items" class="grid-view <?php echo $grid_type == 'items' ? 'selected':'' ; ?>">
                        <?php echo __('Items View','openpos'); ?> 
                        </a>
                        <a href="javascript:void(0);" data-id="orders" class="grid-view <?php echo $grid_type == 'orders' ? 'selected':'' ; ?>">
                        <?php echo __('Orders View','openpos'); ?> 
                        </a>
                    </p>
            </div>
            <div class="col-md-6 col-sm-6 col-xs-8 grid-view-area">
                <div class="col-md-6 col-md-offset-1">
                    <form class="form-horizontal"  action="<?php echo $kitchen_url ; ?>" id="kitchen-form" method="get">
                        <div class="form-group">
                            <label for="inputEmail3" class="col-sm-3 col-xs-3 col-md-3 control-label"><?php echo __('Area','openpos'); ?></label>
                            <div class="col-sm-8 col-xs-8 col-md-8">
                                    <select class="form-control" name="type">
                                        <option value="all" <?php echo ($kitchen_type == 'all') ? 'selected':'';?> > <?php echo __('All','openpos'); ?></option>
                                        <?php foreach($all_area as $a_code => $area): ?>
                                            <option value="<?php echo esc_attr($a_code); ?>" <?php echo ($kitchen_type == $a_code ) ? 'selected':'';?> ><?php echo $area['label']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="display" value="<?php echo $grid_type; ?>"  />
                                    <input type="hidden" name="id" value="<?php echo $id ; ?>"  />
                                    
                                    <input type="submit" style="display:none;" />
                            </div>
                            
                        </div>

                    </form>
                </div>
            </div>
            <div class="col-sm-2 col-md-2 pull-right grid-view-reload" style="text-align:right;">
                        <a href="javascript:void(0);" data-id="<?php echo $id; ?>" id="refresh-kitchen"> <span class="glyphicon glyphicon-retweet" aria-hidden="true"></span> </a>
            </div>
        </div>
    </div>
    <div  id="bill-content">
        <?php if($grid_type == 'items'): ?>
            <div id="bill-content-items" class="bill-content-container">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th class="text-center">#</th>
                            <th><?php echo __('Item','openpos'); ?></th>
                            <th class="text-center"><?php echo __('Qty','openpos'); ?></th>
                            <th><?php echo __('Order Time','openpos'); ?></th>
                            <th><?php echo __('Table / Order','openpos'); ?></th>
                            <th class="text-center"><?php echo __('Ready ?','openpos'); ?></th>
                        </tr>
                        </thead>
                        <tbody id="kitchen-table-body">

                        </tbody>
                    </table>
            </div>
        <?php else: ?>
            <div id="bill-content-orders" class="bill-content-container" >
                <div id="kitchen-table-body"></div>
            </div>
            
        <?php endif; ?>
    </div>
 
</div>
<?php if($grid_type != 'items'): ?>
 <div id="bill-content-page-container" class="is-open">
    <div class="top-control">
        <a href="javascript:void(0)" data-action="setting"  class="page-menu"><span class="glyphicon glyphicon-wrench" aria-hidden="true"></span></a>
    </div>
    <div class="mid-control" id="bill-pagination"></div>
    <div class="bottom-control">
        <a href="javascript:void(0)" data-action="refresh" class="page-menu"><span class="glyphicon glyphicon-refresh" aria-hidden="true"></span></a>
    </div>

    <a href="javascript:void(0)" class="page-menu-arrow" id="page-menu-arrow">
        <span class="glyphicon glyphicon-triangle-right menu-close" aria-hidden="true"></span>
        <span class="glyphicon glyphicon-triangle-left menu-open " aria-hidden="true"></span>
    </a>
       
 </div>
 <?php endif; ?>
 <!-- Modal -->
<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel"><?php echo __('Setting','openpos'); ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form class="form-horizontal">
            <div class="form-group">
                <label for="inputPassword3" class="col-sm-2 col-xs-4 control-label"><?php echo __('Dark mode','openpos'); ?></label>
                <div class="col-sm-6 col-xs-8">
                    <div class="row">
                        <div class="col-sm-4 col-xs-4">
                            <input class="btn btn-default grid-setting-action" data-action="darkmode" id="input-darkmode" checked type="checkbox" />
                        </div>
                       
                    </div>
                    
                </div>
            </div>
            <div class="form-group">
                <label for="inputEmail3" class="col-sm-2 col-xs-4 control-label"><?php echo __('Columns','openpos'); ?></label>
                <div class="col-sm-6 col-xs-8">
                    <div class="row">
                        <div class="col-sm-4 col-xs-4">
                            <input class="btn btn-default pull-right grid-setting-action" data-action="reduct" data-type="column"  type="button" value="-">
                        </div>
                        <div class="col-sm-4 col-xs-4">
                            <input type="number" disabled class="form-control" id="input-column">
                        </div>
                        <div class="col-sm-4 col-xs-4">
                            <input class="btn btn-default pull-left grid-setting-action" data-action="increase" data-type="column" type="button" value="+">
                        </div>
                    </div>
               
                </div>
            </div>
            <div class="form-group">
                <label for="inputPassword3" class="col-sm-2 col-xs-4 control-label"><?php echo __('Rows','openpos'); ?></label>
                <div class="col-sm-6 col-xs-8">
                    <div class="row">
                        <div class="col-sm-4 col-xs-4">
                            <input class="btn btn-default pull-right grid-setting-action" data-action="reduct" data-type="row" type="button" value="-">
                        </div>
                        <div class="col-sm-4 col-xs-4">
                            <input type="number" disabled class="form-control" id="input-row">
                        </div>
                        <div class="col-sm-4 col-xs-4">
                            <input class="btn btn-default pull-left grid-setting-action" data-action="increase" data-type="row" type="button" value="+">
                        </div>
                    </div>
                    
                </div>
            </div>
            
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        
      </div>
    </div>
  </div>
</div>

<?php
$handes = array(
    'openpos.kitchen.script'
);

wp_print_scripts(apply_filters('openpos_kitchen_footer_js',$handes));
?>

<button id="button-notification" style="display: none;"  type="button"></button>

<script type="text/javascript">

    (function($) {

        $(document).ready(function(){
            $('#button-notification').on('click',function(){
                $.playSound("<?php echo apply_filters('op_kitchen_notification_sound', OPENPOS_URL.'/assets/sound/helium.mp3');  ?>");
            });
            $('body').on('new-dish-come',function(){
                $('#button-notification').trigger('click');
            })

        });
    }(jQuery));

</script>