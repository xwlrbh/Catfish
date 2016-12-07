/**
 * Created by A.J on 2016/10/16.
 */
$(document).ready(function(){
    $("#catfishtijiaoliuyan").click(function(){
        var obj = $(this);
        if(obj.children("span:eq(1)").hasClass("hidden")){
            obj.children("span:eq(0)").removeClass("hidden");
            obj.children("span:eq(1)").addClass("hidden");
            $.post($("#webroot").text()+"index/Index/liuyan", $("#catfishliuyan").serialize(),
                function(data){
                    obj.children("span:eq(0)").addClass("hidden");
                    if(data == 'ok'){
                        obj.children("span:eq(1)").removeClass("hidden");
                    }
                    else
                    {
                        $("#catfishliuyantishi").text(data);
                    }
                });
        }
    });
});