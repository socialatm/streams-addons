<form id="face_form_settings" method="post" action="http://localhost/admin/addons/faces/" class="">

    <h1>Face Detection, Recognition and Matching</h1>
    <div id="face_detectors">
        <h2>Step 1 - Face Detection - Detectors</h2>
        <div>A face detector finds the position of a face and hands it over to
            a face recognition model (see next step).</div>
        <div>Recommended: Choose one single detector only except you want to compare
            the effectivness of detectors. Every additional detector will slow down
            everything by factor 2.</div>
    </div>
    <div id="face_models">
        <h2>Step 2 - Face Recognition - Models</h2>
        <div>A face recognition model takes a face from a face detector and
            creates a so called embedding (basically an array of numbers) that
            represents a face.</div>
        <div>Recommended: Choose one single model only except you want to compare
            the effectivness of models. Every additional model will slow down
            everything by factor 2.</div>
    </div>
    <div id="face_metrics">
        <h2>Step 3 - Face Matching - Distance Metrics</h2>
        <div>A distance metric is a function that calculates a distance between
            embeddings (faces) that where created by a recognition model (see above).</div>
        <div>Recommended: Choosing more than one option might improve the recognition rate.
            Keep in mind that if you choose more than one option
            this will slow down the matching of faces by factor 2, euclidean_l2 even more
            (but it is sometimes more reliable).</div>
    </div>
    <h2>Tuning</h2>
    <div id="face_size_detection">
        <h3>Minimum Face Size - Detection</h3>
        <div>Faces smaller than this will be ignored.</div>
    </div>
    <div id="face_size_recognition">
        <h3>Minimum Face Size - Recognition</h3>
        <div>"training"... faces given a name by the user and now used to find the same person in other pictures.</div>
        <div>"result"... faces not given a name by the user yet or marked as unknown.</div>
        <div>Sizes are in pixel. </div>
    </div>
    <h2>Statistics</h2>
    <div id="face_history">
        <h3>Keep History</h3>
        <div>This keeps a record of how correct the recognition works over time
            This will allow you to activate the statistics at any time later (see below).</div>
        <div>If the "immediate search" is switched on (see below) it might be more
            save to switch the "history" on as well.</div>
    </div>
    <div id="face_statistics">
        <h3>Write Statistics into a CSV File</h3>
        <div>Activate this if you want to write all deteted and recognized faces 
            into one single CSV file "faces/faces_statistics.csv" and if you want 
            to compare the results of different combinations of detectors, models
            and distance metrics. The results will go into a CSV file "faces/models_statistics.csv".
            Make sure to activate the "history" above.</div>
    </div>
    <div id="face_enforce_all">
        <h3>Enforce all Models to match Faces</h3>
        <div>Activate only if you want to look at statistics for example if you
            want to compare the effectivness of detectors, models and distance metrics.
            If switched on this will slow down face matching.</div>
        <div>Recommended: Switch off</div>
    </div>
    <h2>Performance</h2>
    <div id="face_performance">
        <h3>Immediate Search</h3>
        <div>Start the face recognition always immediatly after a users has set
            or changed a name. Advantage: The names will be updated in the
            browser as soon as the face recognition finds a person. Disadvantage:
            Increased server load.</div>
    </div>
    <hr/>
    <h1>Browser Appearance</h1>
    <h2>Sortation</h2>
    <div id="face_sortation">
        <h3>Date and Time</h3>
        <div>Sort the images by the time an images was taken (exif) or the
        time it was uploaded. Some images do not carry the information when
        they where taken.</div>
        <div>Recommended: Switch off</div>
    </div>
    <h2>Zoom</h2>
    <div id="face_zoom">
        <h3>Images per Row</h3>
        <div>Start value for zoom. Possible values: 1 to 6.</div>
    </div>

    <hr/>
    <h1 id="face_attributes">Facial Attributes and Demography</h1>
    <hr/>
    <h1>Presets</h1>
    <div id="face_experimental">
        <h2>Experimental</h2>
        <div>Activate only if you want to compare all detectors, models and distance metrics.
            Make sure the server has enough CPU and RAM.</div>
        <div>Recommended: Switch on for experimental reasons only.
            THIS CONSUMES MUCH CPU, RAM, TIME AND ENERGY.</div>
    </div>
    <div id="face_detaults">
        <h2>Default</h2>
        <div>Reset all of the options above to default ones.</div>
        <div>RECOMMENDED: SWITCH ON and press "Submit"</div>
    </div>
    <hr/>
    <div>Please contact your server admin if you want to use a disabled option.</div>
    <div class="submit">
        <input type="submit" name="page_faces" value="Submit" class="float-right">
    </div>

    <div id="placeholdername_container" class="clearfix form-group checkbox">
        <label for="id_placeholdername">placeholdername</label>
        <div class="float-right">
            <input type="checkbox" name="placeholdername" id="id_placeholdername" value="1" checked="checked">
            <label class="switchlabel" for="id_placeholdername">
                <span class="onoffswitch-inner" data-on="" data-off=""></span>
                <span class="onoffswitch-switch"></span>
            </label>
        </div>
    </div>
    <div id="id_placeholdername_wrapper" class="form-group">
        <label for="id_placeholdername" id="label_placeholdername">unit</label>
        <input class="form-control" name="placeholdername" id="id_placeholdername" type="text" value="2">
    </div>

</form>

<div style="display: none;">
    <p>    
        Addon Faces v{{$version}} ).
    </p>
    <p id="faces_log_level">{{$loglevel}}</p>
</div>

<script src="/addon/faces/view/js/settings.js"></script>