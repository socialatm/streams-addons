function t() {
    var now = new Date();
    var dateString = now.toISOString();
    return dateString;
}

var minZoomLoaded = 3;
var zoom = 3;
var faceEditControls = "";

//https://www.w3schools.com/jsref/prop_element_offsettop.asp

function editName(faceFrame) {
    hideEditFrame();

    var name_id = $(faceFrame).attr("name_id");
    ((loglevel >= 0) ? console.log(t() + " user starts do edit name id =" + name_id + " Checking permissions...") : null);
    var perm = canWriteName(name_id);
    if (!isSearchMe) {
        if (perm) {
            ((loglevel >= 0) ? console.log(t() + " Yes, write permission to edit this name.. This IF helps the owner of the name to see and change the name here without having the permission granted to write the faces of the channel owner (owner of the images itself)") : null);
        } else if (can_write) {
            ((loglevel >= 0) ? console.log(t() + " Edit anyway. Why? This can happen, if the owner of the name has withdrawn the permissions.") : null);
        } else {
            ((loglevel >= 0) ? console.log(t() + " Neither permission to edit faces on this channel, nor to edit this face particulary") : null);
            return;
        }
    }

    ((loglevel >= 2) ? console.log(t() + " figure out where to position the input field and buttons") : null);

    var id = $(faceFrame).attr("id");

    var r = getCssValueAsNumber($(faceFrame).css("right"));
    var l = getCssValueAsNumber($(faceFrame).css("left"));

    var par = $(faceFrame).parent();
    var img = par.find('img:first');
    var imgId = img.attr("id");

    var offsetTop = faceFrame.offsetTop;
    var faceFrameId = "face-frame-" + imgId // example: face-frame-img-1 ... is create dynamically while appending pictures
    var faceFrameEdit = document.getElementById(faceFrameId);
    faceFrameEdit.innerHTML = faceEditControls;
    var options = document.getElementById("face-name-list-search").innerHTML;
    document.getElementById("face-name-list-set").innerHTML = options;

    faceFrameEdit.style.top = offsetTop + "px";
    if (l > r) {
        faceFrameEdit.style.right = r + 5 + "px";
        faceFrameEdit.style.left = "";
    } else {
        faceFrameEdit.style.left = l + 5 + "px";
        faceFrameEdit.style.right = "";
    }
//    var name_shown = faceFrame.getElementsByClassName("face-name-shown")[0].innerHTML;
    var name_shown = faceFrame.getElementsByClassName("face-name-shown")[0].getAttribute('nameandowner');
    document.getElementById("input-face-name").value = name_shown;
    document.getElementById("input-face-name").setAttribute("face_id", id); // to read out the face id if the user edits the name
    document.getElementById("input-face-name").focus();

    if (isSearchMe) {
        $('#face-edit-set-name').hide();
        $('#face-edit-set-unknown').hide();
        document.getElementById("input-face-name").disabled = true;
    }

    ((loglevel >= 0) ? console.log(t() + " edit frame  was opened and shows name = " + name_shown) : null);
}

function canWriteName(nameID) {
    var i;
    for (i = 0; i < receivedNames.length; i++) {
        var name_id = receivedNames[i].id;
        if (name_id == nameID) {
            if (receivedNames[i].w == 1) {
                ((loglevel >= 1) ? console.log(t() + " yes, has permission to write name id = " + nameID) : null);
                return true;
            }
        }
    }
    ((loglevel >= 1) ? console.log(t() + " no permission to write name id = " + nameID) : null);
    return false;
}

function getIdForNameAndOwner(nameandowner) {
    var i;
    for (i = 0; i < receivedNames.length; i++) {
        var name = receivedNames[i].nameAndOwner;
        if (name === nameandowner) {  // double check just in case
            ((loglevel >= 2) ? console.log(t() + " found id = " + receivedNames[i].id + " for = " + nameandowner) : null);
            return receivedNames[i].id;
        }
    }
    ((loglevel >= 1) ? console.log(t() + " found not id for = " + nameandowner) : null);
    return 0;
}

function appendNames() {
    ((loglevel >= 1) ? console.log(t() + " start to append received name to the name list (as option value)") : null);
    var i;
    for (i = 0; i < receivedNames.length; i++) {
        var id = receivedNames[i].id;
        var name = receivedNames[i].name;
        receivedNames[i].nameAndOwner = receivedNames[i].name + " (" + receivedNames[i].channel_address + ")";
        name = name.trim();
        if (!isIdInList(id, "face-name-list-search")) {  // double check just in case
            $(".face-name-list").append("<option value=\"" + receivedNames[i].nameAndOwner + "\" xchan_hash=\"" + receivedNames[i].xchan_hash + "\" nameid=\"" + id + "\" channel_id=\"" + receivedNames[i].channel_id + "\" channel_address=\"" + receivedNames[i].channel_address + "\">" + receivedNames[i].nameAndOwner + "</option>");
            ((loglevel >= 0) ? console.log(t() + " append name = " + receivedNames[i].nameAndOwner + " with channel id = " + receivedNames[i].channel_id + " and name id = " + id) : null);
        }
    }
}

function clearNameList() {
    $(".face-name-list").empty();
    receivedNames = [];
    ((loglevel >= 0) ? console.log(t() + " cleared name list") : null);
}

function appendName(name) {
    ((loglevel >= 1) ? console.log(t() + " append single name to list (as option value)") : null);
    if (!name) {
        ((loglevel >= 1) ? console.log(t() + " name is null") : null);
        return;
    }
    // append in ui
    if (!isIdInList(name.id, "face-name-list-search")) {
        name.nameAndOwner = name.name + " (" + name.channel_address + ")";
        $(".face-name-list").append("<option value=\"" + name.nameAndOwner + "\" xchan_hash=\"" + name.xchan_hash + "\" nameid=\"" + name.id + "\" channel_id=\"" + name.channel_id + "\" channel_address=\"" + name.channel_address + "\">" + name.nameAndOwner + "</option>");
        ((loglevel >= 0) ? console.log(t() + " append name = " + name.nameAndOwner + " with channel id = " + name.channel_id + " and name id = " + name.id) : null);
    }
    // append to list received names
    var i;
    for (i = 0; i < receivedNames.length; i++) {
        var existing_id = receivedNames[i].id;
        if (existing_id == name.id) {
            ((loglevel >= 1) ? console.log(t() + " name does exist already in array or received names") : null);
            return;
        }
    }
    ((loglevel >= 1) ? console.log(t() + " appending name to array or received name as well") : null);
    receivedNames.push(name);
}

function isIdInList(id, selector) {
    ((loglevel >= 1) ? console.log(t() + " is name id = " + id + " in list with selector = " + selector + " ?") : null);
    var o = document.getElementById(selector);
    var i;
    for (i = 0; i < o.options.length; i++) {
        var listID = o.options[i].getAttribute("nameid");
        if (listID == id) {
            ((loglevel >= 1) ? console.log(t() + " yes") : null);
            return true;
        }
    }
    ((loglevel >= 1) ? console.log(t() + " no") : null);
    return false;
}

function isNameInList(name, selector) {
    ((loglevel >= 1) ? console.log(t() + " is name = " + name + " in list with selector = " + selector + " ?") : null);
    var o = document.getElementById(selector);
    var i;
    for (i = 0; i < o.options.length; i++) {
        var text = o.options[i].text;
        if (text == name) {
            ((loglevel >= 1) ? console.log(t() + " yes") : null);
            return true;
        }
    }
    ((loglevel >= 1) ? console.log(t() + " no") : null);
    return false;
}

function setName() {
    var face_id_full = document.getElementById("input-face-name").getAttribute("face_id");
    var name_shown = document.getElementById(face_id_full).getElementsByClassName("face-name-shown")[0].getAttribute('nameAndOwner');
    var name = document.getElementById("input-face-name").value.trim();
    ((loglevel >= 0) ? console.log(t() + " start to set new face name new = " + name + " was name = " + name_shown + " with face id=" + face_id_full) : null);
    if (name === name_shown) {
        ((loglevel >= 0) ? console.log(t() + " name did not change") : null);
        var verified_id = document.getElementById(face_id_full).getAttribute("person_verified_id");
        if (verified_id != "0") {
            ((loglevel >= 0) ? console.log(t() + " Do nothing, was verified already") : null);
            hideEditFrame();
            return;
        }
    }
    if (name == "") {
        ((loglevel >= 0) ? console.log(t() + " Input field for name was emtpy. This will set all name date for a face encoding to the defaults: verified, guessed, unknowns, ignore. Why? This will give the face recognition the chance to guess the face again, despite it was set to verified or unknown before.") : null);
        name = -3;
    } else {
        if (!isNameInList(name, "face-name-list-search")) {
            ((loglevel >= 0) ? console.log(t() + " The new name = " + name + " will be appened to the name list (as option value) as soon as it was successfully sent to the server.") : null);
//        $(".face-name-list").append("<option value=\"" + name + "\" nameid=\"" + -1 + "\">" + name + "</option>");
        }
        ((loglevel >= 1) ? console.log(t() + " The style of the frame will be changed after it was successfully sent to the server.") : null);//    document.getElementById(face_id_full).style.border = "medium solid green";
    }
    preparePostName(face_id_full, name);
}

function setNameUnkown() {
    var face_id_full = document.getElementById("input-face-name").getAttribute("face_id");
    ((loglevel >= 0) ? console.log(t() + " set face name > unknown - face id=" + face_id_full) : null);
    ((loglevel >= 1) ? console.log(t() + " The style of the frame will be changed after it was successfully sent to the server.") : null);
    preparePostName(face_id_full, -1);
}

function setNameIgnore() {
    var face_id_full = document.getElementById("input-face-name").getAttribute("face_id");
    ((loglevel >= 0) ? console.log(t() + "set face name > ignore - face id=" + face_id_full) : null);
    ((loglevel >= 1) ? console.log(t() + " The style of the frame will be changed after it was successfully sent to the server.") : null);
    preparePostName(face_id_full, -2);
}
function preparePostName(face_id_full, name) {
    ((loglevel >= 0) ? console.log(t() + " start to prepare name  to send it to the server, face id = " + face_id_full) : null);
    var encoding_id = face_id_full.split("-")[1];
    var marked_ignore = 0;
    var person_marked_unknown = 0;
    var person_verified = 0;
    var new_name = "";
    var xchan_hash = "";
    if (name === -2) {
        marked_ignore = 1;
        name = "";
    } else if (name === -1) {
        person_marked_unknown = 1;
        name = "";
    } else if (name === -3) {
        // let the face recognition try to guess
        person_marked_unknown = 0;
        // let the face recognition try to guess (after the next http get it will be to late for the user to set "ignore" back to "0")
        marked_ignore = 0;
        name = "";
    } else {
        var i;
        for (i = 0; i < receivedNames.length; i++) {
            var id = receivedNames[i].id;
            var listName = receivedNames[i].nameAndOwner;
            if (name === listName) {
                person_verified = id;
                xchan_hash = receivedNames[i].xchan_hash
                break;
            }
        }
        if (person_verified === 0) {
            new_name = name;
        }
    }
    var name_shown = document.getElementById(face_id_full).getElementsByClassName("face-name-shown")[0].getAttribute('nameAndOwner');
    var name_id_old = getIdForNameAndOwner(name_shown);
    unsentNames.push({
        "id": encoding_id, // the encoding id will be send as "id" as response from server
        "encoding_id": encoding_id,
        "person_verified": person_verified,
        "person_marked_unknown": person_marked_unknown,
        "marked_ignore": marked_ignore,
        "new_name": new_name,
        "name": name,
        "name_id_old": name_id_old,
        "xchan_hash": xchan_hash,
    })
    hideEditFrame();
    postNames();
}

var unsentNames = [];

function removeNameFromUnsentList(faces) {
    var hasRemoved = false;
    var k;
    for (k = 0; k < faces.length; k++) {
        var face = faces[k];
        var i;
        for (i = 0; i < unsentNames.length; i++) {
            var id = unsentNames[i].id;
            if (face.id === id) {
                unsentNames.remove(unsentNames[i]);
                ((loglevel >= 0) ? console.log(t() + " removed face with id = " + id + " from list of unsent faces") : null);
                hasRemoved = true;
                break;
            }
        }
    }
    ((loglevel >= 0) ? console.log(t() + " Success yes/no for: face was removed from list of unsent faces : " + hasRemoved) : null);
}

function postNames() {
    ((loglevel >= 0) ? console.log(t() + " post the next name in the list of unsent faces ") : null);
    if (unsentNames.length === 0) {
        ((loglevel >= 0) ? console.log(t() + " no name left to send ") : null);
        clearCounterNamesSending();
//        postSearch();
        return;
    }
    var name = unsentNames[0];
    var nameString = JSON.stringify(name);
    ((loglevel >= 0) ? console.log(t() + " About to send the first name in the list of unsent name to the server. Post value is : " + nameString) : null);
    animate_on();
    setCounterNamesSending();
    var postURL = getURL() + "/name";
    ((loglevel >= 0) ? console.log(t() + " url =  " + postURL) : null);
    $.post(postURL, {name: nameString}, function (data) {
        ((loglevel >= 0) ? console.log(t() + " Received response from server after posting a name") : null);
        if (isSearchMe) {
            ((loglevel >= 0) ? console.log(t() + "The tagged user deleted one of his face encodings. Reloading the whole page so the user can check it...") : null);
            window.location.href = getURL();
        }
        removeNameFromUnsentList(data['encodings']);
        if (data['status']) {
            ((loglevel >= 0) ? console.log(t() + " Server response to sending the name was without errors") : null);
            appendName(data['name']);
            // this will update the names and frames for the one single face that was sent and received again
            var faces = data['encodings'];
            updateFaces(faces);
            postNames();
        } else {
            ((loglevel >= 0) ? console.log(t() + " Error sending name. Server responded with: " + data['errormsg']) : null);
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

function addSearchName() {
    ((loglevel >= 1) ? console.log(t() + " selected a search name") : null);
    var name = document.getElementById("input-search-name").value.trim();
    if (name == "") {
        ((loglevel >= 1) ? console.log(t() + " name was null") : null);
        return;
    }
    if (!isNameInList(name, "face-name-list-search")) {
        ((loglevel >= 0) ? console.log(t() + " you can not search for a name that is not known") : null);
        return;
    }
    ((loglevel >= 0) ? console.log(t() + " search name = " + name) : null);
    var names = document.querySelectorAll('[searchnameid]');
    var i;
    for (i = 0; i < names.length; i++) {
        var text = names[i].innerText.trim();
        if (text == name) {
            ((loglevel >= 0) ? console.log(t() + " seach name already selected " + name) : null);
            return;
        }
    }
//    if (checked = document.getElementById('face-search-and').checked) {
//    }
    var id = getIdForNameAndOwner(name);
    if (id == 0) {
        ((loglevel >= 0) ? console.log(t() + " can not search for a name with an unknown id ") : null);
        return;
    }
    $("#face-active-filter-names").append("<button class=\"btn btn-face-search-name\" searchnameid=\"" + id + "\">" + name + " <i class=\"fa fa-remove fa-lg\"></i></button>");
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

var blockAppending = false;
var filterStringServer = "";
var filterNames = [];
var oldestImageLoadedId = "";
var mostRecentImageLoadedId = "";
function search() {
    oldestImageLoadedId = "";
    mostRecentImageLoadedId = "";
    isAppendingToTop = false;
    createFilterString();
    // stop loading images
    ((loglevel >= 0) ? console.log(t() + " Clear some arrays received from server (images, encodings, names) to stop the loading of images ") : null);
    blockAppending = true;
    receivedImages = [];
    receivedFaceEncodings = [];
    receivedNames = [];
    picturesProcessed = [];
    ((loglevel >= 1) ? console.log(t() + " Remove pictures ") : null);
    $("#face-panel-pictures").empty();

    // send search filter to server
    postSearch();
}

function createFilterString() {
    $("#input-search-name").val("");

    filterNames = [];
    ((loglevel >= 1) ? console.log(t() + " look for search buttons containing the search names ") : null);
    var buttons = document.getElementsByClassName("btn-face-search-name");
    var i;
    for (i = 0; i < buttons.length; i++) {
        var id = buttons[i].getAttribute("searchnameid");
        var xchan_hash = geXChanForID(id);
        filterNames.push({"id": id});
//        filterNames.push({"xchan_hash": xchan_hash});
        ((loglevel >= 1) ? console.log(t() + " append search name with id = " + id + " and xchan_hash=" + xchan_hash) : null);
    }

    toogleAndCheckbox();
    var checked = $("#face-search-and")[0].checked;
    ((loglevel >= 1) ? console.log(t() + " AND = " + checked) : null);

    var from = $("#face-date-from").val() + " 00:00:00";
    var to = $("#face-date-to").val() + " 23:59:59";
    ((loglevel >= 1) ? console.log(t() + " from = " + from) : null);
    ((loglevel >= 1) ? console.log(t() + " to = " + to) : null);

    var filter = {"from": from, "to": to, "names": filterNames, "and": checked};

    if (isAppendingToTop) {
        filter.mostRecentImageLoadedId = mostRecentImageLoadedId;
    } else {
        filter.oldestImageLoadedId = oldestImageLoadedId;
    }

    filterStringServer = JSON.stringify(filter);
    ((loglevel >= 0) ? console.log(t() + " filter (search) for server is = " + filterStringServer) : null);
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
    ((loglevel >= 1) ? console.log(t() + " get CSS value = " + s + " as number") : null);
    var i = 0;
    if (isNaN(s)) {
        if (s.indexOf(".") >= 0) {
            s = s.substring(0, s.indexOf("."));
        }
        if (s.indexOf("px") >= 0) {
            s = s.substring(0, s.indexOf("px"));
        }
        i = parseInt(s);
        ((loglevel >= 1) ? console.log(t() + " return CSS value = " + s + " as number = " + i) : null);
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
        search();
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
//    if (containers.length === 1) {
//        return;
//    }
    if (containers.length < zoom) {
        $(".img-container-zoomable").css("width", (100 / zoom).toString() + "%");
        return;
    }
    var zoomedImages = [];
    var h;
    var i;
    for (i = 0; i < containers.length; i++) {
        var img_container = containers[i];
        if (i === 0) {
            h = img_container.clientHeight;
        } else {
            var img = img_container.getElementsByTagName("img")[0];
            var img_natural_px_height = img.naturalHeight;
            var img_natural_px_width = img.naturalWidth;
            var w_for_same_height = h * img_natural_px_width / img_natural_px_height;
            img_container.style.width = w_for_same_height + "px";
            var buffer = 10;
            var w_all = w_for_same_height + w + buffer;
            var factor = w / w_all;
            var j;
            for (j = 0; j <= i; j++) {
                var h_pix = containers[j].clientHeight;
                var w_pix = containers[j].clientWidth;
                var w_percent_IS = w_pix * 100 / w;
                var w_percent = factor * w_percent_IS;
//                containers[j].style.width = Math.floor(w_percent) + "%"; // This does not look well. Take 20 px buffer instead, see above
                containers[j].style.width = w_percent + "%";
                zoomedImages[j] = containers[j];
            }
            h = img_container.clientHeight;
        }
        if (i === zoom - 1) {
            var k;
            for (k = 0; k < zoomedImages.length; k++) {
                zoomedImages[k].classList.remove("img-container-zoomable");
            }
            var id = img_container.id.split("-")[2];
            id = img_container.id;
            $("<div class=\"img-container face-img-spacer\"  style=\"min-width: 100%; min-height: 1px;\"> </div>").insertAfter("#" + id);
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
    ((loglevel >= 0) ? console.log(t() + " start to show image to user, image = " + JSON.stringify(Object.assign({}, img))) : null);
    var html = "";
    var faces = img.encodings;
    var i;
    for (i = 0; i < faces.length; i++) {
        ((loglevel >= 0) ? console.log(t() + " append face number = " + i + " in image") : null);
        // row from db table face_encodings (joined with attach.hash to complete the URL)
        var face = faces[i];
        if (i === 0) {
            var url = window.location.href;
            url = url.substring(0, url.indexOf("/faces"));
            var src = img.src; // hash of the picture from db table attach
            // background on hubzilla/zap: every picture was resized when uploaded
            if (zoom > 0 && zoom < 4) {
                src += "-" + zoom;

            } else if (zoom > 3) {
                src += "-3"
            }
            var url = url + "/photo/" + src;
            var width = (100 / zoom).toString() + "%";
            html += "<div class=\"img-container img-container-zoomable\" id=\"img-container-" + img.id + "\" width=\"" + width + "\">";
            html += "   <div class=\"face-container\">";
            html += "       <img src=\"" + url + "\" id=\"img-" + img.id + "\" class=\"img-face\" onload=\"setFrameSizes(this)\" onerror=\"showDeleteButtonForTaggedContact(" + face.id + ")\" onclick=\"hideEditFrame(false)\" ondblclick=\"openSingleImage(this)\">";
        }
        ((loglevel >= 0) ? console.log(t() + " adding face number = " + i + " with face id=" + face.id + " to image with id=" + img.id) : null);
        var top = 1;
        var left = 1;
        var bottom = 98;
        var right = 98;
        html += "           <div class=\"face-frame face-frame-name\" id=\"face-" + face.id + "\" finder=\"" + face.f + "\" style=\"top: " + top + "%;left: " + left + "%; bottom: " + bottom + "%; right: " + right + "%;\" onclick=\"editName(this)\">";
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
        ((loglevel >= 0) ? console.log(t() + " most recent image id: " + mostRecentImageLoadedId) : null);
    }
    oldestImageLoadedId = img.id;
    ((loglevel >= 0) ? console.log(t() + " oldest image id: " + oldestImageLoadedId) : null);

    if (blockAppending) {
        // just in case a seach was started meanwhile
        ((loglevel >= 0) ? console.log(t() + " was interrupted to show image to user. A new server request was started.") : null);
        return;
    }
    if (isAppendingToTop) {
        $("#face-panel-pictures").prepend(html);
    } else {
        $("#face-panel-pictures").append(html);
    }
}

function getImageForId(id) {
    var i;
    for (i = 0; i < receivedImages.length; i++) {
        if (id == receivedImages[i].id) {
            ((loglevel >= 1) ? console.log(t() + " returning image for id = " + id + " found in the list of received images") : null);
            return receivedImages[i];
        }
    }
    ((loglevel >= 1) ? console.log(t() + " found no image for id = " + id + " in the list of received images") : null);
}

function showDeleteButtonForTaggedContact(face_id) {
    $("#face-panel-pictures").append("No permission to view this image. Use the button to remove your tag. <button class=\"btn btn-face-delete-me\" id=\"deleteme-" + face_id + "\" onclick=\"postDeleteMyEncoding(" + face_id + ")\">Remove Me <i class=\"fa fa-remove fa-lg\"></i></button>");
    ((loglevel >= 1) ? console.log(t() + " created button to delete the tag from image, encoding_id =  " + face_id) : null);
    appendNextPicture();
}

function postDeleteMyEncoding(face_id) {
    var postURL = getURL();
    ((loglevel >= 0) ? console.log(t() + " url =  " + postURL) : null);
    $.post(postURL, {delete_encoding_id: face_id}, function (data) {
        ((loglevel >= 0) ? console.log(t() + " Received response from server to delete an enconding (for a tagged contact without the permission to view the image)") : null);
        if (data['status']) {
            ((loglevel >= 0) ? console.log(t() + "The tagged user deleted one of his face encodings. Reloading the whole page so the user can check it...") : null);
            window.location.href = getURL();
        } else {
            ((loglevel >= 0) ? console.log(t() + " Error deleting a face encoding. Server responded with: " + data['errormsg']) : null);
        }
        animate_off();
    },
            'json');
}

function setFrameSizes(img) {
    ((loglevel >= 1) ? console.log(t() + " start to set frame size") : null);
    var px_width = img.naturalWidth;
    var px_height = img.naturalHeight;
    ((loglevel >= 1) ? console.log(t() + " img h=" + px_height + "px, w=" + px_width + "px, id=" + img.id) : null);
    var splittees = img.id.split("-");
    var img_id = splittees[1];
    var image = getImageForId(img_id);
    var faces = image.encodings;
    var i;
    for (i = 0; i < faces.length; i++) {
        var face = faces[i];
        var faceLocation = face.l;
        var locArray = faceLocation.split(",");
        // face_recogntion (finder2): left, right, top, bottom coordinates of the face (in persent)
        var nameFrame = document.getElementById("face-" + face.id);
        ((loglevel >= 0) ? console.log(t() + " face id = " + face.id) : null);
        nameFrame.style.top = locArray[2] + "%";
        nameFrame.style.left = locArray[0] + "%";
        nameFrame.style.bottom = locArray[3] + "%";
        nameFrame.style.right = locArray[1] + "%";
        styleFaceFrame(face);
    }
    ((loglevel >= 1) ? console.log(t() + " finished to set frame size") : null);
    if (isAppendingToTop) {
        zoomPictures();
    } else {
        zoomLastPictures(img);
    }
    appendNextPicture();
}

function styleFaceFrame(face) {

    // person_verified = pv
    // person_recognized = pr
    // person_unknown = pu

    ((loglevel >= 0) ? console.log(t() + " face = " + JSON.stringify(Object.assign({}, face))) : null);
    var nameFrame = document.getElementById("face-" + face.id);
    if (!nameFrame) {
        // no image yet to update (happens after http get = loading the page)
        ((loglevel >= 1) ? console.log(t() + " no image yet to update (happens after http get = loading the page") : null);
        return;
    }
    var name = "";
    var name_id = "0";
    if (face.pv != 0) {
        // prio 1 because the user said this is person XY
        if (isSearchMe) {
            nameFrame.style.border = "medium dashed red";
        } else {
            nameFrame.style.border = "rgba(255,255,255,.5)";
        }
        name = getNameForID(face.pv);
        name_id = face.pv;
        ((loglevel >= 1) ? console.log(t() + " verified name id = " + name_id) : null);
    } else if (face.pu == 1) {
        // prio 2 because the user said this a person I can't remember the name at the moment or that completly unknown
        nameFrame.style.border = "thin dotted black";
        ((loglevel >= 1) ? console.log(t() + " unknown name") : null);
    } else if (face.pi == 1) {
        // prio 3 because the user want to ignore this face. 
        // The frame is invisible but the user can click on it to change the name.
        // After the next "http get" this will vanish from the ui completely
        nameFrame.style.border = "rgba(255,255,255,.5)";
        ((loglevel >= 1) ? console.log(t() + " ignored name") : null);
    } else if (face.pr != 0) {
        // prio 4 because this is guessed by the face recognition
        nameFrame.style.border = "medium dashed red";
        name = getNameForID(face.pr);
        if (name === "") {
            nameFrame.style.border = "medium dotted red";
        }
        name_id = face.pr;
        ((loglevel >= 1) ? console.log(t() + " recognized name id = " + name_id + " (guessed by the face recognition)") : null);
    } else {
        // prio 5 because this is the default if nothing is known about this face
        nameFrame.style.border = "medium dotted red";
        ((loglevel >= 1) ? console.log(t() + " nothing known about this and therefor styled as dotted red") : null);
    }
    nameFrame.getElementsByClassName("face-name-shown")[0].innerHTML = name;
    var nameAndOwner = getNameAndOwnerForID(name_id);
    nameFrame.getElementsByClassName("face-name-shown")[0].setAttribute("nameAndOwner", nameAndOwner);
    ((loglevel >= 0) ? console.log(t() + " frame shows name = " + nameAndOwner) : null);
    nameFrame.setAttribute("name_id", name_id);
    // used to decide later if to post the name to the server or not
    nameFrame.setAttribute("person_verified_id", face.pv); // this one is important
    nameFrame.setAttribute("person_recognized_id", face.pr); // could be used for some statistics 
    nameFrame.setAttribute("person_not_known", face.pu); // could be used for some statistics 
}

function styleAllAgain() {
    ((loglevel >= 0) ? console.log(t() + " start - style all again") : null);
    var k;
    for (k = 0; k < receivedFaceEncodings.length; k++) {
        styleFaceFrame(receivedFaceEncodings[k]);
    }
    ((loglevel >= 1) ? console.log(t() + " finished - style all again") : null);
}

function getNameForID(id) {
    var k;
    for (k = 0; k < receivedNames.length; k++) {
        var list_id = receivedNames[k].id;
        if (id == list_id) {
            ((loglevel >= 1) ? console.log(t() + " return name = " + receivedNames[k].name + " for id = " + id) : null);
            return receivedNames[k].name;
        }
    }
    // Sometimes persons are deleted from the list of known persons but the
    // still exist in encodings. The next run of the face recognition would fix this.
    ((loglevel >= 0) ? console.log(t() + " found no name for id = " + id + ". Why: Sometimes persons are deleted from the list of known persons but still exist in encodings. The next run of the face recognition will fix this.") : null);
    return "";
}

function geXChanForID(id) {
    var k;
    for (k = 0; k < receivedNames.length; k++) {
        var list_id = receivedNames[k].id;
        if (id == list_id) {
            ((loglevel >= 1) ? console.log(t() + " return xchan_hash = " + receivedNames[k].xchan_hash + " for id = " + id) : null);
            return receivedNames[k].xchan_hash;
        }
    }
    // Sometimes persons are deleted from the list of known persons but the
    // still exist in encodings. The next run of the face recognition would fix this.
    ((loglevel >= 0) ? console.log(t() + " found no xchan_hash for id = " + id + ". Why: Sometimes persons are deleted from the list of known persons but still exist in encodings. The next run of the face recognition will fix this.") : null);
    return "";
}

function getNameAndOwnerForID(id) {
    var k;
    for (k = 0; k < receivedNames.length; k++) {
        var list_id = receivedNames[k].id;
        if (id == list_id) {
            ((loglevel >= 1) ? console.log(t() + " return name-and-owner = " + receivedNames[k].name + " for id = " + id) : null);
            return receivedNames[k].nameAndOwner;
        }
    }
    // Sometimes persons are deletet from the list of known persons but the
    // still exist in encodings. The next run of the face recognition would fix this.
    ((loglevel >= 0) ? console.log(t() + " found no name-and-owner for id = " + id + ". Why: Sometimes persons are deleted from the list of known persons but still exist in encodings. The next run of the face recognition will fix this.") : null);
    return "";
}

function getIdForName(name) {
    var i;
    for (i = 0; i < receivedNames.length; i++) {
        var text = receivedNames[i].name;
        if (text == name) {
            ((loglevel >= 1) ? console.log(t() + " return id = " + receivedNames[i].id + " for name = " + name) : null);
            return receivedNames[i].id;
        }
    }
    ((loglevel >= 1) ? console.log(t() + " found no id for name = " + name) : null);
    return "0";
}

function sleep(ms) {
    ((loglevel >= 1) ? console.log(t() + " start to sleep for ms = " + ms) : null);
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function appendNextPicture() {
    ((loglevel >= 0) ? console.log(t() + " 'Sleep 0.5 seconds befor loading next picture...") : null);
    await sleep(200);
    ((loglevel >= 1) ? console.log(t() + " woke up from sleeping to appending the next picture ") : null);
    if (blockAppending) {
        // just in case a seach was started meanwhile
        ((loglevel >= 0) ? console.log(t() + " woke up from sleeping but was told to stopp appending the next picture ") : null);
        return;
    }
    appendPictures();
}

var can_write = false;
var receivedImages;
var receivedFaceEncodings;
var receivedNames;
var picturesProcessed = [];
var loglevel = -1; // will be set by server

function appendPictures() {
    ((loglevel >= 0) ? console.log(t() + " start to append next picture... pictures processed so far = " + picturesProcessed.length) : null);
    setCounterImagesLoading();
    html = "";
    var k;
    for (k = 0; k < receivedImages.length; k++) {
        var img = receivedImages[k];
        var id = img.id;
        if (picturesProcessed.includes(id)) {
            ((loglevel >= 1) ? console.log(t() + " image with id = " + id + " was processed already. Continue...") : null);
            continue;
        }
        picturesProcessed.push(img.id);
        appendPicture(img);
        break;
    }
    if (receivedImages.length === k) {
        ((loglevel >= 0) ? console.log(t() + " all images where processed. Reached end of list of images") : null);
        clearCounterImagesLoading(true);
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
    $('#face-scoll-top-message').text("");
    $('#face-scoll-top-message').fadeOut();
    ((loglevel >= 1) ? console.log(t() + " animate off") : null);
}

function clearCounterImagesLoading(isLoadAgain) {
    $("#button_share_box_counter_download").html('<sub></sub>');
    ((loglevel >= 1) ? console.log(t() + " clear image counter shown to user") : null);
    animate_off();
    var shouldLoadMore = isEndVisible();
    ((loglevel >= 0) ? console.log(t() + " after loading images > is end visible= " + shouldLoadMore) : null);
    if (shouldLoadMore && isLoadAgain) {
        loadMoreImages();
    } else {
        blockSearch = false;
    }
}

function setCounterImagesLoading() {
    $("#button_share_box_counter_download").html('<sub>' + picturesProcessed.length + "/" + receivedImages.length + '</sub>');
    $("#button_share_box").css({'color': 'green'});
    ((loglevel >= 1) ? console.log(t() + " set counter for images to load to " + picturesProcessed.length + "/" + receivedImages.length) : null);
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

function startFaceDetection() {
    var postURL = window.location.href + "/start";
    ((loglevel >= 0) ? console.log(t() + " about to post the request to start the face detection and recognition: url = " + postURL) : null);
    $.post(postURL, {something: ""}, function (data) {
        if (data['status']) {
            ((loglevel >= 0) ? console.log(t() + " Server response: Successfully started face detection and recognition.") : null);
        } else {
            ((loglevel >= 0) ? console.log(t() + " Server did send and error for starting face detection: " + data['errormsg']) : null);
        }
    },
            'json');

}


function postSearch() {
    blockSearch = true;
    animate_on();
    var postURL = getURL();
    if (!isSearchMe) {
        postURL = getURL() + "/search";
    }
    ((loglevel >= 0) ? console.log(t() + " about to post a search to the server: url = " + postURL + " , filter = " + filterStringServer) : null);
    $.post(postURL, {filter: filterStringServer}, function (data) {
        blockAppending = false;
        if (data['status']) {
            ((loglevel >= 0) ? console.log(t() + " Successfully received pictures from server.") : null);
            if (data['images'].length < 1) {
                ((loglevel >= 0) ? console.log(t() + " But no results where received from server.") : null);
                clearCounterImagesLoading(false);
                return;
            }
            receivedImages = data['images'];
            if (isAppendingToTop) {
                receivedImages = receivedImages.reverse();
            }
            ((loglevel >= 2) ? console.log(t() + " received images = " + JSON.stringify(Object.assign({}, receivedImages))) : null);
//            receivedFaceEncodings = [];
            var i;
            for (i = 0; i < receivedImages.length; i++) {
                var encs = receivedImages[i].encodings;
                var k;
                for (k = 0; k < encs.length; k++) {
                    receivedFaceEncodings.push(encs[k]);
                    ((loglevel >= 0) ? console.log(t() + " received face encoding = " + JSON.stringify(Object.assign({}, encs[k]))) : null);
                }
            }
            clearNameList();
            receivedNames = data['names'];
            if (!receivedNames) {
                ((loglevel >= 0) ? console.log(t() + " received names = " + JSON.stringify(Object.assign({}, receivedNames))) : null);
            }
            appendNames();
            updateFaces(receivedFaceEncodings); // updates all name frames in ui
            appendPictures();
        } else {
            ((loglevel >= 0) ? console.log(t() + " Server did send and error for searching pictures: " + data['errormsg']) : null);
            animate_off();
        }
        ((loglevel >= 0) ? console.log(t() + " finished processing server response for a search - leaving function") : null);
    },
            'json');
}

function getURL() {
    var postURL = window.location.href;
    var url = window.location.href;
    if (url.indexOf("?") > 1) {
        var splittees = url.split("?");
        postURL = splittees[0];
    }
    return postURL;
}

$('#aclModal').on('hidden.bs.modal', function (e) {
    ((loglevel >= 0) ? console.log(t() + " ACL modal dialog was closed by user") : null);
    var acls = $('.acl-field');
    var groupAllow = [];
    var groupDeny = [];
    var contactAllow = [];
    var contactDeny = [];
    var k;
    for (k = 0; k < acls.length; k++) {
        var name = acls[k].getAttribute("name");
        var value = acls[k].value;
        if (name == "contact_allow[]") {
            contactAllow.push(value);
        } else if (name == "group_allow[]") {
            groupAllow.push(value);
        } else if (name == "contact_deny[]") {
            contactDeny.push(value);
        } else if (name == "group_deny[]") {
            groupDeny.push(value);
        }
    }
    var acl_to_send = {
        contact_allow: contactAllow,
        group_allow: groupAllow,
        contact_deny: contactDeny,
        groupDeny: groupDeny};
    acl_to_send_json = JSON.stringify(acl_to_send);
    sendACLs(acl_to_send_json);
});

function sendACLs(acl_string) {
    var postURL = getURL() + "/permissions";
    ((loglevel >= 0) ? console.log(t() + " Sending request to get ACL: url = " + postURL + " , acl = " + acl_string) : null);
    animate_on();
    $("#jot-perms-icon").html('<sup>!</sup>');
    $.post(postURL, {acl: acl_string}, function (data) {
        if (data['status']) {
            $("#jot-perms-icon").html('<sup></sup>');
            $("#jot-perms-icon").css({'color': ''});
            ((loglevel >= 0) ? console.log(t() + " Successfully sent ACLs to server.") : null);
        } else {
            $("#jot-perms-icon").html('<sup>!</sup>');
            $("#jot-perms-icon").css({'color': 'red'});
            ((loglevel >= 0) ? console.log(t() + " Error sending ACLs: " + data['errormsg']) : null);
        }
        animate_off();
    });
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

    var rDate = [year, month, day].join('-');
    ((loglevel >= 2) ? console.log(t() + " formated date = " + rDate) : null);
    return rDate;
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
    ((loglevel >= 0) ? console.log(t() + " check date = " + s) : null);
    var i = s.search(/\d\d\d\d-\d\d-\d\d/);
    ((loglevel >= 0) ? console.log(t() + " date check found index = " + i) : null);
    if (i < 0) {
        return "";
    }
    return s;
}

function isModeSearchMe() {
    var url = getURL();
    if (url.toLocaleLowerCase().endsWith("/searchme")) {
        $('#attach_edit_form_acl').hide();
        $('#button-faces-filter').hide();
        return true;
    }
    return false;
}

var isSearchMe = false;

var blockSearch = true;
var observerEnd = new IntersectionObserver(function (entries) {
    if (entries[0].isIntersecting === true) {
        ((loglevel >= 0) ? console.log(t() + " scrolled to end") : null);
        if (!blockSearch) {
            loadMoreImages();
        }
    }
}, {threshold: [1]});

function loadMoreImages() {
    ((loglevel >= 0) ? console.log(t() + " load more images") : null);
    if (oldestImageLoadedId == "") {
        return;
    }
    isAppendingToTop = false;
    createFilterString();
    postSearch();
}

function isEndVisible() {
    var elem = $("#face-scoll-end")
    var docViewTop = $(window).scrollTop();
    var docViewBottom = docViewTop + $(window).height();

    var elemTop = $(elem).offset().top;
    var elemBottom = elemTop + $(elem).height();

    return ((elemBottom <= docViewBottom) && (elemTop >= docViewTop));
}
var observerTop = new IntersectionObserver(function (entries) {
    if (entries[0].isIntersecting === true) {
        ((loglevel >= 0) ? console.log(t() + " scrolled to top") : null);
        if (!blockSearch) {
            loadNewImages();
        }
    }
}, {threshold: [1]});

var isAppendingToTop = false;
function loadNewImages() {
    ((loglevel >= 0) ? console.log(t() + " load new images") : null);
    isAppendingToTop = true;
    $('#face-scoll-top-message').text("Loading...");
    $("#face-scoll-top-message").css("background-color", "red");
    $('#face-scoll-top-message').fadeIn();
    startFaceDetection();
    createFilterString();
    postSearch();
}

$(document).ready(function () {
    loglevel = parseInt($("#faces_log_level").text());
    console.log(t() + " log level = " + loglevel);
    observerEnd.observe(document.querySelector("#face-scoll-end"));
    observerTop.observe(document.querySelector("#face-scoll-top"));
    faceEditControls = $("#template-face-frame-edit-controls").html();
    $("#template-face-frame-edit-controls").remove();
    var dFrom = $("#faces_date_from").text();
    ((loglevel >= 0) ? console.log(t() + " date from = " + dFrom) : null);
    var dTo = $("#faces_date_to").text();
    ((loglevel >= 0) ? console.log(t() + " date to = " + dTo) : null);
    initDate(dFrom, dTo);
//    createFilterString()
    var v = $("#faces_can_write").text();
    can_write = (v === 'true');
    ((loglevel >= 0) ? console.log(t() + " can write = " + can_write) : null);
    var io = $("#faces_is_owner").text();
    is_owner = (io === 'true');
    if (!is_owner) {
        $("#dbtn-acl").prop("disabled", true);
    }
    ((loglevel >= 0) ? console.log(t() + " is owner = " + is_owner) : null);
    ((loglevel >= 0) ? console.log(t() + " can write = " + can_write) : null);
//    $('#region_1').remove();
//    $('#region_3').remove();
    zoom = parseInt($("#faces_zoom").text());
    ((loglevel >= 0) ? console.log(t() + " zoom = " + zoom) : null);
    isSearchMe = isModeSearchMe();
//    postSearch();
    search();
    if (!isSearchMe) {
        startFaceDetection();
    }
}
);

