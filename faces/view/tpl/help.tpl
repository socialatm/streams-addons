<h1>Why this Addon?</h1>
<p>
    There are a couple of reasons why this addon was written.
</p>
<h2>
    Reclaim artificial Intelligence (AI) from private Companies
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
    ...and choose what works best.
</p>
<p>
    This addon bundles some of the most recent state-of-the-art face recognition methods
    from universities as well as big companies like Google and Facebook.
</p>
<p>
    This addon makes it easy for you to play around with some parameters without
    the need of programming skills.
</p>
<p>
    Parameters you can set:
</p>
<ul>
    <li>Detectors (this is a FACE)</li>
    <li>Models (this face is JANE)</li>
    <li>Combine detectors and models. Be aware that 5 detectors combined with
        7 models will produce 35 faces (instead of one) that have to be created, stored and matched.</li>
    <li>Set a minimum size for a face to be detected.</li>
    <li>Set the minimum size of know faces used search the same person (to train the model).</li>
    <li>Set the minimum size of unknown faces to be matched with known faces.</li>
    <li>Choose a distance metric to match faces, or use all.</li>
    <li>Threshold of confidence: Recognition - this face is JANE.
        This threshold depends on the combination of a model and a distance metric.
        The author of deepface Sefik Ilkin Serengil already fine tuned these thresholds
        <a href="https://sefiks.com/2020/05/22/fine-tuning-the-threshold-in-face-recognition/">see</a>.
    </li>
</ul>
<p>
    Parameters you can not set:
</p>
<ul>
    <li>Threshold of confidence: Detection - this is a FACE</li>
    <li>Method to align and normalize faces to increase the accuracy.</li>
</ul>
<h2>
    Proove Myths
</h2>
<p>
    AI ("artifical intelligence" we should better call it machine learning)
    is conquering more and more aspects of our lifes.
    Most of us will use face recognition for fun.
    Some just search their foto album. Others search for relatives using payed websites.
    Sometimes the consequences of this technology are quite serious.
    People sometimes land on terrorist lists or get blackmailed.
</p>
<p>
    In any case you can almost be sure as soon as you upload a photo to a private company it will
    be used for purposes you are not asked for and did not aggreed to.
</p>
<p>
    Once they have it... How well do recent face recognition methods work?
    How reliably do they recognize YOU?
</p>
<p>
    How many false positives are produced by differnet detectors and recognition models?
</p>
<p>
    Of course the big players like Google, Apple, Amazon,... have a bunch of other data
    to make a better prediction than this software can do for you.
    They will use the location data, 
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
    eyes are at the same horizontal line. Normalization corrects the perspective,
    light, face expression (duck face, smile,...) and produces a kind of
    neutral looking avatar face. The result is handed over to the next step.
</p>
<h3>3. Creation of Face Representations</h3>
<p>This process creates a face representation for a face, basically a
    multidimensional vector, sometimes called embedding.
    The embeddings are created once and are stored in the file face.gzip.</p>
<p>Available face recognition models:</p>
<ul>
    <li>Facnenet (Google)</li>
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
<p>
    Available distance metrics:
</p>
<ul>
    <li>cosine</li>
    <li>euclidean</li>
    <li>euclidean_l2</li>
</ul>
<p>Please look at the <a href="https://github.com/serengil/deepface">official
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



