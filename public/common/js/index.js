/**
 * Created by A.J on 2016/10/17.
 */
$(document).ready(function(){
    $.post("admin/Index/version", { },
        function(data){
            $("#latestversion").html(data);
        });
});