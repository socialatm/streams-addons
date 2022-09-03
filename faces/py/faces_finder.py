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
import pandas as pd
import itertools


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

        self.util = None
        self.ram_allowed = 80

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
                        logging.warning(
                            str(el) + " is not a valid model (or already set). Hint: The model name is case " +
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

    def preprocess_face_224(self, face, detector_name):
        face_224 = functions.preprocess_face(
            img=face,
            target_size=(224, 224),
            grayscale=False,
            enforce_detection=False,
            detector_backend=detector_name,
            align=True)
        return face_224

    def analyse(self, face, detector_name, existing_face):
        tic = time.time()

        # -----------------------------------
        # facial attributes emotion, age, gender, race

        attributes = {}

        if existing_face is not None and existing_face.emotion != "":
            # copy existing attribute
            attributes['emotions'] = existing_face.emotions
            attributes['emotion'] = existing_face.emotion
        elif "Emotion" in self.attributes_names:
            # extract attribute
            attributes['emotions'] = []
            if self.attributes_models["Emotion"] is None:
                logging.debug("loading model Emotion")
                self.attributes_models["Emotion"] = DeepFace.build_model('Emotion');
            emotion_model = self.attributes_models["Emotion"]
            gray_img = functions.preprocess_face(
                img=face,
                target_size=(48, 48),
                grayscale=True,
                enforce_detection=False,
                detector_backend=detector_name,
                align=True)
            emotion_labels = ['Angry', 'Disgust', 'Fear', 'Happy', 'Sad', 'Surprise', 'Neutral']
            emotion_predictions = emotion_model.predict(gray_img)[0, :]
            sum_of_predictions = emotion_predictions.sum()
            emotions = []
            for i in range(0, len(emotion_labels)):
                emotion = []
                emotion_label = emotion_labels[i]
                emotion_prediction = 100 * emotion_predictions[i] / sum_of_predictions
                emotion.append(emotion_label)
                emotion.append(emotion_prediction)
                emotions.append(emotion)
            attributes['emotions'] = str(emotions)
            emotion = emotion_labels[np.argmax(emotion_predictions)]
            logging.debug("emotion: " + emotion)
            attributes['emotion'] = emotion
        else:
            # default value
            attributes['emotions'] = ""
            attributes['emotion'] = ""

        face_224 = None

        if existing_face is not None and existing_face.age != -1:
            attributes['age'] = existing_face.age
        elif "Age" in self.attributes_names:
            if face_224 is None:
                face_224 = self.preprocess_face_224(face, detector_name)
            if self.attributes_models["Age"] is None:
                logging.debug("loading model Age")
                self.attributes_models["Age"] = DeepFace.build_model('Age');
            age_model = self.attributes_models["Age"]
            age_predictions = age_model.predict(face_224)[0, :]
            apparent_age = Age.findApparentAge(age_predictions)
            logging.debug("age: " + str(int(apparent_age)))
            attributes['age'] = apparent_age
        else:
            attributes['age'] = -1

        if existing_face is not None and existing_face.gender != "":
            attributes['gender'] = existing_face.gender
            attributes['gender_prediction'] = existing_face.gender_prediction
        elif "Gender" in self.attributes_names:
            if face_224 is None:
                face_224 = self.preprocess_face_224(face, detector_name)
            if self.attributes_models["Gender"] is None:
                logging.debug("loading model Gender")
                self.attributes_models["Gender"] = DeepFace.build_model('Gender');
            gender_model = self.attributes_models["Gender"]
            gender_prediction = gender_model.predict(face_224)[0, :]
            if np.argmax(gender_prediction) == 0:
                gender = "W"
            elif np.argmax(gender_prediction) == 1:
                gender = "M"
            logging.debug("gender_prediction: " + str(gender_prediction))
            logging.debug("gender: " + gender)
            attributes['gender'] = gender
            attributes['gender_prediction'] = gender_prediction[0]
        else:
            attributes['gender'] = ""
            attributes['gender_prediction'] = ""

        if existing_face is not None and existing_face.race != "":
            attributes['races'] = existing_face.races
            attributes['race'] = existing_face.race
        elif "Race" in self.attributes_names:
            races = []
            if face_224 is None:
                face_224 = self.preprocess_face_224(face, detector_name)
            if self.attributes_models["Race"] is None:
                logging.debug("loading model Race")
                self.attributes_models["Race"] = DeepFace.build_model('Race');
            race_model = self.attributes_models["Race"]
            race_predictions = race_model.predict(face_224)[0, :]
            race_labels = ['asian', 'indian', 'black', 'white', 'middle eastern', 'latino hispanic']
            sum_of_predictions = race_predictions.sum()
            for i in range(0, len(race_labels)):
                race = []
                race_label = race_labels[i]
                race_prediction = 100 * race_predictions[i] / sum_of_predictions
                race.append(race_label)
                race.append(race_prediction)
                races.append(race)
            attributes['races'] = str(races)
            # logging.debug("races: " + str(races))
            dominant_race = race_labels[np.argmax(race_predictions)]
            attributes['race'] = dominant_race
            logging.debug("dominant race: " + dominant_race)
        else:
            attributes['races'] = ""
            attributes['race'] = ""

        toc = time.time()
        logging.debug("face processing took " + str(round(toc - tic, 3)) + " seconds")
        return attributes

    def detect(self, path, os_path_on_server, detector_name, df):

        df_detector = df.loc[
            (df['file'] == path) &
            (df['detector'] == detector_name)]

        if not self.go_on(df_detector):
            return [df, False]

        if self.detectors[detector_name] is None:
            logging.debug("loading detector " + detector_name)
            self.detectors[detector_name] = FaceDetector.build_model(detector_name)
            if not self.util.check_ram(self.ram_allowed):
                return [df, False]  # the caller will do a ram check as well and exit

        detector = self.detectors[detector_name]
        start_time = time.time()
        logging.debug(path + " detecting faces....")
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
            faces = FaceDetector.detect_faces(detector, detector_name, img, align=True)
        except:
            # Return an empty face to signal that no face is found in this file.
            # This prevents the detection to try to find faces again and again (but never finds one)
            logging.debug("Found no faces in image " + path)
            for model_name in self.model_names:
                # prevent that the combination of file AND detector AND model is found again as "new"
                df = self.add_empty_face_detection(
                    path, round(time.time() - tic, 5), model_name, [0], 0, 0, mtime, df, detector_name)
            return [df, True]
        image_height, image_width, c = img.shape
        toc = time.time()
        duration_detection = round(toc - tic, 5)
        logging.debug(str(len(faces)) + " faces " + str(duration_detection) + " s  " + path)

        success = {}
        count_1 = 0  # for logging only
        count_2 = 0  # for logging only

        # ---------------------------------
        # iterate every face in this image
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

            # ----------------------------------------------------------------------------------------------------------
            # face attributes

            # Does a face for this detector exist already?
            position = self.calculate_css_location(x, y, w, h, image_height, image_width),
            same_face_for_detector = self.get_same_face(df_detector, position)  # face holding attributes or empty face
            # 1. find, or 2. complete, or 3. just copy existing facial attributes
            attributes = self.analyse(face, detector_name, same_face_for_detector)

            # ----------------------------------------------------------------------------------------------------------
            # face recognition - create face embeddings
            #
            # iterate activated models
            for model_name in self.model_names:
                if model_name not in success:
                    success[model_name] = False

                existing_face_for_model = df_detector.loc[(df_detector['model'] == model_name)]
                if len(existing_face_for_model) != 0:

                    # -------------------------------------------------------------------------------------------------
                    # embedding for this model does exist

                    success[model_name] = True  # do not add empty face later on
                    # copy facial attributes
                    df.loc[(df['id'] == existing_face_for_model.id),
                           ['emotions', 'emotion', 'age', 'gender', 'gender_prediction', 'races', 'race']] = [
                        str(existing_face_for_model.emotions[0]),
                        existing_face_for_model.emotion[0],
                        existing_face_for_model.age[0],
                        existing_face_for_model.gender[0],
                        existing_face_for_model.gender_prediction[0],
                        existing_face_for_model.races[0],
                        existing_face_for_model.race[0]]
                else:

                    # -------------------------------------------------------------------------------------------------
                    # create an embedding for this model of the face

                    if self.models[model_name] is None:
                        logging.debug("loading model " + model_name)
                        self.models[model_name] = DeepFace.build_model(model_name)
                        if not self.util.check_ram(self.ram_allowed):
                            return [df, True]  # the caller will do a ram check as well and exit

                    logging.debug(str(count_1) + " " + detector_name + " " + model_name + " " + path)
                    model = self.models[model_name]
                    # if model_name in existing_models:
                    #     logging.debug("Ignoring model " + model_name + " in file because it exists, file= " + path)
                    #     continue
                    tic = time.time()
                    region = [x, y, w, h]
                    custom_face = img[y:y + h, x:x + w]

                    # -----------------------------------
                    # representations for (later) face recognition

                    input_shape = functions.find_input_shape(model)
                    input_shape_x = input_shape[0]
                    input_shape_y = input_shape[1]

                    custom_face = functions.preprocess_face(
                        img=custom_face,
                        target_size=(input_shape_y, input_shape_x),
                        enforce_detection=False,
                        detector_backend=detector_name,
                        align=True)
                    # check preprocess_face function handled
                    representation = []
                    if custom_face.shape[1:3] == input_shape:
                        representation = model.predict(custom_face)[0, :]
                    else:
                        logging.debug("Ignoring face because the preprocessing found a different input shape (" + str(
                            custom_face.shape[1:3]) + ") than the FaceDetector (" + str(
                            input_shape) + ") detector=" + detector_name + ", model=" + model_name + ", file= " + path)
                        # prevent that the combination of file AND detector AND model is found again as "new"
                        df = self.add_empty_face_detection(path, duration_detection, model_name,
                                                           [x, y, w, h], 0, 0, mtime, df, detector_name)
                        success[model_name] = True
                        continue
                    count_2 = count_2 + 1
                    toc = time.time()

                    row = pd.Series(
                        [self.get_random_string(),
                         path,
                         self.calculate_css_location(x, y, w, h, image_height, image_width),
                         w,
                         w_percent,
                         0,  # face_nr
                         '',  # name
                         '',  # name_recognized
                         '',  # time_name_set
                         detector_name,
                         model_name,
                         duration_detection,
                         round(toc - tic, 5),
                         datetime.utcnow(),
                         np.float64(representation),  # most models use float32 (Facenet512 uses float64)
                         -1,  # distance
                         '',  # distance_metric
                         0.0,  # duration_recognized
                         '',  # directory
                         '',  # exif_date
                         mtime,
                         attributes['emotions'],
                         attributes['emotion'],
                         attributes['age'],
                         attributes['gender'],
                         attributes['gender_prediction'],
                         attributes['races'],
                         attributes['race']], index=df.columns)

                    df = df.append(row, ignore_index=True)

                    logging.debug("creation of face representations took " +
                                  str(round(toc - tic, 5)) + " seconds using detector=" +
                                  detector_name + " for recognition model=" + model_name)
                    success[model_name] = True

        if count_1 == 0:
            logging.debug("No face found in " + str(
                round(time.time() - start_time, 5)) + " seconds for face representations in " + path)
        for model_name in self.model_names:
            if not success[model_name]:
                # prevent that the combination of file AND detector AND model is found again as "new"
                df = self.add_empty_face_detection(path, duration_detection, model_name, [0], 0, 0, mtime,
                                                   df, detector_name)
        else:
            logging.info(str(count_1) + " (" + str(len(faces)) + ") faces, " + str(count_2) + " embeddings " +
                         str(round(time.time() - start_time,
                                   5)) + "s " + detector_name + " models=" + str(self.model_names) +
                         " " + path)
        return [df, True]

    def get_same_face(self, df, position):
        if len(position) < 2:
            position = list(sum(position, []))  # sometimes the position comes as tuple
        for face in df.itertuples():
            pos = face.position
            if self.util.is_same_face(pos, position):
                return face
        return None

    def get_random_string(self):
        s = ""
        for i in range(15):
            s += random.choice(string.ascii_letters)
        return s

    def go_on(self, df):
        # check if this image need to be processed
        if len(df) == 0:
            return True
        if df[df.isin(self.model_names).any(axis=1)].empty:
            return True
        for attr in self.attributes_names:
            if attr.lower() in ["emotion", "gender", "race"]:
                if '' in df[attr.lower()].values:
                    return True
            else:
                if -1 in df['age'].values:
                    return True
        return False

    def add_empty_face_detection(self, path, duration_detection, model_name, pos, width, percent, mtime, faces_df,
                                 detector_name):
        row = pd.Series(
            [self.get_random_string(),
             path,
             pos,
             width,
             percent,
             0,
             '',  # name
             '',  # name_recognized
             '',  # time_named
             detector_name,
             model_name,
             duration_detection,
             0.0,
             datetime.utcnow(),
             [0.0],  # representation
             -1,  # distance
             '',  # distance_metrics
             0.0,  # duration_recognized
             '',  # directory,
             '',  # exif_date,
             mtime,  # mtime
             "",  # emotions
             "",  # emotion
             -1,  # age
             "",  # gender
             "",  # races
             ""  # race
             ], index=faces_df.columns)

        faces_df = faces_df.append(row, ignore_index=True)
        return faces_df

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
        location_css = [int(round(margin_left_percent)),
                        int(round(margin_right_percent)),
                        int(round(margin_top_percent)),
                        int(round(margin_bottom_percent))]
        return location_css

    def log_ram(self):
        total_memory, used_memory, free_memory = map(int, os.popen('free -t -el').readlines()[-1].split()[1:])
        logging.debug("RAM memory used: " + str(round((used_memory / total_memory) * 100, 2)) + " %")
