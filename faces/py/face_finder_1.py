import numpy as np
import os
import datetime
import time
import cv2
from sklearn.preprocessing import LabelEncoder
from sklearn.svm import SVC
from PIL import Image

class Finder:

    def __init__(self):
        self.id = 1
        self.name = "opencv dnn sklearn"
        self.confidence = 0.5
        self.minsize = 20
        self.count_files = 0
        self.count_detected = 0
        self.count_trained = 0
        self.count_compared = 0
        self.count_predicted = 0

    def load(self, logger, csv):
        self.logger = logger
        self.logger.name = self.name
        dirpath = os.path.dirname(os.path.realpath(__file__))
        self.logger.log("current directory is : " + dirpath, 2)

        # load our serialized model from disk
        self.logger.log("loading model...", 2)
        self.net = cv2.dnn.readNetFromCaffe(dirpath + "/deploy.prototxt", dirpath + "/res10_300x300_ssd_iter_140000_fp16.caffemodel") # https://github.com/spmallick/learnopencv/tree/master/FaceDetectionComparison/models
        # load our serialized face embedding model from disk
        self.logger.log("loading face recognizer...", 2)
        self.embedder = cv2.dnn.readNetFromTorch(dirpath + "/openface.nn4.small2.v1.t7")
        for element in csv.split(";"):
            conf = element.split("=")
            if conf[0].strip() == 'confidence':
                self.confidence = float(conf[1])
            if conf[0].strip() == 'minsize':
                self.minsize = int(conf[1])
        self.logger.log("config confidence=" + str(self.confidence), 1)
        self.logger.log("config minsize=" + str(self.minsize), 1)

    def detect(self, path):
        startTime = int(round(time.time() * 1000))
        self.count_files += 1
        image = cv2.imread(path)
        (h, w) = image.shape[:2]
        self.logger.log("Image h=" + str(h) + ", w=" + str(w), 2)
        try:
            # load the input image and construct an input blob for the image
            # by resizing to a fixed 300x300 pixels and then normalizing it
            blob = cv2.dnn.blobFromImage(cv2.resize(image, (300, 300)), 1.0, (300, 300), (104.0, 177.0, 123.0))
            # pass the blob through the network and obtain the detections and
            # predictions
            self.logger.log("computing object detections...", 2)
            self.net.setInput(blob)
        except:
            self.logger.log("WARNING Skip this images. Why? Face_recognition failed to load file", 1)
            self.logger.log("A reason might be that an image exceeds the max image size from IMAGE = " + str(Image.MAX_IMAGE_PIXELS), 1)
            return [False, "error"]

        detections = self.net.forward()
        if detections is None:
            self.logger.log("Found no face in image with file id=" + str(id), 1)
            return [False, "no face"]
        else:
            # loop over the detections
            faces = []
            for i in range(0, detections.shape[2]):
                # extract the confidence (i.e., probability) associated with the
                # prediction
                confidence = detections[0, 0, i, 2]
                # filter out weak detections by ensuring the `confidence` is
                # greater than the minimum confidence
                if confidence > self.confidence:
                    # compute the (x, y)-coordinates of the bounding box for the object
                    box = detections[0, 0, i, 3:7] * np.array([w, h, w, h])
                    (startX, startY, endX, endY) = box.astype("int")

                    # extract the face ROI and grab the ROI dimensions
                    face = image[startY:endY, startX:endX]
                    (fH, fW) = face.shape[:2]
                    # ensure the face width and height are sufficiently large
                    if fW < self.minsize or fH < self.minsize:
                        self.logger.log("Found a face but ignore because it is too small confidence=" + str(confidence), 3)
                        continue

                    # construct a blob for the face ROI, then pass the blob
                    # through our face embedding model to obtain the 128-d
                    # quantification of the faceface_recognition -
                    faceBlob = cv2.dnn.blobFromImage(face, 1.0 / 255, (96, 96), (0, 0, 0), swapRB=True, crop=False)
                    self.embedder.setInput(faceBlob)
                    vec = self.embedder.forward()
                    encoding = vec.flatten()
                    a = []
                    for v in encoding:
                        a.append(str(v))
                    encodingS = ",".join(a)
                    a = []
                    for v in box:
                        a.append(str(v))
                    locationS = ",".join(a)
                    locationCSS = self.calculateCssLocation(startY, endX, endY, startX, h, w)
                    if not locationCSS:
                        continue
                    elapsed = int(round(time.time() * 1000)) - startTime
                    self.logger.log("Found a face with confidence=" + str(confidence) + " at position startX=" + str(startX) + ", startY=" + str(startY) + ", endX=" + str(endX) + ", endY=" + str(endY) + ". Used time is " + str(elapsed) + " ms. File path: " + path, 0)
                    faces.append([encodingS, locationS, confidence, face, elapsed, locationCSS])
                    self.count_detected += 1
        if len(faces) < 1:
            self.logger.log("Found no face (or the found ones are to small) in image", 1)
            return [False, "no face"]
        return faces

    def calculateCssLocation(self, top, right, bottom, left, h, w):
        px_top = top
        px_left = left
        px_right = w - right
        if px_right < 0:
            # sometimes it happens
            return False
        px_bottom = h - bottom
        if px_bottom < 0:
            # sometimes it happens
            return False
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
        face_encodings = []
        face_ids = []
        for (encoding_id, encoding, location, person_verified) in rows:
            person_id = str(person_verified)
            self.logger.log("loaded encoding_id=" + str(encoding_id) + ", person_verified=" + str(person_verified), 3)
            ar = np.array(encoding.split(","), dtype=np.float32)
            face_encodings.append(ar)
            face_ids.append(person_id)
            self.count_trained += 1

        data = {"embeddings": face_encodings, "names": face_ids}

        # encode the labels
        self.logger.log("encoding labels...", 2)
        self.le = LabelEncoder()
        labels = self.le.fit_transform(data["names"])
        # self.logger.log(str(type(labels)) + " -> " + str(labels), 2)

        # train the model used to accept the 128-d embeddings of the face and
        # then produce the actual face recognition
        self.logger.log("training model...", 2)
        self.recognizer = SVC(C=1.0, kernel="linear", probability=True)
        try:
            self.recognizer.fit(data["embeddings"], labels)
        except:
            self.logger.log("Found not enough verified faces", 2)
            return [False, "Not enough verified faces"]
        return [True, "Loaded verified faces"]

    def recognize(self, rows):
        predictedFaces = []
        for (encoding_id, encoding, location, face_to_predict) in rows:
            self.count_compared += 1
            startTime = int(round(time.time() * 1000))
            self.logger.log("loaded encoding_id=" + str(encoding_id), 2)
            vec_restored = np.array([np.array(encoding.split(","), dtype=np.float32)])
            preds = self.recognizer.predict_proba(vec_restored)[0]
            # self.logger.log(str(type(preds)) + " -> " + str(preds), 2)
            j = np.argmax(preds)
            proba = preds[j]  # we do not use the probability right here as we do with face_recognition. (This might change?)
            name = self.le.classes_[j]
            person_recognized = name
            self.logger.log("MATCH: Encoding with id=" + str(encoding_id) + " is predicted as " + str(person_recognized) + " with a probability of " + str(proba), 0)
            time_string = datetime.datetime.now(datetime.timezone.utc)
            elapsed = int(round(time.time() * 1000)) - startTime
            predictedFaces.append((str(person_recognized), str(proba), time_string, elapsed, str(encoding_id)))
            self.count_predicted += 1
        return predictedFaces