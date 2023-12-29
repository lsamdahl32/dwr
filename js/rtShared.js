/**
 * rtShared.js
 * common js functions used by the ReportTable class
 * by Lee Samdahl
 * 3/26/2020
 */

var rtShared = function (identifier, settings) {

    // var settings = {
    //     sort1: "",
    //     sort2: "",
    //     sort3: "",
    //     limit: 10,
    //     offset: 0,
    //     page: 1,
    //     totPages: 0,
    //     filter: "",
    //     editCol: "",
    //     showAsList: false,
    //     noColResize: false,
    //     ajaxHandler: "",
    //     refreshCallback: "",
    //     flushColWidths: false,
    //     colClass:       ""
    //     linkField:       ""
    //     linkID:          ""
    // };

    $( document ).ready(function () {

        // Button bar for pagination of table
        $(".buttonBar" + identifier).on('click', function () {
            var direction = $(this).attr("id").substring(0, 4);
            if (direction === 'prev') {
                settings.page--;
                if (settings.page < 1) {
                    settings.page = 1;
                }
            } else if (direction === 'next') {
                settings.page++;
                if (settings.page > settings.totPages) {
                    settings.page = settings.totPages;
                }
            } else if (direction === 'firs') {
                settings.page = 1;
            } else if (direction === 'last') {
                settings.page = settings.totPages;
            }
            settings.offset = (settings.page - 1) * settings.limit;
            $("#offset" + identifier).val(settings.offset);
            refreshTable();
            $("#page_count_top" + identifier).html(' Page ' + settings.page + ' of ' + settings.totPages + ' ');
            $("#page_count_bottom" + identifier).html(' Page ' + settings.page + ' of ' + settings.totPages + ' ');
            setButtonState();
            return false;
        });

        // column resizing
        if (!settings.noColResize && !settings.showAsList) {
            $("#report_table_" + identifier).colResizable({
                resizeMode:     'overflow',
                // postbackSafe:   true,
                // useLocalStorage:true,
                hoverCursor:    "ew-resize",
                dragCursor:     "ew-resize",
                flush:          settings.flushColWidths,
                liveDrag:       true,
                onResize:       function (e, i) {
                    $('#double-scroll' + identifier).trigger('resize.doubleScroll'); // need to fix the top scrollbar after a resize
                    // save the new column width
                    var wid = [];
                    $("#report_table_" + identifier + " th").each(function(c){
                        wid.push($(this).css("width"));
                    });
                    var data = {
                        rt_process: "saveColWidths",
                        identifier: identifier,
                        colWidths:  wid,
                        index:      i
                    };
                    if ($("#myForm").length === 1) {
                        data = getSearchCriteria(data);
                    }
                    $.post(settings.ajaxHandler, data, function (results) {
                        checkLoginStatus(data);
                    });
                }
            });
        }

        // Sort rows by clicking column headings
        $("#report_table_" + identifier + " .ColSort").on('click', function () {
            var fld = $(this).attr("data-fieldname");
            if (settings.sort1.indexOf(fld) !== -1) {
                // reverse the sort order
                if (settings.sort1.indexOf(' ASC') > 0) {
                    settings.sort1 = fld + " DESC";
                } else if (settings.sort1.indexOf(' DESC') > 0) {
                    settings.sort1 = fld + " ASC";
                } else {
                    // assume ASC
                    settings.sort1 = fld + " DESC";
                }
            } else {
                settings.sort3 = settings.sort2;
                settings.sort2 = settings.sort1;
                settings.sort1 = fld + " ASC";
            }
            sortTheTable(settings.sort1, settings.sort2, settings.sort3);

        }).mouseenter(function () {
            var ndx = $(this).index();
            $("#report_table_" + identifier + " .rt_col"+ndx).addClass(settings.colClass);
        }).mouseleave(function () {
            var ndx = $(this).index();
            $("#report_table_" + identifier + " .rt_col"+ndx).removeClass(settings.colClass);
        });

        // handle delegated events for the table body
        $("#report_table_" + identifier).on('click', '.doLink', function () { // delegated event handler for type = link columns
            var fld = $(this).attr("data-fieldname");
            var dat = $(this).attr("data-fielddata");
            var typ = $(this).attr("data-type");
            if ($('#myForm').find('#linkField').length) {
                $('#linkField').val(fld);
                $('#linkID').val(dat);
                $('#linkProcess').val(typ);
                $('#myForm').submit();
            } else {
                // no form - retain old GET method for compatibility
                document.location = "?"+fld+"="+dat+"&process="+typ+"&source=self";
            }

        }).on('click', '#cbheading', function () { // delegated event handler for checkboxes
            if($(this).is(':checked')) {
                $(".selectCheckboxes").prop("checked", true);
            } else {
                $(".selectCheckboxes").prop("checked", false);
            }

        }).on('click', '.expandr', function () { // delegated event handler for expander rows
            var cls = $(this).attr('id');
            var clsObj = $("." + cls);
            clsObj.toggle();
            if (clsObj.is(":visible")) {
                $(this).html('<i class="bi-caret-up"></i>');
            } else {
                $(this).html('<i class="bi-caret-right"></i>');
            }
        });

        // handle changing the number of rows per page
        $("#limitSelect" + identifier).on('change', function () {
            settings.limit = $(this).val();
            settings.page = 1;
            settings.offset = 0;
            settings.filter = $("#filterSelect" + identifier).val();
            refreshTable();
            setPaginationData();
        });

        // handle changing the filter select box
        $("#filterSelect" + identifier).on('change', function () {
            settings.filter = $(this).val();
            settings.page = 1;
            settings.offset = 0;
            refreshTable();
            setPaginationData();
        });

        // handle editable columns
        $("#report_table_" + identifier + " .editColumn").on('click', function (e) {
            e.stopPropagation();
            $(".editColumn").html('<i class="bi-pencil" style="font-size: 1em;"></i>').attr("title","Edit this column");
            if (settings.editCol !== this.id) {
                settings.editCol = this.id;
                $("#"+this.id).html('<i class="bi-slash-circle" style="font-size: 1em;"></i>').attr("title","Cancel editing this column");
            } else {
                settings.editCol = "";
            }
            refreshTable();
        });

        // handle list format sorting
        $("#openCloseSortSelect" + identifier).on('click', function () {
            $(this).blur();
            if ($("#rtSortSelect" + identifier).is(':visible')) {
                $("#rtSortSelect" + identifier).hide(200);
            } else {
                $("#rtSortSelect" + identifier).show(200);
                // trap clicks outside of sort box
                $(document).on('click.closeSort' + identifier, function (e) {
                    if (($(e.target).closest("#rtSortSelect" + identifier).length === 0) && ($(e.target).closest("#openCloseSortSelect" + identifier).length === 0)) {
                        $("#rtSortSelect" + identifier).hide(200);
                        $(document).off('click.closeSort' + identifier);
                    }
                });
            }
        });

        setButtonState();
    });


    function refreshTable() {
        if (!settings.disabled) {
            processing(true);
            var data = {
                rt_process: "load",
                identifier: identifier,
                sort1:      settings.sort1,
                sort2:      settings.sort2,
                sort3:      settings.sort3,
                limit:      settings.limit,
                offset:     settings.offset,
                filter:     settings.filter,
                editCol:    settings.editCol,
                showAsList: settings.showAsList,
                bindtypes:  settings.bindtypes,
                bindValues: settings.bindValues,
                where:      settings.where
            };
            if ($("#myForm").length === 1) {
                data = getSearchCriteria(data);
                // console.log(data);
            }
            $.post(settings.ajaxHandler, data, function (data) {
                checkLoginStatus(data);
                // console.log(data);
                var obj = JSON.parse(data);
                if (typeof obj === 'object') {
                    settings.countRows = Number(obj['countRows']);
                    let tr = $("#report_table_" + identifier + " .report_hdr_row").closest('tr');
                    if (settings.hasProfileMode && settings.countRows === 1) { // added 5/25/21 to support hasProfileMode
                        // clear old data
                        tr.siblings().remove();
                        if (typeof window[settings.refreshCallback] == 'function') { // callback function after refresh
                            window[settings.refreshCallback](settings.limit, settings.offset, identifier, settings.countRows, obj['rows']);
                        }
                    } else {
                        // clear old data
                        tr.siblings().remove();
                        // append new
                        tr.after(obj['rows']);
                        processing(false);
                        if (typeof window[settings.refreshCallback] == 'function') { // callback function after refresh
                            window[settings.refreshCallback](settings.limit, settings.offset, identifier, settings.countRows, {});
                        }
                    }
                    $('#double-scroll' + identifier).trigger('resize.doubleScroll'); // need to fix the top scrollbar after a refresh
                } else {
                    processing(false);
                    alert(data);
                }
            });
        }
    }

    function setPaginationData() {
        var data = {
            rt_process: "totPages",
            identifier: identifier,
            limit:      settings.limit,
            offset:     settings.offset,
            filter:     settings.filter,
            bindtypes:  settings.bindtypes,
            bindValues: settings.bindValues,
            where:      settings.where
        };
        if ($("#myForm").length === 1) {
            data = getSearchCriteria(data);
        }
        $.post(settings.ajaxHandler, data , function (data) {
            checkLoginStatus(data);
            var obj = JSON.parse(data);
            if (typeof obj === 'object') {
                settings.totPages = obj['totPages'];
                $("#limit" + identifier).val(settings.limit);
                $("#offset" + identifier).val(settings.offset);
                $("#page_count_top" + identifier).html(' Page ' + settings.page + ' of ' + settings.totPages + ' ');
                $("#page_count_bottom" + identifier).html(' Page ' + settings.page + ' of ' + settings.totPages + ' ');
                $("#countRows" + identifier).html(obj['countRows']);
                setButtonState();
            } else {
                alert(data);
            }
        });
    }

    function getSearchCriteria(nvp) {
        var getAll = false;
        $("#myForm input, #myForm select, #myForm textarea").each(function () {
            if (this.name === 'SubmitRequest' && this.value === 'altSearchCriteria') {
                getAll = true;
            }
            if (!getAll) {
                if ((this.name === 'SubmitRequest' && this.value === 'authlog') || this.name.substring(this.name.length - 4) === '_in1' || this.name.substring(this.name.length - 4) === '_in2' || this.name.substring(this.name.length - 3) === '_so' || this.name === 'subtotalBySort') {
                    nvp[this.name] = this.value;
                } else if (this.name.substring(this.name.length - 4) === '_sho') {
                    if ($( this ).is(":checked")) { // checkboxes only POST if they are checked
                        nvp[this.name] = 1;
                    }
                }
            } else {
                nvp[this.name] = this.value;
            }
            if (settings.linkField !== '') {
                nvp['linkField'] = settings.linkField;
                nvp['linkID'] = settings.linkID;
            }
        });
        // console.log(nvp);
        return nvp;
    }

    function setButtonState() {
        // set initial state of buttons
        if (settings.totPages === 0) {
            $(".leftBtns" + identifier).attr("disabled", true);
            $(".rightBtns" + identifier).attr("disabled", true);
        } else {
            if (settings.page === 1) {
                $(".leftBtns" + identifier).attr("disabled", true);
            } else {
                $(".leftBtns" + identifier).attr("disabled", false);
            }
            if (settings.page == settings.totPages) { // type coersion intended
                $(".rightBtns" + identifier).attr("disabled", true);
            } else {
                $(".rightBtns" + identifier).attr("disabled", false);
            }
        }
    }

    $(document).ready(function() {
        $('#double-scroll' + identifier).doubleScroll({
            resetOnWindowResize:    true,
            onlyIfScroll:           true,
            timeToWaitForResize:    60,
            contentElement:         $("#report_table_" + identifier)
        });

        $("#sortBtn" + identifier).on('click', function () {
            $("#rtSortSelect" + identifier).hide(200);
            $(document).off('click.closeSort' + identifier);
            let sort1 = "";
            let sort2 = "";
            let sort3 = "";
            let sortBy1 = $("#sortBy" + identifier + "1").val();
            let sortDir1 = $("#sortDir" + identifier + "1").val();
            let sortBy2 = $("#sortBy" + identifier + "2").val();
            let sortDir2 = $("#sortDir" + identifier + "2").val();
            let sortBy3 = $("#sortBy" + identifier + "3").val();
            let sortDir3 = $("#sortDir" + identifier + "3").val();
            sort1 = sortBy1 + ' ' + sortDir1;
            if (sortBy2 !== '') {
                sort2 =sortBy2 + ' ' + sortDir2;
            }
            if (sortBy3 !== '') {
                sort3 =sortBy3 + ' ' + sortDir3;
            }
            sortTheTable(sort1, sort2, sort3);
        });

        $("#rtRestoreDefaults").on('click', function () {
            if (settings.linkField !== '') {
                doSubmitWithValues({btnRestoreDefaults: 1}, settings.linkID, settings.linkField);
            } else {
                doSubmitWithValues({btnRestoreDefaults: 1});
            }
        });

    });

    function sortTheTable(sort1, sort2, sort3) {
        settings.sort1 = sort1;
        settings.sort2 = sort2;
        settings.sort3 = sort3;
        // set the hidden fields (if any)
        $("#sort1" + identifier).val(settings.sort1);
        $("#sort2" + identifier).val(settings.sort2);
        $("#sort3" + identifier).val(settings.sort3);
        refreshTable();
        // show sort order
        $("#report_table_" + identifier + " .sortArrows").remove();
        $("#report_table_" + identifier + " .ColSort").each(function () {
            var fld = $(this).attr("data-fieldname");
            if (settings.sort1.indexOf(fld) !== -1) {
                $("#sortBy" + identifier + "1").val(fld);
                if (settings.sort1.indexOf('DESC') > 0) {
                    $("#sortDir" + identifier + "1").val("DESC");
                    $(this).append('<span class="sortArrows" style="margin-left: 8px;" title="Sorted descending"><i class="bi-caret-down" style="font-size: 1em;"></i></span>');
                } else {
                    $("#sortDir" + identifier + "1").val("ASC");
                    $(this).append('<span class="sortArrows" style="margin-left: 8px;" title="Sorted ascending"><i class="bi-caret-up" style="font-size: 1em;"></i></span>');
                }
            }
        });
        var data = {
            rt_process: "getSort",
            identifier: identifier,
            sort1:      settings.sort1,
            sort2:      settings.sort2,
            sort3:      settings.sort3
        }
        if ($("#myForm").length === 1) {
            data = getSearchCriteria(data);
        }
        $.post(settings.ajaxHandler, data, function (data) {
            checkLoginStatus(data);
            var obj = JSON.parse(data);
            if (typeof obj === 'object') {
                $("#sortFieldDisplay" + identifier).html("Sorted by: "+ obj['sort']);
            }
        });
        console.log("Sorted and Refreshed");
    }

    // this function will activate an overlay for the table
    function processing(state) {
        if (state) {
            var tableObj = $("#report_table_" + identifier);
            var wid = tableObj.parent().width();
            var hite = tableObj.parent().height();
            $("#report_table_overlay_" + identifier).width(wid).height(hite);
        } else {
            $("#report_table_overlay_" + identifier).width(0).height(0);
        }
    }

    return {
        // public access to the settings of this instance
        identifier: identifier,
        settings: settings,

        // Public API functions
        // to refresh the grid
        refresh: function() {
            refreshTable();
            setPaginationData();
            console.log("Refreshed");
        },
        // to sort the grid
        setOrderBy: function (sort1, sort2, sort3) {
            sortTheTable(sort1, sort2, sort3);
        },
        disableRefresh: function (state) {
            settings.disabled = state;
        },
        setBindtypes: function (bindtypes) {
            settings['bindtypes'] = bindtypes;
        },
        setBindValues: function (bindValues) {
            settings['bindValues'] = bindValues;
        },
        setWhere: function (where) {
            settings['where'] = where;
        },
        setLinkID: function (id) {
            settings['linkID'] = id;
        },
        clearFilter: function () {
            settings.filter = 'All';
            $("#filterSelect" + identifier + " option:first").attr('selected', true);
        },
        setResizable: function (callback){
            let el = $("#report_table_" + identifier);
            el.colResizable({
                disable:    true,
            });
            el.colResizable({
                resizeMode: 'overflow',
                useLocalStorage: true,
                hoverCursor: "ew-resize",
                dragCursor:  "ew-resize",
                liveDrag:    true,
                onResize:    function (e, i) {
                    $(window).trigger('resize'); // call resize in case scroll bars need to be added
                    if (typeof window[callback] == 'function') { // callback function after column resize on sub table
                        window[callback](e, i);
                    }
                },
            });
        }

    };

};
