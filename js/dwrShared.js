
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
