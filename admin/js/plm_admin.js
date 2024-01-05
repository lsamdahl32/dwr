$(document).ready(function () {
    $("#adminMenu a").on('click', function () {
        let id = $(this).attr('id');
        let type = $(this).attr('data-type');
        loadPage(id, type);
        if (window.matchMedia('(max-width: 920px)').matches) {
            showHideMenu(false);
        }
    });

    // export print options
    $("#openExportPrintOptions").on('click', function () {
        $("#openExportPrintOptions").hide(200);
        $("#exportPrintOptions").show(200);
    });

    $("#cancelExportBtn").on('click', function () {
        $("#openExportPrintOptions").show(200);
        $("#exportPrintOptions").hide(200);
    });

    $("#exportPrintOptions input[type='radio']").on('change', function () {
        if ($("#exporttype1").is(":checked")) {
            // $("#ExportPrint_excel_options").hide(200);
            $("#ExportPrint_csv_options").hide(200);
            $("#ExportPrint_pdf_options").show(200);
            // } else if ($("#exporttype2").is(":checked")) {
            //     $("#ExportPrint_csv_options").hide(200);
            //     $("#ExportPrint_pdf_options").hide(200);
            //     $("#ExportPrint_excel_options").show(200);
        } else {
            $("#ExportPrint_pdf_options").hide(200);
            // $("#ExportPrint_excel_options").hide(200);
            $("#ExportPrint_csv_options").show(200);
        }
    });

    $("#exportBtn").on('click', function () {
        let data = {};
        $('#myForm').serializeArray().map(function(x){data[x.name] = x.value;});
        data['atr_process']     = 'export';
        data['exporttype']      = $("input[name='exporttype']:checked").val();
        data['scaleFactor']     = $("#scaleFactorInput").val();
        data['currentpagePDF']     = $("#currentpageInputPDF").is(":checked");
        data['currentpageCSV']     = $("#currentpageInputCSV").is(":checked");
        // console.log(data);
        post_to_url(window.location.href, data, 'post');
    });

});

function loadPage(id, type) {
    $("#home_right_col_titlebar h1").html(id);
    $("#adminMenu a").removeClass('menuSelected');
    $("#" + id).addClass('menuSelected');
    $(".home_right_col_content").hide();
    $("#" + type).show();
}

function searchForBookings() {
    // ajax call to get results formatted as html    
    let search = $("#searchBookings").val();
    if (search.length > 0) {
        $.post(window.location.href, {
            process: 'searchBookings',
            search: search,
        }, function (result) {
            // console.log(result);
            $('#searchBookingsResultsContents').html(result);        
            $('#searchBookingsResults').show(200);
        });
    }
}

/**
 * This function will redirect the user to the login page if the AJAX return contains such a redirect
 * It should be called immediately after any AJAX call that is login sensitive.
 * todo needs to handle objects also
 * @param data
 */
function checkLoginStatus(data) {
    if (typeof data === 'string') {
        // Disabled in this project
        // if (data.substring(0, 42) === "<meta http-equiv='refresh' content='0;url=") {
        //     let calledFrom = decodeURI(data.substring(data.indexOf('?calledFrom'), (data.length - 4))); // sent from pageHeader?
        //     document.location = '/login.php' + calledFrom;
        // } else if (data.indexOf('<title>Spectrasonics Admin Tools Login</title>') > 0) {
        //     document.location = '/login.php'; // probably sent from manageAjax.php
        // }
    }
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
