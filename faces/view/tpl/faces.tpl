


<div id="face-panel-pictures" class="clearfix">
    <!-- filled dynamically by javascript -->
    <!-- all the pictures go here -->    
</div>
<div id="face-scoll-end"></div>

<div style="display: none;">
<p>    
Addon Faces ( version {{$version}} ) - The GUI is the hardest part.
</p>
<p>
Feedback of face recognition...<br>
status: {{$status}}<br>
message: {{$message}}
</p>
<p id="faces_can_write">{{$can_write}}</p>
<p id="faces_is_owner">{{$is_owner}}</p>
<p id="faces_log_level">{{$log_level}}</p>
<p id="faces_date_from">{{$faces_date_from}}</p>
<p id="faces_date_to">{{$faces_date_to}}</p>
<p id="faces_zoom">{{$faces_zoom}}</p>
</div>

<!-- this div will be copied by javascript and then removed after loading the page -->
<div id="template-face-frame-edit-controls" style="display: none;">
    <div class="d-flex justify-content-center">
        <div>
            <input type="text" id="input-face-name" name="face-search-faces" list="face-name-list-set" class="form-control input-face-name-list" onchange="setName()">
            <datalist id="face-name-list-set" class="face-name-list">
                <!-- filled dynamically by javascript -->
            </datalist>
        </div>
    </div>
    <div>
        <button class="btn" id="face-edit-set-name" onclick="setName()"><i class="fa fa-thumbs-up fa-lg"></i> </button>
        <button class="btn" id="face-edit-set-unknown" onclick="setNameUnkown()"><i class="fa fa-question fa-lg"></i> </button>
        <button class="btn" id="face-edit-set-ignore" onclick="setNameIgnore()"><i class="fa fa-eye-slash fa-lg"></i></button>
    </div>
</div>

<!-- controls that are permanently shown at the bottom of the page -->
<div id="face-footer-buttons">  
    <div class="d-flex justify-content-center">
        <div>
            <button class="btn" id="button-faces-filter"><i class="fa fa-filter fa-lg"></i></button>
        </div>
        <div>
            <button class="btn" id="button-faces-hide-frames"><i class="fa fa-eye-slash fa-lg"></i></button>
        </div>
        <div>
            <form id="attach_edit_form_acl" action="faces/{{$channelnick}}/notneededanymore/" method="post" class="acl-form" data-form_id="attach_edit_form_acl" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
                <div>
                    <div id="attach-edit-perms" class="btn-group pull-right">
                        <button id="dbtn-acl" class="btn" data-toggle="modal" data-target="#aclModal" title="{{$permset}}" type="button">
                            <i id="jot-perms-icon" class="fa fa-{{$lockstate}}"></i>
                        </button>
                    </div> 
                </div>
            </form>
          </div>         
        <div>
            <button class="btn" id="button_share_box">
                <span id="button_share_box_counter_upload"></span>
                <i class="fa fa-refresh fa-lg"></i>
                <span id="button_share_box_counter_download"></span>
            </button>
        </div>
        <div>
            <button class="btn faces_zoom" id="button_faces_zoom_in"><i class="fa fa-search-plus fa-lg"></i></button>
        </div>
        <div>
            <button class="btn faces_zoom" id="button_faces_zoom_out"><i class="fa fa-search-minus fa-lg"></i></button>
        </div>
    </div>
</div>

<!-- controls for the search filter fixed at the bottom of the page -->
<div id="face-footer">
    <div id="panel_faces_filter">
        
        <div>
            <div class="form-group d-flex justify-content-center" id="face-active-filter-names">
                <!-- filled dynamically by javascript -->
            </div>
            <div class="foorm-group d-flex justify-content-center" style="display: none;">
                <label class="face-name-and-buttons">AND <input type="checkbox" id="face-search-and" class="face-name-and-buttons"></label>
            </div>
            <div class="form-group d-flex justify-content-center">
                <input type="text" id="input-search-name" name="input-face-name-list" list="face-name-list-search" class="form-coontrol input-face-name-list" autocomplete="off" onchange="addSearchName(this)">
                <datalist id="face-name-list-search" class="face-name-list">
                    <!-- filled dynamically by javascript -->
                </datalist>
            </div>
            <div class="form-group d-flex justify-content-center">
                    <input type="date" id="face-date-from" class="face-date" name="face-date-from" class="form-control">
                    <label for="face-date-from"> <i class="fa fa-arrow-left fa-lg"></i> </label>
                    <label for="face-date-to"> <i class="fa fa-arrow-right fa-lg"></i> </label>
                    <input type="date" id="face-date-to" class="face-date" name="face-date-to" class="form-control">
            </div>
        </div>

    </div>
</div>       

<div id="acl_modal_faces">{{$acl_modal}}</div>


<script src="/addon/faces/view/js/faces.js"></script>

<!-- https://www.w3schools.com/howto/tryit.asp?filename=tryhow_js_autocomplete -->