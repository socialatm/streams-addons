var loglevel = 3; // 0 = errors/warnings, 1 = info, 2 = debug, 3 = dump data structures
var templateCheckbox = "";
var templateTextfield = "";

function t() {
    var now = new Date();
    var dateString = now.toISOString();
    return dateString;
}

function createCheckboxes(list, after, belongsToExperimental) {
    list = list.reverse();
    var html = "";
    for (var i = 0; i < list.length; i++) {
        var name = list[i][0];
        createCheckbox(list[i], after, belongsToExperimental);
    }
}

function createCheckbox(list, after, belongsToExperimental) {
    var name = list[0];
    var value = list[1];
    var disabled = list[2];
    var html = "";
    html = templateCheckbox.replaceAll("placeholdername", name);
    $(html).insertAfter(after);
    if (!value) {
        $("#id_" + name).removeAttr("checked");
    }
    if (disabled) {
        $("#id_" + name).attr("disabled", true);
    }
    $("#id_" + name).addClass(after.replace("#", "")); // for presets
    if (belongsToExperimental) {
        $("#id_" + name).addClass("belongsToExperimental"); // for presets
    }
}

function createTextFields(list, after) {
    for (var i = 0; i < list.length; i++) {
        createTextField(list[i], after);
    }
}

function createTextField(list, after) {
    var name = list[0];
    var value = list[1];
    var html = "";
    html = templateTextfield.replaceAll("placeholdername", name);
    $(html).insertAfter(after);
    $("#id_" + name).val(value);
    $("#label_" + name).html(name);
    if (name !== "zoom") {
        $("#id_" + name).addClass("belongsToExperimental"); // for presets
    }
}

function fillGUI(config) {
    experimental_allowed = config.experimental_allowed;
    createCheckboxes(config.detectors, "#face_detectors", true);
    createCheckboxes(config.models, "#face_models", true);
    createCheckboxes(config.distance_metrics, "#face_metrics", true);
    createCheckboxes(config.demography, "#face_attributes", true);
    createCheckboxes(config.statistics, "#face_statistics", true);
    createCheckboxes(config.history, "#face_history", true);
    createCheckboxes(config.enforce, "#face_enforce_all", true);
    createCheckboxes(config.immediatly, "#face_performance", true);
    createCheckboxes(config.faces_defaults, "#face_detaults");
    createCheckboxes(config.faces_experimental, "#face_experimental");
    createCheckboxes(config.ascending, "#face_sortation", false);
    createCheckboxes(config.exif, "#face_sortation", false);
    createTextFields(config.min_face_width_detection, "#face_size_detection");
    createTextFields(config.min_face_width_recognition, "#face_size_recognition");
    createTextFields(config.zoom, "#face_zoom");

    document.getElementById("id_reset").addEventListener("click", presetDefault);
    document.getElementById("id_experimental").addEventListener("click", presetExperimental);

    const checkboxes = document.getElementsByClassName('belongsToExperimental');
    for (const box of checkboxes) {
        box.addEventListener('click', function setBackPresetButtons() {
            document.getElementById("id_reset").checked = false;
            document.getElementById("id_experimental").checked = false;
        });
    }
}

function requestConfig() {
    var i = window.location.pathname.lastIndexOf(window.location.pathname.split("/")[3]);
    var postURL = window.location.pathname.substr(0, i) + "config";
    ((loglevel >= 1) ? console.log(t() + " requesting configuration, url=" + postURL) : null);
    $.post(postURL, [], function (data) {
        ((loglevel >= 1) ? console.log(t() + " Server response: " + JSON.stringify(data)) : null);
        if (data['config']) {
            fillGUI(data['config']);
        } else {
            ((loglevel >= 0) ? console.log(t() + " Error: received no config from server ") : null);
        }
    },
            'json');
}

function setPostURL() {
    $("#face_form_settings").attr("action", window.location.pathname);
}

function setToDefault(checkboxes, all) {
    var firstEnableCheckbox = null;
    for (var i = 0; i < checkboxes.length; i++) {
        var box = checkboxes[i];
        if (all) {
            box.checked = false;
        } else if (!box.disabled && firstEnableCheckbox === null) {
            firstEnableCheckbox = box;
            box.checked = true;
        } else if (!box.disabled && firstEnableCheckbox !== null) {
            box.checked = false;
        }
    }
}

function presetDefault() {
    var checkbox = document.getElementById("id_reset");
    if (!checkbox.checked) {
        return;
    }
    document.getElementById("id_experimental").checked = false;
    var checkboxgroups = ["face_detectors", "face_models", "face_metrics"];
    checkboxgroups.forEach(group => {
        var checkboxes = document.getElementsByClassName(group);
        setToDefault(checkboxes, false);
    });
    checkboxgroups = ["face_attributes", "face_statistics", "face_history", "face_enforce_all", "face_performance", "face_sortation"];
    checkboxgroups.forEach(group => {
        var checkboxes = document.getElementsByClassName(group);
        setToDefault(checkboxes, true);
    });
    $("#id_pixel").val("50");
    $("#id_percent").val("2");
    $("#id_result").val("50");
    $("#id_training").val("224");
}

function presetExperimental() {
    var checkbox = document.getElementById("id_experimental");
    if (!checkbox.checked) {
        return;
    }
    document.getElementById("id_reset").checked = false;
    var checkboxes = document.getElementsByClassName("belongsToExperimental");
    for (var i = 0; i < checkboxes.length; i++) {
        var box = checkboxes[i];
        if (!box.disabled) {
            box.checked = true;
        }
    }
}

function correctLinks() {
    $('.link_correction').each(function (i, obj) {
        path = window.location.pathname;
        path = path.substr(0,path.lastIndexOf("/settings"));
        channel = path.substr(path.lastIndexOf("/") + 1);
        link = obj.href;
        link = link.replace("channel-nick", channel);
        obj.href = link;
        ((loglevel >= 1) ? console.log(t() + " link nr=" + i) : null);
    });
}

$(document).ready(function () {
    loglevel = parseInt($("#faces_log_level").text());
    ((loglevel >= 1) ? console.log(t() + " loglevel=" + loglevel) : null);
    templateCheckbox = $("#placeholdername_container").prop('outerHTML');
    $("#placeholdername_container").remove();
    templateTextfield = $("#id_placeholdername_wrapper").prop('outerHTML');
    $("#id_placeholdername_wrapper").remove();
    setPostURL();
    correctLinks();
    requestConfig();
}
);

