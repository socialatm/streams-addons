from deepface.commons import distance as dst
import time
import logging


class Recognizer:

    def __init__(self):
        self.names = None
        self.model_name = None
        self.distance_metrics_valid = ["cosine", "euclidean", "euclidean_l2"]
        # "Euclidean L2 form seems to be more stable than cosine and regular Euclidean distance
        # based on experiments." stated from https://github.com/serengil/deepface/
        self.distance_metrics = []
        self.distance_metric_default = "euclidean_l2"
        self.first_result = True
        self.thresholds_config = None
        self.most_similar_apply = True
        self.most_similar_percent = 70
        self.most_similar_number = 10

    def configure(self, json):

        # --------------------------------------------------------------------------------------------------------------
        # not set by user in frontend

        if "recognizer" in json and "valid_distance_metrics" in json["recognizer"]:
            self.distance_metrics_valid = json["recognizer"]["valid_distance_metrics"]
        logging.debug("config: valid_distance_metrics=" + str(self.distance_metrics_valid))

        # --------------------------------------------------------------------------------------------------------------
        # set by user in frontend

        if "distance_metrics" in json:
            for el in json["distance_metrics"]:
                if el[1]:
                    if el[0] in self.distance_metrics_valid and el[0] not in self.distance_metrics:
                        self.distance_metrics.append(el[0])
                    else:
                        logging.warning(str(el) +
                                        " is not a valid distance metrics (or already set). Hint: The distance" +
                                        " metrics is case sensitive.  Loading default model if no more valid" +
                                        " model name is given...")
                        logging.warning("Valid models are: " + str(self.distance_metrics_valid))
        logging.debug("config: distance_metrics=" + str(self.distance_metrics))

        if "enforce" in json:
            if json["enforce"][0][1]:
                self.first_result = False
            else:
                self.first_result = True
        logging.debug("config: first_result=" + str(self.first_result))

        if len(self.distance_metrics) == 0:
            self.distance_metrics.append(self.distance_metric_default)
            logging.warning("Using distance_metric_default=" + self.distance_metric_default)
            
        if "most_similar_number" in json:
            value = json["most_similar_number"][0][1]
            if isinstance(value, str) and value.isdigit():
                self.most_similar_number = int(value)
            elif isinstance(value, int):
                self.most_similar_number = value
            
        if "most_similar_percent" in json:
            value = json["most_similar_percent"][0][1]
            if isinstance(value, str) and value.isdigit():
                self.most_similar_number = int(value)
            elif isinstance(value, int):
                self.most_similar_percent = value
            
        logging.debug("config: most_similar_number=" + str(self.most_similar_number))
        logging.debug("config: most_similar_percent=" + str(self.most_similar_percent))

    def configure_thresholds(self, json, model_names):
        for model_name in model_names:
            if json[model_name]:
                if self.thresholds_config is None:
                    self.thresholds_config = {}
                for metric in self.distance_metrics:
                    if json[model_name][metric]:
                        if model_name not in self.thresholds_config:
                            self.thresholds_config[model_name] = {}
                        self.thresholds_config[model_name][metric] = float(json[model_name][metric])

    def train(self, names, model_name):
        if not 'source' in names.columns:
            names['source'] = ''  # for sharing faces with contacts
        self.model_name = model_name
        logging.debug("Received  " + str(len(names)) + " face(s) for model='" + model_name + "' as training data")
        if self.most_similar_apply:
            names = self.filter_best(names)
        self.names = names
        logging.info("Using  " + str(len(names)) + " face(s) for model='" + model_name + "' as training data")
        return names

    # Params
    # faces... pandas.DataFrame
    # model_name... name of the face regognition model, e.g. 'VGG-Face', 'Facenet', 'Facenet512', 'ArcFace'
    # return... an array of faces that where recognized
    def recognize(self, faces, thresholds):
        faces_recognized = []
        if len(faces) == 0:
            logging.debug("Received no face to recognize")
            return False
        if len(self.names) == 0:
            logging.debug("Received no face as training data")
            return False
        logging.debug("Searching in " + str(len(faces)) + " face(s) for model='" + self.model_name + "'...")
        start_time = time.time()
        matches = 0

        if thresholds is None or len(thresholds) < 1:
            thresholds = {}
            for distance_metric in self.distance_metrics:
                if self.thresholds_config is not None and self.thresholds_config[self.model_name][distance_metric]:
                    thresholds[distance_metric] = self.thresholds_config[self.model_name][distance_metric]
                else:
                    threshold = dst.findThreshold(self.model_name, distance_metric)
                    thresholds[distance_metric] = threshold

        #############################################################################################
        # start of face recognition
        # 
        # this is based on https://github.com/serengil/deepface/blob/master/deepface/commons/realtime.py
        #
        for index, face in faces.iterrows():
            img1_representation = face['representation']

            tic = time.time()

            for key in thresholds:
                distance_metric = key

                def find_distance(row):
                    img2_representation = row['representation']
                    distance = 1000  # initialize very large value
                    if distance_metric == 'cosine':
                        distance = dst.findCosineDistance(img1_representation, img2_representation)
                    elif distance_metric == 'euclidean':
                        distance = dst.findEuclideanDistance(img1_representation, img2_representation)
                    elif distance_metric == 'euclidean_l2':
                        distance = dst.findEuclideanDistance(dst.l2_normalize(img1_representation),
                                                             dst.l2_normalize(img2_representation))
                    return distance

                self.names['distance'] = self.names.apply(find_distance, axis=1)
                self.names = self.names.sort_values(by=["distance"])

                candidate = self.names.iloc[0]
                name = candidate['name']
                best_distance = candidate['distance']

                threshold = thresholds[distance_metric]

                if best_distance <= threshold:
                    face_recognized = {'id': face['id'],
                                       'name_recognized': name,
                                       'duration_recognized': round(time.time() - tic, 5),
                                       'distance': best_distance,
                                       'distance_metric': distance_metric}
                    # logging.debug("a face was recognized as " + str(name) + ", face id=" + str(
                    #     face['id']) + ", model=" + self.model_name + ", file=" + face['file'])
                    faces_recognized.append(face_recognized)
                    matches += 1
                    break
        logging.info(str(matches) + " matches in " + str(len(faces)) + " faces " + str(time.time() - start_time) +
                     "s " + self.model_name)

        return faces_recognized

    def create_probe_thresholds(self, metric):
        probe_thresholds = []
        if self.thresholds_config is None:
            default_threshold = dst.findThreshold(self.model_name, metric)
        else:
            default_threshold = self.thresholds_config[self.model_name][metric]
        for x in range(5, 16, 1):
            t = x / 10 * default_threshold
            probe_thresholds.append({metric: t})
        return probe_thresholds
    
    def filter_best(self, training_data):
        tic = time.time()
        training_data["average_distance"] = 1000
        names = training_data.name.unique()
        # calculate distances between the same person
        for name in names:
            faces = training_data[training_data['name'] == name]
            logging.debug("gathering most similar faces in " + str(len(faces)) + " faces for " + name)
            if len(faces) < 3:
                continue
            for index, face in faces.iterrows():
                img1_representation = face['representation']

                def find_distance(row):
                    img2_representation = row['representation']
                    distance = 1000  # initialize very large value
                    distance = dst.findCosineDistance(img1_representation, img2_representation)
                    return distance

                faces['distance'] = faces.apply(find_distance, axis=1)
                distances = faces["distance"].values
                sum = 0
                for distance in distances:
                    sum = sum + distance
                average = sum / len(distances)
                face["average_distance"] = average
                training_data.at[index, "average_distance"] = average
        # leave those embeddings for each person that have the nearest average distance to the same person
        keys = []
        for name in names:
            faces = training_data[training_data['name'] == name]
            # take the best as defined in percent and max number
            faces = faces.sort_values(by=["average_distance"])
            l = len(faces)
            if l > 2:
                l = l * self.most_similar_percent / 100
                l = round(l)
                if l < 1:
                    l = 1
            if l > self.most_similar_number:
                l = self.most_similar_number
            i = 0
            for index, face in faces.iterrows():
                if i >= l:
                    keys.append(index)
                i = 1 + i
            logging.debug("left " + str(l) + " faces for " + name)
        best = training_data.drop(keys)        
        if 'id' in best.columns:
            best = best.drop('id', axis=1)
        if 'distance' in best.columns:
            best = best.drop('distance', axis=1)
        logging.debug("did choose best faces and left " + str(len(best)) + " out of " + str(len(training_data))
                      + " faces as training data, max count=" + str(self.most_similar_number)
                      + ", max percent=" + str(self.most_similar_percent)
                      + " in " + str(round(time.time() - tic, 5)) + " s")
        return best
