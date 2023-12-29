/**
 * tabbar2.js
 * common js functions used by the Tabbar class
 * by Lee Samdahl
 * 4/6/2021
 */

let tabbar = function (identifier, settings) {

    // let settings = {
    //     identifier:      "",
    //     currentTab:      0,
    //     currentTabID:    "",
    //     tabBodyId:       "",
    //     useAjax:         0,
    //     ajaxHandler:     "",
    //     refreshCallback: "",
    //     linkField:       "",
    //     linkID:          "",
    //     disabled:        false
    // }

    // items on tabs must use delegated event handlers
    // Tab Click Handler
    $(".tab_bar_buttons" + settings.identifier).on('click', function () {
        if (!settings.disabled) {
            let id = this.id;
            let tab_identifier = $(this).attr('data-tabIdentifier');
            let tab_index = $(this).attr('data-tabIndex'); // zero-based
            let tab_function = $(this).attr('data-function');
            let tab_elementID = $(this).attr('data-elementID');
            let tab_param0 = $(this).attr('data-param0');
            let tab_param1 = $(this).attr('data-param1');
            $(".tab_bar_buttons" + settings.identifier).removeClass("tab_bar_buttons_active");
            $("#" + id).addClass("tab_bar_buttons_active");
            settings.currentTabID = tab_identifier;
            settings.currentTab = tab_index;
            // set the session value for the current tab
            $.post(this.ajaxHandler, {
                process: "doSaveSessionVar",
                var:     settings.identifier + '_tab_selected',
                val:     tab_identifier
            });
            // get the tab contents
            if (!settings.useAjax) {
                if (tab_elementID !== '') { // prefilled html elements that are simply shown or hidden
                    $(".tabContents_" + settings.identifier).hide();
                    $("#" + tab_elementID).show(0, function () {
                        if (typeof window[settings.refreshCallback] == 'function') { // callback function after refresh
                            window[settings.refreshCallback](settings.identifier, settings.currentTab, settings.currentTabID);
                        }
                    });
                } else { // reload page with each tab selection
                    doSubmitWithValues({'tab_selected': id, 'tabbar_identifier': settings.identifier}, tab_param0); // assumes that first param is $id
                }
            } else { // use AJAX to get tab contents
                let data = {
                    tabbar_identifier: settings.identifier,
                    tab_show:          true,
                    tab_selected:      id,
                    tab_identifier:    tab_identifier,
                    tab_function:      tab_function,
                    tab_param0:        tab_param0,
                    tab_param1:        tab_param1
                };
                getTheTabContent(data, tab_elementID);
            }
        }
    });

    $("#tab_bar_container" + settings.identifier).on('click', ".tab_bar_scroll" + settings.identifier, function () {
        let el = $(".tab_bar_buttons_scroll" + settings.identifier);
        let container = $("#tab_bar_container" + settings.identifier).width();
        let buttons = el.width();
        let lmarg = el.css("margin-left");
        let nlmarg = Number(lmarg.substring(0, lmarg.indexOf('px')));
        let direction = $(this).attr('data-direction');
        if (direction === "right") {
            if (container - (buttons + nlmarg) < 0) {
                nlmarg = (nlmarg - 154);
                el.css("margin-left", nlmarg.toString() + "px");
            }
        } else {
            nlmarg = (nlmarg + 154);
            if (nlmarg > 0) nlmarg = 0;
            el.css("margin-left", nlmarg.toString() + "px" );
        }
    });

    function refreshTabs() {
        let data = {
            tabbar_identifier:  settings.identifier,
            process:            'refreshTabs'
        };
        if ($("#myForm").length === 1 && $("input[name='SubmitRequest']").length > 0) {
            data = getSearchCriteria(data);
        }
        $.post(settings.ajaxHandler, data, function (results) {
            // console.log(results);
            $(".tab_bar_container" + settings.identifier).html(results);
            checkWidth();
        });
    }

    function checkWidth() {
        // if container is smaller than buttons, make sure active tab is showing
        let el = $(".tab_bar_buttons_scroll" + settings.identifier);
        let container = $("#tab_bar_container" + settings.identifier).width();
        let buttons = el.width();
        let current = settings.currentTab;
        if (buttons > container) { // if we need to scroll the buttons
            $(".tab_bar_scroll" + settings.identifier).show(); // show the buttons
            let width = 0;
            let nlmarg = 0;
            let i = 0;
            $(".tab_bar_buttons" + settings.identifier).each(function() {
                if (i < current) {
                    if ((width + $(this).width()) > (container - 150)) {
                        nlmarg -= $(this).width();
                    } else {
                        width += $(this).width();
                    }
                } else {
                    return false;
                }
                i++;
            })
            // console.log(nlmarg);
            el.css("margin-left", nlmarg.toString() + "px");
        } else {
            // hide the buttons
            $(".tab_bar_scroll" + settings.identifier).hide();
            el.css("margin-left", "0px");
        }
    }

    $( document ).ready(function () {
        // checkWidth();

        if (settings.useAjax) {
            // load default tab content
            let el = $("#" + settings.currentTabID);
            let id = settings.currentTabID;
            let tab_identifier = el.attr('data-tabIdentifier');
            let tab_function = el.attr('data-function');
            let tab_elementID = el.attr('data-elementID');
            let tab_param0 = el.attr('data-param0');
            let tab_param1 = el.attr('data-param1');
            let data = {
                tabbar_identifier:  settings.identifier,
                tab_show:           true,
                tab_selected:       id,
                tab_identifier:     tab_identifier,
                tab_function:       tab_function,
                tab_param0:         tab_param0,
                tab_param1:         tab_param1
            };
            let data2 = {
                ...data,
                ...settings.post,
            }
            getTheTabContent(data2, tab_elementID);
        }
    });

    function getTheTabContent(data, tab_elementID) {
        if ($("#myForm").length === 1 && $("input[name='SubmitRequest']").length > 0) {
            data = getSearchCriteria(data);
            // console.log(data);
        }
        $.post(settings.ajaxHandler, data, function (results) {
            if (tab_elementID !== '') {
                $(".tabContents_" + settings.identifier).hide();
                $("#" + tab_elementID).html(results).show();
            } else {
                $("#" + settings.tabBodyId).html(results);
            }
            if (settings.refreshCallback !== "") { // callback function after refresh
                window[settings.refreshCallback](settings.identifier, settings.currentTab, settings.currentTabID);
            }
        });
    }

    function getSearchCriteria(nvp) {
        let getAll = false;
        if (this.name === 'SubmitRequest' && this.value === 'altSearchCriteria') {
            getAll = true;
        }
        $("#myForm input, #myForm select, #myForm textarea").each(function () {
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
        });
        if (settings.linkField !== '') {
            nvp['linkField'] = settings.linkField;
            nvp['linkID'] = settings.linkID;
        }
        // console.log(nvp);
        return nvp;
    }

    return {
        // public access to the settings of this instance
        settings: settings,

        // Public API functions

        // draw the tabbar
        refresh: function () {
            refreshTabs();
        },

        // to change the current tab
        setTab: function(tabID) {
            tabID = tabID || ""; // default for tabID
            if (tabID !== "") {
                $("#" + tabID).trigger('click');
                // console.log("Current Tab: " + tabID);
            }
        },
        getCurrentTab: function () {
            return settings.currentTabID;
        },
        disabled: function (state) {
            settings.disabled = state;
            if (state) {
                $(".tab_bar_buttons" + settings.identifier).addClass('tab_bar_buttons_disabled');
            } else {
                $(".tab_bar_buttons" + settings.identifier).removeClass('tab_bar_buttons_disabled');
            }
        }

    };

}
