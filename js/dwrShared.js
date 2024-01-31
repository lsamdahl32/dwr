
$( document ).ready(function () {

    $("#nav-toggle").on('click', function() {
        let el = $("header nav");
        if (el.css("left") === "-350px") {
            // open
            el.css("left", "0");
            // trap clicks outside of menu
            $(document).on('click.navMenu', function (e) {
                // if (e.target.id !== "nav-toggle" && e.target.parentElement.id.substring(0, 3) !== "nav") {
                if (e.target.id !== "nav-toggle" && e.target.parentElement.parentElement.id.substring(0, 3) !== "nav") {
                    el.css("left", "-350px");
                    $(document).off('click.navMenu');
                }
            });
        } else {
            // close
            el.css("left", "-350px");
            $(document).off('click.navMenu');
        }
    });

});

function doSubmitWithValues(nvp, id, keyField, replace) {
    var theForm = $("#myForm");
    if (!theForm.length) {
        $('body').append('<form action="" method="post"  id="myForm"></form>');
        theForm = $("#myForm");
    }
    for (var key in nvp) {
        var value = nvp[key];
        $("<input type='hidden' value=\""+value+"\" />")
            .attr("id", key)
            .attr("name", key)
            .appendTo(theForm);
    }
    // replace any search values
    for (var key in replace) {
        $("#" + key).val(replace[key]);
    }
    let el = $("#myForm input[name=linkID]");
    if (el.length > 0) { // replace the value
        el.val(id);
    } else {
        $("<input type='hidden' value=" + id + " />")
                .attr("id", "linkID")
                .attr("name", "linkID")
                .appendTo(theForm);
    }
    let el2 = $("#myForm input[name=linkField]");
    if (el2.length > 0) { // replace the value
        el2.val(id);
    } else {
        $("<input type='hidden' value=" + keyField + " />")
                .attr("id", "linkField")
                .attr("name", "linkField")
                .appendTo(theForm);
    }
    theForm.submit();
}

function post_to_url(url, data, method, target) {
    method = method || "post"; // Set method to post by default, if not specified.
    target = target || "_blank"; // default for target

    $('body').append('<form action="'+url+'" method="'+method+'" target="'+target+'" id="postToUrl"></form>');
    $.each(data,function(n,v){
        $('#postToUrl').append('<input type="hidden" name="'+n+'" value="'+v+'" />');
    });
    // console.log('Posting ' + url);
    $('#postToUrl').submit().remove();
}
