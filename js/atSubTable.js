/**
 * atSubTable.js
 * js functions used by the ATSubTable class
 *@author Lee Samdahl
 *@createdOn 5/24/2023
 */

let atrSubTable = function (settings) {

    function modeChanged(mode, id) {
        if (mode === 'list') { // refresh the grid
            settings.rt.refresh();
            $("#editMode_" + settings.nameModifier).hide(400);
            $("#reportMode_" + settings.nameModifier).show(400);
        }
    }

    $("#addNewRecord_" + settings.nameModifier).on('click', function () {
        settings.rm.setMode('add');
        settings.rm.setCurrentID(0);
        settings.rm.refresh();
        // rmsp.settings.subTitle = 'Edit';
        $("#profileHeadingrm" + settings.rm_identifier).html('Add ' + settings.editTitle);
        $("#saveAddNew" + settings.rm_identifier).show();
        $("#reportMode_" + settings.nameModifier).hide(400);
        $("#editMode_" + settings.nameModifier).show(400);
    });

    function setEditMode(id) {
        settings.rm.setMode('edit');
        settings.rm.setCurrentID(id);
        settings.rm.refresh();
        if (settings.rm.settings.editModeNoColumns) {
            $("#profile-flex-cols" + settings.rm_identifier).css('display','block');
        }
        $("#profileHeadingrm" + settings.rm_identifier).html('Edit ' + settings.editTitle);
        $("#saveAddNew_" + settings.rm_identifier).hide();
        $("#reportMode_" + settings.nameModifier).hide(settings.transitionTime);
        $("#editMode_" + settings.nameModifier).show(settings.transitionTime);
    }

    return {
        // expose external functions and properties
        settings: settings,

        setMode: function (newMode) {
            settings.rm.setMode(newMode);
        },

        tabbarCallback: function (curId) {
            settings.rt.setLinkID(curId);
            if (settings.hasManage) {
                settings.rm.setParID(curId);
                let mode = settings.rm.settings.mode;
                if (mode === 'list') {
                    // refresh the grid
                    settings.rt.refresh();
                    settings.rt.setResizable();
                } else if (mode === 'edit') {
                    // get the inputs
                    setEditMode(settings.rm.getCurrentID());
                } else if (mode === "add") {
                    settings.rm.setMode('list');
                }
            } else {
                // refresh the grid
                settings.rt.refresh();
                settings.rt.setResizable();
            }
        },

        modeChanged: function (mode, id) {
            modeChanged(mode, id);
        },

        setEditMode: function (id) {
            setEditMode(id);
        }
    }
}
