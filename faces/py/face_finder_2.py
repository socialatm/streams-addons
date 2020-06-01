import numpy as np
from PIL import Image
import face_recognition
import time
import datetime

class Finder:

    def __init__(self):
        self.id = 2
        self.name = "dlib face_recognition"
        self.tolerance = 0.6
        self.model = "hog" # hog or cnn
        self.count_files = 0
        self.count_detected = 0
        self.count_trained = 0
        self.count_compared = 0
        self.count_predicted = 0

    def load(self, logger, csv):
        self.logger = logger
        self.logger.name = self.name
        for element in csv.split(";"):
            conf = element.split("=")
            if conf[0].strip() == 'tolerance':
                self.tolerance = float(conf[1])
            if conf[0].strip() == 'model':
                self.model = conf[1]
        # default is 0.6
        # less than 0.6 is more strict
        # https://github.com/ageitgey/face_recognition/wiki/Face-Recognition-Accuracy-Problems#question-face-recognition-works-well-with-european-individuals-but-overall-accuracy-is-lower-with-asian-individuals
        self.logger.log("config tolerance=" + str(self.tolerance) + ", model=" + self.model, 1)

    def detect(self, path):
        startTime = int(round(time.time() * 1000))
        self.count_files += 1
        try:
            image = face_recognition.load_image_file(path)
            (h, w) = image.shape[:2]
            self.logger.log("Image h=" + str(h) + ", w=" + str(w), 2)
        except:
            self.logger.log("WARNING Skip this images. Why? Face_recognition failed to load file", 1)
            self.logger.log("A reason might be that an image exceeds the max image size from IMAGE = " + str(Image.MAX_IMAGE_PIXELS), 1)
            return [False, "error"]

        face_locations = face_recognition.face_locations(image, model=self.model)
        face_encodings = face_recognition.face_encodings(image, face_locations)
        number_encodings = len(face_encodings)
        self.logger.log(str(number_encodings) + " faces in image with file=" + str(path), 2)
        if(number_encodings < 1):
            return [False, "no face"]
        faces = []
        for encoding, location in zip(face_encodings, face_locations):
            a = []
            for v in encoding:
                a.append(str(v))
                # self.logger.log(str(type(v)) + " -> " + str(v), 4) # class 'numpy.float64'> -> -0.12385625392198563
            encodingS = ",".join(a)
            a = []
            for v in location:
                a.append(str(v))
            locationS = ",".join(a)
            top, right, bottom, left = location
            face = image[top:bottom, left:right]
            locationCSS = self.calculateCssLocation(top, right, bottom, left, h, w)
            elapsed = int(round(time.time() * 1000)) - startTime
            self.logger.log("Found a face at position top=" + str(top) + ", bottom=" + str(bottom) + ", left=" + str(left) + ", right=" + str(right) + ". Used time is " + str(elapsed) + " ms. File path: " + path, 0)
            faces.append([encodingS, locationS, "0.0", face, elapsed, locationCSS])
            self.count_detected += 1
        if len(faces) < 1:
            self.logger.log("Found no face (or the found ones are to small) in image", 2)
            return [False, "no face"]
        return faces

    def calculateCssLocation(self, top, right, bottom, left, h, w):
        px_top = top
        px_left = left
        px_right = w - right
        px_bottom = h - bottom;
        right_percent = (px_right * 100) / w
        left_percent = (px_left * 100) / w
        top_percent = (px_top * 100) / h
        bottom_percent = (px_bottom * 100) / h
        l = [left_percent, right_percent, top_percent, bottom_percent]
        a = []
        for v in l:
            a.append(str(int(v)))
        locationCSS = ",".join(a)
        self.logger.log("CSS location of face is: " + locationCSS, 3)
        return locationCSS

    def train(self, rows):
        self.verified_face_encodings = []
        self.verified_face_ids = []
        for (encoding_id, encoding, location, person_verified) in rows:
            self.logger.log("loaded encoding_id=" + str(encoding_id), 3)
            # self.log.log(encoding, 4)
            string_array = encoding.split(",")
            a = []
            for s in string_array:
                n = np.float64(s)
                a.append(n)
            # self.logger.log(str(a), 4)
            self.verified_face_encodings.append(a)
            self.verified_face_ids.append(person_verified)
            self.count_trained += 1
        return [True, "Loaded verified faces"]

    def recognize(self, rows):
        predictedFaces = []
        if len(self.verified_face_encodings) < 1:
            self.logger.log("no verified faces yet", 1)
            return predictedFaces
        for (encoding_id, encoding, location, person_verified) in rows:
            self.count_compared += 1
            startTime = int(round(time.time() * 1000))
            self.logger.log("loaded encoding_id=" + str(encoding_id), 2)
            # self.logger.debug(encoding)
            unknown_face_enconding = np.array(encoding.split(","), dtype=np.float64)
            # self.logger.log(str(unknown_face_enconding), 4)
            results = face_recognition.compare_faces(self.verified_face_encodings, unknown_face_enconding, tolerance=self.tolerance)
            self.logger.log(str(results), 3)
            face_distances = face_recognition.face_distance(self.verified_face_encodings, unknown_face_enconding)
            best_match_index = np.argmin(face_distances)
            if results[best_match_index]:
                face_id = self.verified_face_ids[best_match_index]
                distance = face_distances[best_match_index]
                self.logger.log("MATCH: encoding id=" + str(encoding_id) + " is predicted as " + str(face_id) + " with a distance of " + str(distance), 0)
                time_string = datetime.datetime.now(datetime.timezone.utc)
                elapsed = int(round(time.time() * 1000)) - startTime
                predictedFaces.append((str(face_id), str(distance), time_string, elapsed, str(encoding_id)))
                self.count_predicted += 1
            else:
                self.logger.log("Encoding with id=" + str(encoding_id) + " has no match with verified faces", 2)

        return predictedFaces