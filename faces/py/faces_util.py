import pandas as pd
import time
import logging


class Util:

    def __init__(self):
        self.IGNORE = "-ignore-"
        self.is_css_position = True

    def keep_most_effectiv_method_only(self, df, most_effective_method):
        most_effective_method = most_effective_method.sort_values(by=["accuracy"], ascending=[False])
        df_result = pd.DataFrame(columns=df.columns)
        files = df.file.unique()
        for file in files:
            df_temp = df[df['file'] == file]
            numbers = df_temp.face_nr.unique()
            for number in numbers:
                matches = df_temp.loc[
                    (df_temp['position'] != "") & (df_temp['file'] == file) & (df_temp['face_nr'] == number)]
                count = len(matches)
                if count == 1:
                    df_result = pd.concat([df_result, matches], ignore_index=True)
                elif count > 1:
                    if len(most_effective_method) == 0:
                        df_result = pd.concat([df_result, matches.iloc[[0]]], ignore_index=True)
                        continue
                    keys = matches.index
                    # TODO: there must e a better approach
                    found = False
                    for row in most_effective_method.itertuples():
                        model = row.model
                        detector = row.detector
                        i = matches[(matches["model"] == model) & (matches["detector"] == detector)].index
                        if len(i) == 0:
                            continue
                        elif len(i) == 1:
                            df_result = pd.concat([df_result, df.loc[[i[0]]]], ignore_index=True)
                            found = True
                            break
                        else:
                            # This happened while testing stuff - the two files for names and representations where
                            # not in sync (after "cleanup" that was interrupted)
                            logging.error(
                                "Double face for file='" + file + "',  model='" + model + "', detector='" + detector +
                                "'. May be the 'cleanup' was interrupted after deleting pictures?")
                    if not found:
                        df_result = pd.concat([df_result, df.loc[[matches.index[0]]]], ignore_index=True)
        return df_result

    def remove_detector_model(self, df, models_to_remove, remove_detectors, dir):
        keys = []
        if (models_to_remove != "") and (remove_detectors != ""):
            for model in models_to_remove.split(","):
                for detector in remove_detectors.split(","):
                    i = df.loc[(df['detector'] == detector) & (df['model'] == model)].index
                    keys.extend(i.to_list())
            logging.info("directory " + dir + " - removing " + str(
                len(keys)) + " faces for detector=" + remove_detectors + " and model=" + models_to_remove)
        elif remove_detectors != "":
            for detector in remove_detectors.split(","):
                i = df.loc[df['detector'] == detector].index
                keys.extend(i.to_list())
            logging.info(
                "directory " + dir + " - removing " + str(
                    len(keys)) + " faces for detectors=" + remove_detectors)
        elif models_to_remove != "":
            for model in models_to_remove.split(","):
                i = df.loc[df['model'] == model].index
                keys.extend(i.to_list())
            logging.info(
                "directory " + dir + " - removing " + str(len(keys)) + " faces for model=" + models_to_remove)
        return keys

    def copy_name_to_same_faces(self, df):
        logging.debug("Start copy names to same faces")
        start_copy = time.time()
        df = df.sort_values(["file", "face_nr", "time_named"], ascending=[True, True, False])
        file_last = ""
        face_nr_last = ""
        name = ""
        time_named = ""
        for row in df.itertuples():
            file = row.file
            face_nr = row.face_nr
            if file != file_last:
                face_nr_last = face_nr
                file_last = file
                name = row.name
                time_named = row.time_named
                continue
            if face_nr != face_nr_last:
                face_nr_last = face_nr
                name = row.name
                time_named = row.time_named
                continue
            df.loc[row.Index, ['name', 'time_named']] = [name, time_named]
            # df.loc[row.Index, ['name']] = [name]
        logging.debug("Finished copy names to same faces after " + str(time.time() - start_copy) + " seconds.")
        return df

    def filter_by_last_named(self, df):
        logging.debug("filter last named faces")
        df.loc[(df['time_named'].isna() == True), 'time_named'] = ""  # get rid of "nan" values
        df = df.sort_values(["file", "face_nr", "time_named"], ascending=[True, True, False])
        file_last = ""
        face_nr_last = ""
        name = ""
        time_named = ""
        keys = []
        ignore = False
        for row in df.itertuples():
            file = row.file
            face_nr = row.face_nr
            if file != file_last:
                face_nr_last = face_nr
                file_last = file
                name = row.name
                time_named = row.time_named
                if time_named == "":
                    ignore = True
                else:
                    ignore = False
                continue
            if face_nr != face_nr_last:
                face_nr_last = face_nr
                name = row.name
                time_named = row.time_named
                if time_named == "":
                    ignore = True
                else:
                    ignore = False
                continue
            if not ignore:
                keys.append(row.Index)
        if len(keys) > 0:
            df = df.drop(keys)
            logging.debug("removed " + str(len(keys)) + " faces.")
        return df

    # TODO: In praxis this function should be obsolete.
    # Why?
    # The keepMostEffectivMethodOnly() is applied before and this has done the job already.
    # After keepMostEffectivMethodOnly() only one face should be present at a certain position in the images.
    def minimize_results(self, df, remove_method):
        no_faces = df.loc[(df['face_nr'] == 0)]
        if len(no_faces) > 0:
            keys = no_faces.index
            df = df.drop(keys)
        paths = df.file.unique()
        for path in paths:
            image = df[df['file'] == path]
            unique_face_numbers = image.face_nr.unique()
            for nr in unique_face_numbers:  # faces at the same position in image
                if nr == 0:
                    continue  # indicates an image without a face
                faces = image.loc[image["face_nr"] == nr]
                if len(faces) == 1:
                    continue
                names = faces.name.unique()
                names_recognized = faces.name_recognized.unique()
                if len(names) > 0 and names[0] != "":
                    # keep one single face for every name (it should exist exactly one single name always)
                    if len(names) > 1:
                        logging.warning(
                            "This should never happen and is a programming error. More than one names (set by user) "
                            "where found in file=" + str(path) + " for face number=" + str(nr) +
                            ". The program will use the first name only.")
                    faces = df.loc[(df['face_nr'] == nr) & (df['file'] == path)]
                    keys = faces.index
                    if remove_method:
                        df.loc[keys[0], ["model", "detector", "name_recognized"]] = ["", "", ""]
                    keys = keys[1:]
                    df = df.drop(keys)
                elif len(names_recognized) > 0:
                    # keep one single face for every recognized name
                    for name in names_recognized:
                        faces = df.loc[(df['face_nr'] == nr) & (df['file'] == path) & (df['name_recognized'] == name)]
                        keys = faces.index
                        if name != "":
                            if remove_method:
                                df.loc[keys[0], ["model", "detector"]] = ["", ""]
                            keys = keys[1:]
                            df = df.drop(keys)
                        elif name == "" and len(names_recognized) > 1:
                            df = df.drop(keys)
                        elif name == "" and len(names_recognized) == 1:
                            if remove_method:
                                df.loc[keys[0], ["model", "detector"]] = ["", ""]
                            keys = keys[1:]
                            df = df.drop(keys)
        return df

    def number_unique_faces(self, df):
        tic = time.time()
        # order by "time_created" keeps same order (better) over time if several detectors are used.
        # This is important for naming faces (by PHP)
        df = df.sort_values(by=["file"])
        file = ""
        faces = []
        nr = 0
        for row in df.itertuples():
            if file != row.file:
                file = row.file
                faces = []
                nr = 0
            if len(row.position) != 4:
                continue
            if len(faces) == 0:
                nr += 1
                faces.append({'nr': nr, 'position': row.position})
                df.loc[row.Index, "face_nr"] = nr
                continue
            found = False
            for face in faces:
                if self.is_same_face(face["position"], row.position):
                    df.loc[row.Index, "face_nr"] = face['nr']
                    found = True
                    break
            if not found:
                nr += 1
                faces.append({'nr': nr, 'position': row.position})
                df.loc[row.Index, "face_nr"] = nr
        logging.debug("number unique faces took " + str(time.time() - tic) + " seconds for " + str(len(df)) + " files")
        return df

    def is_same_face(self, face_a, face_b):
        if self.is_css_position:
            # margins left, right, top, bottom in percent
            middle_of_face_x = int(face_a[0]) + (100 - (int(face_a[1]) + int(face_a[0]))) / 2
            middle_of_face_y = int(face_a[2]) + (100 - (int(face_a[2]) + int(face_a[3]))) / 2
            end_of_face_b_x = 100 - face_b[1]
            end_of_face_b_y = 100 - face_b[3]
            # is middle of face inside face_b position?
            if (face_b[0] < middle_of_face_x) and (middle_of_face_x < (end_of_face_b_x)):
                if (face_b[2] < middle_of_face_y) and (middle_of_face_y < (end_of_face_b_y)):
                    middle_of_face_b_x = int(face_b[0]) + (100 - (int(face_b[1]) + int(face_b[0]))) / 2
                    middle_of_face_b_y = int(face_b[2]) + (100 - (int(face_b[2]) + int(face_b[3]))) / 2
                    end_of_face_x = 100 - face_a[1]
                    end_of_face_y = 100 - face_a[3]
                    # is middle of face_b position inside face ?
                    if (face_a[0] < middle_of_face_b_x) and (middle_of_face_b_x < (end_of_face_x)):
                        if (face_a[2] < middle_of_face_b_y) and (middle_of_face_b_y < (end_of_face_y)):
                            return True
        else:
            # x, y, w, h : left upper corner is x=0, y=0
            middle_of_face_x = int(face_a[0]) + int(face_a[2]) / 2
            middle_of_face_y = int(face_a[1]) + int(face_a[3]) / 2
            end_of_face_b_x = face_b[0] + face_b[2]
            end_of_face_b_y = face_b[1] + face_b[3]
            # is middle of face b inside face a?
            if (face_b[0] < middle_of_face_x) and (middle_of_face_x < (end_of_face_b_x)):
                if (face_b[1] < middle_of_face_y) and (middle_of_face_y < (end_of_face_b_y)):
                    middle_of_face_b_x = int(face_b[0]) + int(face_b[2]) / 2
                    middle_of_face_b_y = int(face_b[1]) + int(face_b[3]) / 2
                    end_of_face_x = face_a[0] + face_a[2]
                    end_of_face_y = face_a[1] + face_a[3]
                    # is middle of face a inside face b?
                    if (face_a[0] < middle_of_face_b_x) and (middle_of_face_b_x < (end_of_face_x)):
                        if (face_a[1] < middle_of_face_b_y) and (middle_of_face_b_y < (end_of_face_y)):
                            return True
        return False

    def get_most_successful_method(self, df, write_detector):
        df_results = df.loc[
            df['time_named'] != "", ['name', 'name_recognized', 'model', 'detector', 'duration_representation',
                                     'duration_detection']]

        df_most_effective = pd.DataFrame(
            columns=['model', 'detector', 'accuracy', 'detected', 'verified', 'recognized', 'correct', 'wrong',
                     'ignored', "seconds", 'seconds/face'])
        models = df_results.model.unique()
        detectors = df_results.detector.unique()
        for detector in detectors:
            sum_correct = 0
            sum_wrong = 0
            sum_ignored = 0
            sum_detected = 0
            sum_recognized = 0
            for model in models:
                correct = df_results.loc[(df_results['model'] == model) & (df_results['detector'] == detector) & (
                        df_results['name'] == df_results['name_recognized'])]
                sum_correct += len(correct)
                wrong = df_results.loc[(df_results['model'] == model) & (df_results['detector'] == detector) & (
                        df_results['name'] != df_results['name_recognized'])]
                sum_wrong += len(wrong)
                ignored = df_results.loc[(df_results['model'] == model) & (df_results['detector'] == detector) & (
                        df_results['name'] == self.IGNORE)]
                sum_ignored += len(ignored)
                df_all = df_results.loc[
                    (df_results['model'] == model) & (df_results['detector'] == detector), 'duration_representation']
                if len(df_all) == 0:
                    continue
                detected = df.loc[(df['model'] == model) & (df['detector'] == detector) & (df['position'] != "")]
                recognized = df.loc[(df['model'] == model) & (df['detector'] == detector)
                                    & (df['position'] != "") & (df['name_recognized'] != "")]
                sum_detected += len(detected)
                sum_recognized += len(recognized)
                accuracy = round(len(correct) * 100 / len(df_all), 1)
                row = {'model': model, 'detector': detector, 'accuracy': accuracy, 'detected': len(detected),
                       'verified': len(df_all), 'recognized': len(recognized), 'correct': len(correct),
                       'wrong': len(wrong), 'ignored': len(ignored), 'seconds': round(sum(df_all.values)),
                       'seconds/face': round(sum(df_all.values) / len(df_all), 2)}
                df_most_effective = df_most_effective.append(row, ignore_index=True)
            if write_detector:
                df_all = df_results.loc[df_results['detector'] == detector, 'duration_representation']
                if len(df_all) == 0:
                    continue
                accuracy = round(sum_correct * 100 / len(df_all), 1)
                row = {'model': "", 'detector': detector, 'accuracy': accuracy, 'detected': sum_detected,
                       'verified': len(df_all), 'recognized': sum_recognized, 'correct': sum_correct,
                       'wrong': sum_wrong, 'ignored': sum_ignored, 'seconds': round(sum(df_all.values)),
                       'seconds/face': round(sum(df_all.values) / len(df_all), 2)}
                df_most_effective = df_most_effective.append(row, ignore_index=True)
        df_most_effective = df_most_effective.sort_values(by=["accuracy"], ascending=[False])
        logging.debug("Tabel of effectiveness of detectors and models was created")
        return df_most_effective

    def has_any_name_changed(self, df, df_names):
        files = df.file.unique()
        for file in files:
            faces = df[df['file'] == file]
            face_numbers = faces.face_nr.unique()
            for number in face_numbers:
                face_a = df[(df['file'] == file) & (df['face_nr'] == number)]
                faces_b = df_names[df_names['file'] == file]
                if len(faces_b) == 0:
                    return True
                for face_b in faces_b.itertuples():
                    position_b = face_b.position
                    # position_b = []
                    # for el in face_b.position.strip("[] ").replace(" ", "").split(","):
                    # for el in face_b.position.strip("[] ").split(" "):
                    # position_b.append(int(el))
                    if self.is_same_face(face_a.iloc[0, 2], position_b):
                        if face_a.iloc[0, 4] != face_b.name or face_a.iloc[0, 5] != face_b.name_recognized:
                            return True
        return False

    def create_frame_embeddings(self):
        df = pd.DataFrame({'id': pd.Series(dtype='str'),
                           'file': pd.Series(dtype='str'),
                           'position': pd.Series(dtype='int'),
                           'width': pd.Series(dtype='int'),  # px
                           'face_nr': pd.Series(dtype='int'),
                           'name': pd.Series(dtype='str'),
                           'name_recognized': pd.Series(dtype='str'),
                           'time_named': pd.Series(dtype='str'),
                           'exif_date': pd.Series(dtype='str'),
                           'detector': pd.Series(dtype='str'),
                           'model': pd.Series(dtype='str'),
                           'duration_detection': pd.Series(dtype='float'),
                           'duration_representation': pd.Series(dtype='float'),
                           'time_created': pd.Series(dtype='datetime64[ns]'),
                           'representation': pd.Series(dtype='float64'),
                           'distance': pd.Series(dtype='float'),
                           'distance_metric': pd.Series(dtype='str'),
                           'duration_recognized': pd.Series(dtype='float'),
                           'directory': pd.Series(dtype='str')})
        return df

    def add_row_embedding(self, df, values):
        if df is None:
            df = self.create_frame_embeddings()

        row = pd.Series(
            [values[0],
             values[1],
             values[2],
             values[3],
             values[4],
             values[5],
             values[6],
             values[7],
             values[8],
             values[9],
             values[10],
             values[11],
             values[12],
             values[13],
             values[14],
             values[15],
             values[16],
             values[17],
             values[18]], index=df.columns)

        df = df.append(row, ignore_index=True)
        return df

    def create_frame_attributes(self):
        df = pd.DataFrame({'file': pd.Series(dtype='str'),
                           'position': pd.Series(dtype='int'),
                           'detector': pd.Series(dtype='str'),
                           'emotions': pd.Series(dtype='str'),
                           'dominant_emotion': pd.Series(dtype='str'),
                           'age': pd.Series(dtype='int'),
                           'gender': pd.Series(dtype='str'),
                           'races': pd.Series(dtype='str'),
                           'dominant_race': pd.Series(dtype='str'),
                           'created': pd.Series(dtype='str'),
                           'duration': pd.Series(dtype='float')})
        return df

    def add_row_attributes(self, df, values):
        if df is None:
            df = self.create_frame_attributes()

        row = pd.Series(
            [values[0],
             values[1],
             values[2],
             values[3],
             values[4],
             values[5],
             values[6],
             values[7],
             values[8],
             values[9],
             values[10]], index=df.columns)

        df = df.append(row, ignore_index=True)
        return df
