/** password functions
 * Lee - 3/17/2020
 * expects const DB set to $db
 * Contained in an anonymous closure
 */
(function () {

    $( document ).ready(function () {
        $("#resetForm").on('click', function () {
            dirty = false;
            if ($("#new_password_span").length) {
                closePasswordForm();
            }
        });

        $("#change_password_button").on('click', function () {
            $("#new_password_span").show();
            $("#change_password_button").hide();
            $("#password").attr("disabled", false);
            $("#password2").attr("disabled", false);
            // $("#submitbutton").attr("disabled", true); // this won't work with new RecordManage class
        });

        $("#password2").on("keyup", function () {
            passwordMatch();
        });

        $("#password").on("keyup", function () {
            if (DB.substring(0, 6) === 'atools') {
                var pw = $("#password").val();
                var pw2 = $("#password2").val();
                $("#str4").addClass("bi-x-circle").addClass("bad_color").removeClass('bi-check-circle').removeClass('good_color');
                //if txtpass bigger than 11
                //if txtpass has both lower and uppercase characters
                //if txtpass has at least one number give 1 point
                if ((pw.length > 11) && ((pw.match(/[a-z]/)) && (pw.match(/[A-Z]/))) && (pw.match(/\d+/))) $("#str4").addClass("bi-check-circle").addClass("good_color").removeClass('bi-x-circle').removeClass('bad_color');
            }
            //if matches pw2
            passwordMatch();
        })

    });

    function passwordMatch() {
        if ($("#new_password_span").length) {
            // if passwords match and have minimum chars, upper/lower and #'s, enable the Submit button
            var pw = $("#password").val();
            var pw2 = $("#password2").val();
            if (pw === pw2 && pw.length > 0) {
                $("#str5").addClass("bi-check-circle").addClass("good_color").removeClass('bi-x-circle').removeClass('bad_color');
                // $("#submitbutton").attr("disabled", false); // this won't work with new RecordManage class
            } else {
                // do not match
                $("#str5").addClass("bi-x-circle").addClass("bad_color").removeClass('bi-check-circle').removeClass('good_color');
                // $("#submitbutton").attr("disabled", true); // this won't work with new RecordManage class
            }
        }
    }
}());

function closePasswordForm() {
    $("#new_password_span").hide();
    $("#change_password_button").show();
    $("#password").attr("disabled", true).val(''); // disable password input so it won't show up in POST
    $("#password2").attr("disabled", true).val('');
    // $("#submitbutton").attr("disabled", false); // this won't work with new RecordManage class
}
