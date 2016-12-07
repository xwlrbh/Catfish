/**
 * Created by A.J on 2016/10/10.
 */
$(document).ready(function(){
    $("form").submit(function(){
        $(this).find("button[type='submit']").children("span").removeClass("hidden");
    });
});