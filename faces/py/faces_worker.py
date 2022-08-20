import importlib
import os
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
        self.proc_id = None
        self.db = None
        self.log_data = False
        self.finder = None
        self.recognizer = None
        self.dirImages = None
        self.file_name_face_representations = "faces.gzip"
        self.file_name_facial_attributes = "demography.json"
        self.file_name_face_names = "faces.json"
        self.file_name_faces_statistic = "face_statistics.csv"
        self.file_name_models_statistic = "model_statistics.csv"
        self.dir_addon = "faces"

        # Watch this!
        # What happens if self.keep_history = True
        # The file .faces.gzip will be written if the face recognition finds something.
        # Q: Why does it matter?
        # A: In case the face recognition runs for ZOT/Nomad and the channel has clones
        #    the written file will trigger a file sync of .faces.gzip. between the clones (servers).
        self.keep_history = False  # True/False
        self.statistics = False  # True/False
        self.columnsToIncludeAll = ["model", "detector", "duration_detection", "duration_representation",
                                    "time_created", "distance", "distance_metric", "duration_recognized", "width"]
        self.columnsToInclude = []  # ["model", "detector"] extra columns if faces.json / faces.cs
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
        self.file_embedding = None
        self.file_names = None
        self.file_demography = None
        self.file_face_statistics = None
        self.file_model_statistics = None
        self.channel = None
        self.util = faces_util.Util()

    def set_finder(self, csv):
        deepface_specs = importlib.util.find_spec("deepface")
        if deepface_specs is not None:
            self.finder = faces_finder.Finder()
            self.finder.configure(csv)
        else:
            logging.error("FAILED to set finder. Reason: module deepface not found")

    def set_recognizer(self, csv):
        deepface_specs = importlib.util.find_spec("deepface")
        if deepface_specs is not None:
            self.recognizer = faces_recognizer.Recognizer()
            self.recognizer.configure(csv)
        else:
            logging.critical("FAILED to set finder. Reason: module deepface not found")

    def set_db(self, db):
        self.db = db
        logging.debug("database was set")

    def configure(self, csv):
        # example csv
        # optional-face-data=duration_detection,duration_representation,time_created,distance,distance_metric,duration_recognized;statistics=True
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
                        logging.warning(column + " is not a valid column in faces.gzip")
                        logging.warning("Valid columns are: " + str(self.columnsToIncludeAll))
            elif key == 'log_data':
                if value == "on":
                    self.log_data = True
                if value == "off":
                    self.log_data = False
            elif key == 'statistics':
                if value == "on" or value == 'true':
                    self.statistics = True
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
        logging.debug("Configuration statistics=" + str(self.statistics))
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

    def run(self, dir_images, proc_id, channel_id, do_recognize):
        logging.info("start with " + self.finder.detector_name + " in dir=" + dir_images + ", proc_id=" +
                     proc_id + ", channel_id=" + str(channel_id))
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
            if do_recognize:
                if channel_id == 0 or channel_id == self.channel:
                    self.recognize(folders)
                else:
                    logging.debug("no recognition is run for channel id = " + str(self.channel))
                    continue
        self.write_alive_signal(self.FINISHED)
        logging.debug("Finished with " + self.finder.detector_name + " in dir=" + dir_images + ", proc_id=" +
                      proc_id + ", channel_id=" + str(channel_id))

    def process_dir(self):
        logging.debug(self.folder + " / channel " + str(self.channel) + " - start detecting/analyzing")

        if self.check_file_by_name(self.file_name_face_names) is False:
            return
        if self.check_file_by_name(self.file_name_face_representations) is False:
            return
        self.check_file_by_name(self.file_name_facial_attributes)  # for cleanup

        self.cleanup()
        self.detect()
        self.analyse()

    # Find and store all face representations for face recognition.
    # The face representations are stored in binary pickle file.
    def detect(self):
        logging.debug(self.folder + " - 2. Step: detecting new images ---")

        df = self.get_face_representations()  # pandas.DataFrame holding all face representation

        images = self.add_new_images(df, self.file_name_face_representations)
        if len(images) == 0:
            logging.debug(self.folder + " - No new images for face detection in directory")
            return

        logging.debug(self.folder + " - searching for faces in " + str(len(images)) + " images")

        if not self.finder.is_loaded():
            self.finder.load()
            self.write_alive_signal(self.RUNNING)

        time_start_detection = time.time()
        logging.debug(self.folder + " - start to detect faces for " + str(len(images)) + " images")
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
                for face in faces:
                    df = self.util.add_row_embedding(df, face)

            elapsed_time = time.time() - time_start_detection
            if elapsed_time > self.timeBackUp:
                if self.store_face_presentations(df) is False:
                    return
                logging.info(self.folder + " - elapsed time " + str(elapsed_time) + " > " + str(self.timeBackUp))
                time_start_detection = time.time()
            self.write_alive_signal(self.RUNNING)
        # Why not storing the faces (faces.json) at this point of time?
        # - The face detection and the creation of the face representations are cpu, memory and time-consuming
        # - In praxis
        #   o The user uploads some or many more pictures to a directory
        #   o The user opens the addon. This will start the face detection that runs for a couple of minutes
        #   o Meanwhile the user clicks on some faces and name the faces or changes some face names. The changes
        #     are written into to faces.json
        # - After some time the face detection has ended and will write faces.json.
        #   This overwrites the face names the user has set in the meantime.
        logging.info(self.folder + " - finished detection of faces in " + str(len(images)) + " images")
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

        logging.debug(self.folder + " - 3. Step: analysing facial attributes ---")
        df = self.get_facial_attributes()  # pandas.DataFrame holding all facial attributes

        images = self.add_new_images(df, self.file_name_facial_attributes)
        if len(images) == 0:
            logging.debug(self.folder + " - No new images for analysis of facial attributes")
            return

        logging.info(self.folder + " - analyzing facial attributes in " + str(len(images)) + " new images")

        if not self.finder.is_loaded():
            self.finder.load()
            self.write_alive_signal(self.RUNNING)

        logging.debug(self.folder + " - start to analyse faces in " + str(len(images)) + " images")
        for image in images:
            path = image[0]
            os_path_on_server = os.path.join(self.dirImages, image[1])
            faces = self.finder.analyse(path, os_path_on_server)
            self.write_alive_signal(self.RUNNING)
            if faces:
                for face in faces:
                    df = self.util.add_row_attributes(df, face)

        logging.debug(self.folder + " - finished analyse of faces in " + str(len(images)) + " images")
        self.store_facial_attributes(df)

    # -------------------------------------------------------------
    # Basic steps:
    # 1. In every directory
    #    - Load all DataFrames holding the face representations (faces.gzip)
    #    - Load all DataFrames holding the names (faces.json)
    #    - Write all names (set by the user) from faces.json to faces.gzip
    # 2. Add all DataFrames to get one big DataFrame holding all face representation and known names
    # 4. Try to recognize known faces in every directory
    # 5. Check if the recognition found new faces or changed names
    # 6. Write the CSV (faces.json) if the values for "name" or "name_recognized" have changed
    # 7. If history is switched on: write faces.gzip (including now all face embedding AND names)
    #
    def recognize(self, folders):
        logging.info("START COMPARING FACES. Loading all necessary data...")
        df = self.load_face_names(folders)
        if df is None:
            return

        def include_as_training_data(row):
            w = row['width']
            if w < self.finder.min_width_train:
                return 0
            return 1

        def include_as_result_data(row):
            w = row['width']
            if w < self.finder.min_width_result:
                return 0
            return 1

        df['min_size_train'] = df.apply(include_as_training_data, axis=1)
        df['min_size_result'] = df.apply(include_as_result_data, axis=1)

        df.loc[(df['min_size_result']) < 1 & (df['name'] == ""),
               ['name_recognized', 'distance', 'distance_metric', 'duration_recognized']] = ["", -1.0, "", 0]

        logging.debug("Get all faces with a name, face representation and min width")
        df_no_name = df.loc[
            (df['name'] == "") &
            (df['name'] != self.IGNORE) &
            (df['duration_representation'] != 0.0) &
            (df['min_size_result'] == 1) &
            (df['time_named'] == ""), ['id', 'representation', 'model', 'file', 'face_nr']]

        # Loop through models
        # - loop through the ordered list models (start parameter)
        # - optionally stop recognition after a first match (start parameter)
        models = df.model.unique()
        for model in models:
            logging.debug("Start recognition using model=" + model + " Gathering faces as training data...")
            df_training_data = df.loc[
                (df['model'] == model) &
                (df['name'] != "") &
                (df['name'] != self.IGNORE) &
                (df['min_size_train'] == 1) &
                (df['representation'] != ""), ['id', 'representation', 'name']]
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

        most_effective_method = self.util.get_most_successful_method(df, False)

        df = df.drop('min_size_train', axis=1)
        df = df.drop('min_size_result', axis=1)

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

            # ---
            # Concatenate all face representations of all directories
            df_representations = self.get_face_representations()
            if df_representations is None:
                continue

            # ---
            # Read names in directory
            df_names = self.get_face_names()

            if df_names is not None:
                # ---
                # Write all known names into the face representations
                # Background:
                # This step is needed if
                #  - "statistics mode" / "history" is not switched on
                #  - names are written
                # Background:
                # If the "statistics mode" is NOT switched on then names are stored only in the file ".faces.json"
                # If the "statistics mode" IS switched on then names are stored along with the face representations
                # in the file ".faces.gzip".
                for face in df_names.itertuples():
                    df_representations.loc[(df_representations['id'] == face.id),
                                           ['name', 'time_named']] = [face.name, face.time_named]

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

    # The main goal of this function is to avoid writing results (faces.gzip)
    # if nothing has changed after the process of face recognition.
    # Why does it matter?
    # A file is synchronized between Streams-/ Hubzilla clones as soon as a file is stored via webDAV
    #
    # param df_recognized data frame that is the result after the process of face recognition
    # param df_current    data frame that is read from the names.json
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
            logging.debug(self.folder + " - removed " + str(len(keys)) + " ignored faces in face names")
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
    # - faces representations, faces.gzip, or
    # - facial attributes, faces_attributes.json
    # - names of faces, faces.json
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
                                self.folder + " - adding image because new combination of image=" + path +
                                " AND model=" + model_name + " AND detector=" + self.finder.detector_name)
                            new_images.append(image)
                            break
                if storage is self.file_name_facial_attributes:
                    if not ((df['file'] == path) & (df['detector'] == self.finder.detector_name)).any():
                        # path AND detector do not exist in one row in stored DataFrame
                        logging.debug(
                            self.folder + " - adding image because new combination of image=" + path +
                            " AND detector=" + self.finder.detector_name)
                        new_images.append(image)

        return new_images

    def get_images(self):
        query = ("SELECT display_path, os_path "
                 "FROM `attach` "
                 "WHERE `uid` = %s AND "
                 "`folder` = %s AND "
                 "`is_photo` = '1' AND "
                 "( `filetype` = 'image/jpeg' OR `filetype` = 'image/png')")
        data = (self.channel, self.folder)
        images = self.db.select(query, data)
        if len(images) == 0:
            logging.debug(self.folder + " - no images in this directory")
        return images

    def check_file_by_name(self, file_name):
        if file_name == self.file_name_face_representations:
            self.file_embedding = None
        elif file_name == self.file_name_face_names:
            self.file_names = None
        elif file_name == self.file_name_facial_attributes:
            self.file_demography = None

        query = "SELECT os_path FROM `attach` WHERE `uid` = %s AND `folder` = %s AND `filename` = %s LIMIT 1"
        data = (self.channel, self.folder, file_name)
        r = self.db.select(query, data)
        if len(r) == 0:
            logging.debug(self.folder + " - skipping... no file " + file_name)
            return False

        path = os.path.join(self.dirImages, r[0][0])
        if os.path.exists(path) and os.path.isfile(path) and os.access(path, os.W_OK):
            if file_name == self.file_name_face_representations:
                self.file_embedding = path
                return True
            elif file_name == self.file_name_face_names:
                self.file_names = path
                return True
            elif file_name == self.file_name_facial_attributes:
                self.file_demography = path
                return True
        logging.debug(self.folder + " - skipping... no file or write permission, file " + path)
        return False

    def check_file_by_channel(self, file_name):
        if file_name == self.file_name_faces_statistic:
            self.file_face_statistics = None
        elif file_name == self.file_name_models_statistic:
            self.file_model_statistics = None

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
                self.file_face_statistics = path
                return True
            elif file_name == self.file_name_models_statistic:
                self.file_model_statistics = path
                return True
        logging.debug("skipping... no file or write permission, file " + display_path)
        return False

    def check_file_by_display_path(self, file_name, display_dir):
        if file_name == self.file_name_face_names:
            self.file_names = None
        elif file_name == self.file_name_face_representations:
            self.file_embedding = None

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
                self.file_names = path
                return True
            elif file_name == self.file_name_face_representations:
                self.file_embedding = path
                return True
        logging.debug("skipping... no file or write permission, file " + display_path)
        return False

    def get_face_representations(self):
        # Load stored face representations
        df = None  # pandas.DataFrame that holds all face representations
        if os.path.exists(self.file_embedding):
            if os.stat(self.file_embedding).st_size == 0:
                logging.debug(self.folder + " - file face representations is empty yet " + self.file_embedding)
                return df
            df = pd.read_parquet(self.file_embedding, engine="pyarrow")
            logging.debug(self.folder + " - loaded face representations from file " + self.file_embedding)
        if df is not None and len(df) == 0:
            return None
        return df

    def store_face_presentations(self, df):
        if os.path.exists(self.file_embedding) is False:
            logging.debug(self.folder + " - face representations does not exist " + self.file_embedding)
            return False
        df = df.reset_index(drop=True)
        df.to_parquet(self.file_embedding, engine="pyarrow", compression='gzip')
        logging.debug(self.folder + " - stored face representations in file " + self.file_embedding)

        return True

    def get_facial_attributes(self):
        # Load stored facial attributes
        df = None  # pandas.DataFrame that holds all facial attributes
        if self.file_demography is None:
            return df  # might happen if the feature was switched off
        if os.stat(self.file_demography).st_size == 0:
            logging.debug(self.folder + " - file facial attributes is empty yet " + self.file_demography)
            return df
        if os.path.exists(self.file_demography):
            df = pd.read_json(self.file_demography)
            logging.debug(self.folder + " - loaded facial attributes from file " + self.file_demography)
        return df

    def store_facial_attributes(self, df):
        df.to_json(self.file_demography)
        logging.debug(self.folder + " - stored facial attributes in file " + self.file_demography)

    def get_face_names(self):
        # Load stored names
        df = None  # pandas.DataFrame that holds all face names
        if os.stat(self.file_names).st_size == 0:
            logging.debug(self.folder + " - file holding face names is empty yet " + self.file_names)
            return df
        if os.path.exists(self.file_names):
            df = pd.read_json(self.file_names)
            logging.debug(self.folder + " - loaded face names from file " + self.file_names)
        return df

    def init_face_names(self, df_representation):
        df_names = self.get_face_names()
        if (df_names is None) or (len(df_names) == 0):
            logging.debug("No face names yet or no longer because images where delete in dir. File=" + self.file_names)
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
        if os.path.exists(self.file_names):
            df.to_json(self.file_names)
        logging.debug(self.folder + " - stored face names in file " + self.file_names)

    def cleanup(self):
        logging.debug(self.folder + " - 1. Step: cleaning up ---")
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
                        logging.info(self.folder + " " + str(len(images)) + " faces removed from face representations")

        df = self.get_face_names()
        if df is not None:
            keys = self.util.remove_detector_model(df, self.removeModels, self.removeDetectors, self.folder)
            i = df[~df.file.isin(images)].index
            keys.extend(i.to_list())
            if len(keys) > 0:
                df = df.drop(keys)
                self.store_face_names(df)
                if len(i) > 0:
                    logging.info(self.folder + " " + str(len(images)) + " faces removed from face name")

        df = self.get_facial_attributes()
        if df is not None:
            keys = self.util.remove_detector_model(df, "", self.removeDetectors, self.folder)
            i = df[~df.file.isin(images)].index
            keys.extend(i.to_list())
            if len(keys) > 0:
                df = df.drop(keys)
                self.store_facial_attributes(df)
                if len(i) > 0:
                    logging.info(self.folder + " " + str(len(images)) + " faces removed from facial attributes")

    def write_statistics(self, df, most_effective_method):
        self.write_alive_signal(self.RUNNING)
        if self.check_file_by_channel(self.file_name_faces_statistic) is False:
            return
        if self.check_file_by_channel(self.file_name_models_statistic) is False:
            return

        if self.statistics:
            df = df.drop('representation', axis=1)
            df.to_csv(self.file_face_statistics)
            logging.debug("Wrote face statistics to file=" + self.file_name_faces_statistic)
            df_models = self.util.get_most_successful_method(df, True)
            name_count = len(df.name.unique()) - 1
            files = df.file.unique()
            message = str(len(files)) + " images, " + str(name_count) + " different names, minimum face width " + str(
                self.finder.min_face_width_percent) + " % of image or " + str(self.finder.min_face_width_pixel) + " px"
            row = {'model': message, 'detector': "", 'accuracy': "", 'detected': "", 'verified': "", 'recognized': "",
                   'correct': "", 'wrong': "", 'ignored': "", 'seconds': "", 'seconds/face': ""}
            df_models = df_models.append(row, ignore_index=True)
            df_models.to_csv(self.file_model_statistics)
            logging.debug("Wrote model statistics to file=" + self.file_name_models_statistic)
        else:
            f = open(self.file_face_statistics, 'w')
            f.write("")
            f.close()
            f = open(self.file_model_statistics, 'w')
            f.write("")
            f.close()


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
        exif_date = ""
        files = df.file.unique()
        for file in files:
            exif_dates = df.loc[(df['file'] == file) & (df['exif_date'] != ""), "exif_date"]
            if len(exif_dates) > 0:
                exif_date = exif_dates.values[0]
                logging.debug(self.folder + " - exif date exists '" + exif_date + "' for image=" + file)
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
                    logging.debug(self.folder + " - skipping... no " + file)
                    continue
                path = os.path.join(self.dirImages, os_path[0][0])
                exif_date = self.exiftool.getDate(path)
                logging.debug(self.folder + " - exif date = '" + str(exif_date) + "' for image=" + file)
            if exif_date != "":
                df.loc[(df['file'] == file) & (df['exif_date'] == ""), "exif_date"] = exif_date
            continue
        return df
