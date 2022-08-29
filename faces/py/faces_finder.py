import os
import time
from deepface.commons import functions, distance as dst
from deepface import DeepFace
from deepface.detectors import FaceDetector
from deepface.extendedmodels import Age
import cv2
import numpy as np
from datetime import datetime
import random
import string
import logging


class Finder:

    def __init__(self):

        self.model_names = []
        self.model_name_default = "Facenet512"  # default
        self.conf_models = ""
        self.models = {}
        # 'DeepID' gives invalid results
        self.models_valid = ['VGG-Face', 'Facenet', 'Facenet512', 'ArcFace', 'OpenFace', 'DeepFace', 'SFace']

        self.detector_names = []
        self.detector_name_default = "retinaface"  # default
        self.detectors = {}
        self.detectors_valid = ["opencv", "ssd", "mtcnn", "retinaface", "mediapipe"]
        self.detector_name = "retinaface"  # default, can be set by caller

        self.attributes_valid = ["Gender", "Age", "Race", "Emotion"]
        self.attributes_names = []
        self.attributes_models = {}

        self.age_model = None
        self.emotion_model = None
        self.gender_model = None
        self.race_model = None
        self.conf_demography = ""
        self.is_analyse = True  # default, can be set by caller
        self.is_analyse_emotion = False  # default, can be set by caller
        self.is_analyse_age = False  # default, can be set by caller
        self.is_analyse_gender = False  # default, can be set by caller
        self.is_analyse_race = False  # default, can be

        self.min_face_width_percent = 5  # in %, can be set by caller
        self.min_face_width_pixel = 50  # in px, can be set by caller
        self.css_position = True
        self.min_width_train = 224
        self.min_width_result = 50

    # parameter csv
    #   example:
    #     "model=VGG-Face;detector_backend=retinaface;analyse=on;age=on;face=on;emotion=on;gender=on;race=off;distance_metric=cosine;min-face-width=30"
    #
    # models for face recognition...
    #   ...according to deepface author 'serengil' in unit_tests.py
    #   robust models are:
    #  'VGG-Face', 'Facenet', 'Facenet512', 'ArcFace'
    #
    # detector
    #   ...according to deepface author 'serengil' mtcnn and retinaface give better results.
    #   Opencv is faster but not as accurate.
    #
    # facial attributes
    # Use "on"/"off" to switch the analysis of facial attributes on. They are off be default.
    #   - off switches all attributes on/off      , default is on
    #   - emotion switches all emotion analysis on/off, default is on
    #   - age     switches all age analysis on/off    , default is on
    #   - gender  switches all gender analysis on/off , default is on
    #   - race    switches all race analysis on/off   , default is on
    #
    # min face width in %
    #   min-face-width=30
    #
    # css_position
    #   ...position of face in images
    #   default is "on"
    #   - css_position=on  margins left, right, top, bottom in percent
    #   - css_position=off x, y, w, h in pixel starting at top left corner
    #  
    def configure(self, json):

        # --------------------------------------------------------------------------------------------------------------
        # not set by user in frontend

        if "finder" in json:
            if "valid_detectors" in json["finder"]:
                self.detectors_valid = json["finder"]["valid_detectors"]

            if "valid_models" in json["finder"]:
                self.models_valid = json["finder"]["valid_models"]

            if "valid_attributes" in json["finder"]:
                self.attributes_valid = json["finder"]["valid_attributes"]

            if "use_css_position" in json["finder"]:
                self.css_position = json["finder"]["use_css_position"]

        logging.debug("config: detectors_valid=" + str(self.detectors_valid))
        logging.debug("config: valid_models=" + str(self.models_valid))
        logging.debug("config: valid_attributes=" + str(self.attributes_valid))
        logging.debug("config: use_css_position=" + str(self.css_position))

        # --------------------------------------------------------------------------------------------------------------
        # set by user in frontend

        if "detectors" in json:
            for el in json["detectors"]:
                if el[1]:
                    if el[0] in self.detectors_valid and not el[0] in self.detector_names:
                        self.detector_names.append(el[0])
                        self.detectors[el[0]] = None
                    else:
                        logging.warning(str(el[0]) +
                                        " is not a valid detector (or already set). Hint: The detector name is case " +
                                        "sensitive.  Loading default model if no more valid model name is given...")
                        logging.warning("Valid detectors are: " + str(self.detectors_valid))
        if len(self.detector_names) == 0:
            self.detector_names.append(self.detector_name_default)  # default
        logging.debug("config: detectors=" + str(self.detector_names))

        if "models" in json:
            for el in json["models"]:
                if el[1]:
                    if el[0] in self.models_valid and el[0] not in self.model_names:
                        self.model_names.append(el[0])
                        self.models[el[0]] = None
                    else:
                        logging.warning(str(el) + " is not a valid model (or already set). Hint: The model name is case " +
                                        "sensitive.  Loading default model if no more valid model name is given...")
                        logging.warning("Valid models are: " + str(self.models_valid))
        logging.debug("config: models=" + str(self.model_names))

        if "demography" in json:
            for el in json["demography"]:
                if el[1]:
                    if el[0] in self.attributes_valid and el[0] not in self.attributes_names:
                        self.attributes_names.append(el[0])
                        self.attributes_models[el[0]] = None
                    else:
                        logging.warning(
                            str(el) + " is not a valid demography model (or already set). Hint: The model name is case " +
                            "sensitive.  Loading default model if no more valid model name is given...")
                        logging.warning("Valid models are: " + str(self.attributes_valid))
        logging.debug("config: demography models=" + str(self.attributes_names))

        if "min_face_width_detection" in json:
            for element in json["min_face_width_detection"]:
                if element[0] == "pixel":
                    self.min_face_width_pixel = element[1]
                elif element[0] == "percent":
                    self.min_face_width_percent = element[1]
        logging.debug("config: min_face_width_pixel=" + str(self.min_face_width_pixel))
        logging.debug("config: min_face_width_percent=" + str(self.min_face_width_percent))

        if "min_face_width_recognition" in json:
            for element in json["min_face_width_recognition"]:
                if element[0] == "training":
                    self.min_width_train = element[1]
                elif element[0] == "result":
                    self.min_width_result = element[1]
        logging.debug("config: min_width_train=" + str(self.min_width_train))
        logging.debug("config: min_width_result=" + str(self.min_width_result))

        if len(self.model_names) == 0:
            self.model_names.append(self.model_name_default)  # default

    # load the detector and all models
    def load(self):
        if len(self.model_names) == 0:
            self.model_names.append(self.model_name_default)  # default
        for model_name in self.model_names:
            if self.models is None:
                self.models = {}
            self.models[model_name] = DeepFace.build_model(model_name)
            logging.debug("loaded face recognition model " + model_name)
            self.log_ram()
        self.conf_models = (','.join(self.model_names))
        self.detector = FaceDetector.build_model(self.detector_name)
        logging.debug("loaded detector backend " + self.detector_name)

        if self.is_analyse_emotion:
            self.emotion_model = DeepFace.build_model('Emotion')
            logging.debug("Emotion model was loaded")
            self.log_ram()
        if self.is_analyse_age:
            self.age_model = DeepFace.build_model('Age')
            logging.debug("Age model was loaded")
            self.log_ram()
        if self.is_analyse_gender:
            self.gender_model = DeepFace.build_model('Gender')
            logging.debug("Gender model was loaded")
            self.log_ram()
        if self.is_analyse_race:
            self.race_model = DeepFace.build_model('Race')
            logging.debug("Race model was loaded")
            self.log_ram()

    def is_loaded(self):
        if self.models is None:
            return False
        else:
            return True

    def is_analyse_on(self):
        if self.is_analyse_emotion:
            return True
        if self.is_analyse_age:
            return True
        if self.is_analyse_gender:
            return True
        if self.is_analyse_race:
            return True
        return False

    def analyse(self, path, os_path_on_server):
        start_time = time.time()
        faces_to_return = []
        logging.debug("read facial attributes in image " + path + ", os path = " + os_path_on_server)
        img = cv2.imread(os_path_on_server)
        try:
            # The same face detection is done in function "detect".
            # Think about how to do the face detection once for
            # - face analysis > function analyse
            # - face representation (used for face recognition) > function detect
            #
            # Faces store list of detected_face and region pair
            faces = FaceDetector.detect_faces(self.detector, self.detector_name, img, align=True)
        except:
            # Return an empty face to signal that no face is found in this file.
            # This prevents the detection to try to find faces again and again (but never finds one)
            logging.debug("Found no faces in image " + path)
            empty_face = self.get_empty_face_analyse(path, start_time)
            faces_to_return.append(empty_face)
            return faces_to_return
        logging.debug("Found " + str(len(faces)) + " faces ")

        image_height, image_width, c = img.shape

        counter = 0
        for face, (x, y, w, h) in faces:
            w_percent = int(w * 100 / image_width)
            if (w_percent < self.min_face_width_percent) or (
                    w < self.min_face_width_pixel):  # discard small detected faces
                logging.debug(
                    "Ignore face because width=" + str(w_percent) + "% or " + str(w) +
                    "px is is less than minimum " + str(self.min_face_width_percent) + "% or " +
                    str(self.min_face_width_pixel) + "px")
                continue
            counter += 1
            tic = time.time()
            region = [x, y, w, h]
            custom_face = img[y:y + h, x:x + w]
            face_to_return = []
            face_to_return.append(path)
            face_to_return.append(self.calculate_css_location(x, y, w, h, image_height, image_width))
            face_to_return.append(self.detector_name)

            # -----------------------------------
            # facial attributes emotion, age, gender, race

            emotions = []
            dominant_emotion = ""
            if self.emotion_model is not None:
                gray_img = functions.preprocess_face(
                    img=custom_face,
                    target_size=(48, 48),
                    grayscale=True,
                    enforce_detection=False,
                    detector_backend=self.detector_name,
                    align=True)
                emotion_labels = ['Angry', 'Disgust', 'Fear', 'Happy', 'Sad', 'Surprise', 'Neutral']
                emotion_predictions = self.emotion_model.predict(gray_img)[0, :]
                sum_of_predictions = emotion_predictions.sum()
                for i in range(0, len(emotion_labels)):
                    emotion = []
                    emotion_label = emotion_labels[i]
                    emotion_prediction = 100 * emotion_predictions[i] / sum_of_predictions
                    emotion.append(emotion_label)
                    emotion.append(emotion_prediction)
                    emotions.append(emotion)
                dominant_emotion = emotion_labels[np.argmax(emotion_predictions)]
                logging.debug("dominant emotion: " + dominant_emotion)

            face_224 = None
            face_224 = functions.preprocess_face(
                img=custom_face,
                target_size=(224, 224),
                grayscale=False,
                enforce_detection=False,
                detector_backend=self.detector_name,
                align=True)

            apparent_age = -1
            if self.age_model is not None:
                age_predictions = self.age_model.predict(face_224)[0, :]
                apparent_age = Age.findApparentAge(age_predictions)
                logging.debug("age: " + str(int(apparent_age)))

            gender = ""
            if self.gender_model is not None:
                gender_prediction = self.gender_model.predict(face_224)[0, :]
                if np.argmax(gender_prediction) == 0:
                    gender = "W"
                elif np.argmax(gender_prediction) == 1:
                    gender = "M"
                logging.debug("gender: " + gender)

            races = []
            dominant_race = ""
            if self.race_model is not None:
                race_predictions = self.race_model.predict(face_224)[0, :]
                race_labels = ['asian', 'indian', 'black', 'white', 'middle eastern', 'latino hispanic']
                sum_of_predictions = race_predictions.sum()
                for i in range(0, len(race_labels)):
                    race = []
                    race_label = race_labels[i]
                    race_prediction = 100 * race_predictions[i] / sum_of_predictions
                    race.append(race_label)
                    race.append(race_prediction)
                    races.append(race)
                dominant_race = race_labels[np.argmax(race_predictions)]
                logging.debug("dominant race: " + dominant_race)

            toc = time.time()

            face_to_return.append(emotions)
            face_to_return.append(dominant_emotion)
            face_to_return.append(int(apparent_age))
            face_to_return.append(gender)
            face_to_return.append(races)
            face_to_return.append(dominant_race)
            face_to_return.append(datetime.utcnow())
            face_to_return.append(round(toc - tic, 5))

            faces_to_return.append(face_to_return)
            logging.debug("face processing took " + str(round(toc - tic, 3)) + " seconds")

        if len(faces_to_return) == 0:
            logging.debug("No face found in " + str(
                round(time.time() - start_time, 5)) + " seconds for facial attributes in " + path)
            faces_to_return.append(self.get_empty_face_analyse(path, start_time))
        else:
            logging.info(str(counter) + "(" + str(len(faces)) + ") faces in " + str(
                round(time.time() - start_time,
                      5)) + "s " + self.detector_name + " models=" + self.conf_demography + " " + path)
        return faces_to_return

    def detect(self, path, os_path_on_server, existing_models):
        start_time = time.time()
        faces_to_return = []
        logging.debug("reading image to get face representations  " + path + ", os path on server " + os_path_on_server)
        mtime = str(datetime.fromtimestamp(os.path.getmtime(os_path_on_server)))
        img = cv2.imread(os_path_on_server)
        tic = time.time()
        try:
            # The same face detection is done in function "analyse".
            # Think about how to do the face detection once for
            # - face analysis > function analyse
            # - face representation (used for face recognition) > function detect
            #
            # Faces store list of detected_face and region pair
            faces = FaceDetector.detect_faces(self.detector, self.detector_name, img, align=True)
        except:
            # Return an empty face to signal that no face is found in this file.
            # This prevents the detection to try to find faces again and again (but never finds one)
            logging.debug("Found no faces in image " + path)
            for model_name in self.model_names:
                # prevent that the combination of file AND detector AND model is found again as "new"
                faces_to_return.append(self.get_empty_face_detection(path, start_time, model_name, [0], 0, 0, mtime))
            return faces_to_return
        image_height, image_width, c = img.shape
        toc = time.time()
        duration_detection = round(toc - tic, 5)
        logging.debug("Found " + str(len(faces)) + " faces in " + str(duration_detection) + " s, file= " + path)

        count_1 = 0  # for logging only
        count_2 = 0  # for logging only
        for face, (x, y, w, h) in faces:
            w_percent = int(w * 100 / image_width)
            if (w_percent < self.min_face_width_percent) or (
                    w < self.min_face_width_pixel):  # discard small detected faces
                logging.debug(
                    "Ignore face because width=" + str(w_percent) + "% or " + str(w) +
                    "px is is less than minimum of " + str(self.min_face_width_percent) + "% or " +
                    str(self.min_face_width_pixel) + "px")
                continue
            count_1 = count_1 + 1
            for model_name in self.model_names:
                if model_name in existing_models:
                    logging.debug("Ignoring model " + model_name + " in file because it exists, file= " + path)
                    continue
                tic = time.time()
                region = [x, y, w, h]
                custom_face = img[y:y + h, x:x + w]

                # -----------------------------------
                # representations for (later) face recognition

                model = self.models.get(model_name)

                input_shape = functions.find_input_shape(model)
                input_shape_x = input_shape[0]
                input_shape_y = input_shape[1]

                custom_face = functions.preprocess_face(
                    img=custom_face,
                    target_size=(input_shape_y, input_shape_x),
                    enforce_detection=False,
                    detector_backend=self.detector_name,
                    align=True)
                # check preprocess_face function handled
                representation = []
                if custom_face.shape[1:3] == input_shape:
                    representation = model.predict(custom_face)[0, :]
                else:
                    logging.debug("Ignoring face because the preprocessing found a different input shape (" + str(
                        custom_face.shape[1:3]) + ") than the FaceDetector (" + str(
                        input_shape) + ") detector=" + self.detector_name + ", model=" + model_name + ", file= " + path)
                    # prevent that the combination of file AND detector AND model is found again as "new"
                    faces_to_return.append(
                        self.get_empty_face_detection(path, start_time, model_name, [x, y, w, h], 0, 0, mtime))
                    continue
                count_2 = count_2 + 1

                toc = time.time()
                face_to_return = []
                face_to_return.append(self.get_random_string())
                face_to_return.append(path)
                face_to_return.append(self.calculate_css_location(x, y, w, h, image_height, image_width)),
                face_to_return.append(w)
                face_to_return.append(w_percent)
                face_to_return.append(0)  # face_nr
                face_to_return.append('')  # name
                face_to_return.append('')  # name_recognized
                face_to_return.append('')  # time_name_set
                face_to_return.append(self.detector_name)
                face_to_return.append(model_name)
                face_to_return.append(duration_detection)
                face_to_return.append(round(toc - tic, 5))
                face_to_return.append(datetime.utcnow())
                face_to_return.append(np.float64(representation))  # most models use float32 (Facenet512 uses float64)
                face_to_return.append(-1)  # distance
                face_to_return.append('')  # distance_metric
                face_to_return.append(0.0)  # duration_recognized
                face_to_return.append('')  # directory
                face_to_return.append('')  # exif_date
                face_to_return.append(mtime)  # mtime (time the file was last modified)

                faces_to_return.append(face_to_return)
                logging.debug("creation of face representations took " +
                              str(round(toc - tic, 5)) + " seconds using detector=" +
                              self.detector_name + " for recognition model=" + model_name)

        if len(faces_to_return) == 0:
            logging.debug("No face found in " + str(
                round(time.time() - start_time, 5)) + " seconds for face representations in " + path)
            for model_name in self.model_names:
                # prevent that the combination of file AND detector AND model is found again as "new"
                faces_to_return.append(self.get_empty_face_detection(path, start_time, model_name, [0], 0, 0, mtime))
        else:
            logging.info(str(count_1) + " (" + str(len(faces)) + ") faces, " + str(count_2) + " embeddings " +
                         str(round(time.time() - start_time,
                                   5)) + "s " + self.detector_name + " models=" + self.conf_models +
                         " " + path)
        return faces_to_return

    def get_random_string(self):
        s = ""
        for i in range(15):
            s += random.choice(string.ascii_letters)
        return s

    def get_empty_face_detection(self, path, start_time, model_name, pos, width, percent, mtime):
        empty_face = [
            self.get_random_string(),
            path,
            pos,
            width,
            percent,
            0,
            '',  # name
            '',  # name_recognized
            '',  # time_named
            self.detector_name,
            model_name,
            round(time.time() - start_time, 5),
            0.0,
            datetime.utcnow(),
            [0.0],  # representation
            -1,  # distance
            '',  # distance_metrics
            0.0,  # duration_recognized
            '',  # directory,
            '',  # exif_date,
            mtime  # mtime
        ]
        return empty_face

    def get_empty_face_analyse(self, path, start_time):
        empty_face = [
            path,
            [0],
            self.detector_name,
            '',
            '',
            '',
            '',
            '',
            '',
            datetime.utcnow(),
            round(time.time() - start_time, 5)
        ]
        return empty_face

    def calculate_css_location(self, x, y, w, h, h_img, w_img):
        if not self.css_position:
            return [x, y, w, h]
        margin_left_percent = (x * 100) / w_img
        if margin_left_percent < 0:
            margin_left_percent = 0
        margin_top_percent = (y * 100) / h_img
        if margin_top_percent < 0:
            margin_top_percent = 0
        margin_right_percent = (w_img - x - w) * 100 / w_img
        if margin_right_percent < 0:
            margin_right_percent = 0
        margin_bottom_percent = (h_img - y - h) * 100 / h_img
        if margin_bottom_percent < 0:
            margin_bottom_percent = 0  # happened for mtcnn
        location_css = [
            int(round(margin_left_percent)),
            int(round(margin_right_percent)),
            int(round(margin_top_percent)),
            int(round(margin_bottom_percent))]
        return location_css

    def log_ram(self):
        total_memory, used_memory, free_memory = map(int, os.popen('free -t -el').readlines()[-1].split()[1:])
        logging.debug("RAM memory used: " + str(round((used_memory / total_memory) * 100, 2)) + " %")
