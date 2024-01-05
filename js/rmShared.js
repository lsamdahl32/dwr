/**
 * rmShared.js
 * common js functions used by the RecordManage class
 * by Lee Samdahl
 * 4/22/2021
 */

let editModeScripts = function (identifier, settings) {

    let timeoutHandle, timeoutHandle2, timeoutHandle3;
    let tinySettings = {};
    let dirty = false;

    function changeMode(mode) {
        if (typeof window[settings.modeCallback] == 'function') { // callback function after mode change
            window[settings.modeCallback](settings.mode, settings.curId);
        }
    }

    $("#editRecord" + identifier).on('click', function () {
        // reload the record
        settings.mode = 'edit';
        settings.editmode = true;
        if (settings.noProfileMode && settings.allowDelete) {
            $("#btnDeleteEdit" + identifier).show();
        }
        if ($("#duplicatebutton" + identifier).length > 0) {
            $("#duplicatebutton" + identifier).show();
        }
        // reloadInputs('edit');
        getEditPage('edit', function () { // callback ensures that the edit page is not shown until the data is loaded
            $(".readMode" + identifier).hide(400);
            if (settings.editModeNoColumns) {
                $("#profile-flex-cols" + identifier).css('display','block');
            }
            if ($("#stateZone").length) {
                countryChange($("#countrySelect").val());
            }
            $("#ReturntoSearchResults").hide();
            $(".editMode" + identifier).show(400);
            changeMode(settings.mode);
            resetTimeout();
        });
        $("#profileHeading" + identifier).html('Edit ' + settings.editTitle);
    })

    $("#btnClose" + identifier).on('click', function () {
        let mode = settings.mode;
        if ((mode === 'edit' || mode === 'add') && !settings.noProfileMode) {
            closeEditMode(400);
            if (mode === 'add') {
                settings.mode = 'list';
                changeMode(settings.mode);
            }
        } else { // must be a subtable
            closeEditMode(400);
        }
    })

    function doCancel() {
        // closes the edit page, invokes the unload event
        settings.editmode = false; // prevent unload event
        if ($("#new_password_span").length) {
            closePasswordForm(); // close password entry panel, if exists
        }
        $("#btnClose" + identifier).click();
    }

    function closeEditMode(delay) {
        $(".form_error").html(''); // clear old error messages
        $('#timeoutWarning' + identifier).hide();
        clearTimeout(timeoutHandle);
        clearTimeout(timeoutHandle2);
        $(window).off("unload.recordlocking");
        $(window).off("beforeunload");
        dirty = false;
        unLockRecord();
        if (!settings.noProfileMode) {
            $(".editMode" + identifier).hide(delay);
            if (settings.editModeNoColumns) {
                $("#profile-flex-cols" + identifier).css('display', 'flex');
            }
            $(".readMode" + identifier).show(delay);
            if ($("#new_password_span").length) {
                closePasswordForm(); // close password entry panel, if exists
            }
            $("#profileHeading" + identifier).html(settings.subTitle);
            settings.mode = 'view';
            $("#ReturntoSearchResults").show();
            changeMode(settings.mode);
        } else {
            settings.mode = 'list';
            changeMode(settings.mode);
            $("#" + identifier + "-edit").hide(delay);
            $("#" + identifier + "-report").show(delay);
        }
    }

    $("#hideProfileView" + identifier).on('click', function () {
        if ($("#hideProfile" + identifier).is(':visible')) {
            hideProfile(true);
        } else {
            hideProfile(false);
        }
    });

    $(".refreshProfile").on('click', function () {
        // if (settings.mode === 'view') {
            $.post(settings.self, {
                rm_process: 'getProfile',
                identifier: identifier,
                id:         settings.curId
            }, function (data) {
                checkLoginStatus(data);
                let obj = JSON.parse(data);
                    // console.log(obj);
                    reloadProfile(obj, window[rmRefreshCallback]);
            });
        // }
    });

    function hideProfile(state) {
        if (state) {
            $("#hideProfile" + identifier).hide(400);
            $("#hideProfileView" + identifier).html('<i class="bi-arrows-expand"></i>&nbsp;Show').attr('title','Show Profile Section');
        } else {
            $("#hideProfile" + identifier).show(400);
            $("#hideProfileView" + identifier).html('<i class="bi-arrows-collapse"></i>&nbsp;Hide').attr('title','Hide Profile Section');
        }
    }

    $("#saveAddNew" + identifier).on('click', function () {
        $("<input type='hidden' value='1' />")
                .attr("name", "saveAndAddNew")
                .appendTo("#myEditForm" + identifier);
        $("#btnSubmit" + identifier).click();
    });

    function unLockRecord() {
        $.post(settings.ajaxPath + 'admin_ajax.php', {
            process:         'unlockRecord',
            db:              settings.DB,
            table:           settings.table,
            keyname:         settings.keyField,
            id:              settings.curId,
            requireSameUser: true
        }, function (data) {
            // do nothing
        });
    }

    $("#btnDeleteView" + identifier).on('click', function () {
        var id = $(this).attr('data-id');
        var name = 'Record';
        $("#confirmText1").html('Delete ' + name);
        jqConfIcon('Erase');
        $("#delSerialsText").html('Are you sure you want to delete this ' + name + '?');
        $("#doConfirmAction").val("Delete").on("click.delete", function () {
            $("#doConfirmAction").off("click.delete");
            doDeleteRecord(id);
        });
        $('#jqConfDialog').jqmShow();
    });

    $("#btnDeleteEdit" + identifier).on('click', function () {
        var id = $(this).attr('data-id');
        var name = settings.editTitle;
        $("#confirmText1").html('Delete ' + name);
        jqConfIcon('Erase');
        $("#delSerialsText").html('Are you sure you want to delete this ' + name + '?');
        $("#doConfirmAction").val("Delete").on("click.delete", function () {
            $("#doConfirmAction").off("click.delete");
            doDeleteRecord(id);
        });
        $('#jqConfDialog').jqmShow();
    });

    function doDeleteRecord(id) {
        let data = "btnDelete" + identifier + "=" + settings.curId + "&rm_process=deleteRecord&identifier=" + identifier + "&linkID=" + settings.curId;
        // console.log(data);
        $.post(settings.self, data, function (result) {
            checkLoginStatus(result);
            // console.log(result);
            var obj = JSON.parse(result);
            if (typeof obj === 'object') {
                // console.log(obj);
                if (obj['result'] === 'Success') {
                    dirty = false;
                    jqAlert(obj.msg);
                    settings.mode = 'list'; // always return to list mode after successful delete
                    changeMode(settings.mode);
                } else if (result.length > 0) {
                    setSaveMessage(obj.msg);
                }
            }
        });
    }

    $("#resetTimeout" + identifier).on('click', function () {
        $('#timeoutWarning' + identifier).hide();
        resetTimeout();
    });

    function resetTimeout() {
        $(window).on('beforeunload', function () {
            return 'Changes you made may not be saved.';
        });
        if (settings.mode === 'edit' && settings.useLocking) { // record locking only used during edit mode, but the function needs to be present all the time because of a call in tinymce.init (eduTemplatesManage.php and others)
            // reset the timeout whenever any change is made to any control in the form
            clearTimeout(timeoutHandle);
            timeoutHandle = setTimeout(function () {
                dirty = false;
                doCancel();
            }, settings.timeout * 1000 ); // x mins times milliseconds
            // set a timer to warn the user after 4.5 minutes that the page will close in 30 seconds
            $('#timeoutWarning' + identifier).hide();
            clearTimeout(timeoutHandle2);
            timeoutHandle2 = setTimeout(function () {
                $("#timeoutWarning" + identifier).show(400);
            }, settings.warningTimeout * 1000 ); // x - .5 mins times milliseconds
            // send to server
            $.post(settings.ajaxPath + 'admin_ajax.php', {
                process: 'resetRecordLockTime',
                db:      settings.DB,
                table:   settings.table,
                keyname: settings.keyField,
                id:      settings.curId
            }, function (data) {
                checkLoginStatus(data);
                if (data == '1') { // type coercion intended
                    // success
                    // if page is closed, remove any record lock
                    $(window).on("unload.recordlocking", function () {
                        if (settings.editmode) {
                            $.post(settings.ajaxPath + 'admin_ajax.php', {
                                process:         'unlockRecord',
                                db:              settings.DB,
                                table:           settings.table,
                                keyname:         settings.keyField,
                                id:              settings.curId,
                                requireSameUser: true
                            }, function (data) {
                                // do nothing
                            });
                        }
                    });
                } else {
                    // check for editmode again???
                    if (settings.editmode) {
                        $("#alertOK").on('click', function () {
                            $("#alertOK").off();
                            doCancel();
                        });
                        jqAlert("Your record lock has been lost. Edit mode is cancelled. Please contact IT if this happens regularly.");
                    }
                }
            });
        }
    }

    $("form#myEditForm" + identifier).submit(function (e) {
        $(".form_error").html('');
        e.preventDefault();
        if (dirty) {
            let addmode = false;
            if (settings.mode === 'add') addmode = true;
            $("#" + settings.keyField + "_" + identifier + "e").val(settings.curId);
            $("#linkID" + identifier).val(settings.curId);
            let data = $("form#myEditForm" + identifier).serialize() + "&btnSubmit" + identifier + "=true" + "&rm_process=saveRecord&identifier=" + identifier + "&mode" + identifier + "=" + settings.mode;
            // console.log(data);
            $.post(settings.self, data, function (result) {
                checkLoginStatus(result);
                // console.log(result);
                var obj = JSON.parse(result);
                // console.log(obj);
                if (typeof obj === 'object') {
                    if (obj['result'] === 'Success') {
                        if (addmode) { // set the new current id
                            settings.curId = obj[settings.keyField];
                            $("#" + settings.keyField + "_" + identifier + "e").val(settings.curId);
                        }
                        clearTimeout(timeoutHandle);
                        clearTimeout(timeoutHandle2);
                        $(window).off("unload.recordlocking", function () {
                        });
                        dirty = false;
                        unLockRecord();
                        setSaveMessage(obj.msg);
                        // callback function if exists
                        if (typeof window[settings.updateCallback] == 'function') {
                            window[settings.updateCallback](obj);
                        }
                        // reload the record
                        reloadProfile(obj, window[rmRefreshCallback]);
                        if ($(document.activeElement).prop('name') === "saveAddNew") { // save and add new
                            // load a new add form
                            let data = {};
                            data["editLinkID" + identifier] = settings.curId;
                            data["saveAndAddNew"] = 1;
                            data["btnAdd" + identifier] = 1;
                            addmode = true;
                            settings.curId = 0;
                            settings.mode = 'add';
                            changeMode(settings.mode);
                            // reloadInputs('add');
                            getEditPage('add');
                        } else {
                            if (!settings.saveReturnsToEdit || addmode) {
                                settings.editmode = false;
                                // return to profile mode unless noProfileMode is set
                                if (!settings.noProfileMode) {
                                    closeEditMode(400);
                                } else { // must be a subtable
                                    closeEditMode(400);
                                    settings.mode = 'list';
                                    changeMode(settings.mode);
                                    $("#" + identifier + "-edit").hide();
                                    $("#" + identifier + "-report").show();
                                }
                            } else { // save returns to edit mode
                                if (addmode) {
                                    // change to edit mode
                                    $("#profileHeading" + identifier).html('Edit ' + settings.editTitle);
                                    addmode = false;
                                    settings.mode = 'edit';
                                    changeMode(settings.mode);
                                    resetTimeout();
                                }
                            }
                        }
                    } else { // save failed show messages
                        var obj = JSON.parse(result);
                        // console.log(obj);
                        if (typeof obj === 'object') {
                            setSaveMessage(obj.msg);
                            Object.keys(obj).forEach(function (key) {
                                // show any error messages
                                $("#" + key + "_" + identifier + "e_err").html(obj[key]);
                            });
                        }
                    }
                }
            });
        } else {
            setSaveMessage('No changes were made.');
            if (!settings.saveReturnsToEdit) {
                settings.editmode = false;
                doCancel();
            }
        }
    });

    $("#duplicatebutton" + identifier).on('click', function () {
        dirty = true;
        settings.mode = 'add';
        addmode = true;
        let data = $("form#myEditForm" + identifier).serialize() + "&duplicatebutton=Duplicate" + "&rm_process=saveRecord&identifier=" + identifier + "&mode" + identifier + "=" + settings.mode;
        //console.log(data);
        $.post(settings.self, data, function (result) {
            checkLoginStatus(result);
            // console.log(result);
            var obj = JSON.parse(result);
            // console.log(obj);
            if (typeof obj === 'object') {
                if (addmode) { // set the new current id
                    settings.curId = obj[settings.keyField];
                    $("#" + settings.keyField + "_" + identifier + "e").val(settings.curId);
                }
                clearTimeout(timeoutHandle);
                clearTimeout(timeoutHandle2);
                $(window).off("unload.recordlocking", function () {
                });
                dirty = false;
                unLockRecord();
                setSaveMessage(obj.msg);
                // callback function if exists
                if (typeof window[settings.updateCallback] == 'function') {
                    window[settings.updateCallback](obj);
                }
                // reload the record
                reloadProfile(obj, window[rmRefreshCallback]);
                settings.editmode = false;
                // return to profile mode unless noProfileMode is set
                if (!settings.noProfileMode) {
                    closeEditMode(400);
                } else { // must be a subtable
                    closeEditMode(400);
                    settings.mode = 'list';
                    changeMode(settings.mode);
                    $("#" + identifier + "-edit").hide();
                    $("#" + identifier + "-report").show();
                }
            }
        });
    });

    function setSaveMessage(msg) {
        $("#saveMessage" + identifier).html(msg).show(400).get(0).scrollIntoView();
        clearTimeout(timeoutHandle3);
        timeoutHandle3 = setTimeout(function () {
            $("#saveMessage" + identifier).hide(400);
        }, 10000 );
    }

    function reloadProfile(obj, callback) {
        if (typeof window[settings.customProfileCallback] == 'function') {
            window[settings.customProfileCallback](obj);
        } else {
            Object.keys(obj).forEach(function (key) {
                $("#profile-flex-cols" + identifier + " #" + key + "_rmc").html(obj[key]); // set the profile fields
            });
        }
        if (typeof callback == 'function') {
            callback(obj);
        }
    }

    // function reloadInputs(mode) {
    //     $(".editModeInputs" + identifier).each(function () {
    //         let el = $(this);
    //         $.post(settings.self , {
    //             rm_process:    'getEditModeInputs',
    //             identifier:     identifier,
    //             id:             settings.curId,
    //             linkField:      settings.parKeyField,
    //             linkID:         settings.parId,
    //             mode:           mode,
    //             field:          el.attr('data-field')
    //         }, function (data) {
    //             // console.log(data);
    //             el.html(data);
    //             if (data.indexOf('mceEditor') > 0) {
    //                 let tarea = el.children('textarea');
    //                 let tinyobj = getTinyObj(tarea[0]);
    //                 tinymce.init(tinyobj);
    //             }
    //         });
    //     });
    // }

    function getEditPage(mode, callback) {
        $.post(settings.self , {
            rm_process:    'getEditPage',
            identifier:     identifier,
            id:             settings.curId,
            linkField:      settings.parKeyField,
            linkID:         settings.parId,
            mode:           mode
        }, function (data) {
            checkLoginStatus(data);
            var obj = JSON.parse(data);
            // console.log(obj);
            Object.keys(obj).forEach(function (key) {
                $(".editModeInputs" + identifier).each(function () {
                    if ($(this).attr('data-field') === key) {
                        $(this).html(obj[key]);
                        if (obj[key].toString().indexOf('mceEditor') > 0) {
                            let tarea = $(this).children('textarea');
                            let tinyobj = getTinyObj(tarea[0]);
                            tinymce.init(tinyobj);
                        }
                    }
                });
            });
            if (typeof callback !== 'undefined') {
                callback(obj);
            }
        });
    }

    function getTinyObj(target) {
        var skin = (tinySettings['allowDark'])? (window.matchMedia("(prefers-color-scheme: dark)").matches ? "oxide-dark" : ""):"";
        var content_css = '';
        if (tinySettings['allowDark']) {
            content_css = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark":'';
        }
        if (tinySettings['content_css'] !== '') {
            if (content_css !== '') {
                content_css = '(' + content_css + ', ' + tinySettings['content_css'] + ')';
            } else {
                content_css = tinySettings['content_css'];
            }
        }
        let tinyobj = {
            // selector:         "textarea.mceEditor",
            target:           target,
            width:            tinySettings['width'],
            height:           tinySettings['height'],
            plugins:          tinySettings['plugins'],
            menu:             tinySettings['menu'],
            menubar:          tinySettings['menubar'],
            toolbar:          tinySettings['toolbar'],
            toolbar_drawer:   tinySettings['toolbar_drawer'],
            templates:        tinySettings['templates'],
            fontsize_formats: "8pt 10pt 11pt 12pt 14pt 18pt 24pt 36pt",
            setup:            function (ed) {
                ed.on("change", function (e) {
                    ed.save();
                    dirty = true;
                }).on("keyup", function (e) {
                    resetTimeout();
                });
            },
            branding:         false,
            skin:             skin,
            readonly:         tinySettings['readonly']
        }
        if (content_css !== '') {
            tinyobj['content_css'] = content_css;
        }
        return Object.assign(tinyobj, tinySettings['extra']);
    }

    function formatDate(date) {
        var d = new Date(date),
                month = '' + (d.getMonth() + 1),
                day = '' + d.getDate(),
                year = d.getFullYear();

        if (month.length < 2)
            month = '0' + month;
        if (day.length < 2)
            day = '0' + day;

        return [year, month, day].join('-');
    }

    // edit form delegated event handlers
    $("#myEditForm" + identifier).on("keyup", "textarea", function () { // for long editing jobs, need to reset timeout
        dirty = true;
        if (settings.useLocking) {  // record locking only used during edit mode
            resetTimeout();
        }
    }).on("change", "input, select, textarea", function () {
        dirty = true;
        if (settings.mode === 'edit' && settings.useLocking) {  // record locking only used during edit mode
            resetTimeout();
        }
    }).on('change', "#countrySelect", function () {
        countryChange(this.value);
    }).on('change', "#stateZone", function () {
        $("#state").val(this.value);

        /**
         * handle remove event for Multi-Select List Boxes
         */
    }).on('click', '.multiSelectRemove', function () { // need to use delegated event handler since items can be dynamically added
        var table = $(this).attr('data-identifier');
        var id = $(this).attr('data-id');
        $("#multiSelect_Item_" + table + "_" + id).remove();
        var listDiv = $("#multiSelect_" + table + "_" + identifier + "e");
        if (listDiv.html().trim().length === 0) {
            listDiv.html("No items found.");
        }
        dirty = true;

        /**
         * Handle add event for Multi-Select List boxes
         */
    }).on("change", ".multiSelect_Add", function(e) {
        var data_identifier = $(this).attr('data-identifier');
        var msStruct = {
            db:             $(this).attr('data-db'),
            sourceTable:    $(this).attr('data-sourceTable'),
            sourceField:    $(this).attr('data-sourceField'),
            displayField:   $(this).attr('data-displayField'),
            field:          $(this).attr('data-field')
        };
        var id = $(this).find(":selected").val();
        if (id > 0) {
            $.post(settings.ajaxPath + "admin_ajax.php?process=lookupRecord", {
                table:    msStruct.sourceTable,
                db:       msStruct.db,
                keyField: msStruct.sourceField,
                key:      id
            }, function (data) {
                checkLoginStatus(data);
                let obj = JSON.parse(data);
                if (obj['response'] === 'Success') {
                    let row = obj['row'];
                    if ($("#multiSelect_Item_" + data_identifier + "_" + id).length === 0) {
                        var listDiv = $("#multiSelect_" + data_identifier + "_" + identifier + "e");
                        if (listDiv.html() === "No items found.") {
                            listDiv.html("");
                        }
                        listDiv.append(
                                "<div class='multiSelect_items' id='multiSelect_Item_" + data_identifier + "_" + id + "' " +
                                "   data-db='"+msStruct.db+"' data-identifier='"+data_identifier+"' data-sourceTable='"+msStruct.sourceTable+"' data-sourceField='"+msStruct.sourceField+"' data-displayField='"+msStruct.displayField+"' data-field='"+msStruct.field+"' >" +
                                "<button class='buttonBar multiSelectRemove' type='button' data-identifier='" + data_identifier + "' data-id='" + id + "' style='width: 16px; height: 16px;' title='Remove this item from this record.'>" +
                                '<i class="bi-trash" style="font-size: 14px;"></i></button>&nbsp;' + row[msStruct.displayField] + "<input type='hidden' name='" + msStruct.field + "[]' value='" + id + "' />" +
                                "</div>");
                        dirty = true;
                        // re-sort the list
                        let list = document.querySelector('.multiSelectList'); // todo warning - may not work if page has more than one multi select list
                        [...list.children]
                                .sort((a,b)=>a.innerText>b.innerText?1:-1)
                                .forEach(node=>list.appendChild(node));

                        $(".multiSelectList").trigger({
                            type: "itemAdded",
                            item: row[msStruct.displayField]
                        });
                    }
                } else {
                    alert(data);
                }
            });
            $(this).val(0);
        }
    });

    // if (window.tinymce) { // if a tinymce editor exists and record is locked, set readonly mode
    //     if (!settings.allowEditing) {
    //         tinymce.activeEditor.mode.set('readonly'); // sets readonly in tinymce 5.x
    //     }
    // }

    function countryChange(country) {
        // if state select exists
        if ($("#stateZone").length) {
            // clear the state drop down
            $("#stateZone").find('option').remove();
            $.post(settings.ajaxPath + 'admin_ajax.php', {
                process:    'getRecords',
                db:         "tshop",
                select:     "SELECT z.* FROM `zc_zones` z INNER JOIN `zc_countries` c ON c.`countries_id` = z.`zone_country_id` ",
                where:      "`countries_name` = ?",
                bindtypes:  's',
                bindvalues: country,
                order:      'zone_name'
            }, function (data) {
                checkLoginStatus(data);
                if (data.substring(0, 7) === 'Success') {
                    // success
                    var obj = JSON.parse(data.substring(8));
                    // console.log(obj);
                    $("#stateZone").append('<option disabled selected >Please Select</option>');
                    obj.forEach(function (item, index) {
                        if ($("#state").val() === item['zone_code']) {
                            $("#stateZone").append('<option value="' + item['zone_code'] + '" selected >' + item['zone_name'] + '</option>');
                        } else {
                            $("#stateZone").append('<option value="' + item['zone_code'] + '">' + item['zone_name'] + '</option>');
                        }
                    })
                    $("#stateZone").show();
                    $("#state").get(0).type = 'hidden';
                } else {
                    // no state, hide select and show the text box
                    $("#stateZone").hide();
                    $("#state").get(0).type = 'text';
                }
            });
        }

    }


    return {
        // expose external functions and properties
        settings: settings,

        getIdentifier: identifier,

        timeoutReset: function () {
            resetTimeout();
        },

        setMode: function (newMode) {
            settings.mode = newMode;
            changeMode(newMode);
        },

        getDirty: function () {
            return dirty
        },

        setDirty: function (val) {
            dirty = val;
            if (!dirty) {
                // clear beforeunload event also
                $(window).off("beforeunload");
            }
        },

        closeEdit: function () {
            closeEditMode(400);
        },

        getCurrentID: function () {
            return settings.curId
        },

        setCurrentID: function (id) {
            settings.curId = id;
        },

        getParID: function () {
            return (settings.parId);
        },

        setParID: function (id) {
            settings.parId = id;
        },

        hideProfile: function (state) {
            hideProfile(state);
        },

        countryChange(country) {
            countryChange(country);
        },

        refresh: function (callback) {
            if (settings.mode === 'view') {
                // must set mode and current id before calling
                $("#addComment" + identifier).hide();
                $.post(settings.self , {
                    rm_process:    'getProfile',
                    identifier:     identifier,
                    id:             settings.curId
                }, function (data) {
                    var obj = JSON.parse(data);
                    // console.log(obj);
                    reloadProfile(obj, callback);
                });
            } else if (settings.mode === 'edit') {
                $(".readMode" + identifier).hide(400);
                if (settings.editModeNoColumns) {
                    $("#profile-flex-cols" + identifier).css('display','block');
                }
                $("#addComment" + identifier).hide();
                $("#profileHeading" + identifier).html('Edit ' + settings.editTitle);
                if (settings.noProfileMode && settings.allowDelete) {
                    $("#btnDeleteEdit" + identifier).show();
                }
                if ($("#duplicatebutton" + identifier).length > 0) {
                    $("#duplicatebutton" + identifier).show();
                }
                resetTimeout();
                // reloadInputs('edit');
                getEditPage('edit', callback);
            } else { // must be add mode
                $(".readMode" + identifier).hide(400);
                if (settings.editModeNoColumns) {
                    $("#profile-flex-cols" + identifier).css('display','block');
                }
                $("#addComment" + identifier).show();
                $("#btnDeleteEdit" + identifier).hide();
                $("#duplicatebutton" + identifier).hide();
                $(".editMode" + identifier).show(400);
                $("#profileHeading" + identifier).html('Add ' + settings.editTitle);
                // reloadInputs('add');
                getEditPage('add', callback);
            }
        },

        setTinySettings: function (tSettings) {
            tinySettings = tSettings;
        }
    };

}

function doFixPath(id) { // from RT column array attributes setting
    // look for /data/ and remove everything before it. Called by the file manager upon saving.
    var vlu = $("#" + id).val();
    if (vlu.indexOf("/data/") > 0) {
        $("#" + id).val(vlu.substring(vlu.indexOf("/data/"), 999)).trigger('change');
    }
    // also change the preview image, if exists
    if ($("#" + id + "_preview").length) {
        $("#" + id + "_preview").html("<img src='" + $("#" + id).val() + "' style='max-width: 700px;'>")
    }
}
