import importlib
import os
import pickle
import pandas as pd
import time
import datetime
import logging
import sys
import faces_exiftool
import faces_util

deepface_spec = importlib.util.find_spec("deepface")
if deepface_spec is not None:
    import faces_finder
    import faces_recognizer


class Worker:

    def __init__(self):
        self.log_data = False
        self.finder = None
        self.recognizer = None
        self.dirImages = None
        self.proc_id = None
        self.db = None
        self.file_name_face_representations = "faces.pkl"
        self.file_name_face_representations_dbg = "faces_debug.csv"
        self.file_name_facial_attributes = "facial_attributes.csv"
        self.file_name_face_names = "faces.csv"
        self.file_name_faces_statistic = "faces_statistics.csv"
        self.file_name_models_statistic = "models_statistics.csv"
        self.dir_addon = "faces"

        # Watch this!
        # What happens if self.keep_history = True
        # The file .faces.pkl will be written if the face recognition finds something.
        # Q: Why does it matter?
        # A: In case the face recognition runs for ZOT/Nomad and the channel has clones
        #    the written file will trigger a file sync of .faces.pkl. between the clones (servers).
        self.keep_history = False  # True/False
        self.statistics_mode = False  # True/False

        self.columnNamesFaceRecognition = [
            'id',
            'file',
            'position',
            'face_nr',
            'name',
            'name_recognized',
            'time_named',
            'exif_date',
            'detector',
            'model',
            'duration_detection',
            'duration_representation',
            'time_created',
            'representation',
            'distance',
            'distance_metric',
            'duration_recognized',
            'directory'
        ]
        self.columnNameFacialAttributes = [
            'file',
            'position',
            'detector',
            'emotions',
            'dominant_emotion',
            'age',
            'gender',
            'races',
            'dominant_race',
            'created',
            'duration'
        ]
        self.columnsToIncludeAll = ["model", "detector", "duration_detection", "duration_representation",
                                    "time_created", "distance", "distance_metric", "duration_recognized"]
        self.columnsToInclude = ["model", "detector"]
        self.columnsSort = ["file", "position", "face_nr", "name", "name_recognized", "time_named", "exif_date",
                            "detector", "model"]
        self.timeLastAliveSignal = 0
        self.timeToWait = 10  # second
        self.timeBackUp = 60 * 5  # second
        self.lockFile = None
        self.RUNNING = "running"
        self.FINISHED = "finished"
        self.pid = ""
        self.exiftool = None
        self.removeDetectors = ""
        self.removeModels = ""
        self.IGNORE = "-ignore-"
        self.sort_column = "exif_date"
        self.sort_direction = True
        self.follow_sym_links = False

        self.folder = None
        self.faces_pkl = None
        self.faces_pkl_dbg = None
        self.faces_csv = None
        self.faces_attr = None
        self.faces_statistics = None
        self.models_statistics = None
        self.channel = None
        self.util = faces_util.Util()

    def set_finder(self, csv):
        deepface_spec = importlib.util.find_spec("deepface")
        if deepface_spec is not None:
            self.finder = faces_finder.Finder()
            self.finder.configure(csv)
        else:
            logging.error("FAILED to set finder. Reason: module deepface not found")

    def set_recognizer(self, csv):
        deepface_spec = importlib.util.find_spec("deepface")
        if deepface_spec is not None:
            self.recognizer = faces_recognizer.Recognizer()
            self.recognizer.configure(csv)
        else:
            logging.critical("FAILED to set finder. Reason: module deepface not found")

    def set_db(self, db):
        self.db = db
        logging.debug("database was set")

    def configure(self, csv):
        # example csv
        # optional-face-data=duration_detection,duration_representation,time_created,distance,distance_metric,duration_recognized;statistics_mode=True
        for element in csv.split(";"):
            conf = element.split("=")
            if len(conf) < 2:
                continue
            key = conf[0].strip().lower()
            value = conf[1].strip().lower()
            if key == 'optional-face-data':
                self.columnsToInclude = []
                s = conf[1].split(",")
                for column in s:
                    column = column.strip().lower()
                    if column in self.columnsToIncludeAll:
                        self.columnsToInclude.append(column)
                    else:
                        logging.warning(column + " is not a valid column in faces.csv")
                        logging.warning("Valid columns are: " + str(self.columnsToIncludeAll))
            elif key == 'log_data':
                if value == "on":
                    self.log_data = True
                if value == "off":
                    self.log_data = False
            elif key == 'statistics_mode' or key == 'statistics':
                if value == "on" or value == 'true':
                    self.statistics_mode = True
            elif key == 'history':
                if value == "on" or value == "true":
                    self.keep_history = True
            elif key == 'sort_column':
                column = value
                if column in self.columnsSort:
                    self.sort_column = column
            elif key == 'sort_direction':
                if value == "1":
                    self.sort_direction = False
            elif key == 'follow_sym_links':
                if value == "on":
                    self.follow_sym_links = True
            elif key == 'rm_detectors':
                self.removeDetectors = conf[1].strip()
                logging.debug("Configuration rm_detectors=" + self.removeDetectors)
            elif key == 'rm_models':
                self.removeModels = conf[1].strip()
                logging.debug("Configuration rm_models=" + self.removeModels)
        logging.debug("Configuration log_data=" + str(self.log_data))
        logging.debug("Configuration statistics_mode=" + str(self.statistics_mode))
        logging.debug("Configuration history=" + str(self.keep_history))
        logging.debug("Configuration sort_column=" + self.sort_column)
        logging.debug("Configuration sort_direction=" + str(self.sort_direction))
        logging.debug("Configuration follow_sym_links=" + str(self.follow_sym_links))

        self.set_finder(csv)
        self.set_recognizer(csv)

        self.exiftool = faces_exiftool.ExifTool()
        if not self.exiftool.getVersion():
            logging.warning(
                "Exiftool is not available 'exiftool -ver'. Exiftool is used to read the date and time from images.")
            self.exiftool = None

        self.util.is_css_position = self.finder.css_position

    def run(self, dir_images, proc_id, channel_id, doRecognize):
        logging.info("dir=" + dir_images + ", proc_id=" + proc_id + ", channel_id=" + str(
            channel_id) + ", " + self.finder.detector_name)
        self.dirImages = dir_images
        if os.access(self.dirImages, os.R_OK) is False:
            logging.error("can not read directory " + self.dirImages)
            self.stop()
        self.proc_id = proc_id
        self.write_alive_signal(self.RUNNING)
        # get all channel_id's (users) that have the app faces installed
        query = "select app_channel from app where app_plugin = 'faces' AND app_channel != 0"
        data = {}
        app_channels = self.db.select(query, data)
        # loop through every user who has the faces addon switched on
        for app_channel in app_channels:
            self.channel = app_channel[0]
            query = "SELECT folder FROM `attach` WHERE `uid` = %s AND `filename` = %s"
            data = (self.channel, self.file_name_face_names)
            folders = self.db.select(query, data)
            if len(folders) == 0:
                logging.info("no files " + self.file_name_face_names + " for channel_id " + str(self.channel))
                continue
            for f in folders:
                self.folder = f[0]
                self.process_dir()
            if doRecognize:
                if channel_id == 0 or channel_id == self.channel:
                    self.recognize(folders)
                else:
                    logging.debug("no recognition is run for channel id = " + str(self.channel))
                    continue
        self.write_alive_signal(self.FINISHED)
        logging.info("OK, Good bye...")
        logging.debug("OK")

    def process_dir(self):
        logging.debug("directory " + self.folder + " / channel " + str(self.channel) + " - start detecting/analyzing")

        if self.check_file_by_name(self.file_name_face_names) is False:
            return
        if self.check_file_by_name(self.file_name_face_representations) is False:
            return
        self.check_file_by_name(self.file_name_face_representations_dbg)
        self.check_file_by_name(self.file_name_facial_attributes)  # for cleanup

        self.cleanup()
        self.detect()
        self.analyse()

    # Find and store all face representations for face recognition.
    # The face representations are stored in binary pickle file.
    def detect(self):
        logging.debug("directory " + self.folder + " - 2. Step: detecting new images ---")

        df = self.get_face_representations()  # pandas.DataFrame holding all face representation

        images = self.add_new_images(df, self.file_name_face_representations)
        if len(images) == 0:
            logging.debug("directory " + self.folder + " - No new images for face detection in directory")
            return

        logging.info("directory " + self.folder + " - new images found - start detection and recognition")

        if not self.finder.is_loaded():
            self.finder.load()
            self.write_alive_signal(self.RUNNING)

        time_start_detection = time.time()
        logging.debug("directory " + self.folder + " - start to detect faces for " + str(len(images)) + " images")
        for image in images:
            # The list of models per detector can change. Do not find existing representations again.
            path = image[0]
            os_path_on_server = os.path.join(self.dirImages, image[1])
            existing_models = []
            if df is not None:
                existing_models = df.loc[
                    (df["file"] == path) & (df["detector"] == self.finder.detector_name), "model"].values
            faces = self.finder.detect(path, os_path_on_server, existing_models)
            if faces:
                if df is None:
                    df = pd.DataFrame(columns=self.columnNamesFaceRecognition)
                for face in faces:
                    if len(df) == 0:
                        df.loc[len(df.index)] = face
                    else:
                        df.loc[max(df.index) + 1] = face

            elapsed_time = time.time() - time_start_detection
            if elapsed_time > self.timeBackUp:
                if self.store_face_presentations(df) is False:
                    return
                logging.info("directory " + self.folder + " - store face representations: elapsed time=" + str(
                    elapsed_time) + " > " + str(self.timeBackUp))
                time_start_detection = time.time()
            self.write_alive_signal(self.RUNNING)
        # Why not storing the faces (faces.csv) at this point of time?
        # - The face detection and the creation of the face representations are cpu, memory and time-consuming
        # - In praxis
        #   o The user uploads some or many more pictures to a directory
        #   o The user opens the addon. This will start the face detection that runs for a couple of minutes
        #   o Meanwhile the user clicks on some faces and name the faces or changes some face names. The changes
        #     are written into to faces.csv
        # - After some time the face detection has ended and will write faces.csv.
        #   This overwrites the face names the user has set in the meantime.
        logging.info("directory " + self.folder + " - finished detection of faces in " + str(len(images)) + " images")
        df = self.write_exif_dates(df)

        if self.store_face_presentations(df) is False:
            return
        self.init_face_names(df)

    # Find and store all facial attributes, emotion, age, gender, race
    # What facial attributes to find is configurable, see method setFinder().
    def analyse(self):
        if not self.finder.is_analyse_on():
            return

        if self.check_file_by_name(self.file_name_facial_attributes) is False:
            return

        logging.debug("directory " + self.folder + " - 3. Step: analysing facial attributes ---")
        df = self.get_facial_attributes()  # pandas.DataFrame holding all facial attributes

        images = self.add_new_images(df, self.file_name_facial_attributes)
        if len(images) == 0:
            logging.debug("directory " + self.folder + " - No new images for analysis of facial attributes")
            return

        logging.info("directory " + self.folder + " - new images found - start analyzing attributes...")

        if not self.finder.is_loaded():
            self.finder.load()
            self.write_alive_signal(self.RUNNING)

        logging.debug("directory " + self.folder + " - start to analyse faces in " + str(len(images)) + " images")
        for image in images:
            path = image[0]
            os_path_on_server = os.path.join(self.dirImages, image[1])
            faces = self.finder.analyse(path, os_path_on_server)
            self.write_alive_signal(self.RUNNING)
            if faces:
                if df is None:
                    df = pd.DataFrame(columns=self.columnNameFacialAttributes)
                for face in faces:
                    df.loc[len(df.index)] = face

        logging.debug("directory " + self.folder + " - finished analyse of faces in " + str(len(images)) + " images")
        self.store_facial_attributes(df)

    # -------------------------------------------------------------
    # Basic steps:
    # 1. In every directory
    #    - Load all DataFrames holding the face representations (faces.pkl)
    #    - Load all DataFrames holding the names (faces.csv)
    #    - Write all names (set by the user) from faces.csv to faces.pkl
    # 2. Add all DataFrames to get one big DataFrame holding all face representation and known names
    # 4. Try to recognize known faces in every directory
    # 5. Check if the recognition found new faces or changed names
    # 6. Write the CSV (faces.csv) if the values for "name" or "name_recognized" have changed
    #
    def recognize(self, folders):
        logging.info("START COMPARING FACES for channel " + str(self.channel) + ". Loading all necessary data...")
        df = self.load_face_names(folders)
        if df is None:
            return

        # Loop through models
        # - loop through the list of ordered models (start parameter)
        # - optionally stop recognition after a match (start parameter)
        logging.debug("Get all faces with a name and with a face representation")
        df_no_name = df.loc[(df['name'] == "") & (df['name'] != self.IGNORE) & (df['representation'] != "") & (
                df['time_named'] == ""), ['id', 'representation', 'model', 'file', 'face_nr']]
        models = df.model.unique()
        for model in models:
            logging.debug("Start recognition using model=" + model + " Gathering faces as training data...")
            df_training_data = df.loc[(df['model'] == model) & (df['name'] != "") & (df['name'] != self.IGNORE) & (
                    df['representation'] != ""), ['id', 'representation', 'name']]
            if len(df_training_data) == 0:
                logging.debug("No training data (names set) for model=" + model)
                continue
            self.recognizer.train(df_training_data, model)
            df_model = df_no_name.loc[df_no_name['model'] == model]
            if len(df_model) == 0:
                logging.debug("No faces to search for model=" + model)
                continue
            faces = self.recognizer.recognize(df_model)
            if faces:
                for face in faces:
                    face_id = face['id']
                    df.loc[df['id'] == face_id, ['name_recognized', 'duration_recognized', 'distance',
                                                 'distance_metric']] = [face['name_recognized'],
                                                                        face['duration_recognized'], face['distance'],
                                                                        face['distance_metric']]
                if self.recognizer.first_result:
                    df_no_name = self.remove_other_than_recognized(faces, df_no_name)
            self.write_alive_signal(self.RUNNING)

        most_effective_method = self.util.get_most_successful_method(df, False)

        directories = df.directory.unique()
        for d in directories:
            df_directory = df[df['directory'] == d]
            self.store_face_names_if_changed(df_directory, d, most_effective_method)

        self.write_statistics(df, self.dirImages)

    def remove_other_than_recognized(self, faces, df):
        for face in faces:
            face_id = face["id"]
            face = df.loc[df['id'] == face_id, ["file", "face_nr"]]
            if len(face) == 0:
                continue  # has different detector and was removed in loop before
            file = face["file"].item()
            face_nr = face["face_nr"].item()
            f = df.loc[(df['file'] == file) & (df['face_nr'] == face_nr)]
            keys = f.index
            if len(keys) == 1:
                return df
            df = df.drop(keys[1:])
        return df

    def load_face_names(self, folders):
        # df
        # df is the one big pandas.DataFrame that holds
        # - all face representations
        # - all names
        # - over all directories
        df = None
        for f in folders:
            self.folder = f[0]

            if self.check_file_by_name(self.file_name_face_representations) is False:
                return
            if self.check_file_by_name(self.file_name_face_names) is False:
                return
            self.check_file_by_name(self.file_name_face_representations_dbg)

            # ---
            # Concatenate all face representations of all directories
            df_representations = self.get_face_representations()
            if df_representations is None:
                continue

            # ---
            # Read names in directory
            df_names = self.get_face_names()

            if df_names is not None:
                df_names.loc[(df_names['name'].isna() == True), 'name'] = ""  # get rid of "nan" values
                df_names.loc[(df_names['time_named'].isna() == True), 'time_named'] = ""  # get rid of "nan" values

                # ---
                # Write all known names into the face representations
                # Background:
                # This step is needed if
                #  - "statistics mode" is not switched on
                #  - names are written
                # Background:
                # If the "statistics mode" is NOT switched on then names are stored only in the file ".faces.csv"
                # If the "statistics mode" IS switched on then names are stored along with the face representations
                # in the file ".faces.pkl".
                for face in df_names.itertuples():
                    df_representations.loc[(df_representations['id'] == face.id), ['name', 'time_named']] = [face.name,
                                                                                                             face.time_named]

            # ---
            # Find same faces (by position) in images and write the name set by the user into every face.
            # Explanation:
            # - faces are found by different detectors for different models
            # - a face is the same if found
            #   o in same file
            #   o at same position (x, y, h, w)
            df_representations = self.util.number_unique_faces(df_representations)
            df_representations = self.util.copy_name_to_same_faces(df_representations)

            # ---
            # Concatenate all face representations (including known names) of all directories
            if df is None:
                df = df_representations
            else:
                df = pd.concat([df, df_representations], ignore_index=True)

        if df is None:
            return df

        # column 'directory' to have a quick filter for directories
        def append_directory(x):
            path = x['file']
            index = path.rfind("/")
            if index < 0:
                directory = path
            else:
                directory = path[0:index]
            return directory

        df['directory'] = df.apply(append_directory, axis=1)
        logging.debug("Appended directory to each face")

        return df

    # The main goal of this function is to avoid writing results (.faces.csv)
    # if nothing has changed after the process of face recognition.
    # Why does it matter?
    # A file is synchronized between ZOT-/Nomad clones as soon as a file is stored via webDAV
    #
    # param df_recognized data frame that is the result after the process of face recognition
    # param df_current    data frame that is read from the .faces.csv
    def store_face_names_if_changed(self, df_recognized, faces_dir, most_effective_method):
        if self.check_file_by_display_path(self.file_name_face_representations, faces_dir) is False:
            return
        if self.check_file_by_display_path(self.file_name_face_names, faces_dir) is False:
            return
        if self.init_face_names(df_recognized):
            return
        df_names = self.get_face_names()
        # copy new or changed names.... the user might have changed names while the face recognition was running
        for face in df_names.itertuples():
            # copy changed names into the results (it fact all names but changed or new names are the reason)
            df_recognized.loc[(df_recognized['id'] == face.id), ['name', 'time_named']] = [face.name, face.time_named]
        # remove ignored names
        keys = df_names.loc[(df_names['name'] == self.IGNORE)].index
        if len(keys) > 0:
            df_names = df_names.drop((keys))
            logging.debug("directory " + self.folder + " - removed " + str(len(keys)) + " ignored faces in face names")
        df_names.loc[(df_names['name'].isna() == True), 'name'] = ""  # get rid of "nan" values
        df_names.loc[(df_names['name_recognized'].isna() == True), 'name_recognized'] = ""  # get rid of "nan" values
        # apply changed names using the timestamps
        df_recognized = self.util.copy_name_to_same_faces(df_recognized)
        # "reduce" result file
        df = self.util.filter_by_last_named(df_recognized)
        if most_effective_method is not None:  # for unit testing
            df = self.util.keep_most_effectiv_method_only(df, most_effective_method)
        else:
            df = self.util.minimize_results(df, False)
        # compare the content of the results (face recognition) with the content of the file containing the names
        # (that might have changed while the face recognition was running)
        # has any name or recognized name changed while the face recognition was running?
        has_names_changed = self.util.has_any_name_changed(df, df_names)
        if has_names_changed:
            self.write_results(df_recognized, df)
        else:
            logging.debug("faces have not changed. No need to store faces to file.")

    def write_results(self, df_recognized, df_names):
        self.write_alive_signal(self.RUNNING)
        df_names.drop('directory', axis=1, inplace=True)
        logging.debug("faces have changed or where None before. Storing to file.")
        self.store_face_names(df_names)
        if self.keep_history:
            self.store_face_presentations(df_recognized)

    # Add new images to a pandas.DataFrame for
    # - faces representations, faces.pkl, or
    # - facial attributes, faces_attributes.csv
    # - names of faces, faces.csv
    #
    # df...  pandas.DataFrame
    # dir... String, directory
    def add_new_images(self, df, storage):
        images = self.get_images()
        new_images = []
        for image in images:
            path = image[0]
            # Look for new images
            if df is None:
                new_images.append(image)
            else:
                if storage is self.file_name_face_representations:
                    for model_name in self.finder.model_names:
                        if not ((df['file'] == path) & (df['model'] == model_name) & (
                                df['detector'] == self.finder.detector_name)).any():
                            # path AND detector AND model do not exist in one row in stored DataFrame
                            logging.debug(
                                "directory " + self.folder + " - adding image because new combination of image=" + path + " AND model=" + model_name + " AND detector=" + self.finder.detector_name)
                            new_images.append(image)
                            break
                if storage is self.file_name_facial_attributes:
                    if not ((df['file'] == path) & (df['detector'] == self.finder.detector_name)).any():
                        # path AND detector do not exist in one row in stored DataFrame
                        logging.debug(
                            "directory " + self.folder + " - adding image because new combination of image=" + path + " AND detector=" + self.finder.detector_name)
                        new_images.append(image)

        return new_images

    def get_images(self):
        images = []
        query = ("SELECT display_path, os_path "
                 "FROM `attach` "
                 "WHERE `uid` = %s AND "
                 "`folder` = %s AND "
                 "`is_photo` = '1' AND "
                 "( `filetype` = 'image/jpeg' OR `filetype` = 'image/png')")
        data = (self.channel, self.folder)
        images = self.db.select(query, data)
        if len(images) == 0:
            logging.debug("directory " + self.folder + " - no images in this directory")
        return images

    def check_file_by_name(self, file_name):
        if file_name == self.file_name_face_representations:
            self.faces_pkl = None
        if file_name == self.file_name_face_representations_dbg:
            self.faces_pkl_dbg = None
        elif file_name == self.file_name_face_names:
            self.faces_csv = None
        elif file_name == self.file_name_facial_attributes:
            self.faces_attr = None

        query = "SELECT os_path FROM `attach` WHERE `uid` = %s AND `folder` = %s AND `filename` = %s LIMIT 1"
        data = (self.channel, self.folder, file_name)
        r = self.db.select(query, data)
        if len(r) == 0:
            logging.debug("directory " + self.folder + " - skipping... no file " + file_name)
            return False

        path = os.path.join(self.dirImages, r[0][0])
        if os.path.exists(path) and os.path.isfile(path) and os.access(path, os.W_OK):
            if file_name == self.file_name_face_representations:
                self.faces_pkl = path
                return True
            elif file_name == self.file_name_face_names:
                self.faces_csv = path
                return True
            elif file_name == self.file_name_facial_attributes:
                self.faces_attr = path
                return True
            elif file_name == self.file_name_face_representations_dbg:
                self.faces_pkl_dbg = path
                return True
        logging.debug("directory " + self.folder + " - skipping... no file or write permission, file " + path)
        return False

    def check_file_by_channel(self, file_name):
        if file_name == self.file_name_faces_statistic:
            self.faces_statistics = None
        elif file_name == self.file_name_models_statistic:
            self.models_statistics = None

        display_path = os.path.join(self.dir_addon, file_name)
        query = "SELECT os_path FROM `attach` WHERE `uid` = %s AND `display_path` = %s LIMIT 1"
        data = (self.channel, display_path)
        r = self.db.select(query, data)
        if len(r) == 0:
            logging.debug("skipping... no file " + display_path)
            return False

        path = os.path.join(self.dirImages, r[0][0])
        if os.path.exists(path) and os.path.isfile(path) and os.access(path, os.W_OK):
            if file_name == self.file_name_faces_statistic:
                self.faces_statistics = path
                return True
            elif file_name == self.file_name_models_statistic:
                self.models_statistics = path
                return True
        logging.debug("skipping... no file or write permission, file " + display_path)
        return False

    def check_file_by_display_path(self, file_name, display_dir):
        if file_name == self.file_name_face_names:
            self.faces_csv = None
        elif file_name == self.file_name_face_representations:
            self.faces_pkl = None

        display_path = os.path.join(display_dir, file_name)
        query = "SELECT os_path FROM `attach` WHERE `uid` = %s AND `display_path` = %s LIMIT 1"
        data = (self.channel, display_path)
        r = self.db.select(query, data)
        if len(r) == 0:
            logging.debug("skipping... no file " + display_path)
            return False

        path = os.path.join(self.dirImages, r[0][0])
        if os.path.exists(path) and os.path.isfile(path) and os.access(path, os.W_OK):
            if file_name == self.file_name_face_names:
                self.faces_csv = path
                return True
            elif file_name == self.file_name_face_representations:
                self.faces_pkl = path
                return True
        logging.debug("skipping... no file or write permission, file " + display_path)
        return False

    def get_face_representations(self):
        # Load stored face representations
        df = None  # pandas.DataFrame that holds all face representations
        if os.stat(self.faces_pkl).st_size == 0:
            logging.debug("directory " + self.folder + " - file face representations is empty yet " + self.faces_pkl)
            return df
        if os.path.exists(self.faces_pkl):
            f = open(self.faces_pkl, 'rb')
            df = pickle.load(f)
            f.close()
            logging.debug("directory " + self.folder + " - loaded face representations from file " + self.faces_pkl)
        return df

    def store_face_presentations(self, df):
        if os.path.exists(self.faces_pkl) is False:
            logging.debug("directory " + self.folder + " - face representations does not exist " + self.faces_pkl)
            return False
        df = df.reset_index(drop=True)
        f = open(self.faces_pkl, "wb")
        pickle.dump(df, f)
        f.close()
        logging.debug("directory " + self.folder + " - stored face representations in file " + self.faces_pkl)

        is_needed = False # seems useless because there is the big statistics csv containing all results
        if logging.root.level >= logging.DEBUG and is_needed:
            if self.faces_pkl_dbg and os.path.exists(self.faces_pkl_dbg) is False:
                logging.debug("directory " + self.folder + " - dbg csv file does not exist " + self.faces_pkl_dbg)
            else:
                pd.set_option('display.max_colwidth', None)
                df = df.drop('representation', axis=1)
                df.to_csv(self.faces_pkl_dbg, index=False, sep=';', na_rep='')

        return True

    def get_facial_attributes(self):
        # Load stored facial attributes
        df = None  # pandas.DataFrame that holds all facial attributes
        if self.faces_attr is None:
            return df  # might happen if the feature was switched off
        if os.stat(self.faces_attr).st_size == 0:
            logging.debug("directory " + self.folder + " - file facial attributes is empty yet " + self.faces_attr)
            return df
        if os.path.exists(self.faces_attr):
            df = pd.read_csv(self.faces_attr)
            logging.debug("directory " + self.folder + " - loaded facial attributes from file " + self.faces_attr)
        return df

    def store_facial_attributes(self, df):
        df.to_csv(self.faces_attr, index=False)
        logging.debug("directory " + self.folder + " - stored facial attributes in file " + self.faces_attr)

    def get_face_names(self):
        # Load stored names
        df = None  # pandas.DataFrame that holds all face names
        if os.stat(self.faces_csv).st_size == 0:
            logging.debug("directory " + self.folder + " - file holding face names is empty yet " + self.faces_csv)
            return df
        if os.path.exists(self.faces_csv):
            df = pd.read_csv(self.faces_csv)
            logging.debug("directory " + self.folder + " - loaded face names from file " + self.faces_csv)
        return df

    def init_face_names(self, df_representation):
        df_names = self.get_face_names()
        if (df_names is None) or (len(df_names) == 0):
            logging.debug("No face names yet or no longer because images where delete in dir. File=" + self.faces_csv)
            most_effective_method = self.util.get_most_successful_method(df_representation, False)
            df_names = self.util.filter_by_last_named(df_representation)
            df_names = self.util.number_unique_faces(df_names)
            if most_effective_method is not None:  # for unit testing
                df_names = self.util.keep_most_effectiv_method_only(df_names, most_effective_method)
            else:
                df_names = self.util.minimize_results(df_names, False)
            self.write_results(df_representation, df_names)
            return True
        else:
            return False

    def store_face_names(self, df):
        df = self.util.minimize_results(df, False)
        for column in self.columnsToIncludeAll:
            if column not in df.columns:  # for unit testing
                continue
            if column not in self.columnsToInclude:
                df = df.drop(column, axis=1)
        if 'representation' in df.columns:  # for unit testing
            df = df.drop('representation', axis=1)
        df = df.sort_values(by=[self.sort_column], ascending=[self.sort_direction])
        if os.path.exists(self.faces_csv):
            df.to_csv(self.faces_csv, index=False)
        logging.debug("directory " + self.folder + " - stored face names in file " + self.faces_csv)

    def cleanup(self):
        logging.debug("directory " + self.folder + " - 1. Step: cleaning up ---")
        imgs = self.get_images()
        images = []
        for i in imgs:
            images.append(i[0])
        df = self.get_face_representations()
        if df is not None:
            keys = self.util.remove_detector_model(df, self.removeModels, self.removeDetectors, self.folder)
            i = df[~df.file.isin(images)].index
            keys.extend(i.to_list())
            if len(keys) > 0:
                df = df.drop(keys)
                if self.store_face_presentations(df):
                    if len(i) > 0:
                        logging.info("directory " + self.folder + " - " + str(
                            len(images)) + " image(s) where deleted and removed from face representations")

        df = self.get_face_names()
        if df is not None:
            keys = self.util.remove_detector_model(df, self.removeModels, self.removeDetectors, self.folder)
            i = df[~df.file.isin(images)].index
            keys.extend(i.to_list())
            if len(keys) > 0:
                df = df.drop(keys)
                self.store_face_names(df)
                if len(i) > 0:
                    logging.info("directory " + self.folder + " - " + str(
                        len(images)) + " image(s) where deleted and removed from face names")

        df = self.get_facial_attributes()
        if df is not None:
            keys = self.util.remove_detector_model(df, "", self.removeDetectors, self.folder)
            i = df[~df.file.isin(images)].index
            keys.extend(i.to_list())
            if len(keys) > 0:
                df = df.drop(keys)
                self.store_facial_attributes(df)
                if len(i) > 0:
                    logging.info("directory " + self.folder + " - " + str(
                        len(i)) + " image(s) where deleted and removed from facial attributes")

    def write_statistics(self, df, most_effective_method):
        self.write_alive_signal(self.RUNNING)
        if self.statistics_mode:
            if self.check_file_by_channel(self.file_name_faces_statistic) is False:
                return
            df = df.drop('representation', axis=1)
            df.to_csv(self.faces_statistics)
            logging.debug("Wrote face statistics to file=" + self.file_name_faces_statistic)
            if self.check_file_by_channel(self.file_name_models_statistic) is False:
                return
            df_models = self.util.get_most_successful_method(df, True)
            name_count = len(df.name.unique()) - 1
            files = df.file.unique()
            message = str(len(files)) + " images, " + str(name_count) + " different names, minimum face width " + str(
                self.finder.min_face_width_percent) + " % of image or " + str(self.finder.min_face_width_pixel) + " px"
            row = {'model': message, 'detector': "", 'accuracy': "", 'detected': "", 'verified': "", 'recognized': "",
                   'correct': "", 'wrong': "", 'ignored': "", 'seconds': "", 'seconds/face': ""}
            df_models = df_models.append(row, ignore_index=True)
            df_models.to_csv(self.models_statistics)
            logging.debug("Wrote model statistics to file=" + self.file_name_models_statistic)

    def set_time_to_wait(self, seconds):
        self.timeToWait = seconds

    def write_alive_signal(self, status):
        self.check_pid()
        elapsed_time = time.time() - self.timeLastAliveSignal
        if elapsed_time < self.timeToWait and status != self.FINISHED:
            return
        now = datetime.datetime.utcnow()
        content = status + " " + now.strftime('%Y-%m-%d %H:%M:%S') + " pid " + self.proc_id
        query = "UPDATE config SET v = %s WHERE cat = %s AND k = %s"
        data = (content, 'faces', 'status')
        self.db.update(query, data)
        logging.debug("lock status written to db:  " + content)
        self.timeLastAliveSignal = time.time()

    def check_pid(self):
        query = "SELECT v FROM config WHERE cat = 'faces' AND k = 'status'"
        data = {}
        rows = self.db.select(query, data)
        if len(rows) != 1:
            logging.critical("Found " + str(len(rows)) + " results for status in database")
            self.stop()
        status = rows[0][0]
        splittees = status.split()
        if splittees[3] != "pid" or len(splittees) != 5:
            logging.critical("4th and 5th element is no pid. Found status:  " + status)
            self.stop()
        if splittees[4] != self.proc_id:
            logging.critical("Found wrong pid: own=" + self.proc_id + ", found= " + splittees[4])
            self.stop()

    def stop(self):
        self.db.close()
        logging.error("Stopping program. Good By...")
        sys.exit()

    def write_exif_dates(self, df):
        if not self.exiftool:
            return df
        df.loc[(df['exif_date'].isna() == True), 'exif_date'] = ""  # get rid of "nan" values
        exif_date = ""
        files = df.file.unique()
        for file in files:
            exif_dates = df.loc[(df['file'] == file) & (df['exif_date'] != ""), "exif_date"]
            if len(exif_dates) > 0:
                exif_date = exif_dates.values[0]
                logging.debug("directory " + self.folder + " - exif date exists '" + exif_date + "' for image=" + file)
            else:
                query = ("SELECT os_path "
                         "FROM `attach` "
                         "WHERE `uid` = %s AND "
                         "`folder` = %s AND "
                         "`is_photo` = '1' AND "
                         "`display_path` = %s")
                data = (self.channel, self.folder, file)
                os_path = self.db.select(query, data)
                if len(os_path) == 0:
                    logging.debug("directory " + self.folder + " - skipping... no " + file)
                    continue
                path = os.path.join(self.dirImages, os_path[0][0])
                exif_date = self.exiftool.getDate(path)
                logging.debug("directory " + self.folder + " - exif date = '" + exif_date + "' for image=" + file)
            if exif_date != "":
                df.loc[(df['file'] == file) & (df['exif_date'] == ""), "exif_date"] = exif_date
            continue
        return df
