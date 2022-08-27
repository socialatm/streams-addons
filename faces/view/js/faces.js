var zoom = 3;
var minZoomLoaded = 1;
var imageCounter = 0;
var loadedCountMaxMultiplier = 3;
var loadedCountMax = loadedCountMaxMultiplier * zoom;  // How many picture to load befor stopping. After the stop the user has to scroll down to load more images.
var loadedCount = 0;
var isFaceRecognitionRunning = false;
var sortDirectionReverse = false;
var stopLoadingImages = false;
var blockScrolledToEnd = false;

var endPanel = null;

// TODO set log level by server
var loglevel = 3; // 0 = errors/warnings, 1 = info, 2 = debug, 3 = dump data structures

let images = [];
let channel_name = "";
var receivedFaceEncodings = [];
var receivedNames = [];
var picturesProcessedID = [];
var files_name = []; // async http post for jquery >= 1.8 seems to be not asynchronous anymore
var counter_files_name = 0;

var server_procid = "";
var server_time = "";

var immediateSearch = false;

function t() {
    var now = new Date();
    var dateString = now.toISOString();
    return dateString;
}

function clear() {
    images = [];
    faces = [];
    receivedFaceEncodings = [];
    receivedNames = [];
    picturesProcessedID = [];
    countFilteredImages = images.length;
    files_name = [];
    counter_files_name = 0;
    $("#face-panel-pictures").empty();
}

$(".collapseit").click(function () {
    ((loglevel >= 2) ? console.log(t() + "remove options shown") : null);
    var content = this.nextElementSibling;
    if (content.style.display === "block") {
        content.style.display = "none";
    } else {
        content.style.display = "block";
    }
    //document.getElementById("face-panel-config-remove").style.visibility = "visible"; 
});

function getMinWidth() {
    var w = $('#min_face_width').val();
    ((loglevel >= 2) ? console.log(t() + "min width=" + w) : null);
    return w;
}

function getLogLevel() {
    var logLevel = document.getElementById("faces-log-level").value;
    ((loglevel >= 2) ? console.log(t() + "logLevel=" + logLevel) : null);
    return logLevel;
}

function postStart(isStart) {
    let postURL = window.location + "/start";
    if (!isStart) {
        postURL = window.location + "/recognize";
    }
    ((loglevel >= 1) ? console.log(t() + " about to start the face detection and recognition: url = " + postURL) : null);

    $.post(postURL, {}, function (data) {
        if (!data['status']) {
            ((loglevel >= 0) ? console.log(t() + " ERROR " + data['message']) : null);
            return;
        }
        ((loglevel >= 1) ? console.log(t() + " server message: " + data['message']) : null);
        ((loglevel >= 3) ? console.log(t() + " server data response: " + JSON.stringify(data)) : null);
        if (data['names']) {
            loadFaceData(data['names']); // creates list of images
        }
        setImmediateSearch(data);
        waitForFinishedFaceDetection();
    },
            'json');
}

function postUpdate() {
    var action = new Array();
    var postURL = window.location + "/results";
    //clear();
    ((loglevel >= 1) ? console.log(t() + " about to get the results of the face detection and recognition: url = " + postURL) : null);
    $.post(postURL, {action}, function (data) {
        if (!data['status']) {
            ((loglevel >= 0) ? console.log(t() + " ERROR " + data['message']) : null);
            return;
        }
        ((loglevel >= 1) ? console.log(t() + " server message: " + data['message']) : null);
        ((loglevel >= 2) ? console.log(t() + " server data response: " + JSON.stringify(data)) : null);
        if (data['names']) {
            loadFaceData(data['names']); // creates list of images
        }
        setImmediateSearch(data);
    },
            'json');
}

function setImmediateSearch(data) {
    if (data['immediatly']) {
        immediateSearch = data['immediatly'];
    }
}

function loadFaceData(files) {
    var i;
    for (i = 0; i < files.length; i++) {
        var f = files[i];
        ((loglevel >= 2) ? console.log(t() + " Received file " + f) : null);
        var url = window.location.origin + "/cloud/" + f;
        jQuery.ajax({
            url: url,
            success: function (data) {
                ((loglevel >= 3) ? console.log(t() + " loaded " + data) : null);
                readFaces(data, f);
                if (++counter_files_name === files.length) {
                    ((loglevel >= 3) ? console.log(t() + " last file was loaded: " + counter_files_name) : null);
                    if (stopLoadingImages) {
                        return;
                    }
                    filterAndSort();
                    appendPictures();
                }
            },
            async: true
        });
    }
}

function loadFaceData_old(files) {
    var i;
    for (i = 0; i < files.length; i++) {
        var f = files[i];
        var url = window.location.origin + "/cloud/" + f;
        ((loglevel >= 2) ? console.log(t() + " Requesting file " + url) : null);
        var jqxhr = $.ajax({
            type: "POST",
            url: url,
            data: "",
            dataType: "json",
            async: true
        });
        jqxhr.done(function (data) {
            ((loglevel >= 2) ? console.log(t() + " success loading url " + url) : null);
            ((loglevel >= 2) ? console.log(t() + " loaded " + data) : null);
            readFaces(data, f);
            if (++counter_files_name === files.length) {
                ((loglevel >= 3) ? console.log(t() + " last file was loaded: " + counter_files_name) : null);
                if (stopLoadingImages) {
                    return;
                }
                filterAndSort();
                appendPictures();
            }
        });
        jqxhr.fail(function (data) {
            ((loglevel >= 1) ? console.log(t() + " failed to load url " + url) : null);
            ((loglevel >= 1) ? console.log(t() + " failed - loaded " + data) : null);
        });
    }
}

function readFaces(imgs, csvFile) {
    let i = 0;
    while (imgs.file[i]) {
        let face = {
            id: imgs.id[i],
            url: "/cloud/" + channel_name + "/" + imgs.file[i],
            pos: imgs.position[i],
            face_nr: imgs.face_nr[i],
            name: imgs.name[i],
            name_recognized: imgs.name_recognized[i],
            time_named: imgs.time_named[i],
            exif_date: imgs.exif_date[i],
            csv_file: csvFile,
            sent: false
        };
        i++;
        appendName({name: face.name});
        appendFaceToImages(face);
    }
}

function appendFaceToImages(face) {
    var existingFace = getFaceForId(face.id);
    if (existingFace) {
        ((loglevel >= 2) ? console.log(t() + " try to update face id=" + face.id + " of image=" + face['url']) : null);
        updateFace(face);
        return;
    }
    var appended = false;
    images.forEach(image => {
        if (image['url'] === face['url']) {
            var existingFace = getFaceAtSamePosition(image, face);
            if (!existingFace) {
                image['faces'].push(face);
                ((loglevel >= 2) ? console.log(t() + " appended face to image = " + face['url']) : null);
            } else {
                face.id = existingFace.id;
                updateFace(face);
            }
            appended = true;
        }
    });
    if (appended) {
        return;
    }
    if (face.name.trim() == "-ignore-") {
        return;
    }
    var faces = [];
    faces.push(face);
    var image = {id: imageCounter++, url: face['url'], faces: faces, pass: true, exif_date: face['exif_date']};
    images.push(image);
    ((loglevel >= 2) ? console.log(t() + " created new image = " + face['url']) : null);
}


function editName(faceFrame) {
    hideEditFrame();

    var id = $(faceFrame).attr("id");
    ((loglevel >= 1) ? console.log(t() + " user starts do edit face id =" + id) : null);

    ((loglevel >= 2) ? console.log(t() + " figure out where to position the input field and buttons") : null);


    var r = getCssValueAsNumber($(faceFrame).css("right"));
    var l = getCssValueAsNumber($(faceFrame).css("left"));
    var top = getCssValueAsNumber($(faceFrame).css("top"));
    var bottom = getCssValueAsNumber($(faceFrame).css("bottom"));

    var par = $(faceFrame).parent();
    var img = par.find('img:first');
    var imgId = img.attr("id");

    var offsetTop = faceFrame.offsetTop;
    var faceFrameId = "face-frame-" + imgId // example: face-frame-img-1 ... is create dynamically while appending pictures
    var faceFrameEdit = document.getElementById(faceFrameId);
    faceFrameEdit.innerHTML = faceEditControls;
    var options = document.getElementById("face-name-list-search").innerHTML;
    document.getElementById("face-name-list-set").innerHTML = options;

    //faceFrameEdit.style.top = offsetTop + "px";
    if (top > bottom) {
        faceFrameEdit.style.bottom = bottom + 5 + "px";
        faceFrameEdit.style.top = "";
    } else {
        faceFrameEdit.style.top = top + 5 + "px";
        faceFrameEdit.style.bottom = "";
    }
    if (l > r) {
        faceFrameEdit.style.right = r + 5 + "px";
        faceFrameEdit.style.left = "";
    } else {
        faceFrameEdit.style.left = l + 5 + "px";
        faceFrameEdit.style.right = "";
    }
    var name_shown = faceFrame.innerText;
    document.getElementById("input-face-name").value = name_shown;
    document.getElementById("input-face-name").setAttribute("face_id", id); // to read out the face id if the user edits the name
    document.getElementById("input-face-name").focus();

    ((loglevel >= 1) ? console.log(t() + " edit frame  was opened and shows name = " + name_shown) : null);
}

function appendName(name) {
    ((loglevel >= 3) ? console.log(t() + " append single name to list (as option value)") : null);
    if (!name) {
        ((loglevel >= 2) ? console.log(t() + " name is null") : null);
        return;
    }
    if (name.name == "") {
        ((loglevel >= 3) ? console.log(t() + " name is empty") : null);
        return;
    }
    // append in ui
    if (!isNameInList(name.name, "face-name-list-search")) {
        $(".face-name-list").append("<option value=\"" + name.name + "\">" + name.name + "</option>");
        ((loglevel >= 1) ? console.log(t() + " append name = " + name.name) : null);
    }
    // append to list received names
    var i;
    for (i = 0; i < receivedNames.length; i++) {
        var existing_name = receivedNames[i].name;
        if (existing_name == name.name) {
            ((loglevel >= 2) ? console.log(t() + " name does exist already in array or received names") : null);
            return;
        }
    }
    ((loglevel >= 3) ? console.log(t() + " appending name to array or received name as well") : null);
    receivedNames.push(name);
}

function isNameInList(name, selector) {
    ((loglevel >= 2) ? console.log(t() + " is name = " + name + " in list with selector = " + selector + " ?") : null);
    var o = document.getElementById(selector);
    var i;
    for (i = 0; i < o.options.length; i++) {
        var text = o.options[i].text;
        if (text == name) {
            ((loglevel >= 2) ? console.log(t() + " yes") : null);
            return true;
        }
    }
    ((loglevel >= 2) ? console.log(t() + " no") : null);
    return false;
}


function setName() {
    var face_id_full = document.getElementById("input-face-name").getAttribute("face_id");
    var name_shown = document.getElementById(face_id_full).innerText;
    var name = document.getElementById("input-face-name").value.trim();
    ((loglevel >= 1) ? console.log(t() + " start to set new face name new = " + name + " was name = " + name_shown + " with face id=" + face_id_full) : null);
    if (name === name_shown) {
        ((loglevel >= 1) ? console.log(t() + " name did not change") : null);
        var isVerified = document.getElementById(face_id_full).getAttribute("isVerified");
        if (isVerified == "1") {
            ((loglevel >= 1) ? console.log(t() + " Do nothing, was verified already") : null);
            hideEditFrame();
            return;
        }
    }
    if (name != "") {
        if (!isNameInList(name, "face-name-list-search")) {
            ((loglevel >= 1) ? console.log(t() + " The new name = " + name + " will be appened to the name list (as option value) as soon as it was successfully sent to the server.") : null);
            appendName({name: name});
        }
        ((loglevel >= 1) ? console.log(t() + " The style of the frame will be changed after it was successfully sent to the server.") : null);//    document.getElementById(face_id_full).style.border = "medium solid green";
    }
    preparePostName(face_id_full, name);
}

function setNameUnkown() {
    var face_id_full = document.getElementById("input-face-name").getAttribute("face_id");
    ((loglevel >= 1) ? console.log(t() + " set face name > unknown - face id=" + face_id_full) : null);
    ((loglevel >= 1) ? console.log(t() + " The style of the frame will be changed after it was successfully sent to the server.") : null);
    preparePostName(face_id_full, -1);
}

function setNameIgnore() {
    var face_id_full = document.getElementById("input-face-name").getAttribute("face_id");
    ((loglevel >= 1) ? console.log(t() + "set face name > ignore - face id=" + face_id_full) : null);
    ((loglevel >= 1) ? console.log(t() + " The style of the frame will be changed after it was successfully sent to the server.") : null);
    preparePostName(face_id_full, -2);
}

function preparePostName(face_id_full, name) {
    ((loglevel >= 1) ? console.log(t() + " start to prepare name  to send it to the server, face id = " + face_id_full) : null);
    var face_id = face_id_full.split("-")[1];
    if (name === -2) {
        // clicked "ignore"
        name = "-ignore-";
    } else if (name === -1) {
        // clickt "unknown"
        name = "";
    } else if (name === -3) {
        // let the face recognition try to guess
        name = "";
    }
    var name_old = document.getElementById(face_id_full).innerText;
    var face = getFaceForId(face_id);
    face.sent = true;
    face.name = name;
    var file = face['url'];
    var position = face['pos'];
    name = name.replace(",", " ");  // format of csv 
    unsentNames.push({
        "id": face_id,
        "file": file,
        "position": position,
        "name_old": name_old,
        "name": name
    });
    hideEditFrame();
    postNames();
}


var unsentNames = [];

function removeNameFromUnsentList(face) {
    if (!face) {
        ((loglevel >= 1) ? console.log(t() + " can not remove face id from unsent list because the id is null (received as server response)") : null);
        return false;
    }
    ((loglevel >= 1) ? console.log(t() + "remove face from unsent list, face = " + JSON.stringify(face)) : null);
    var faceID = face.id;
    var hasRemoved = false;
    var i;
    for (i = 0; i < unsentNames.length; i++) {
        var id = unsentNames[i].id;
        if (faceID === id) {
            //unsentNames.remove(unsentNames[i]);
            unsentNames.splice(i, 1);
            ((loglevel >= 1) ? console.log(t() + " removed face with id = " + id + " from list of unsent faces") : null);
            hasRemoved = true;
            break;
        }
    }
    ((loglevel >= 1) ? console.log(t() + " Success yes/no for: face was removed from list of unsent faces : " + hasRemoved) : null);
    return hasRemoved;
}



function postNames() {
    ((loglevel >= 1) ? console.log(t() + " post the next name in the list of unsent faces ") : null);
    if (unsentNames.length === 0) {
        ((loglevel >= 1) ? console.log(t() + " no name left to send ") : null);
        clearCounterNamesSending();
        if (!isFaceRecognitionRunning && immediateSearch) {
            postStart(false);
        }
        return;
    }
    var action = new Array();
    action["face"] = unsentNames[0];
    var nameString = JSON.stringify(unsentNames[0]);
    ((loglevel >= 1) ? console.log(t() + " About to send the first name in the list of unsent name to the server. Post value is : " + nameString) : null);
    animate_on();
    setCounterNamesSending();
    var postURL = window.location + "/name";
    ((loglevel >= 1) ? console.log(t() + " url =  " + postURL) : null);
    $.post(postURL, {face: unsentNames[0]}, function (data) {
        ((loglevel >= 1) ? console.log(t() + " Received response from server after posting a name") : null);
        if (data['status']) {
            ((loglevel >= 1) ? console.log(t() + " Server response to sending the name was without errors") : null);
            if (removeNameFromUnsentList(data['face'])) {
                styleFaceFrame(data['face']);
                postNames();
            } else {
                ((loglevel >= 1) ? console.log(t() + " Stop sendings names because failed to removed a name/face frome the unsent list. The name/face was received from the server (as response to set its name)") : null);
                clearCounterNamesSending();
            }
        } else {
            ((loglevel >= 0) ? console.log(t() + " Error sending name. Server responded with: " + data['message']) : null);
            clearCounterNamesSending();
        }
    },
            'json');
}

function updateFaces(encodings) {
    // the values of faces changed
    // - if the user gave the face
    //   o a name that was not known before (in DB an in name list)
    //   o another name than before
    // - the face recognition made some new guesses
    ((loglevel >= 2) ? console.log(t() + " Update faces received from server. The faces are: " + JSON.stringify(Object.assign({}, encodings))) : null);
    var i;
    for (i = 0; i < encodings.length; i++) {
        var k;
        for (k = 0; k < receivedFaceEncodings.length; k++) {
            if (encodings[i].id == receivedFaceEncodings[k].id) {
                // replace and update ui (even if nothing changed)
                receivedFaceEncodings[k] = encodings[i];
                styleFaceFrame(receivedFaceEncodings[k]);
                break;
            }
        }
    }
}

$(".faces-search-inputs").focus(function () {
    stopLoadingImages = true;
});


function addSearchName() {
    ((loglevel >= 1) ? console.log(t() + " selected a search name") : null);
    var name = document.getElementById("input-search-name").value.trim();
    if (name == "") {
        ((loglevel >= 1) ? console.log(t() + " name was null") : null);
        return;
    }
    if (!isNameInList(name, "face-name-list-search")) {
        ((loglevel >= 1) ? console.log(t() + " you can not search for a name that is not known") : null);
        return;
    }
    ((loglevel >= 1) ? console.log(t() + " search name = " + name) : null);
    var buttons = document.querySelectorAll('[searchnameid]');
    document.getElementsByClassName('btn-face-search-name');
    var i;
    for (i = 0; i < buttons.length; i++) {
        var text = names[i].innerText.trim();
        if (text == name) {
            ((loglevel >= 1) ? console.log(t() + " seach name already selected " + name) : null);
            return;
        }
    }
    $("#face-active-filter-names").append("<button class=\"btn btn-face-search-name\">" + name + " <i class=\"fa fa-remove fa-lg faces-search-inputs\"></i></button>");
    ((loglevel >= 1) ? console.log(t() + " created button for search name =  " + name) : null);
    search();
}

$('#face-active-filter-names').on('click', '.btn-face-search-name', function () {
    ((loglevel >= 1) ? console.log(t() + " remove button for search name =  " + name) : null);
    $(this).remove();
    search();
});

$(".face-date").change(function () {
    ((loglevel >= 1) ? console.log(t() + " user changed search date  ") : null);
    search();
});

$("#face-search-and").change(function () {
    var buttons = document.getElementsByClassName("btn-face-search-name");
    if (buttons.length < 1) {
        return;
    }
    ((loglevel >= 1) ? console.log(t() + " user changed search AND/OR  ") : null);
    search();
});


var filterStringServer = "";
var oldestImageLoadedId = "";
var mostRecentImageLoadedId = "";
var filter = null;
function search() {
    ((loglevel >= 1) ? console.log(t() + " search was called ") : null);
    $("#face-panel-pictures").empty();
    oldestImageLoadedId = "";
    mostRecentImageLoadedId = "";
    filterAndSort();
    picturesProcessedID = [];
    // stop loading images
    ((loglevel >= 1) ? console.log(t() + " Remove pictures ") : null);
    $("#face-panel-pictures").empty();
    animate_on();
    appendPictures();
}


var countFilteredImages = 0;

function filterAndSort() {
    createFilterString();
    filterImages();
    images.sort(compareExifDates);
    stopLoadingImages = false;
}

function filterImages() {
    if (!filter) {
        countFilteredImages = images.length;
        return;
    }
    countFilteredImages = 0;
    var names = filter.names;
    var i;
    for (i = 0; i < images.length; i++) {
        var faces = images[i].faces;

        var passedTime = false;
        var passedName = false;

        var date = images[i].exif_date;
        if (!date || date == "") {
            continue;
        }
        var splittees = date.split("T");
        if (splittees.length != 2) {
            break;
        }
        date = splittees[0];
        if (date >= filter.from && date <= filter.to) {
            passedTime = true;
            if (names.length == 0) {
                passedName = true;
            } else {
                var and = filter.and;
                for (k = 0; k < names.length; k++) {
                    var name = names[k];
                    var passedName = false;
                    for (j = 0; j < faces.length; j++) {
                        var face = faces[j];
                        if ((face.name != "" && name == face.name) || (face.name == "" && face.time_named == "" && name == face.name_recognized)) {
                            passedName = true;
                            break;
                        }
                    }
                    if (!and && passedName) {
                        break;
                    }
                    if (and && !passedName) {
                        break;
                    }
                }
            }
        }

        if (passedName && passedTime) {
            images[i]['pass'] = true;
            countFilteredImages += 1;
            ((loglevel >= 3) ? console.log(t() + " image id=" + images[i].id + " passed the filter") : null);
        } else {
            images[i]['pass'] = false;
            ((loglevel >= 3) ? console.log(t() + " image id=" + images[i].id + " did not pass the filter") : null);
        }
    }
}

function compareExifDates(a, b) {
    if (a.exif_date < b.exif_date) {
        if (sortDirectionReverse) {
            return -1;
        } else {
            return 1;
        }
    }
    if (a.exif_date > b.exif_date) {
        if (sortDirectionReverse) {
            return 1;
        } else {
            return -1;
        }
    }
    return 0;
}


function createFilterString() {
    $("#input-search-name").val("");

    var filterNames = [];
    ((loglevel >= 1) ? console.log(t() + " look for search buttons containing the search names ") : null);
    var buttons = document.getElementsByClassName("btn-face-search-name");
    var i;
    for (i = 0; i < buttons.length; i++) {
        var name = buttons[i].innerText.trim();
        filterNames.push(name);
        ((loglevel >= 1) ? console.log(t() + " append search name=" + name) : null);
    }

    toogleAndCheckbox();
    var checked = $("#face-search-and")[0].checked;
    ((loglevel >= 1) ? console.log(t() + " AND = " + checked) : null);

    var from = $("#face-date-from").val();
    var to = $("#face-date-to").val();
    ((loglevel >= 1) ? console.log(t() + " from = " + from) : null);
    ((loglevel >= 1) ? console.log(t() + " to = " + to) : null);

    filter = {"from": from, "to": to, "names": filterNames, "and": checked};
}

function toogleAndCheckbox() {
    var buttons = document.getElementsByClassName("btn-face-search-name");
    if (buttons.length > 1) {
        $(".face-name-and-buttons").css("visibility", "visible");
    } else {
        $(".face-name-and-buttons").css("visibility", "hidden");
    }
}



function getCssValueAsNumber(s) {
    ((loglevel >= 3) ? console.log(t() + " get CSS value = " + s + " as number") : null);
    var i = 0;
    if (isNaN(s)) {
        if (s.indexOf(".") >= 0) {
            s = s.substring(0, s.indexOf("."));
        }
        if (s.indexOf("px") >= 0) {
            s = s.substring(0, s.indexOf("px"));
        }
        i = parseInt(s);
        ((loglevel >= 2) ? console.log(t() + " return CSS value = " + s + " as number = " + i) : null);
        return i;
    } else {
        ((loglevel >= 1) ? console.log(t() + " return CSS value = " + s + " as  = " + s + " (failed to parse)") : null);
        return s;
    }
}


function hideEditFrame() {
    $(".face-frame-edit").css({top: '99%'});
    $(".face-frame-edit").html("");
    ((loglevel >= 1) ? console.log(t() + " hide edit frame") : null);
    showFooterButtons();
}

$("#button-faces-filter").click(function () {
    ((loglevel >= 1) ? console.log(t() + " user clicked on filter button") : null);
    showFooterFilter();
});

function showFooterButtons() {
    $("#face-footer-buttons").css("height", "40px");
    $("#face-footer").css("height", "0px");
    ((loglevel >= 1) ? console.log(t() + " show footer buttons") : null);
}

function showFooterFilter() {
    $("#face-footer-buttons").css("height", "0px");
    $("#face-footer").css("height", "170");
    toogleAndCheckbox();
    ((loglevel >= 1) ? console.log(t() + " show footer filter") : null);
    document.getElementById("input-search-name").focus();
}


var isShowFrameON = true;
$("#button-faces-hide-frames").click(function () {
    ((loglevel >= 1) ? console.log(t() + " user clicked button to show/hide faces") : null);
    if (isShowFrameON) {
        $('#button-faces-hide-frames').find('.fa').removeClass("fa-eye-slash");
        $('#button-faces-hide-frames').find('.fa').addClass("fa-eye");
        var frames = document.getElementsByClassName("face-frame-name");
        var k;
        for (k = 0; k < frames.length; k++) {
            frames[k].style.border = "rgba(255,255,255,.5)";
            frames[k].getElementsByClassName("face-name-shown")[0].innerHTML = "";
        }
        isShowFrameON = false;
    } else {
        $('#button-faces-hide-frames').find('.fa').removeClass("fa-eye");
        $('#button-faces-hide-frames').find('.fa').addClass("fa-eye-slash");
        styleAllAgain();
        isShowFrameON = true;
    }
    //    zoomPictures();
});

$("#button_faces_zoom_in").click(function () {
    ((loglevel >= 1) ? console.log(t() + " user clicked zoom in") : null);
    if (zoom > 0) {
        zoom -= 1;
    } else {
        return;
    }
    if (zoom < minZoomLoaded) {
        //search();
        minZoomLoaded = zoom;
    } else {
        zoomPictures();
    }
});

$("#button_faces_zoom_out").click(function () {
    ((loglevel >= 1) ? console.log(t() + " user clicked zoom out") : null);
    if (zoom < 6) {
        zoom += 1;
    } else {
        return;
    }
    zoomPictures();
});

function zoomPictures() {
    loadedCountMax = zoom * loadedCountMaxMultiplier;
    if (zoom < 1) {
        var containers = document.getElementsByClassName("img-container");
        var i;
        for (i = 0; i < containers.length; i++) {
            var img_container = containers[i];
            var face_conatiner = img_container.getElementsByClassName("face-container")[0];
            var img = face_conatiner.getElementsByTagName("img")[0];
            px_width = img.naturalWidth;
            img_container.style.width = px_width + "px";
            img.style.width = px_width + "px";
        }
    } else {
        $(".img-face").css("width", "100%");
        $(".img-container").css("width", "100%");
        $(".face-img-spacer").remove();
        ((loglevel >= 1) ? console.log(t() + " zoom = " + (100 / zoom) + "%") : null);
        var containers = document.getElementsByClassName("img-container");
        for (i = 0; i < containers.length; i++) {
            containers[i].classList.add("img-container-zoomable");
        }
        zoomLastPictures();
        hideEditFrame();
    }
}

function zoomLastPictures(img) {
    if (img) {
        if (zoom < 1) {
            var img_container = img.parentElement.parentElement;
            px_width = img.naturalWidth;
            img_container.style.width = px_width + "px";
            img.style.width = px_width + "px";
            return;
        }
        $(".img-container-zoomable").css("width", "100%");  // to prevent that the images get smaller from image to imgage for a zoom level greater than 2
    }
    if (zoom === 1) {
        return;
    }
    var w = $("#face-panel-pictures").width();
    var containers = document.getElementsByClassName("img-container-zoomable");
    if (containers.length === 1) {
        return;
    }
    // if (containers.length < zoom) {
    //     $(".img-container-zoomable").css("width", (100 / zoom).toString() + "%");
    //     return;
    // }
    var zoomendContainers = [];
    var zoomenedImages = [];
    var h = 0;
    var w_all = 0;
    var i;
    for (i = 0; i < containers.length; i++) {
        var img_container = containers[i];
        var img = img_container.getElementsByTagName("img")[0];
        if (h == 0) {
            h = img.naturalHeight;
            w_all = w_all + img.naturalWidth;
            zoomenedImages[i] = ({h: img.naturalHeight, w: img.naturalWidth, factor: 1});
        } else {
            var factor = h / img.naturalHeight;
            w_all = w_all + factor * img.naturalWidth;
            var factor_all = w / w_all;
            zoomenedImages[i] = ({w: img.naturalWidth, factor: factor});
            for (j = 0; j <= i; j++) {
                var w_pix = zoomenedImages[j]["w"] * zoomenedImages[j]["factor"] * factor_all;
                var w_percent = w_pix * 100 / w;
                containers[j].style.width = w_percent + "%";
                zoomendContainers[j] = containers[j];
            }
        }
        if (i === zoom - 1) {
            var k;
            for (k = 0; k < zoomendContainers.length; k++) {
                zoomendContainers[k].classList.remove("img-container-zoomable");
            }
            var id = img_container.id.split("-")[2];
            id = img_container.id;
            //$("<div class=\"img-container face-img-spacer\"  style=\"min-width: 100%; min-height: 1px;\"> </div>").insertAfter("#" + id);
            $("<div class=\"img-container face-img-spacer\"  style=\"min-width: 100%; height: 1px;\"> </div>").insertAfter("#" + id);
            zoomLastPictures();
            return;
        }
    }
}

function openSingleImage(img) {
    var url = img.getAttribute('src');
    var url_large = url.replace(/-\d$/, "");
    window.open(url_large, "_blank");
}

function appendPicture(img) {
    ((loglevel >= 1) ? console.log(t() + " start to show image to user, image = " + JSON.stringify(Object.assign({}, img))) : null);
    var html = "";
    var faces = img.faces;
    var i;
    for (i = 0; i < faces.length; i++) {
        ((loglevel >= 2) ? console.log(t() + " append face number = " + i + " in image") : null);
        // row from db table face_encodings (joined with attach.hash to complete the URL)
        var face = faces[i];
        if (i === 0) {
            var url = face.url;
            var width = (100 / zoom).toString() + "%";
            //html += "<div class=\"img-container img-container-zoomable\" id=\"img-container-" + img.id + "\" width=\"" + width + "\">";
            html += "<div class=\"img-container img-container-zoomable\" id=\"img-container-" + img.id + "\">";
            html += "   <div class=\"face-container\">";
            html += "       <img src=\"" + url + "\" id=\"img-" + img.id + "\" class=\"img-face\" onload=\"setFrameSizes(this)\" onclick=\"hideEditFrame(false)\" ondblclick=\"openSingleImage(this)\">";
        }
        ((loglevel >= 2) ? console.log(t() + " adding face number = " + i + " with face id=" + face.id + " to image with id=" + img.id) : null);
        var top = 1;
        var left = 1;
        var bottom = 98;
        var right = 98;
        html += "           <div class=\"face-frame face-frame-name\" id=\"face-" + face.id + "\" model=\"" + face.model + "\" style=\"top: " + top + "%;left: " + left + "%; bottom: " + bottom + "%; right: " + right + "%;\" onclick=\"editName(this)\">";
        html += "               <h4 class=\"face-name-shown\">" + face.id + "</h4>";
        html += "           </div>";
        if (i === faces.length - 1) {
            html += "       <div class=\"face-frame face-frame-edit\" id=\"face-frame-img-" + img.id + "\">"; // picture id from table attach (joined with table face_encodings)
            html += "       </div>";
            html += "   </div>";
            html += "</div>";
        }
    }
    if (mostRecentImageLoadedId == "") {
        mostRecentImageLoadedId = img.id;
        ((loglevel >= 2) ? console.log(t() + " most recent image id: " + mostRecentImageLoadedId) : null);
    }

    if (oldestImageLoadedId == 0) {
        oldestImageLoadedId = img.id;
        ((loglevel >= 2) ? console.log(t() + " oldest image id: " + oldestImageLoadedId) : null);
    } else if (img.id < oldestImageLoadedId) {
        oldestImageLoadedId = img.id;
        ((loglevel >= 2) ? console.log(t() + " oldest image id: " + oldestImageLoadedId) : null);
    }
    if (stopLoadingImages) {
        return;
    }
    animate_on();
    $("#face-panel-pictures").append(html);
}

function getImageForId(id) {
    var i;
    for (i = 0; i < images.length; i++) {
        if (id == images[i].id) {
            ((loglevel >= 3) ? console.log(t() + " returning image for id = " + id + " found in the list of received images") : null);
            return images[i];
        }
    }
    ((loglevel >= 3) ? console.log(t() + " found no image for id = " + id + " in the list of received images") : null);
    return false;
}

function getFaceForId(id) {
    var i;
    for (i = 0; i < images.length; i++) {
        var faces = images[i].faces;
        for (j = 0; j < faces.length; j++) {
            var face = faces[j];
            if (id == face.id) {
                ((loglevel >= 3) ? console.log(t() + " returning face for id = " + id + " found in the list of received images") : null);
                return face;
            }
        }
    }
    ((loglevel >= 3) ? console.log(t() + " found no face for id = " + id + " in the list of received images") : null);
    return false
}

function updateFace(face) {
    var i;
    for (i = 0; i < images.length; i++) {
        var faces = images[i].faces;
        for (j = 0; j < faces.length; j++) {
            var f = faces[j];
            if (f.id == face.id) {
                ((loglevel >= 3) ? console.log(t() + " update face id = " + face.id + " in the underlying data array") : null);
                images[i].faces[j] = face;
                ((loglevel >= 3) ? console.log(t() + " style face frame for id = " + face.id) : null);
                styleFaceFrame(face);
                return true;
            }
        }
    }
    ((loglevel >= 1) ? console.log(t() + " found no faces for id = " + id + " to update") : null);
    return false;
}

// If faces are updated they have not always the same face id because the are found by different detectors/models.
// Check the position of existing faces (frames arround faces) to avoid multiple frames around a same face.
function getFaceAtSamePosition(image, face) {
    ((loglevel >= 3) ? console.log(t() + " try to find a face at the same position=" + face.pos + " in image=" + face.url + ", faces id=" + face.id) : null);
    var faces = image.faces;
    for (j = 0; j < faces.length; j++) {
        var f = faces[j];
        // margins left, right, top, bottom in percent
        middle_of_face_x = face.pos[0] + (100 - (face.pos[1] + face.pos[0])) / 2;
        middle_of_face_y = face.pos[2] + (100 - (face.pos[2] + face.pos[3])) / 2;
        end_of_row_face_x = 100 - f.pos[1];
        end_of_row_face_y = 100 - f.pos[3];
        // is middle of face inside row position?
        if ((f.pos[0] < middle_of_face_x) && (middle_of_face_x < (end_of_row_face_x))) {
            if ((f.pos[2] < middle_of_face_y) && (middle_of_face_y < (end_of_row_face_y))) {
                middle_of_row_face_x = f.pos[0] + (100 - (f.pos[1] + f.pos[0])) / 2
                middle_of_row_face_y = f.pos[2] + (100 - (f.pos[2] + f.pos[3])) / 2
                end_of_face_x = 100 - face.pos[1];
                end_of_face_y = 100 - face.pos[3];
                // is middle of row position inside face ?
                if ((face.pos [0] < middle_of_row_face_x) && (middle_of_row_face_x < (end_of_face_x))) {
                    if ((face.pos[2] < middle_of_row_face_y) && (middle_of_row_face_y < (end_of_face_y))) {
                        ((loglevel >= 3) ? console.log(t() + " yes, found a face id=" + f.id + " at position=" + f.pos + " in image=" + face.url) : null);
                        return f;
                    }
                }
            }
        }
    }
    ((loglevel >= 3) ? console.log(t() + " did not find a face at position=" + face.pos + " in image=" + face.url + " for id=" + face.id) : null);
    return false;
}

function setFrameSizes(img) {
    ((loglevel >= 1) ? console.log(t() + " start to set frame size") : null);
    var px_width = img.naturalWidth;
    var px_height = img.naturalHeight;
    ((loglevel >= 2) ? console.log(t() + " img h=" + px_height + "px, w=" + px_width + "px, id=" + img.id) : null);
    var splittees = img.id.split("-");
    var img_id = splittees[1];
    var image = getImageForId(img_id);
    var faces = image.faces;
    var i;
    for (i = 0; i < faces.length; i++) {
        var face = faces[i];
        // What to use depends on the face recognition scripts
        // a) in percent margins [left, right, top, bottom] default
        // b) in pixel:          [x, y, h, w]               from left, top corner in pixel
        var faceLocation = face.pos;
        var nameFrame = document.getElementById("face-" + face.id);
        if (!nameFrame) {
            return;
        }
        ((loglevel >= 3) ? console.log(t() + " face id = " + face.id) : null);
        nameFrame.style.top = faceLocation[2] + "%";
        nameFrame.style.left = faceLocation[0] + "%";
        nameFrame.style.bottom = faceLocation[3] + "%";
        nameFrame.style.right = faceLocation[1] + "%";
        styleFaceFrame(face);
    }
    ((loglevel >= 3) ? console.log(t() + " finished to set frame size") : null);
    zoomLastPictures(img);
    appendNextPicture();
}


function styleFaceFrame(face) {
    if (!face) {
        ((loglevel >= 2) ? console.log(t() + " style frame for face = null > can not style (update frame style and name) ") : null);
        return;
    }
    ((loglevel >= 2) ? console.log(t() + " style frame for face = " + JSON.stringify(Object.assign({}, face))) : null);
    var nameFrame = document.getElementById("face-" + face.id);
    if (!nameFrame) {
        // no image yet to update (happens after http get = loading the page)
        // ((loglevel >= 1) ? console.log(t() + " no image yet to update (happens 1. after http get = loading the page, or 2. after show face frames again after hidden if images where not displayed yet") : null);
        return;
    }
    var name = "";
    var name_id = "0";
    var isVerified = "0";
    if (!face.name_recognized) {  // This can happen if the user sets the name to "unknown"
        face.name_recognized = "";  // avoid "undefined"
    }
    existing_name = "";
    face_existing = getFaceForId(face.id)
    if(face_existing.sent)  {
        ((loglevel >= 2) ? console.log(t() + " This face name was set already. Style frame for face = " + JSON.stringify(Object.assign({}, face))) : null);
        existing_name = face_existing.name;
    }
    
    if(existing_name != "") {
        nameFrame.style.border = "rgba(255,255,255,.5)";
        name = existing_name;
        isVerified = "1";
    } else if (face.name != "") {
        // prio 1 because this face was given a name by user
        if (face.name == "-ignore-") {
            document.getElementById("face-" + face.id).remove();
            return;
        }
        nameFrame.style.border = "rgba(255,255,255,.5)";
        name = face.name;
        isVerified = "1";
        ((loglevel >= 3) ? console.log(t() + " verified face, id = " + face.id) : null);
    } else if (face.name_recognized != "") {
        // prio 2 because this is guessed by the face recognition
        nameFrame.style.border = "medium dashed red";
        if (face.time_named == "") {
            name = face.name_recognized;
        }
        ((loglevel >= 3) ? console.log(t() + " recognized face, id = " + face.id + " (guessed by face recognition)") : null);
    } else {
        // prio 3 because this is the default if nothing is known about this face
        nameFrame.style.border = "medium dotted red";
        ((loglevel >= 3) ? console.log(t() + " nothing known about this and therefor styled as dotted red") : null);
    }
    nameFrame.getElementsByClassName("face-name-shown")[0].innerHTML = name;
    nameFrame.setAttribute("isVerified", isVerified);
    ((loglevel >= 2) ? console.log(t() + " frame shows name = " + name) : null);
}

function styleAllAgain() {
    ((loglevel >= 1) ? console.log(t() + " start - style all again") : null);
    var i;
    for (i = 0; i < images.length; i++) {
        var faces = images[i].faces;
        for (j = 0; j < faces.length; j++) {
            var face = faces[j];
            ((loglevel >= 3) ? console.log(t() + " style face frame for id = " + face.id) : null);
            styleFaceFrame(face);
        }
    }
    ((loglevel >= 1) ? console.log(t() + " finished - style all again") : null);
}

function checkServerStatus(status) {
    if (!status) {
        showServerStatus(0);
    } else if (!isFaceRecognitionRunning) {
        showServerStatus(0);
    } else {
        var proc = status["procid"];
        var utc = status["utc"];
        if (proc != server_procid) {
            server_procid = proc;
            server_time = utc;
            ((loglevel >= 1) ? console.log(t() + " server status with new procid=" + server_procid + ", time=" + server_time) : null);
            showServerStatus(0);
        } else {
            var seconds = (new Date()).getTime() / 1000;
            seconds = Math.round(seconds);
            date = new Date(server_time);
            seconds_stored = date.getTime() / 1000;
            var elapsed = seconds - seconds_stored;
            ((loglevel >= 1) ? console.log(t() + " server status updated with procid=" + server_procid + ", time=" + utc + ", calculated and shown elapsed=" + elapsed + "s") : null);
            showServerStatus(elapsed, status["elapsed"]);

        }
    }
}

function showServerStatus(seconds, elapsed_server) {
    if (seconds != 0) {
        document.getElementById("faces_server_status").style.visibility = "visible";
        if (elapsed_server > 60) {
            document.getElementById("faces_server_status").style.color = "red";
        } else {
            document.getElementById("faces_server_status").style.color = "green";
        }
        document.getElementById("faces_server_status").innerHTML = seconds + "s";
    } else {
        document.getElementById("faces_server_status").style.visibility = "hidden";
        document.getElementById("faces_server_status").innerHTML = "";
    }
}

async function waitForFinishedFaceDetection() {
    isFaceRecognitionRunning = true;
    var ms = 10000;
    var url = window.location + "/status";
    while (isFaceRecognitionRunning) {
        var action = new Array();
        var postURL = window.location + "/status";
        ((loglevel >= 1) ? console.log(t() + " about to get the status of the face detection and recognition: url = " + postURL) : null);
        $.post(postURL, {action}, function (data) {
            ((loglevel >= 1) ? console.log(t() + " received server status " + JSON.stringify(data)) : null);
            if (data['running']) {
                checkServerStatus(data['status']);
            } else {
                isFaceRecognitionRunning = false;
                checkServerStatus(data['status']);
                if (unsentNames.length === 0) {
                    postUpdate();
                } else {
                    postNames();
                }
            }
        },
                'json');
        ((loglevel >= 1) ? console.log(t() + " wait for the face detection to finish, url= " + url + ", wait time=" + ms + " ms") : null);
        await sleep(ms);
    }
}

function sleep(ms) {
    ((loglevel >= 1) ? console.log(t() + " start to sleep for ms = " + ms) : null);
    return new Promise(resolve => setTimeout(resolve, ms));
}

function appendNextPicture() {
    ((loglevel >= 1) ? console.log(t() + " append next picture...") : null);
    if (stopLoadingImages) {
        ((loglevel >= 1) ? console.log(t() + " appending pictures was blocked ") : null);
        return;
    }
    appendPictures();
}

function appendPictures() {
    ((loglevel >= 1) ? console.log(t() + " start to append next picture... pictures processed so far = " + picturesProcessedID.length) : null);
    var k;
    for (k = 0; k < images.length; k++) {
        if (stopLoadingImages) {
            return;
        }
        var img = images[k];
        var id = img.id;
        if (picturesProcessedID.includes(id)) {
            ((loglevel >= 3) ? console.log(t() + " image with id = " + id + " was processed already. Continue...") : null);
            continue;
        }
        if (!img.pass) {
            ((loglevel >= 3) ? console.log(t() + " image with id = " + id + " did not pass the filter. Continue...") : null);
            continue;
        }
        if (loadedCount >= loadedCountMax) {
            ((loglevel >= 1) ? console.log(t() + " loaded " + loadedCount + " images. Scroll down to load more images") : null);
            clearCounterImagesLoading();
            return;

        }
        picturesProcessedID.push(img.id);
        loadedCount = loadedCount + 1;

        setCounterImagesLoading();
        appendPicture(img);
        break;
    }
    if (images.length === k) {
        ((loglevel >= 1) ? console.log(t() + " all images where processed. Reached end of list of images") : null);
        clearCounterImagesLoading();
    }
}

function animate_on() {
    document.getElementById("button_share_box").style.visibility = "visible";
    document.getElementById("button_share_box").disabled = true;
    $('#button_share_box').find('.fa').addClass("fa-spin").addClass("fa-fw");
    ((loglevel >= 1) ? console.log(t() + " animate on") : null);
}

function animate_off() {
    $('#button_share_box').find('.fa').removeClass("fa-spin").removeClass("fa-fw");
    document.getElementById("button_share_box").style.visibility = "hidden";
    $('#face-scroll-top-message').text("");
    $('#face-scroll-top-message').fadeOut();
    ((loglevel >= 1) ? console.log(t() + " animate off") : null);
}

function clearCounterImagesLoading() {
    loadedCount = 0;
    blockScrolledToEnd = false;
    $("#button_share_box_counter_download").html('<sub></sub>');
    ((loglevel >= 1) ? console.log(t() + " clear image counter shown to user") : null);
    animate_off();
}

function setCounterImagesLoading() {
    $("#button_share_box_counter_download").html('<sub>' + picturesProcessedID.length + "/" + images.length + '</sub>');
    $("#button_share_box").css({'color': 'green'});
    ((loglevel >= 1) ? console.log(t() + " set counter for images to load to " + picturesProcessedID.length + "/" + images.length) : null);
}

function clearCounterNamesSending() {
    $("#button_share_box_counter_upload").html('<sup></sup>');
    ((loglevel >= 1) ? console.log(t() + " clear counter of images to load") : null);
    animate_off();
}

function setCounterNamesSending() {
    $("#button_share_box_counter_upload").html('<sup>' + unsentNames.length + '</sup>');
    $("#button_share_box").css({'color': 'red'});
    ((loglevel >= 1) ? console.log(t() + " set counter names to send to " + unsentNames.length) : null);
}

function initDate(fromS, toS) {
    fromS = checkDateString(fromS);
    toS = checkDateString(toS);
    var today = new Date();
    if (fromS == "") {
        from = new Date(new Date().setDate(today.getDate() - 365));
    } else {
        from = new Date(fromS);
    }
    if (toS == "") {
        //        to = new Date(new Date().setDate(today.getDate() + 1));
        to = new Date();
    } else {
        to = new Date(toS);
    }
    document.querySelector("#face-date-from").valueAsDate = from;
    document.querySelector("#face-date-to").valueAsDate = to;
    ((loglevel >= 1) ? console.log(t() + " initial dates: from = " + from + " to = " + to) : null);
}

function checkDateString(s) {
    ((loglevel >= 1) ? console.log(t() + " check date = " + s) : null);
    var i = s.search(/\d\d\d\d-\d\d-\d\d/);
    ((loglevel >= 1) ? console.log(t() + " date check found index = " + i) : null);
    if (i < 0) {
        return "";
    }
    return s;
}

$("#button_faces_zoom_in").click(function () {
    ((loglevel >= 1) ? console.log(t() + " user clicked zoom in") : null);
    if (zoom > 0) {
        zoom -= 1;
    } else {
        return;
    }
    if (zoom < minZoomLoaded) {
        //search();
        minZoomLoaded = zoom;
    } else {
        zoomPictures();
    }
});

var observerEnd = new IntersectionObserver(function (entries) {
    if (entries[0].isIntersecting === true) {
        ((loglevel >= 0) ? console.log(t() + " scrolled to end") : null);
        if (!blockScrolledToEnd) {
            blockScrolledToEnd = true;
            ((loglevel >= 1) ? console.log(t() + " loading more images was NOT blocked") : null);
            appendNextPicture();
        } else {
            ((loglevel >= 1) ? console.log(t() + " loading more images WAS blocked") : null);
        }
    }
}, {threshold: [1]});


$(document).ready(function () {
    document.getElementById("faces_server_status").style.visibility = "hidden";
    loglevel = parseInt($("#faces_log_level").text());
    console.log(t() + " log level = " + loglevel);
    observerEnd.observe(document.querySelector("#face-scoll-end"));
    faceEditControls = $("#template-face-frame-edit-controls").html();
    $("#template-face-frame-edit-controls").remove();
    endPanel = $("#face-scroll-end").html();
    $("#face-scroll-end").remove();
    initDate("", "");
    zoom = parseInt($("#faces_zoom").text());
    ((loglevel >= 1) ? console.log(t() + " zoom = " + zoom) : null);
    channel_name = window.location.pathname.split("/")[2];  // "/faces/nick/"
    //--------------------------------------------------------------------------
    postStart(true);
});


//readFace("GlzqHVvEekONvtM,/home/vm/dev/family-faces/py/delete_me/test/Brigitte_Bardot_1961.jpg,\"[126, 174, 228, 302]\",1,,,,,");
//readFace("GlzqHVvEekONvtM,/home/vm/dev/family-faces/py/delete_me/test/Brigitte_Bardot_1961.jpg,\"[126, 174, 228, 302]\",1,,,Brigitte Bardot,,Brigitte Bardot");
//images.forEach(showImage);