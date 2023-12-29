/**
 * atReports.js
 * js functions used by the ATReports class
 * by Lee Samdahl
 * 6/9/2021
 */

let atrScripts = function (settings) {

    $("#myForm #searchBtn").on('click', function (e) { // Search button clicked
        e.preventDefault(); // don't allow form to actually submit
        if (settings.hasManage) {
            rm.setDirty(false); // if coming from edit mode, clear the dirty flag
        }
        let data = $("#myForm").serialize() + '&atr_process=search';
        // console.log(data);
        $.post(settings.selfURL, data, function (result) {
            checkLoginStatus(result);
            // let arr = result.split("|");
            // var obj = JSON.parse(arr[1]);
            var obj = JSON.parse(result);
            // console.log(obj);
            if (obj.selectCriteria.length > 0) {
                $(".selectCriteria").html('Search For: ' + obj.selectCriteria.join(', '));

            } else {
                $(".selectCriteria").html('Showing All Records');
            }
            // remove any temporary search fields
            $("#myForm .tempSearch").remove();
            if (settings.hasHomePage) {
                $("#at-homepage").hide(transitionTime);
                $("#at-report").show(transitionTime);
                $("#report_page_title").html(pageTitle);
                $("#footer_message_left").html(pageTitle); // show current report title in the footer
            }
            rt.setBindtypes(obj.pq_bindtypes);
            rt.setBindValues(obj.pq_bindvalues);
            rt.setWhere(obj.where);
            rt.refresh(); // refresh the grid
            $("#showAll").show();
            if (breadcrumbs) {
                $("#breadcrumbPageTitle").show();
                $("#breadcrumbs").html(' ' + BI_CARET_RIGHT + ' Search Results');
            }
            if (settings.hideSearchCriteria) { //hide the search criteria
                if (obj.selectCriteria.length > 0) {
                    hideSearch(true);
                } else {
                    hideSearch(false);
                }
            }
        });

        $("#myForm").on('keydown', function (e) { // Handle Enter key clicked in search form
            if (e.keyCode === 13) {
                $("#myForm #searchBtn").trigger('click');
            }
        });
    });

    $(".doOpenSearch").on('click', function () {
        // display search-fieldset, hide openSearchButton
        $("#ar-searchSettings").show();
        $("#openSearchButton").hide(200);
        $("#search-fieldset").show(200, function () {
            $('#myForm input:visible:enabled:first').focus();
        });
    });

    $(".doShowAll").on('click', function () {
        // reset all Selects to first option, clear all inputs, and Submit
        $("#" + keyField + '_in1').val('');// clear any hidden default search
        $("#" + keyField + '_so').val('All');// clear any hidden default search
        $('#myForm input[name="' + keyField + '"]').remove();// clear any hidden default search
        $("#myForm input").each(
                function (index) {
                    var input = $(this);
                    if (input.attr('type') === 'text') {
                        input.val("");
                    }
                }
        );
        $("#myForm select").each(
                function (index) {
                    var sel = $(this);
                    sel[0].selectedIndex = 0;
                    doOnChange(sel.val(), sel.attr('id'));
                }
        );
        $(".selectCriteria").html("New Search");
        $('#myForm input:visible:enabled:first').focus();
    });

    $(".ar_search_fields select").on('change', function () {
        doOnChange(this.value, this.id);
    });

    // drag and drop functions in Report Settings dialog for column order - Lee 5/18/2017
    var dragParentID = 'dragColumnNames';
    var source;
    $("#settingsContent").on('drop', '.dragColumn', function (e) { // use delegated event handlers
        e.preventDefault();
        e.stopPropagation();
        var el = e.target;
        if (!$("#" + el.id).hasClass('dragColumn')) {
            el = e.target.parentElement;
        }
        // insert the dropped element
        var srcOrder = Number($("#" + source.id + " > input").val());
        var destOrder = Number($("#" + el.id + " > input").val());
        // console.log("src: " + srcOrder + " - dest: " + destOrder);
        if (srcOrder > destOrder) {
            // move the intermediate elements down in order
            var totRows = $("#" + dragParentID).children().length;
            // console.log("moved up " + totRows);
            $($("#settingsContent .dragColumn").get().reverse()).each(function (index) {
                var thisInput = $("#" + this.id + " > input");
                if (thisInput.val() <= srcOrder && thisInput.val() > destOrder) {
                    console.log((totRows - (index + 1)) + " " + thisInput.val() + " \n");
                    $(this).html($(this).prev().html());
                    $("#" + this.id + " > input").val((totRows - (index + 1)));
                }
            });
            // replace the destination
            $("#" + el.id).html(e.originalEvent.dataTransfer.getData("text/html"));
            $("#" + el.id + " > input").val(destOrder);
        } else {
            // console.log("moved down ");
            // move intermediate elements up in order
            $("#settingsContent .dragColumn").each(function (index) {
                var thisInput = $("#" + this.id + " > input").val();
                if (thisInput >= srcOrder && thisInput < destOrder) {
                    // move the next item up
                    $(this).html($(this).next().html());
                    $("#" + this.id + " > input").val(thisInput);
                }
            });
            // replace the destination
            $("#" + el.id).html(e.originalEvent.dataTransfer.getData("text/html"));
            $("#" + el.id + " > input").val(destOrder);
        }

    }).on('dragstart', '.dragColumn', function (e) {
        //start drag
        $('body').addClass('is-drag-in-progress');
        source = e.target;
        // console.log(source.id);
        // if (source.tagName === "LABEL" || source.tagName === "IMG" || source.tagName === "INPUT") {
        //     // dragParentID = source.parentElement.parentElement.id;
        //     source = source.parentElement;
        // } else {
        //     // dragParentID = source.parentElement.id;
        // }
        e.originalEvent.dataTransfer.setData("text/html", source.innerHTML);
        e.originalEvent.dataTransfer.effectAllowed = "move";

    }).on('dragover', '.dragColumn', function (e) {
        //drag over
        e.preventDefault();
        e.originalEvent.dataTransfer.dropEffect = "move";

    }).on('dragend', '.dragColumn', function (e) {
        $('body').removeClass('is-drag-in-progress');
        $(".canDropHere").removeClass("canDropHere");

    }).on('dragenter', '.dragColumn', function (e) {
        e.stopPropagation();
        e.preventDefault();
        var el = e.target;
        // if ($("#" + el.id).attr('draggable') === true || $("#" + el.id).parent().attr('draggable') === true ) {
        $("#" + el.id).addClass("canDropHere");
        // }
        return false;

    }).on('dragleave', '.dragColumn', function (e) {
        e.stopPropagation();
        e.preventDefault();
        var el = e.target;
        // if ($(el).attr('draggable') === true || $(el.parent).attr('draggable') === true ) {
        $("#" + el.id).removeClass("canDropHere");
        // }
        return false;

    }).on('click', "#set_results_mode_listing", function () {
        $('#table_mode').hide(200);
        $('#listing_mode').show(200);

    }).on('click', "#set_results_mode_table", function () {
        $('#listing_mode').hide(200);
        $('#table_mode').show(200);

    });

    $('#jqSettingsDialog').on('click', "#sel_all", function () { // delegated events
        $("#settingsContent .showCheckboxes").attr("checked", true);
    }).on('click', "#sel_none", function () {
        $("#settingsContent .showCheckboxes").attr("checked", false);
    });

    $(".openSettings").on('click', function () { // delegated event handler
        var type = $(this).attr('data-type');
        var num = 0;
        var width = 400;
        var content;
        if (type === "search") {
            content = $("#searchCheckboxesDiv").html();
            num = $("#searchCheckboxesDiv .searchCheckboxes").length;
            $("#settingsText1").html("Search Settings");
        } else if (type === "show") {
            content = $("#showOrderCheckboxes").html();
            num = $("#showOrderCheckboxes .showCheckboxes").length;
            $("#settingsText1").html("Report Settings");
        }
        if (num > 14) {
            width = ((Math.floor(num / 14) + 1) * 300);
        }
        $("#settingsType").val(type);
        $("#settingsContent").html(content);
        if (width > $(window).width()) {
            width = $(window).width();
        }
        $('#jqSettingsDialog').width(width).jqmShow(); //.css("margin-left", -(width / 2))
    });

    $(".refreshListing").on('click', function () {
        rt.refresh();
    });

    $(".breadcrumbs").on('click', ".ReturntoSearchResults", function (e) {
        rm.setMode('list');
        rm.setCurrentID(0);
        $("#at-manage").hide(transitionTime);
        $("#at-report").show(transitionTime);
        rt.refresh(); // refresh the grid upon returning from profile view
        if (breadcrumbs) {
            $("#breadcrumbs").html(' ' + BI_CARET_RIGHT + ' Search Results');
        }
    });

    $("#ReturntoSearchResults").on('click', function () {
        rm.setMode('list');
        rm.setCurrentID(0);
        $("#at-manage").hide(transitionTime);
        $("#at-report").show(transitionTime);
        rt.refresh(); // refresh the grid upon returning from profile view
        if (breadcrumbs) {
            $("#breadcrumbs").html(' ' + BI_CARET_RIGHT + ' Search Results');
        }
    });

    $("#btnRestoreDefaults").on('click', function () {
        doSubmitWithValues({btnRestoreDefaults: true}, 0);
    });

    $("#SettingsSave").on('click', function () {
        var obj = {};
        if ($("#settingsType").val() === "search") {
            $("#settingsContent .searchCheckboxes:checked").each(function () {
                obj[this.id] = this.value;
            });
            obj["SettingsSave"] = "SaveSearchSettings";
            doSubmitWithValues(obj, 0);
        } else {
            // report settings
            $("#settingsContent .showCheckboxes:checked").each(function () {
                obj[this.id] = this.value;
            });
            $("#settingsContent .orderInputs").each(function () {
                obj[this.id] = this.value;
            });
            obj["SettingsSave"] = "SaveShowOrderSettings";
            var repl = {};
            if (settings.listSearchResults) {
                obj["results_mode"] = $("input[name='set_results_mode']:checked").val();
                // var listSortField = $("#listSortField").val();
                // var listSortDirection = "";
                // var currSortField = $("#sort1" + rtIdentifier).val().substring(0, listSortField.length);
                // var currSortDirection = currSortField.slice(-5);
                // if ($("#listSortDirection").is(":checked")) {
                //     listSortDirection += " DESC";
                // }
                // if ((currSortField !== listSortField) || currSortDirection !== listSortDirection) {
                //     listSortField += listSortDirection;
                //     repl["sort1" + rtIdentifier] = listSortField;
                // }
            }
            if ($("#settingsContent #subtotalBySortCB").is(":checked")) {
                repl["subtotalBySort"] = "true";
            } else {
                repl["subtotalBySort"] = "false";
            }
            doSubmitWithValues(obj, 0, '', repl);
        }
    });

    $(".doOpenAddMode").on('click', function () {
        addNewRecord();
    });

    function addNewRecord() {
        let el = $("#at-report");
        if (settings.hasHomePage) {
            $("#at-homepage").hide(transitionTime);
            el.show(transitionTime);
            $("#report_page_title").html(pageTitle);
            $("#footer_message_left").html(pageTitle); // show current report title in the footer
        }
        if (allowHideProfile) { // hide the hideProfile button
            rm.hideProfile(false);
            $("#hideProfileView" + rm_identifier).hide();
        }
        $("#ReturntoSearchResults").hide(); // hide the Return button
        hideSearch(false);
        rm.setMode('add');
        rm.setCurrentID(0);
        rm.refresh(function () {
            if (typeof window[rmRefreshCallback] == 'function') {
                window[rmRefreshCallback]();
            }
        });
        $("#profileHeading" + rm_identifier).html('Add ' +  rm.settings.editTitle);
        $("#myEditForm" + rm_identifier + " input[name=mode]").val('add');
        $("#at-tabs").hide(transitionTime);
        el.hide(transitionTime);
        $("#at-manage").show(transitionTime);
    }

    return {
        // expose external functions and properties
        settings: settings,

        addRecord: function () {
            if (rm.settings.mode === 'view' || rm.settings.mode === 'list') { // must not be already in edit or add modes
                addNewRecord();
            }
        },

        returntoListMode: function () {
            rm.setMode('list');
            rm.setCurrentID(0);
            $("#at-manage").hide(transitionTime);
            $("#at-report").show(transitionTime);
            rt.refresh(); // refresh the grid upon returning from profile view
            if (breadcrumbs) {
                $("#breadcrumbs").html(' ' + BI_CARET_RIGHT + ' Search Results');
            }
        }

    }
}

// functions available outside the above module
function doOnChange(val, id) {
    var field = id.substr(0,id.length - 3);
    var in1 = $("#"+field+'_in1');
    if (val === "range") {
        $("."+field+"_btwn").show(200);
        in1.attr('placeholder','From').prop("disabled", false).select();
        $("#"+field+"_so").css('margin-top','11px');
        $("#search_label_"+field).css('margin-top','12px');
    } else if (val === 'Yes' || val === 'No' || val === 'All') {

    } else if ( val === '1' || val === '0') {
        $("."+field+"_btwn").hide(200);
        $("#"+field+"_so").css('margin-top','0');
        $("#search_label_"+field).css('margin-top','0');
        in1.hide(200);
    } else {
        $("."+field+"_btwn").hide(200);
        $("#"+field+"_so").css('margin-top','0');
        $("#search_label_"+field).css('margin-top','0');
        if (val === "") {
            $("#"+field+'_in2').val("");
            in1.prop("disabled", true).val("");
        } else {
            in1.prop("disabled", false);
        }
        in1.attr('placeholder','').show(200).select();
    }
}

function hideSearch(showModify) {
    if (showModify) {
        $("#btnModifySearch").show();
    } else {
        $("#btnModifySearch").hide();
    }
    $("#ar-searchSettings").hide();
    $("#search-fieldset").hide(transitionTime);
    $("#openSearchButton").show(transitionTime);
}

function rtCallback(limit, offset, identifier, countRows, row) {
    if (identifier === rt_identifier) {
        if (countRows === 1 && rt.settings.hasProfileMode) {
            // console.log(row);
            openProfileOrEditMode(keyField, row[0][keyField], '')
            $("#ReturntoSearchResults").hide();
        } else {
            $("#ReturntoSearchResults").show();
            if (allowHideProfile) { // hide the hideProfile button
                $("#hideProfileView" + rm_identifier).show();
            }
            $("#at-manage").hide(transitionTime);
            $("#at-report").show(transitionTime);
            if (typeof window[rtRefreshCallback] == 'function') { // forward to callback function in calling program, if specified 4/14/22
                window[rtRefreshCallback](limit, offset, identifier, countRows, row);
            }
        }
    }
}


function jqAlert(stuff) {
    $("#alertText").html(stuff);
    $('#jqAlertDialog').jqmShow();// TODO FIX THIS
}

// alert dialog
$('#jqAlertDialog').jqm({
    modal:  true,
    toTop:  true,
    onShow: function (hash) {
        hash.o.prependTo('body');
        hash.w.css('opacity', 1).fadeIn();
    },
    onHide: function (hash) {
        hash.w.fadeOut('2000', function () {
            hash.o.remove();
        });
    }
});

// confirm delete dialog
$('#jqConfDialog').jqm({
    trigger: '.jqConfirm',
    modal:   true,
    overlay: 88,
    toTop:   true
});

function jqConfIcon(type) {
    // hide all three elements
    $("#confirmImgExclamation").hide();
    $("#confirmImgWarning").hide();
    $("#confirmImgErase").hide();
    // $(".jqPopupFooter").css('text-align','right'); // restore default alignment to the buttons
    switch (type) {
        case "Exclamation":
            $("#confirmImgExclamation").show();
            break;
        case "Warning":
            $("#confirmImgWarning").show();
            break;
        case "Erase":
            $("#confirmImgErase").show();
            break;
    }
}