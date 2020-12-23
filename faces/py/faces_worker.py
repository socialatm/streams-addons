import datetime
from PIL import Image
import os

import importlib

cv2_spec = importlib.util.find_spec("cv2")
sklearn_spec = importlib.util.find_spec("sklearn")
if cv2_spec is not None and sklearn_spec is not None:
    import face_finder_1

face_recognition_spec = importlib.util.find_spec("face_recognition")
if face_recognition_spec is not None:
    import face_finder_2

class Worker:

    def __init__(self):
        self.logger = None
        # self.channel_id
        # applies to the recognition only (has no effect on detection)
        # -1 default: every channel (user) uses his own face encoding only
        #  0 : kind of admin usage. Does not care wich face encoding belongs to wich channel
        # >0 : recognize faces for this channel only (has no effect on detection)
        self.channel_id = -1
        self.limit = 100
        self.finder1 = None
        self.finder2 = None
        self.time_lastupdated = ''
        self.time_is_awake = 10

    def setFinder1(self, csv):
        self.logger.log("set finder1 using csv=" + csv, 2)
        cv2_spec = importlib.util.find_spec("cv2")
        sklearn_spec = importlib.util.find_spec("sklearn")
        if cv2_spec is not None and sklearn_spec is not None:
            self.finder1 = face_finder_1.Finder()
            self.finder1.load(self.logger, csv)
        else:
            self.logger.log("FAILED to set finder1. Reason: module sklearn not found", 1)

    def setFinder2(self, csv):
        self.logger.log("set finder2 using csv=" + csv, 2)
        face_recognition_spec = importlib.util.find_spec("face_recognition")
        if face_recognition_spec is not None:
            self.finder2 = face_finder_2.Finder()
            self.finder2.load(self.logger, csv)
        else:
            self.logger.log("FAILED to set finder2. Reason: module face_recognition not found", 1)

    def run(self, dir_images, proc_id):
        self.dirImages = dir_images
        self.proc_id = proc_id
        self.logger.log("Started with: limit=" + str(self.limit) + ", channel_id=" + str(self.channel_id) + ", time_is_awake=" + str(self.time_is_awake) + ", proc_id=" + str(self.proc_id) + ", image dir=" + self.dirImages, 1)
        if self.logger.file is not None:        
            self.logger.log("Started logger with: log file=" + self.logger.file + ", log level=" + str(self.logger.loglevel)  + ", log console=" + str(self.logger.console), 1)

        # check images size to avoid DecompressionBombWarning and running out of memory
        Image.warnings.simplefilter('error', Image.DecompressionBombWarning)

        counter_1 = 1
        counter_2 = 1
        i = 1
        while counter_1 > 0 or counter_2 > 0:
            self.logger.name = ""
            self.selectChannelIDs()
            self.logger.log("Starting loop " + str(i), 1)
            if self.finder1 is not None:
                self.finder = self.finder1
                counter_1 = self.loopFiles()
            else:
                counter_1 = 0
            if self.finder2 is not None:
                self.finder = self.finder2
                counter_2 = self.loopFiles()
            else:
                counter_2 = 0
            self.logger.log("Finished loop " + str(i), 1)
            i += 1

        self.logger.log("Finished looping", 1)
        self.updateFinished()

    def loopFiles(self):
        counter = self.detect()
        if self.channel_id > -1:
            self.recognize(self.channel_id)
        else:
            for cid in self.chan_ids:
                self.recognize(cid)
        return counter

    def updateFinished(self):
        time_now = datetime.datetime.now(datetime.timezone.utc)
        msg = self.getStatus("Result")
        query = ("UPDATE faces_proc SET updated = %s, finished = %s, running = %s, summary = %s WHERE proc_id = %s")
        data = (time_now, time_now, "0", msg, str(self.proc_id))
        self.db.update(query, data)

    def updateAwake(self):
        time_now = datetime.datetime.now(datetime.timezone.utc)
        if self.time_lastupdated == '':
            self.time_lastupdated = time_now
        elapsed = (time_now - self.time_lastupdated).total_seconds()
        if self.time_is_awake < elapsed:
            msg = self.getStatus("Status")
            query = ("UPDATE faces_proc SET updated = %s, summary = %s WHERE proc_id = %s")
            data = (time_now, msg, str(self.proc_id))
            self.db.update(query, data)

    def getStatus(self, header):
        msg = header
        if self.finder1 is not None:
            msg = self.appendStatusMessage(self.finder1, msg)
        if self.finder2 is not None:
            msg = self.appendStatusMessage(self.finder2, msg)
        return msg

    def appendStatusMessage(self, f, msg):
        msg += " Finder " + f.name
        msg += ": detected " + str(f.count_detected) + " faces in " + str(f.count_files) + " files"
        msg += ", predicted " + str(f.count_predicted) + " / " + str(f.count_compared) + " faces using " + str(f.count_trained) + " verified faces."
        return msg

    def recognize(self, cid):
        self.logger.log("Read known face encodings from database for channel id=" + str(cid) + "...", 2)
        # load verified encodings
        if cid != 0:
            query = ("SELECT "
                            "encoding_id, encoding, location, person_verified "
                        "FROM "
                            "faces_encoding "
                        "WHERE "
                            "person_verified != 0 AND "
                            "finder = %(finder)s AND "
                            "channel_id = %(channel_id)s  AND "
                            "encoding != ''")
            data = {'finder': self.finder.id, 'channel_id': cid}
        else:
            self.logger.log("Select all face encodings to train the model because channel_id=" + str(cid), 2)
            query = ("SELECT "
                            "encoding_id, encoding, location, person_verified "
                        "FROM "
                            "faces_encoding "
                        "WHERE "
                            "finder = %(finder)s AND "
                            "person_verified != 0 AND "
                            "encoding != ''")
            data = { 'finder': self.finder.id }

        rows = self.db.select(query, data)

        result = self.finder.train(rows)
        numberVerifiedFaces = len(rows)

        if not result[0]:
            status = result[1]
            self.logger.log("Summarized result: " + status, 1)
            return

        self.logger.log("Read unknown face encodings from database for channel id=" + str(cid) + "...", 2)
        # load encodings to recognize
        if cid != 0:
            query = ("SELECT "
                            "encoding_id, encoding, location, person_verified "
                        "FROM "
                            "faces_encoding "
                        "WHERE "
                            "encoding != '' AND "
                            "finder = %(finder)s AND "
                            "person_verified = 0 AND "
                            "person_marked_unknown = 0 AND "
                            "marked_ignore = 0 AND "
                            "channel_id = %(channel_id)s ")
            data = { 'finder': self.finder.id, 'channel_id': cid }
        else:
            self.logger.log("Select all face encodings to recognize because channel_id=" + str(cid), 2)
            query = ("SELECT "
                            "encoding_id, encoding, location, person_verified "
                        "FROM "
                            "faces_encoding "
                        "WHERE "
                            "encoding != '' AND "
                            "finder = %(finder)s AND "
                            "person_verified = 0 AND "
                            "person_marked_unknown = 0 AND "
                            "marked_ignore = 0")
            data = { 'finder': self.finder.id }

        rows = self.db.select(query, data)
        predictedFaces = self.finder.recognize(rows)

        query = ("UPDATE faces_encoding "
                    "SET "
                        "person_recognized = %s, "
                        "distance = %s, "
                        "recognized_updated = %s, "
                        "recognized_time = %s "
                    "WHERE encoding_id = %s ")
        counter_guessed = 0
        for data in predictedFaces:
            self.db.update(query, data)
            self.updateAwake()
            counter_guessed += 1
        msg = "Predicted " + str(counter_guessed) + " faces using " +  str(numberVerifiedFaces) + " faces verified by user(s)."
        self.logger.log("Summarized result: " + msg, 1)

    def detect(self):
        self.logger.name = self.finder.name
        images = self.selectImagesToDetect()
        count_encodings = self.detectFaces(images)
        return count_encodings

    def detectFaces(self, images):
        self.logger.log("Start to detect faces in " + str(len(images)) + " images", 1)
        count_encodings = 0
        for (id, os_path, uid) in images:
            is_update = True
            abs_path = self.dirImages + "/" + os_path
            self.logger.log("start to detect faces in file with id=" + str(id) + " , abs_path=" + abs_path, 2)
            if not os.access(abs_path, os.R_OK):
                self.logger.log("WARNING Skip this images. Why? File might not exist or missing permissions. Face_recognition failed to load file " + abs_path, 1)
                self.writeErrorDetectingFacesInImage(id)
                continue

            faces = self.finder.detect(abs_path)

            if not faces[0]:
                status = faces[1]
                if status == "error":
                    self.logger.log("Error when trying to read file id=" + str(id), 1)
                    self.writeErrorDetectingFacesInImage(id)
                    continue
                elif status == "no face":
                    self.logger.log("No face in image found for file id=" + str(id), 2)
                    self.writeNoFaceFoundInImage(id)
                    continue
                else:
                    self.logger.log("Possible programming error. No face in image found for file id=" + str(id), 1)
                    self.writeErrorDetectingFacesInImage(id)
                    continue

            for face in faces:
                #self.logger.log(str(face), 2)
                encoding, box, confidence, faceImg, elapsed, locationCSS = face
                #cv2.imshow("Detected", faceImg)
                #cv2.waitKey(0)
                if is_update:
                    self.writeFaceEncodingForImage(id, encoding, box, confidence, elapsed, locationCSS)
                    is_update = False
                else:
                    self.insertFaceEncoding(id, uid, encoding, box, confidence, elapsed, locationCSS)
                count_encodings += 1

            self.proc_message = "Detected " +str(count_encodings) + " faces in " + str(len(images)) + " images. "
            self.updateAwake()
        return count_encodings

    def insertFaceEncoding(self, id, uid, encoding, location, confidence, elapsed, locationCSS):
        self.logger.log("Insert into faces_encoding: face encoding for file id=" + str(id) + ", finder id=" + str(self.finder.id), 2)
        time_string = datetime.datetime.now(datetime.timezone.utc)
        query = ("INSERT INTO faces_encoding "
                      "(finder, channel_id, id, encoding, location, location_css, confidence, encoding_created, encoding_time) "
                      "VALUES (%(finder)s, %(channel_id)s, %(image)s, %(encoding)s, %(location)s, %(locationCSS)s, %(confidence)s, %(encoding_created)s, %(encoding_time)s)")
        data = {
            'finder': str(self.finder.id),
            'channel_id': uid,
            'image': str(id),
            'encoding': encoding,
            'location': location,
            'locationCSS': locationCSS,
            'confidence': str(confidence),
            'encoding_created': time_string,
            'encoding_time': elapsed,
        }
        self.db.update(query, data)

    def writeFaceEncodingForImage(self, id, encoding, location, confidence, elapsed, locationCSS):
        self.logger.log("Update faces_encoding: face encoding for file id=" + str(id) + ", finder id=" + str(self.finder.id), 2)
        time_string = datetime.datetime.now(datetime.timezone.utc)
        query = ("UPDATE faces_encoding "
                    "SET "
                        "encoding_created = %s, "
                        "encoding = %s, "
                        "encoding_time = %s, "
                        "confidence = %s, "
                        "location = %s, "
                        "location_css = %s "
                    "WHERE id = %s AND finder = %s")
        data = (time_string, encoding, elapsed, str(confidence), location, locationCSS, str(id), str(self.finder.id))
        self.db.update(query, data)

    def writeErrorDetectingFacesInImage(self, id):
        self.logger.log("Update faces_encoding: face detection throw an error, file id=" + str(id), 2)
        time_string = datetime.datetime.now(datetime.timezone.utc)
        query = ("UPDATE faces_encoding "
                    "SET "
                        "error = %s "
                    "WHERE id = %s AND finder = %s")
        data = ("1", str(id), str(self.finder.id))
        self.db.update(query, data)

    def writeNoFaceFoundInImage(self, id):
        self.logger.log("Update faces_encoding: image with no face, file id=" + str(id), 2)
        time_string = datetime.datetime.now(datetime.timezone.utc)
        query = ("UPDATE faces_encoding "
                    "SET "
                        "encoding_created = %s, "
                        "no_faces = %s "
                    "WHERE id = %s  AND finder = %s")
        data = (time_string, "1", str(id), str(self.finder.id))
        self.db.update(query, data)

    def selectImagesToDetect(self):
        images = []
        self.logger.log("Select images with no face detection performed for channel", 2)
        query = ("SELECT "
                        "a.id, "
                        "a.content, "
                        "a.uid, "
                        "e.error, "
                        "e.no_faces, "
                        "e.encoding "
                    "FROM "
                        "attach a "
                    "INNER JOIN "
                        "faces_encoding e "
                        "USING (id) "
                    "WHERE "
                        "e.encoding='' AND "
                        "e.finder=%(finder)s AND "
                        "e.no_faces=0 AND "
                        "a.is_photo=1 AND "
                        "e.error=0 "
                    "ORDER "
                        "BY a.id DESC "
                    "LIMIT %(limit)s")

        data = { 'finder': self.finder.id, 'limit': self.limit }
        rows = self.db.select(query, data)
        for (id, content, uid, error, no_faces, encoding) in rows:
            os_path = str(content.decode())
            self.logger.log("id=" + str(id) + " , os_path=" + os_path + " , uid=" + str(uid), 2)
            images.append([id, os_path, uid]);
        self.logger.log("Found " + str(len(images)) + " not encoded images in table attach limited by " + str(self.limit), 1)
        return images

    def selectChannelIDs(self):
        query = ("SELECT channel_id, id FROM faces_encoding group by channel_id")
        data = { }
        rows = self.db.select(query, data)
        self.chan_ids = []
        for (channel_id, id) in rows:
            self.logger.log("channel_id=" + str(channel_id), 2)
            self.chan_ids.append(channel_id)
        self.logger.log("Found " + str(len(self.chan_ids)) + " distinct channels in table faces_encoding", 1)
