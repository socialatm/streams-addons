
<h1>Probe Thresholds</h1>

<form id="face_form_probe" method="post" action="http://localhost/admin/addons/faces/" class="">
    <h2>Why this?</h2>
    <p>
        ...to help you to 
    </p>
    <ul>
        <li>find tune the face recognition in case you experience          
            <ul>
                <li>to many false positives, e.g. faces that are not Bob or Jane</li>
                <li>to less matches, e.g. the face recognition finds to less faces of Jane and Bob</li>
            </ul>
            by <a class='link_correction' href="faces/channel-nick/thresholds">overwriting</a> the default thresholds</li>
        <li>compare the accuracy of combinations of detector, model and distance metric
        and help you to choose one that works best for you</li>
    </ul>
    <h2>What will happen?</h2>
    <p>
        If you press "submit" this will start a face recognition that
        iterates through all combinations of
    </p>
    <ul>
        <li>detectors</li>
        <li>models</li>
        <li>distance metrics</li>
    </ul>
    <p>
        you configured under <a class='link_correction' href="faces/channel-nick/settings">settings</a>  where each distance metric is
        iterated in 10 steps from 50% to 150% of its default value 
        for each model-detector combination.
    </p>
    <p>
        Example: The default Cosine is 0.4 for model Facenet. The recognition will iterate
        in ten steps from 0.2 to 0.6.
    </p>
    <h2>Step-for-Step Instructions</h2>
    <ul>
        <li>
            Activate some detectors and models in the <a class='link_correction' href="faces/channel-nick/settings">settings</a>
        </li>
        <li>
            Upload some pictures WITH Bob and Jane into the directory 
            <a class='link_correction' href="cloud/channel-nick/faces/probe/known">known</a>
            (It does not matter if more persons than Bob and Jane are in the pictures.)
        </li>
        <li>
            Use the <a class='link_correction' href="faces/channel-nick">addon</a> 
            to detect and name faces of Jane and Bob you uploaded to 
            <a class='link_correction' href="faces/channel-nick">known</a> before.
            <br>Hint: The browser sorts pictures for upload time. Pictures that you
            just uploaded will land on top of older pictures.
            <br>You can sort the pictures for the time they where shot.
            Please use the setting "exif" in
            the <a class='link_correction' href="faces/channel-nick/settings">settings</a>.
        </li>
        <li>
            Copy some pictures of
            <ul>
                <li>
                    Jane into <a class='link_correction' href="cloud/channel-nick/faces/probe/Jane">Jane</a>
                    (rename as you like)
                </li>
                <li>
                    Bob into <a class='link_correction' href="cloud/channel-nick/faces/probe/Bob">Bob</a>
                    (rename as you like)
                </li>
                <li>...(create more, the name of the directory and person must be identical)</li>
            </ul>
            Each picture should show one person only, e.g. Jane, Bob,...
            <br>Do not set names for them. Leave this to the face recognition.
            <br>(That is the purpose of the whole exercise: The face recognition
            should find Jane and Bob in every single picture.)
        </li>
        <li>
            Copy some pictures WITHOUT Jane and Bob into 
            <a class='link_correction' href="cloud/channel-nick/faces/probe/unknown">unknown</a>.
            <br>(That is the purpose of the whole exercise: The face recognition 
            should not find Jane and Bob in this directory.)
        </li>
        <li>
            Seclect the detectors, models and distance metrics you want to probe 
            under <a class='link_correction' href="faces/channel-nick/settings">settings</a>
        </li>
        <li>
            Come back <a class='link_correction' href="faces/channel-nick/probe">here</a> 
            and press submit.
        </li>
        <li>
            Look for the results in 
            <a class='link_correction' href="cloud/channel-nick/faces/probe.json">probe.json</a>
            <br>There is no feedback when the process is finished.
            <br>You might watch the logfile or delete
            <a class='link_correction' href="cloud/channel-nick/faces/probe.json">probe.json</a>
            in <a class='link_correction' href="cloud/channel-nick/faces">faces</a>
            before.
        </li>
    </ul>


    <div class="submit">
        <input type="submit" name="page_faces" value="Submit" class="float-right">
    </div>

</form>

<div style="display: none;">
    <p>    
        Addon Faces v{{$version}} ).
    </p>
    <p id="faces_log_level">{{$loglevel}}</p>
</div>

<script src="/addon/faces/view/js/probe.js"></script>