<form id="face_form_sharing" method="post" action="" class="">

    
    <h1>Share Faces</h1>
    
    <div id="contact-slider" class="slider form-group">

        <div id="slider-container">
        <i class="fa fa-fw fa-user range-icon"></i>
        <input id="contact-range" title="50" type="range" min="0" max="99" name="closeness" value="50" list="affinity_labels">
        <i class="fa fa-fw fa-users range-icon"></i>
        <span class="range-value">50</span>
        </div>
    </div>
    <div id="faces-contact-list-share" class="form-group">See friend zoom of individual contacts<br/></div>
    <div>These are the contacts you can tag and who are allowed to download your
        faces (embeddings) in return. Please be aware that only the same combination 
        of detector-model will find a face.</div>
    
    <hr/>
    
    <h2>Faces you share</h2>
    <div id="faces-you-share"></div>
    
    <hr/> 
    
    <h2>Faces shared with you</h2>
    <div id="faces-shared-with-you"></div>

</form>

<div style="display: none;">
    <p>    
        Addon Faces v{{$version}} ).
    </p>
    <p id="faces_log_level">{{$loglevel}}</p>
</div>

<script src="/addon/faces/view/js/sharing.js"></script>