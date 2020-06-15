
This addon detects faces in images and makes a guess who it is.

# Privacy

If you run your own server:

- Your faces and names will not leave your server.

If you do not use your own server:

- Activate/deactivate the face recognition yourself, [Art. 7 GDPR](https://gdpr-info.eu/art-7-gdpr/). (There is no server wide face recognition.)
- Allow or deny users/groups to view and edit your faces and names, [Art. 25 GDPR](https://gdpr-info.eu/art-25-gdpr/).
- Export your faces and names, [Art. 20 GDPR](https://gdpr-info.eu/art-20-gdpr/). Visit [this](uexport) page
- Import your faces and names at a different provider, [Art. 20 GDPR](https://gdpr-info.eu/art-20-gdpr/). See the remarks on [this](uexport) page
- Faces and names are synchronized automatically to your channel clones and are kept in sync, [Art. 20 GDPR](https://gdpr-info.eu/art-20-gdpr/).
- Correct you faces and names at any time, [Art. 16 GDPR](https://gdpr-info.eu/art-16-gdpr/).
- Delete your faces and names at any time, [Art. 17 GDPR](https://gdpr-info.eu/art-17-gdpr/), [Art. 21 GDPR](https://gdpr-info.eu/art-21-gdpr/). Append "/remove" to the URL of the addon, example: https://my-domain.org/faces/my-nick-name/remove

If you are tagged by others in their images (you = your channel) :

- You will get a notification who has tagged you and and a link to the images.
- You can view the images and remove yourself.
  * In case you do not have the permission to view an image you will see a delete-button instead.
  * The removal of a tag can't be undone by the owner of the image. (The face is not "taggable" anymore.)

Keep in mind: no image, no face detection. The crucial step is the upload of an image.

# Acknowledgements

The python scripts use other open source libraries and trained models:

- Finder 1: 
  * OpenCV, 3-clause BSD license, https://github.com/opencv/opencv/blob/master/LICENSE
  * [deploy_lowres.prototxt](https://raw.githubusercontent.com/opencv/opencv/master/samples/dnn/face_detector/deploy_lowres.prototxt), 3-clause BSD license, https://github.com/opencv/opencv/blob/master/LICENSE
  * [res10_300x300_ssd_iter_140000_fp16.caffemodel](https://raw.githubusercontent.com/opencv/opencv_3rdparty/dnn_samples_face_detector_20180205_fp16/res10_300x300_ssd_iter_140000_fp16.caffemodel), seems to be same as OpenCV 3-clause BSD license, see https://github.com/opencv/opencv_3rdparty/raw/dnn_samples_face_detector_20170830/res10_300x300_ssd_iter_140000.caffemodel
  * [openface.nn4.small2.v1.t7](https://raw.githubusercontent.com/pyannote/pyannote-data/master/openface.nn4.small2.v1.t7), Apache 2.0 License, https://github.com/pyannote/pyannote-data
- Finder 2:
  * face_recognition, MIT license, https://github.com/ageitgey/face_recognition/blob/master/LICENSE
- mysql-connector-python, GNU GPLv2 (with FOSS License Exception), webpage https://pypi.org/project/mysql-connector-python/

---

# How to install

Before you install and use this addon make sure you installed python and some python packages.

It is recommended to install the Finder 1 and optionally Finder 2 to improve
the detection of faces.

## Python Module face_recognition - Finder 2 (recommended)

The following was tested under Debian 9 and Debian 10.

    #!/bin/bash
    apt-get install -y cmake curl libboost-all-dev python3-setuptools python3 python3-pip
    pip3 install face_recognition
    pip3 install mysql-connector-python
    python3 -c "import face_recognition; print(face_recognition.__version__)"

## Python Module OpenCV - Finder 1 - (optional)

### OpenCV

#### OpenCV as Debian package

Versions of package python3-opencv at time the time of writing (04/2020)...

- Debian10 (stable) has version 3.2.0 what is NOT sufficient. DNN detection was introduced with 3.3.0.
- Debian11 (testing) has version 4.2.0 what is sufficient

Let's pretend the Debian package is version 4.x (the addon checks for min version 4.x)...

    apt install python3-opencv
    python3 -c "import cv2; print(cv2.__version__)"

### OpenCV from the Sources for Debian 10

The following was tested under Debian 10.

https://linuxize.com/post/how-to-install-opencv-on-debian-10/

    apt-get install build-essential cmake git pkg-config libgtk-3-dev \
    libavcodec-dev libavformat-dev libswscale-dev libv4l-dev \
    libxvidcore-dev libx264-dev libjpeg-dev libpng-dev libtiff-dev \
    gfortran openexr libatlas-base-dev python3-dev python3-numpy \
    libtbb2 libtbb-dev libdc1394-22-dev

    mkdir ~/opencv_build && cd ~/opencv_build
    git clone https://github.com/opencv/opencv.git
    git clone https://github.com/opencv/opencv_contrib.git

    cd ~/opencv_build/opencv
    mkdir build && cd build

    cmake -D CMAKE_BUILD_TYPE=RELEASE \
    -D CMAKE_INSTALL_PREFIX=/usr/local \
    -D INSTALL_C_EXAMPLES=ON \
    -D INSTALL_PYTHON_EXAMPLES=ON \
    -D OPENCV_GENERATE_PKGCONFIG=ON \
    -D OPENCV_EXTRA_MODULES_PATH=~/opencv_build/opencv_contrib/modules \
    -D BUILD_EXAMPLES=ON ..

How many CPU cores?

    nproc

Now use the number of cores from the command above for "make -jx"

    make -j2

    make install

    pkg-config --modversion opencv4

    python3 -c "import cv2; print(cv2.__version__)"

### Module Sklearn

Do not forget to install the module sklearn

    pip3 install sklearn

## Python Connection to Database

Make sure to have installed mysql-connector-python

    pip3 install mysql-connector-python

## Exiftool (optionally)

Install Exiftool if you want to write the names into the images.

    apt-get install libimage-exiftool-perl perl-doc 

Why Exiftool?

Exiftool writes the keywords reliably and in a standard way, see https://exiftool.org/TagNames/MWG.html

Why should you write the names into the image?

The names are stored where they belong to and can't get lost.
You can download the images and still have the names in a way other standard
programms can display to you, e.g. darktable, shotwell, good file managers,...

---

# How to configure as Admin

The [settings page](admin/addons/faces) includes a self check and shows the installed versions. 
(That's why it takes a little bit longer to load.)

If a finder is not available the page shows a warning and disables the finder.

## Finder 1

This method was inspired by the article [OpenCV Face Recognition](https://www.pyimagesearch.com/2018/09/24/opencv-face-recognition/)
by Adrian Rosebrock.

Example configuration (admin setting)

    confidence=0.5;minsize=20

### confidence

Used to detect faces.

### minsize [pixel]

Used to detect faces. All faces below the size 20 pixel are ignored.

## Finder 2

At [github](https://github.com/ageitgey/face_recognition)

Example configuration (admin setting)

    tolerance=0.6;model=hog

### tolerance... 

0.6... is the default.  

Taking a smaller value, for example 0.5 would be more strict.
You might think to use a smaller value if you have many images of same persons.

### model

hog... is the default, HOG... Histogram of Oriented Gradients  
cnn... is more accurate but much slower, CNN... Convolutional Neural Network. Note: The Raspberry Pi is not capable of running the CNN detection method.  

To give you an example: if a face takes 0.5 seconds using HOG it would take about 25 seconds for CNN. Of course this depends an your machine.

Read something like [this](https://medium.com/@ageitgey/machine-learning-is-fun-part-4-modern-face-recognition-with-deep-learning-c3cffc121d78) article if you want
to learn more about dlib an face_recognition.

## How to enable logging

The python scripts will log to a file name "faces.log".

1. To set the **log directory**: Activate the addon logrot under [addons](admin/addons/) and set a directory in the settings of the addon [logrot](admin/addons/logrot)
2. **Enable debugging** and set the **log level** under [admin/logs/](/admin/logs/)

The log file will be overwritten every time the python scripts start.

---

# How to use

Provided you uploaded some images to your channel...

1. Install the app [here](/apps/available)
2. Open the app. Result:
  * (Empty) encodings are created for every image of your channel
  * The face detection starts
  * **BE PATIENT** if you use the addon for the **very first time**. Reload the addon page after some time 
3. You will see frames around faces. Click on a frame and 
  * give the face a name, or
  * Choose a contact from the list. (A notification will be sent to the contact if selected from the list.)
  * The face recognition will start to guess the name in other faces.
  * Confirm guessed names to improve the recognition.
4. Set Permissions:
  * View: click the lock icon
  * Write: long click the lock icon
     - (There is still a bug. The permission is set correctly but not shown if the dialog is opened again.)

## FAQs

### What is the difference between "detect" and "recognize"?

detect... this is a face (draw a frame around the face)  
recognize... this face shows "Jane" 

### When is the face detection and recognition started?

It is started every time you

- load the addon page,
- set a name for a face.

Parallel running processes are prevented. The app will tell you "Face detection is still busy."

The face detection and recognition will process the face encodings for all channels
that have the face detection installed (and in use).

### Is it possible to tag names without Python?

Yes, if you have clones.

At least one clone must have installed Python and at least on of the finders to
detect faces. Face encodings and names are synchronized between
the channel clones. Make sure the clones have the addon installed. Once a face is 
detected there is no need for the Python scripts, except that they will
guess the names for other faces.

## Permissions

It is up to you to decide who can see your tagged images. Use the lock icon to set view and write permissions.

### Names

... every name holds the information

- what user is the owner
- what permission do other users have (as individuals or groups)

### Encodings

Face endodings have no special permission. The permission of the image is used.

### Checks

If the addon is opened:

- Check if the observer (observer... who is loocking at the addon) is known.
- Check if the oberserver has the permission to
  * view the addon faces,
  * view images/files at all.
- Check every image if the observer has the permission to view this particular image.
- Show names only that are in the contact list of the owner (and are accepted by the contact).

Only the owner (of the addon / images) can set permissions.

Only observers having write permissions granted by the owner are allowed tag faces.

## Thoughts on Privacy

A machine/programm does not care about privacy. It's the users that are responsible.

Some information to help you to think and decide yourself...

### Step 1)

As soon as somebody 

- who has the addon "faces" installed AND
- uploads a picture

the programm starts to detect faces in the images and stores

- the location of the face, what image at what position and
- the "encoding" for each face, basically an array of 128 floating numbers.

At the moment the addon uses two different methods for face detection and
recognition. There are many more available and more will emerge. (Generally it should be
possible to replace the exiting methods or integrate others into the addon. This was a design
goal.)

### Step 2)

Still the addon does not "know" of any person that belong to the detected faces.
Now the user steps in and starts to tell the face recognition what name belongs
to what face encoding (array of numbers). The programm now starts (using both methods) to compare
the face encodings and make guesses who it is.  
The list of names is stored in a separate database table.

## Deletion of Data

### Admin - for all Users

The admin can delete the encodings and names for all users unter [admin/addons/faces/](/admin/addons/faces/).
This will delete:

- table with all face encodings
- table with all names

### User

A user can delete his face encodings and names by appending "/remove" at
the end of the URL and presses ENTER. Example:

    https://my-domain.org/faces/my-nick-name/remove

### Other ways to clear Faces and Names

#### If you have clones of your channel...

What happens if all faces and names are deleted on clone A of a channel?

- The faces and names are deleted from the database tables faces_encoding and faces_person for the channel (clone A).
- The next face detecting will start as soon as the addon is opened for channel (clone A).
- The addon detects that the face detection has to be run for all images of channel (clone A).
- The addon creates an empty encoding for every "new" image and syncs the empty encodings to the clones.
- The empty encodings will overwrite the existing encodings on the clones.

Technical background

Every image of a channel will result in a face encoding. The face will be flaged as
"has no face" in the database if no face is detected. The image and encoding are and ignored until then.
They will be exported/imported too. Encodings with no face will also be synchronized to channel clones to signalize the clones that
there is no need to look into the images.

#### Technically (not visible for a User)

- A face (encoding) is deleted from the database as soon as "its" image is deleted. (There is no face without image.)
- A name is deleted as soon as it is not used for any face in any image.

## TODOs / Ideas

- double click to open image full size (usefull for large images containing small faces)

## Additional Sources of information

For testing (can be deleted)

    pip3 install --upgrade imutils
    pip3 install scikit-learn