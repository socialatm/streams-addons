<h1>Why this Addon?</h1>
<p>
    There are a couple of reasons.
</p>
<h2>
    Reclaim Artificial Intelligence (AI) from private Companies
</h2>
<p>
    To recognizes faces you will usually use the service of a private company.
    Keep in mind, companies have to make money. They will keep, sell and re-use your data in their own
    interest without asking you.
</p>
<p>
    Keep the data where it belongs to - to YOU.
</p>
<h2>
    Make your own Experiments
</h2>
<p>
    ...and choose what works best for you.
</p>
<p>
    This addon bundles some of the most recent state-of-the-art face recognition methods
    from universities as well as big companies like Google and Facebook.
</p>
<p>
    This addon makes it easy for you to play around with some parameters without
    the need of programming skills:
</p>
<ul>
    <li>
        Choose detectors (task = this is a FACE), see
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Choose models (task = this face is JANE), see
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Combine detectors and models. Be aware that 5 detectors combined with
        7 models will produce 35 faces (instead of one). All have to be created, stored and matched. See
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Set a minimum size for a face to be detected, see
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Set the minimum size of know faces used to search in other images (to train the model), see
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Set the minimum size of unknown faces to be matched with known faces, see
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Choose a distance metric to match faces, see
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    </li>
    <li>
        Choose a threshold of confidence for the recognition (this face is JANE), see
        <a class='link_correction' href="faces/channel-nick/thresholds">thresholds</a>
        <br>The optimal value of a threshold depends mainly on the combination of a model and a distance metric
        but also on the detector used by a model.
        The author of deepface Sefik Ilkin Serengil already fine tuned the thresholds, more
        <a href="https://sefiks.com/2020/05/22/fine-tuning-the-threshold-in-face-recognition/">background</a>.
    </li>
</ul>
<p>
    Parameters you can not set:
</p>
<ul>
    <li>Threshold of confidence for the detection (this is a FACE)</li>
    <li>Method to align and normalize faces to increase the accuracy</li>
</ul>
<h2>
    Proove Myths
</h2>
<p>
    How well does face recognition work in real life situations? What are the
    limits?
</p>
<p>
    Just proove it using this software!
</p>
<p>
    AI ("artifical intelligence" we should better call it machine learning)
    is conquering more and more aspects of our lifes.
    Most of us will use face recognition for fun.
    Some just search their foto album. Others search for relatives using payed websites.
    Sometimes the consequences of this technology are quite serious.
    People can land on terrorist lists or get blackmailed.
</p>
<p>
    How many false positives are produced by differnet detectors and recognition models?
</p>
<p>
    Of course the big players like Google, Apple, Amazon,... have a bunch of other data
    to make a better prediction than this software can do for you.
    They will use the location data in images, 
    the social circle, nearby bluetooth devices and and other data to tell you who is most likly on a picture.
    This is a different story. Face recognition itself can do no more magic than for
    you.
</p>

<hr/>

<h1>Technical Background</h1>

<h2>Basic Steps</h2>

<h3>1. Face Detection</h3>

<p>
    Find a face and its position in a picture, cut the face
    out and hand it over to the next step. Available detectors:
</p>
<ul>
    <li>retinaface</li>
    <li>mtsnn</li>
    <li>ssd</li>
    <li>opencv</li>
    <li>mediapipe (Google)</li>
</ul>
<h3>2. Alignment and Normalization</h3>
<p>
    The alignment rotates the face until the
    eyes sit on the same horizontal line.
</p>
<p>
    The normalization corrects the perspective,
    light, face expression (duck face, smile,...) and produces a kind of
    neutral looking avatar face. The result is handed over to the next step.
</p>
<h3>3. Creation of Face Representations</h3>
<p>This process creates a face representation for a face, basically a
    multidimensional vector, also called embedding.
    The embeddings are created once and are stored in the file face.gzip.</p>
<p>Available face recognition models:</p>
<ul>
    <li>Facenet (Google)</li>
    <li>Facenet512 (Google)</li>
    <li>Deepface (Facebook)</li>
    <li>SFace</li>
    <li>ArcFace</li>
    <li>VGG-Face</li>
    <li>OpenFace</li>
</ul>

<h3>4. Matching (Verification)</h3>

<p>
    This process matches face representations (vectors) for similarity.
    Available metrics:
</p>
<ul>
    <li>cosine</li>
    <li>euclidean</li>
    <li>euclidean_l2</li>
</ul>

<h2>Further Reading</h2>

<p>
    Please look at the <a href="https://github.com/serengil/deepface">official
        documentation</a> and <a href="https://sefiks.com/talks/">public talks</a>
    of Sefik Ilkin Serengil who is the author of the underlying backend deepface.
</p>

<hr/>

<h1>Privacy</h1>
<p>Keep in mind: no upload of an image, no face detection.</p>
<p>
    If you run your own server:
</p>
<ul>
    <li>Your faces (names and face representations) will not leave your server.</li>
</ul>
<p>
    If you use a public server (a European perspective):
</p>
<ul>
    <li>User consent: Activate/deactivate the face recognition yourself, 
        <a href="https://gdpr-info.eu/art-7-gdpr/">Art. 7 GDPR</a>. There is no server wide face recognition.
    </li>
    <li>Data protection by design and by default: Allow or deny users/groups to view and edit your faces and names,
        <a href="https://gdpr-info.eu/art-25-gdpr/">Art. 25 GDPR</a>.
    </li>
    <li>Right to data portability, <a href="https://gdpr-info.eu/art-20-gdpr/">Art. 20 GDPR</a>.:
        <ul>
            <li>Export your faces and names,</li>
            <li>Import your faces and names from a different provider,</li>
            <li>Faces and names are synchronized automatically to your channel clones and are kept in sync.</li>
        </ul>
    </li>
    <li>Right to rectification: Correct you faces and names at any time, 
        <a href="https://gdpr-info.eu/art-16-gdpr/">Art. 16 GDPR</a>.
    </li>
    <li>Right to erasure (‘right to be forgotten’): Delete your faces and names at any time, 
        <a href="https://gdpr-info.eu/art-17-gdpr/">Art. 17 GDPR</a>,
    <li>Right to object: <a href="https://gdpr-info.eu/art-21-gdpr/">Art. 21 GDPR</a>
    </li>
</ul>

<hr>

<h1>Reference</h1>

<h2>Main Page</h2>
<p>
    Open <a class='link_correction' href="faces/channel-nick/">here</a>.
</p>
<p>
    The page will stay empty until the first detection has finished. This can
    take minutes to hours depending on the number of images, the processing
    power of the server and the settings (detectors, models, facial attributes).
</p>
<p>
    Detected faces show a frame.
</p>
<p>
    Click into the frame to set a name. A dialog will pop-up.
</p>
<ul>
    <li>
        Type a name into the text field and press enter or the button thumbs-up.
    </li>
    <li>
        <button class="btn" id="face-edit-set-name"> <i class="fa fa-thumbs-up fa-2x"></i></button>
        Confirm the name.
    </li>
    <li>
        <button class="btn" id="face-edit-set-unknown"> <i class="fa fa-question fa-2x"></i></button>
        This is person you don't know. This face will not be matched with known
        faces anymore but you still will see the frame around the face and are able
        to set a name later on.
    </li>
    <li>
        <button class="btn" id="face-edit-set-ignore"> <i class="fa fa-eye-slash fa-2x"></i></button>
        This is no face at all. Tell the face recognition to ignore this. You will
        not see this face again in the browser.
    </li>
</ul>


<h3>Filter / Search</h3>
<p>
    <button class="btn" id="button-faces-filter"><i class="fa fa-filter fa-2x"></i></button>
    at the bottom of the page.
</p>
<ul>
    <li>
        <strong>Name</strong>: Choose one or more names from the list.
    </li>
    <li>
        <strong>AND</strong> search: Find pictures only where "Jane" AND "Bob" are together in 
        a picture.
    </li>
    <li>
        <strong>Date</strong>: Choose a start and/or an end date.
        You can use the <strong>upload</strong> date of the picture or the date when the
        picture was <strong>taken</strong> (exif date). 
        Default is the upload date. Use the
        <a class='link_correction' href="faces/channel-nick/settings">settings</a>
        to change the setting. Be aware that not every single pictures carries
        the information when it was taken. Those pictures will not be shown if
        the exif date is the filter criterion.
    </li>
</ul>

<h3>Toogle Frames</h3>
<p>
    <button class="btn" id="button-faces-hide-frames"><i class="fa fa-eye-slash fa-2x"></i></button>
    at the bottom of the page. Hide the frames for better visibility of faces.
</p>

<h3>Zoom</h3>
<p>
    <button class="btn faces_zoom" id="button_faces_zoom_in"><i
            class="fa fa-search-plus fa-2x"></i></button>
    <button class="btn faces_zoom" id="button_faces_zoom_out"><i
            class="fa fa-search-minus fa-2x"></i></button>
    at the bottom of the page.
    Show one or up to six images in one row. Set the default zoom under
    <a class='link_correction' href="faces/channel-nick/settings">settings</a>.
</p>

<hr>

<h2>Settings</h2>
<p>
    Open with <a class='link_correction' href="faces/channel-nick/settings">settings</a>.
</p>

<h3>Detectors</h3>

<p>
    Activate one or more detectors.
</p>

<h3>Models</h3>

<p>
    Activate one or more models.
</p>

<h3>Distance Metrics</h3>

<p>
    Activate one or more distance metrics.
</p>

<h3>Tuning - Detection</h3>

<p>
    Set a minimum size for a face to be found in pixel and/or percent of the image
    width. Smaller faces will be ignored.
</p>

<h3>Tuning - Recognition</h3>

<p>
    Set a minimum size for faces used  as training data and for the faces
    that still do not carry a name. Some detectors like retinaface or mtcnn
    are very acurate in finding small faces that might often be in the
    background and thus are not relevant for you.
</p>

<h3>Statistics - History</h3>

<p>
    Store the recognized name along with the name set by the user.
    This will allow you to compare the accuracy of different recognition models. 
</p>

<h3>Statistics - Write Statistics</h3>

<p>
    Write all detected and recognized faces into one single file 
    <a class='link_correction' href="cloud/channel-nick/faces/face_statistics.csv">face_statistics.csv</a>
    This allows you to view details on what detector found what face,
    what model recognized what name, the time it took,...
</p>

<h3>Statistics - Enforce all Models to match Faces</h3>

<p>
    Compare the effectivness of detectors, models and distance metrics.
    There is a result file for this,
    <a class='link_correction' href="cloud/channel-nick/faces/model_statistics.csv">model_statistics.csv</a>.
    If you want to have usefull results you should enable the "history" above and
    probably "immediate search". For your convenience activate
    the preset "experimental" below. This will to the right settings for you
    If switched on this will slow down face matching. There is a good chance to
    find to much false postives (faces that are not "Jane").
</p>

<h3>Performance - Immediate Search</h3>

<p>
    Start the face recognition always immediatly after a users has set
    a name. Advantage: The names will be updated in the
    browser as soon as the face recognition finds a person. Disadvantage:
    Increased server load. 
</p>

<h3>Browser Appearance - Sorting</h3>

<p>
    Sort the images by the time an images was:
</p>

<ul>
    <li>taken (exif))</li>
    <li>uploaded</li>
</ul>

<p>
    Watch this: Some images do not carry the information when they where taken.
    In this case the exif date will be shown as 01.01.1970.
</p>

<p>
    Sort the images:
</p>

<ul>
    <li>ascending</li>
    <li>descending</li>
</ul>


<h3>Browser Appearance - Zoom</h3>

<p>
    Start value for zoom. Possible values: 1 to 6.
</p>

<h3>Facial Attributes and Demography</h3>

<p>
    Acitvate emotion, age, gender, race. The results are not displayed in any
    way at the moment.
</p>

<h3>Presets - Experimental</h3>

<p>
    Activate only if you want to compare all detectors, models and distance metrics. Make sure the server has enough CPU and RAM.
    Recommended: Switch on for experimental reasons only. THIS CONSUMES MUCH CPU, RAM, TIME AND ENERGY.
</p>

<h3>Presets - Default</h3>

<p>
    Reset all of the options above to default ones.
    RECOMMENDED: SWITCH ON and press "Submit".
</p>

<hr>

<h2>Remove</h2>

<p>
    Open with <a class='link_correction' href="faces/channel-nick/remove">remove</a>.
</p>
<p>
    Remove faces and/or names there. You can also remove faces for a certain
    detetor or a model or a combination of detector and models.
</p>

<hr>

<h2>Thresholds (advanced)</h2>
<p>
    Open with <a class='link_correction' href="faces/channel-nick/thresholds">thresholds</a>.
    Fine tune the thresholds for recognition models.
    You can play around with the thresholds in conjunction with
    <a class='link_correction' href="faces/channel-nick/probe">probe</a>.
    The author of deepface Sefik Ilkin Serengil already fine tuned most thresholds
    <a href="https://sefiks.com/2020/05/22/fine-tuning-the-threshold-in-face-recognition/">see</a>.
</p>

<hr>

<h2>Probe (advanced)</h2>
<p>
    The goal is to determine optimised thresholds that find
    "Jane" in all pictures without finding to much "Jane"s ( =
    false positives = persons that are not "Jane").
</p>
<p>
    Open with <a class='link_correction' href="faces/channel-nick/probe">probe</a>.
    You will find detailed step-by-step instructions there. In short this feature will
    start a search using different thresholds for distance metrics.
    The programm will show you the results
    in a table (csv file).
</p>

<hr>

<h1>Remote Detection and Recognition</h1>

<h2>What and Why</h2>
<p>
    You don't want to run the CPU and RAM consuming task of face recognition
    on your server?
</p>
<p>
    There is a python script that provides the same functionality than the
    script running on the server.
</p>
<p>
    How does it work? Do you remember?
    The cloud files on the server are accessible via webDAV.
</p>
<p>
    Once your local 
    machine is connected with the cloud file storage on the server via webDAV the python script
    on your local machine is able to read the pictures on the server
    and write back the results files.
</p>
<p>
    The script looks for the configuration on the server. Visit
    <a class='link_correction' href="faces/channel-nick/settings">settings</a>
    and 
    <a class='link_correction' href="faces/channel-nick/thresholds">thresholds</a>
    to change it.    
</p>
<p>
    The admin page of the addon has a setting to block the execution of
    the python script on the server. If this is activated
    the user is still able to view the faces in the browser and to set names there.
</p>
<h2>Preparations</h2>
<h3>WebDAV - Connect your Computer to the Cloud Files</h3>
<p>
    Mount WebDav-Share using fstab and davfs2<br>
    (<a href="https://www.hagemann.ws/blog/linux-mount-webdav-share-using-fstab-and-davfs2.html">source</a>)
</p>
<p>  
    <span style="color:red;">&lt;localuser&gt;</span>,
    <span style="color:red;">&lt;localusergroup&gt;</span>: The username and group of the currently logged on local user <br>
    <span style="color:red;">&lt;localdir&gt;</span>: The directory where the webdav share should be mounted to<br>
    <span style="color:red;">&lt;webdavurl&gt;</span>: The URL of the webdav share,
    for this server it is: <span style="color:red;" class='webdavurl'></span><br>
    <span style="color:red;">&lt;webdavusername&gt;</span>: Login name, same as in browser<br>
    <span style="color:red;">&lt;webdavpassword&gt;</span>: xxx, same as in browser<br>
</p>
<p>  
    If you are not sure about <span style="color:red;">&lt;localuser&gt;</span>
    and <span style="color:red;">&lt;localusergroup&gt;</span> open a terminal and type
</p>
<p>  
    ...for user...
</p>
<code>
    id -u -n 
</code>
<p>  
    ...for group...
</p>
<code>
    id -g -n
</code>
<br>
<h4>Setup davfs</h4>
<code>
    sudo apt-get update<br>
    sudo apt-get install davfs2<br><br>
    # Add <span style="color:red;">&lt;localuser&gt;</span> to group davfs2
    sudo usermod -aG davfs2 <span style="color:red;">&lt;localuser&gt;</span><br><br>
    # Allow other users than root to mount davfs volumes<br>
    sudo dpkg-reconfigure davfs2<br><br>
    sudo sh -c 'echo "/mnt/<span style="color:red;">&lt;localdir&gt;</span> <span style="color:red;">&lt;webdavusername&gt;</span> <span style="color:red;">&lt;webdavpassword&gt;</span>" >> /etc/davfs2/secrets'<br>
    sudo mkdir /mnt/<span style="color:red;">&lt;localdir&gt;</span>
</code>
<br>
<h4>Setup /etc/fstab</h4>
<code>
    gedit admin:///etc/fstab
</code>
<p>
    Edit /etc/fstab ...
</p>
<code>
    ..<br>
    <span style="color:red;">&lt;webdavurl&gt;</span> /mnt/<span style="color:red;">&lt;localdir&gt;</span> davfs rw,auto,user,uid=<span style="color:red;">&lt;localuser&gt;</span>,gid=<span style="color:red;">&lt;localusergroup&gt;</span>,_netdev 0 0<br>
    ...
</code>
<p>
    While <span style="color:red;">root</span> is the mounting user,
    it is still possible to change the owner of the file system.
    That is what options <span style="color:red;">uid</span> and
    <span style="color:red;">gid</span> are for.
</p>
<br>
<h4>Linking to your Home directory</h4>
<p>
    You might want to see the webdav share in your Home directory: 
</p>
<code>
    ln -s /mnt/<span style="color:red;">&lt;localdir&gt;</span> /home/<span style="color:red;">&lt;localuser&gt;</span>/WebDav
</code>
<br>
<h3>Install Python Package Manager and Python Modules</h3>
<p>
    The following was tested under Debian 11.
</p>
<p>
    Python Package Manager
</p>
<code>
    su -<br>
    apt-get update<br>
    apt-get -y install python3-pip<br>
    pip --version
</code>
<p>
    Python Modules
</p>
<code>
    pip install deepface mediapipe fastparquet pyarrow
</code>
<br>
<h3>Run the Script</h3>
<p>
    Do this only once
</p>
<code>
    sudo apt-get install git<br>
    cd ~<br>
    git clone https://codeberg.org/streams/streams-addons.git
</code>
<p>
    Every time you want to run the script...
</p>
<p>
    Preparation: Connect via webDAV
</p>
<code>
    mount /home/<span style="color:red;">&lt;localuser&gt;</span>/WebDav
</code>
<p>
    Run the script.
</p>
<code>
    cd ~<br>
    cd streams-addons<br>
    python3 faces/py/run.py -d /home/<span style="color:red;">&lt;localuser&gt;</span>/WebDav/
</code>





    


<script src="/addon/faces/view/js/help.js"></script>
