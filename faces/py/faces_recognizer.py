from deepface.commons import distance as dst
import time
import logging


class Recognizer:

    def __init__(self):
        self.distance_metrics_valid = ["cosine", "euclidean", "euclidean_l2"]
        # "Euclidean L2 form seems to be more stable than cosine and regular Euclidean distance
        # based on experiments." stated from https://github.com/serengil/deepface/
        self.distance_metrics = []
        self.distance_metric_default = "cosine"
        self.first_result = True

    # csv
    # example
    #   distance_metric=cosine;first_result=on
    def configure(self, csv):
        logging.debug("configuration csv=" + csv)
        for element in csv.split(";"):
            conf = element.split("=")
            if len(conf) < 2:
                continue
            if conf[0].strip().lower() == 'distance_metrics':
                for el in conf[1].split(","):
                    if el.strip().lower() in self.distance_metrics_valid:
                        self.distance_metrics.append(el.strip().lower())
                    else:
                        logging.warning(conf[1] + " is not a valid distance_metric.")
                        logging.warning("Valid distance metrics are: " + str(self.distance_metrics_valid))
            if conf[0].strip().lower() == 'first_result':
                if conf[1].strip().lower() == "on":
                    self.first_result = True
                else:
                    self.first_result = False
            if conf[0].strip().lower() == 'enforce':
                if conf[1].strip().lower() == "on" or conf[1].strip().lower() == "true":
                    self.first_result = False
                else:
                    self.first_result = True
        if len(self.distance_metrics) == 0:
            self.distance_metrics.append(self.distance_metric_default)
            logging.warning("Using distance_metric_default=" + self.distance_metric_default)
        logging.debug("Configuration distance_metrics=" + str(self.distance_metrics))
        logging.debug("Configuration first_result=" + str(self.first_result))

    def train(self, names, model_name):
        self.names = names
        self.model_name = model_name
    # faces... pandas.DataFrame
    # model_name... name of the face regognition model, e.g. 'VGG-Face', 'Facenet', 'Facenet512', 'ArcFace'
    # return... an array of faces that where recognized
    def recognize(self, faces):
        faces_recognized = []
        logging.info("Received  " + str(len(names)) + " face(s) for model='" + model_name + "' as training data")

    # Params
        if len(faces) == 0:
            logging.debug("Received no face to recognize")
            return False
        if len(self.names) == 0:
            logging.debug("Received no face as training data")
            return False
        logging.debug("Searching in " + str(len(faces)) + " face(s) for model='" + self.model_name + "'...")
        start_time = time.time()
        matches = 0

        thresholds = {}
        for distance_metric in self.distance_metrics:
            threshold = dst.findThreshold(self.model_name, distance_metric)
            thresholds[distance_metric] = threshold

        #############################################################################################
        # start of face recognition
        # 
        # this is based on https://github.com/serengil/deepface/blob/master/deepface/commons/realtime.py
        #
        for index, face in faces.iterrows():
            img1_representation = face['representation']
            # Check if face is really of the same model (Should always be. But if not: the results would be unexpected and the cause would not be obvious.)
            if face['model'] != self.model_name:
                logging.warning("Received face(id=" + face['id'] + ") with wrong model=" + str(
                    face['model']) + " but expected model=" + self.model_name)
                return False

            def findDistance(row):
                img2_representation = row['representation']

                # print(row['id'] + " " + row['model'] + " " + row['detector'] + " len img presentations " + str(len(img1_representation)) + ", " + str(len(img2_representation)))

                distance = 1000  # initialize very large value
                if self.distance_metric == 'cosine':
                    distance = dst.findCosineDistance(img1_representation, img2_representation)
                elif self.distance_metric == 'euclidean':
                    distance = dst.findEuclideanDistance(img1_representation, img2_representation)
                elif self.distance_metric == 'euclidean_l2':
                    distance = dst.findEuclideanDistance(dst.l2_normalize(img1_representation),
                                                         dst.l2_normalize(img2_representation))

                return distance

            tic = time.time()

            for distance_metric in self.distance_metrics:
                self.distance_metric = distance_metric
                threshold = thresholds[self.distance_metric]

                self.names['distance'] = self.names.apply(findDistance, axis=1)
                self.names = self.names.sort_values(by=["distance"])

                candidate = self.names.iloc[0]
                name = candidate['name']
                best_distance = candidate['distance']

                if best_distance <= threshold:
                    face_recognized = {}
                    face_recognized['id'] = face['id']
                    face_recognized['name_recognized'] = name
                    face_recognized['duration_recognized'] = round(time.time() - tic, 5)
                    face_recognized['distance'] = best_distance
                    face_recognized['distance_metric'] = self.distance_metric
                    logging.debug("a face was recognized as " + str(name) + ", face id=" + str(
                        face['id']) + ", model=" + self.model_name + ", file=" + face['file'])
                    faces_recognized.append(face_recognized)
                    matches += 1
                    break
        logging.info(str(matches) + " matches in " + str(len(faces)) + " faces " + str(time.time() - start_time) +
                     "s " + self.model_name)

        return faces_recognized
