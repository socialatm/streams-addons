<form id="face_form_settings" method="post" action="" class="">

    <h1>Face Detection, Recognition and Matching</h1>
    <div id="face_detectors">
        <h2>Step 1 - Face Detection - Detectors</h2>
        <div>A face detector finds the position and size of a face, 
            cuts it off and hands it over to
            a face recognition model (see next step).</div>
        <div>Recommended: Choose one single detector except you want to compare
            the effectivness of detectors. Every additional detector will slow down
            everything by factor 2.</div>
    </div>
    <div id="face_models">
        <h2>Step 2 - Face Recognition - Models</h2>
        <div>A face recognition model takes a face from a face detector and
            creates an embedding (basically a vector) that
            represents a face.</div>
        <div>Recommended: Choose one single model except you want to compare
            the effectivness of models. Every additional model will slow down
            everything by factor 2.</div>
    </div>
    <div id="face_metrics">
        <h2>Step 3 - Face Matching - Distance Metrics</h2>
        <div>A distance metric is a function that calculates a distance between
            vectors (embeddings) that where created by a recognition model (see above).</div>
        <div>Advanced: Set your own
            <a class='link_correction' href="faces/channel-nick/thresholds">thresholds</a>.</div>
        <div>Recommended: Choose one single distance metric except you want to compare
            the effectivness of metrics.</div>
    </div>
    <h2>Tuning</h2>
    <div id="face_size_detection">
        <h3>Minimum Face Size - Detection</h3>
        <div>Faces smaller than this will be ignored.</div>
        <div>Changing values here will have no effect on already detected faces.</div>
    </div>
    <div id="face_size_recognition">
        <h3>Minimum Face Size - Recognition (Matching of Faces)</h3>
        <div><strong>training</strong>... [px] training data, faces having a name</div>
        <div><strong>result</strong>... [px] faces without a name</div>
        <div>Faces smaller than this will be ignored.</div>
    </div>
    <div id="face_most_similar_recognition">
        <h3>Most similar Faces only</h3>
        <div>For the machine some faces look more similar to a person than others
            although it is the same person. Use only the most similar faces for searching.
        </div>
        <div>
            This speeds up the search and should result in less false positives.
        </div>
    </div>
    <h2>Statistics</h2>
    <div id="face_history">
        <h3>Keep History</h3>
        <div>
            Store the recognized name along with the name set by the
            user.
        </div>
        <div>
            This will allow you to compare the accuracy of different
            recognition models in
            <a class='link_correction' href="cloud/channel-nick/faces/model_statistics.csv">model_statistics.csv</a>.
        </div>
    </div>
    <div id="face_statistics">
        <h3>Write Statistics</h3>
        <div>Write all detected and recognized faces into one single file 
            <a class='link_correction' href="cloud/channel-nick/faces/face_statistics.csv">face_statistics.csv</a>
            This allows you to view details on what detector found what face,
            what model recognized what name, the time it took,...</div>
    </div>
    <div id="face_enforce_all">
        <h3>Enforce all Models to match Faces</h3>
        <div>Compare the effectivness of detectors, models and distance metrics.
            If switched on this will slow down face matching.</div>
        <div>Recommended: Switch off</div>
    </div>
    <h2>Performance</h2>
    <div id="face_performance">
        <h3>Immediate Search</h3>
        <div>Start the face recognition always immediatly after a users has set
            a name. Advantage: The names will be updated in the
            browser as soon as the face recognition finds a person. Disadvantage:
            Increased server load.</div>
    </div>
    <hr/>
    <h1>Browser Appearance</h1>
    <h2>Sorting</h2>
    <div id="face_sortation">
        <h3>Date and Time</h3>
        <div>Sort the images by the time an images was taken (exif) or the
            time it was uploaded. Some images do not carry the information when
            they where taken.</div>
        <div>Recommended: Switch off to get consistent results</div>
    </div>
    <h2>Zoom</h2>
    <div id="face_zoom">
        <h3>Images per Row</h3>
        <div>Start value for zoom. Possible values: 1 to 6.</div>
    </div>

    <hr/>
						
    <h1 id="face_attributes">Facial Attributes and Demography</h1>
    
    <hr/>
    
    <h1>Share Faces</h1>
    
    <div id="contact-slider" class="slider form-group">
        <div id="slider-container">
        <i class="fa fa-fw fa-user range-icon"></i>
        <input id="contact-range" title="50" type="range" min="0" max="99" name="closeness" value="50" list="affinity_labels">
        <i class="fa fa-fw fa-users range-icon"></i>
        <span class="range-value">50</span>
        </div>
    </div>
    <div class="form-group">See friend zoom of individual contacts</div>
    
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