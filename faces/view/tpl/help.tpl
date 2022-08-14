<h1>Why this Addon?</h1>
<p>
    There are a couple of reasons why this addon was written.
</p>
<p>
    This addon bundles some of the most recent state-of-the-art face recognition methods
    from universitiy reserach projects all over the world as well as methods
    developed from and used by big companies like Google and Facebook.
</p>
<p>
    Some of them are trained with millions of faces from different people.
</p>
<h2>
    Fun
</h2>
<p>
    Obvious.
</p>
<h2>
    Proove Myths
</h2>
<p>
    AI ("artifical intellegence") is conquering more and more aspects of our lifes.
    Most of us will use face recognition for fun.
    Some just search their foto album. Others search for relatives using payed websites.
    Sometimes the consequences of this technology are quite serious.
    There are many cases where people will land on terrorist list or get blackmailed.
</p>
<p>
    How well do recent face recognition methods work?
    But how reliably do they recognize YOU?
</p>
<p>
    How many false positives will be produced by differnet detectors and recognition models.
</p>
<p>
    Do you know a programm to test it on your own own pictures? Well, here it is. 
</p>
<h2>
    What Face Recognition Method is the best?
</h2>
<p>
    As said before this addon bundles some of the most recent face recognition methods.
</p>
<p>
    You can switch them all on and compare the results.
    The addon writes files showing statistics. Be warned: This will slow
    down everthing. But you can.
</p>
<p>
    Some background. The whole process consists of three steps
</p>
<<ul>
    <li>Face detection. Find a face and its postion in a picture.
        This is done by so called detectors:
        <<ul>
            <li>retinaface</li>
            <li>mtsnn</li>
            <li>ssd</li>
            <li>opencv</li>
        </ul>
        The results will be handed over to the next step.
    </li>
    <li>Creation of face representations, sometimes called embeddings, basically an array of numbers.
        This is done by face recognition models
        <<ul>
            <li>Facnenet (Google)</li>
            <li>Facenet512 (Google)</li>
            <li>Deepface (Facebook)</li>
            <li>SFace</li>
            <li>ArcFace</li>
            <li>VGG-Face</li>
            <li>OpenFace</li>
        </ul>
    </li>
    <li>Matching of face representations. How similar are the representations.
        This is done by mathematiall standard methods, distance metrics.
        The distance metrics provided are
        <<ul>
            <li>cosine</li>
            <li>euclidean_l2</li>
            <li>Deepface (Facebook)</li>
            <li>euclidean</li>
        </ul>
    </li>
</ul>
<<p>
    If you switch on every detetor and every model the addon will
    combine every detector with every face recognition model.</p>
<<p>
    To give you an example. You have one single picture showing one single face.
    The addon will produceThis will give 4x7=28 results.</p>
<<p>text</p>


<h1>Privacy</h1>
<p>Keep in mind: no upload of an image, no face detection.</p>
<p>
    If you run your own server:
</p>
<ul>
    <li>Your faces (names and face representations) will not leave your server.</li>
</ul>
<p>
    If you use a public server:
</p>
<ul>
    <li>User consent: Activate/deactivate the face recognition yourself, 
        <a href="https://gdpr-info.eu/art-7-gdpr/">Art. 7 GDPR</a>. There is no server wide face recognition.
    </li>
    <li>Data protection by design and by default: Allow or deny users/groups to view and edit your faces and names,
        <a href="https://gdpr-info.eu/art-25-gdpr/">Art. 25 GDPR</a>.
    </li>
    <li>Right to data portability:
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
    <li>Right to object: , 
        <a href="https://gdpr-info.eu/art-21-gdpr/">Art. 21 GDPR</a>
    </li>
</ul>



