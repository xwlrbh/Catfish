/**
 * Created by A.J on 2016/10/2.
 */

$(document).ready(function(){
    if($("#fabushijian").length > 0){
        $("#fabushijian").datetimepicker({format: 'yyyy-mm-dd hh:ii:ss'});
    }
    var um = UM.getEditor('editor',{
        autoFloatEnabled:false
    });
    var tmp='',pic='',picw='';
    //修改初始化
    if($("#slt").val() != ''){
        tmp = $("#suolvetu").html();
        $("#suolvetu img").attr('src',$("#slt").val());
    }
    //保存
    $("#baocun").click(function(){
        $("#zhengwen").text(um.getContent());
        if($("#zhaiyao").val() == ''){
            if(um.getContentTxt().length > 200){
                $("#zhaiyao").val(um.getContentTxt().substr(0,200)+'...');
            }
            else{
                $("#zhaiyao").val(um.getContentTxt());
            }
        }
    });
    //缩略图
    $('#upload').uploadify({
        auto:true,
        fileTypeExts:'*.jpg;*.png;*.gif;*.jpeg',
        multi:false,
        formData:{upload:1},
        fileSizeLimit:9999,
        buttonText:$('#buttonText').text(),
        showUploadedPercent:true,//是否实时显示上传的百分比，如20%
        showUploadedSize:false,
        removeTimeout:3,
        uploader:$("#upload_url").text(),
        onUploadComplete:function(file,data){
            pic = $("#webroot").text()+'data/uploads/'+data.replace('\\','/');
            $("#bendi .panel-body").html('<img src="'+pic+'" class="img-responsive" alt="Responsive image">');
        }
    });
    $("#queding").click(function(){
        tmp = $("#suolvetu").html();
        if($("#xuanbendi").hasClass("active") && pic != ''){
            $("#suolvetu").html('<img src="'+pic+'" class="img-responsive" alt="Responsive image">');
            $("#slt").val(pic);
        }
        else if($("#xuanwangluo").hasClass("active") && picw != ''){
            $("#suolvetu").html('<img src="'+picw+'" class="img-responsive" alt="Responsive image">');
            $("#slt").val(picw);
        }
        if(pic != '' || picw != ''){
            $("#shangchuantu").addClass("hidden");
            $("#quxiaotu").removeClass("hidden");
        }
        $('#myModal').modal('hide');
    });
    //取消缩略图
    $("#quxiaotu").click(function(){
        $("#suolvetu").html(tmp);
        $("#slt").val('');
        $("#quxiaotu").addClass("hidden");
        $("#shangchuantu").removeClass("hidden");
    });
    //网络图片
    $("#wangluodizhi").change(function(){
        picw = $("#wangluodizhi").val();
        $("#wangluo .panel-body").html('<img src="'+picw+'" class="img-responsive" alt="Responsive image">');
    });
});
