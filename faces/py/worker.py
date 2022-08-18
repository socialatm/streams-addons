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
        self.file_name_face_representations = "faces.gzip"
        self.file_name_facial_attributes = "demography.json"
        self.file_name_face_names_json = "faces.json"
        self.file_name_faces_statistic = "face_statistics.csv"
        self.file_name_models_statistic = "model_statistics.csv"
        self.dir_addon = "faces"

        # Watch this!
        # What happens if self.keep_history = True
        # The file .faces.pkl will be written if the face recognition finds something.
        # Q: Why does it matter?
        # A: In case the face recognition runs for ZOT/Nomad and the channel has clones
        #    the written file will trigger a file sync of .faces.pkl. between the clones (servers).
        self.keep_history = False  # True/False
        self.statistics_mode = False  # True/False
        self.columnsToIncludeAll = ["model", "detector", "duration_detection", "duration_representation",
                                    "time_created", "distance", "distance_metric", "duration_recognized", "width"]
        self.columnsToInclude = []  # ["model", "detector"] extra columns if faces.json / faces.csv
        self.columnsSort = ["file", "position", "face_nr", "name", "name_recognized", "time_named", "exif_date",
                            "detector", "model"]
        self.timeLastAliveSignal = 0
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
        self.faces_statistics = None
        self.models_statistics = None
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

    def run(self, dir_images, do_recognize):
        logging.info("detector is " + self.finder.detector_name)
        self.dirImages = dir_images
        if os.access(self.dirImages, os.R_OK) is False:
            logging.error("can not read directory " + self.dirImages)
            sys.exit(1)
        exclude = set(['lost+found', '.Trash-1000'])
        for dir, dirnames, files in os.walk(self.dirImages, followlinks=self.follow_sym_links, topdown=True):
            dirnames[:] = [d for d in dirnames if d not in exclude]  # works because of topdown=True is set
            logging.debug("directory " + dir + " - start processing directory")
            self.process_dir(dir)
        logging.debug(self.finder.detector_name + " finished")
        if do_recognize:
            self.recognize()

    def process_dir(self, dir):
        logging.debug("directory " + dir + " - start detecting/analyzing")

        if not self.check_write_access(dir):
            return

        self.cleanup(dir)
        self.detect(dir)
        self.analyse(dir)

    # Find and store all face representations for face recognition.
    # The face representations are stored in binary pickle file.
    def detect(self, dir):
        logging.debug("directory " + dir + " - 2. Step: detecting new images ---")

        df = self.get_face_representations(dir)  # pandas.DataFrame holding all face representation

        images = self.add_new_images(df, self.file_name_face_representations, dir)
        if len(images) == 0:
            logging.debug("directory " + dir + " - No new images for face detection in directory")
            return

        logging.debug(dir + " - searching faces in " + str(len(images)) + " new  images")

        if not self.finder.is_loaded():
            self.finder.load()

        time_start_detection = time.time()
        logging.debug("directory " + dir + " - start to detect faces for " + str(len(images)) + " images")
        for image in images:
            # The list of models per detector can change. Do not find existing representations again.
            os_path_on_server = os.path.join(self.dirImages, image)
            existing_models = []
            if df is not None:
                existing_models = df.loc[
                    (df["file"] == image) & (df["detector"] == self.finder.detector_name), "model"].values
            faces = self.finder.detect(image, os_path_on_server, existing_models)
            if faces:
                for face in faces:
                    df = self.util.add_row_embedding(df, face)

            elapsed_time = time.time() - time_start_detection
            if elapsed_time > self.timeBackUp:
                if self.store_face_presentations(df, dir) is False:
                    return
                logging.debug(dir + " - elapsed time " + str(elapsed_time) + " > " + str(self.timeBackUp))
                time_start_detection = time.time()
        # Why not storing the faces (faces.csv) at this point?
        # - The face detection and the creation of the face representations are cpu, memory and time-consuming
        # - In praxis
        #   o The user uploads some or many more pictures to a directory
        #   o The user opens the addon. This will start the face detection that runs for a couple of minutes
        #   o Meanwhile the user clicks on some faces and name the faces or changes some face names. The changes
        #     are written into to faces.csv
        # - After some time the face detection has ended and will write faces.csv.
        #   This overwrites the face names the user has set in the meantime.
        logging.debug("directory " + dir + " - finished detection of faces in " + str(len(images)) + " images")
        df = self.write_exif_dates(df, dir)

        if self.store_face_presentations(df, dir) is False:
            return
        self.init_face_names(df, dir)

    # Find and store all facial attributes, emotion, age, gender, race
    # What facial attributes to find is configurable, see method setFinder().
    def analyse(self, dir):
        if not self.finder.is_analyse_on():
            return

        logging.debug("directory " + dir + " - 3. Step: analysing facial attributes ---")
        df = self.get_facial_attributes(dir)  # pandas.DataFrame holding all facial attributes

        images = self.add_new_images(df, self.file_name_facial_attributes, dir)
        if len(images) == 0:
            logging.debug("directory " + dir + " - No new images for analysis of facial attributes")
            return

        logging.debug(dir + " - analyzing facial attribute in " + str(len(images)) + " new images")

        if not self.finder.is_loaded():
            self.finder.load()

        logging.debug("directory " + dir + " - start to analyse faces in " + str(len(images)) + " images")
        for image in images:
            os_path_on_server = os.path.join(self.dirImages, image)
            faces = self.finder.analyse(image, os_path_on_server)
            if faces:
                for face in faces:
                    self.util.add_row_attributes(face)

        logging.debug("directory " + dir + " - finished analyse of faces in " + str(len(images)) + " images")
        self.store_facial_attributes(df, dir)

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
    def recognize(self):
        logging.info("START COMPARING FACES. Loading all necessary data...")
        df = self.load_face_names()
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

    def load_face_names(self):
        # df
        # df is the one big pandas.DataFrame that holds
        # - all face representations
        # - all names
        # - over all directories
        df = None
        for dir, dirnames, files in os.walk(self.dirImages, followlinks=self.follow_sym_links):
            # ---
            # Concatenate all face representations of all directories
            df_representations = self.get_face_representations(dir)
            if df_representations is None:
                continue
            # ---
            # Read names in directory
            df_names = self.get_face_names(dir)

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
                # If the "statistics mode" IS switched on then names are stored along with the face representations in the file ".faces.pkl".
                for face in df_names.itertuples():
                    df_representations.loc[(df_representations['id'] == face.id), ['name', 'time_named']] = [face.name,
                                                                                                             face.time_named]

            # ---
            # Find same faces in images and write the name set by the user into every face.
            # Explanation:
            # - faces are found by different detectors for different models
            # - a face is the same if found
            #   o in same file
            #   o at same positon (x, y, h, w)
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

        # column 'directory' to have a quick filter for directries
        def append_directory(x):
            path = x['file']
            index = path.rfind("/")
            if index < 0:
                directory = ""
            else:
                directory = path[0:index]
            return directory

        df['directory'] = df.apply(append_directory, axis=1)
        logging.debug("Appended directory to each face")

        return df

    # The main goal of this function is to avoid writing results (.faces.csv)
    # if nothing has changed after the process of face recognition.
    # Why does it matter?
    # A file is synchronized between Streams/Hubzilla clones as soon as a file is stored via webDAV
    #
    # param df_recognized data frame that is the result after the process of face recognition
    # param df_current    data frame that is read from the .faces.csv
    def store_face_names_if_changed(self, df_recognized, faces_dir, most_effective_method):
        abs_dir = os.path.join(self.dirImages, faces_dir)
        if self.init_face_names(df_recognized, abs_dir):
            return
        df_names = self.get_face_names(abs_dir)
        # copy new or changed names.... the user might have changed names while the face recognition was running
        for face in df_names.itertuples():
            # copy changed names into the results (it fact all names but changed or new names are the reason)
            df_recognized.loc[(df_recognized['id'] == face.id), ['name', 'time_named']] = [face.name, face.time_named]
        # remove ignored names
        keys = df_names.loc[(df_names['name'] == self.IGNORE)].index
        if len(keys) > 0:
            df_names = df_names.drop((keys))
            logging.debug("directory " + faces_dir + " - removed " + str(len(keys)) + " ignored faces in face names")
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
            self.write_results(df_recognized, df, faces_dir)
        else:
            logging.debug("faces have not changed. No need to store faces to file.")

    def write_results(self, df_recognized, df_names, dir):
        df_names.drop('directory', axis=1, inplace=True)
        abs_dir = os.path.join(self.dirImages, dir)
        logging.debug("directory " + dir + " - faces have changed or where None before. Storing to file")
        self.store_face_names(df_names, abs_dir)
        if self.keep_history:
            self.store_face_presentations(df_recognized, abs_dir)

    # Add new images to a pandas.DataFrame for
    # - faces representations, faces.pkl, or
    # - facial attributes, faces_attributes.csv
    # - names of faces, faces.csv
    #
    # df...  pandas.DataFrame
    # dir... String, directory
    def add_new_images(self, df, storage, dir):
        images = self.get_images(dir)
        new_images = []
        for image in images:
            # Look for new images
            if df is None:
                new_images.append(image)
            else:
                if storage is self.file_name_face_representations:
                    for model_name in self.finder.model_names:
                        if not ((df['file'] == image) & (df['model'] == model_name) & (
                                df['detector'] == self.finder.detector_name)).any():
                            # path AND detector AND model do not exist in one row in stored DataFrame
                            logging.debug(
                                "directory " + dir + " - adding image because new combination of image=" +
                                image + " AND model=" + model_name + " AND detector=" + self.finder.detector_name)
                            new_images.append(image)
                            break
                if storage is self.file_name_facial_attributes:
                    if not ((df['file'] == image) & (df['detector'] == self.finder.detector_name)).any():
                        # path AND detector do not exist in one row in stored DataFrame
                        logging.debug(
                            "directory " + dir + " - adding image because new combination of image=" + image +
                            " AND detector=" + self.finder.detector_name)
                        new_images.append(image)

        return new_images

    def get_images(self, dir):
        images = []
        # check if image folders exist
        if os.path.isdir(dir):
            files = os.listdir(dir)
            valid_images = [".jpg", "jpeg", ".png", ".JPG", "JPEG", ".PNG"]
            for file in files:
                p = os.path.join(dir, file)
                if not os.path.isfile(p):
                    continue
                ext = os.path.splitext(p)[1]
                if ext.lower() not in valid_images:
                    continue
                if "," in file:
                    continue  # csv format
                # use a relative path to keep compatibility with server version
                df_path = p[len(self.dirImages) + 1:]
                images.append(df_path)
        return images

    def check_write_access(self, dir):
        if not os.access(dir, os.W_OK):
            logging.debug("directory " + dir + " - skipping... no write permission in directory")
            return False
        return True

    def check_file_access_statistics(self):
        addon_directory = os.path.join(self.dirImages, self.dir_addon)
        self.faces_statistics = os.path.join(addon_directory, self.file_name_faces_statistic)
        self.models_statistics = os.path.join(addon_directory, self.file_name_models_statistic)
        if not os.path.exists(addon_directory) and os.access(self.dirImages, os.W_OK):
            os.mkdir(addon_directory)
        if os.path.exists(addon_directory) and os.path.isdir(addon_directory) and os.access(addon_directory, os.W_OK):
            return True
        logging.debug("no write permissions to statistics directory= " + addon_directory)
        return False

    def get_face_representations(self, dir):
        # Load stored face representations
        df = None  # pandas.DataFrame that holds all face representations
        path = os.path.join(dir, self.file_name_face_representations)
        if os.path.exists(path):
            df = pd.read_parquet(path, engine="pyarrow")
            logging.debug("directory " + dir + " - loaded face representations from file " + path)
        if df is not None and len(df) == 0:
            return None
        return df

    def store_face_presentations(self, df, dir):
        df = df.reset_index(drop=True)
        path = os.path.join(dir, self.file_name_face_representations)
        df.to_parquet(path, engine="pyarrow", compression='gzip')
        logging.debug("directory " + dir + " - stored face representations in file " + path)
        return True

    def get_facial_attributes(self, dir):
        # Load stored facial attributes
        df = None  # pandas.DataFrame that holds all facial attributes
        path = os.path.join(dir, self.file_name_facial_attributes)
        if os.path.exists(path):
            if os.stat(path).st_size == 0:
                logging.info("directory " + dir + " - file holding face attributes is empty " + path)
                return df
            df = pd.read_json(path)
            logging.debug("directory " + dir + " - loaded facial attributes from file " + path)
        return df

    def store_facial_attributes(self, df, dir):
        path = os.path.join(dir, self.file_name_facial_attributes)
        df.to_json(path, index=False)
        logging.debug("directory " + dir + " - stored facial attributes in file " + path)

    def get_face_names(self, dir):
        # Load stored names
        df = None  # pandas.DataFrame that holds all face names
        path = os.path.join(dir, self.file_name_face_names_json)
        if os.path.exists(path):
            df = pd.read_json(path)
            logging.debug("directory " + dir + " - loaded face representations from file " + path)
        return df

    def init_face_names(self, df_representation, dir):
        df_names = self.get_face_names(dir)
        if (df_names is None) or (len(df_names) == 0):
            logging.debug("No face names yet or no longer because images where delete in dir.")
            most_effective_method = self.util.get_most_successful_method(df_representation, False)
            df_names = self.util.filter_by_last_named(df_representation)
            df_names = self.util.number_unique_faces(df_names)
            if most_effective_method is not None:  # for unit testing
                df_names = self.util.keep_most_effectiv_method_only(df_names, most_effective_method)
            else:
                df_names = self.util.minimize_results(df_names, False)
            self.write_results(df_representation, df_names, dir)
            return True
        else:
            return False

    def store_face_names(self, df, dir):
        df = self.util.minimize_results(df, False)
        for column in self.columnsToIncludeAll:
            if column not in df.columns:  # for unit testing
                continue
            if column not in self.columnsToInclude:
                df = df.drop(column, axis=1)
        if 'representation' in df.columns:  # for unit testing
            df = df.drop('representation', axis=1)
        df = df.sort_values(by=[self.sort_column], ascending=[self.sort_direction])

        path = os.path.join(self.dirImages, dir, self.file_name_face_names_json)
        df.to_json(path)

    def cleanup(self, dir):
        logging.debug("directory " + dir + " - 1. Step: cleaning up ---")
        images = self.get_images(dir)
        df = self.get_face_representations(dir)
        if df is not None:
            keys = self.util.remove_detector_model(df, self.removeModels, self.removeDetectors, dir)
            i = df[~df.file.isin(images)].index
            keys.extend(i.to_list())
            if len(keys) > 0:
                df = df.drop(keys)
                if self.store_face_presentations(df, dir):
                    if len(i) > 0:
                        logging.info(dir + " - " + str(len(images)) + " faces removed from face representations")

        df = self.get_face_names(dir)
        if df is not None:
            keys = self.util.remove_detector_model(df, self.removeModels, self.removeDetectors, dir)
            i = df[~df.file.isin(images)].index
            keys.extend(i.to_list())
            if len(keys) > 0:
                df = df.drop(keys)
                self.store_face_names(df, dir)
                if len(i) > 0:
                    logging.info(dir + " - " + str(len(images)) + " faces removed from face names")

        df = self.get_facial_attributes(dir)
        if df is not None:
            keys = self.util.remove_detector_model(df, "", self.removeDetectors, dir)
            i = df[~df.file.isin(images)].index
            keys.extend(i.to_list())
            if len(keys) > 0:
                df = df.drop(keys)
                self.store_facial_attributes(df, dir)
                if len(i) > 0:
                    logging.info(dir + " - " + str(len(i)) + " faces removed from facial attributes")

    def write_statistics(self, df, most_effective_method):
        if self.statistics_mode:
            if not self.check_file_access_statistics():
                return
            df = df.drop('representation', axis=1)
            df.to_csv(self.faces_statistics)
            logging.debug("Wrote face statistics to file=" + self.file_name_faces_statistic)
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
        else:
            if os.path.exists(self.faces_statistics):
                os.remove(self.faces_statistics)
            if os.path.exists(self.models_statistics):
                os.remove(self.models_statistics)

    def write_exif_dates(self, df, dir):
        if not self.exiftool:
            return df
        df.loc[(df['exif_date'].isna() == True), 'exif_date'] = ""  # get rid of "nan" values
        exif_date = ""
        files = df.file.unique()
        for file in files:
            exif_dates = df.loc[(df['file'] == file) & (df['exif_date'] != ""), "exif_date"]
            if len(exif_dates) > 0:
                exif_date = exif_dates.values[0]
                logging.debug(
                    "directory " + dir + " - exif date exists already '" + str(exif_date) +
                    "' for faces in image='" + file + "'")
            else:
                path = os.path.join(self.dirImages, file)
                exif_date = self.exiftool.getDate(path)
                logging.debug(
                    "directory " + dir + " - exif date returned by exiftool is '" + str(exif_date) +
                    "' for faces in image='" + file + "'")
            if exif_date != "":
                df.loc[(df['file'] == file) & (df['exif_date'] == ""), "exif_date"] = exif_date
                logging.debug(
                    "directory " + dir + " - wrote exif date '" + str(exif_date) +
                    "' (where empty) for faces in image='" + file + "'")
            continue
        return df
