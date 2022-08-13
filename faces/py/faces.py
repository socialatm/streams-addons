import faces_worker
import faces_db
import argparse
import sys
import distutils
import logging

parser = argparse.ArgumentParser()
parser.add_argument("--host", help="db host url")
parser.add_argument("--user", help="db user")
parser.add_argument("--pass", help="db pass")
parser.add_argument("--db", help="db")
parser.add_argument("--imagespath", help="absolute path to image dir")
parser.add_argument("--channelid", help="channel id, default is 0")
parser.add_argument("--loglevel")
parser.add_argument("--logfile")
parser.add_argument("--detectors")
parser.add_argument("--models")
parser.add_argument("--demography")
parser.add_argument("--procid")
parser.add_argument("--distance_metrics")
parser.add_argument("--min_face_width_percent")
parser.add_argument("--min_face_width_pixel")
parser.add_argument("--css_position")
parser.add_argument("--first_result")
parser.add_argument("--enforce")
parser.add_argument("--statistics_mode")
parser.add_argument("--history")
parser.add_argument("--rm_detectors")
parser.add_argument("--rm_models")

args = vars(parser.parse_args())

# +++++++++++++
# start logger
# +++++++++++++
frm = logging.Formatter("{asctime} {levelname} {process} {filename} {lineno}: {message}",
                        style="{")
logger = logging.getLogger()
log_file = args["logfile"]
print("param logfile=" + log_file)
if (log_file is not None) and (log_file != ""):
    handler_file = logging.FileHandler(log_file, "w")
    handler_file.setFormatter(frm)
    logger.addHandler(handler_file)
    print("yes, logger is configured to write to file")

loglevel = int(args["loglevel"])
print("param loglevel=" + str(loglevel))
""" 
values from PHP...
LOGGER_NORMAL 0
LOGGER_TRACE 1
LOGGER_DEBUG 2
LOGGER_DATA 3
LOGGER_ALL 4
"""
if loglevel:
    if loglevel < 0:
        logger.setLevel(logging.NOTSET)
    elif loglevel >= 2:
        logger.setLevel(logging.DEBUG)
    elif loglevel >= 0:
        logger.setLevel(logging.INFO)
    print("yes, log level is configured")
else:
    logger.setLevel(logging.INFO)
logging.info("started logging")

logData = False
if loglevel >= 4:
    logData = True

# +++++++++++++++++++
# load db connection
# +++++++++++++++++++
logging.debug("db host = " + args["host"])
logging.debug("db user = " + args["user"])
# logging.debug("db pass = " + args["pass"])
logging.debug("db db = " + args["db"])
db = faces_db.Database(logData)
# db.connect("hubzilla", ".", "127.0.0.1", "hubzilla")
db.connect(args["user"], args["pass"], args["host"], args["db"])

# +++++++++++++++++++
# run parameters
# +++++++++++++++++++

if args["imagespath"] is None:
    logging.error("image directory is not set, exit program...")
    sys.exit()
logging.debug("image dir = " + args["imagespath"])

if args["procid"] is None:
    logging.error("procid is not set, exit program...")
    sys.exit()
logging.debug("procid = " + args["procid"])

channel_id = 0
if args["channelid"]:
    channel_id = int(args["channelid"])

# +++++++++++++++++++
# create config
# +++++++++++++++++++

config = ""

if args["distance_metrics"]:
    config = "distance_metrics=" + args["distance_metrics"]
else:
    config = "distance_metrics=" + "cosine,euclidean_l2,euclidean"

if args["demography"]:
    config += ";analyse=" + args["demography"]
else:
    # config += ";analyse=" + "emotion,age,gender,race"
    config += ";analyse=" + "off"

if args["models"]:
    config += ";model=" + args["models"]
else:
    # config += ";models=" + "Facenet512,ArcFace,VGG-Face,Facenet,OpenFace,DeepFace,SFace"
    config += ";model=" + "Facenet512"

# stop after first match of a face (if more than model is used)
if args["first_result"]:
    config += ";first_result=" + args["first_result"]
elif args["enforce"]:
    config += ";enforce=" + args["enforce"]

# write statistics into csv files to compare detectors and models
if args["statistics_mode"]:
    config += ";statistics_mode=" + args["statistics_mode"]
else:
    config += ";statistics_mode=" + "off"

# write a history of recognition
if args["history"]:
    config += ";history=" + args["history"]
else:
    config += ";history=" + "off"

# in percent of image
if args["min_face_width_percent"]:
    config += ";min_face_width_percent=" + args["min_face_width_percent"]
else:
    config += ";min_face_width_percent=" + "5"

# in pixel
if args["min_face_width_pixel"]:
    config += ";min_face_width_pixel=" + args["min_face_width_pixel"]
else:
    config += ";min_face_width_pixel=" + "50"

# position of face in image
# on... in percent as used by css in browsers
# off... in pixel as calculated by face detection
if args["css_position"]:
    config += ";css_position=" + args["css_position"]
else:
    config += ";css_position=" + "on"

if logData:
    config += ";log_data=on"

# list of detectors to remove
if args["rm_detectors"]:
    config += ";rm_detectors=" + args["rm_detectors"]

# list of models to remove
if args["rm_models"]:
    config += ";rm_models=" + args["rm_models"]

# d = "	retinaface,mtcnn,ssd,opencv"
d = "retinaface"
if args["detectors"]:
    d = args["detectors"]
logging.debug("detectors = " + d)
detectors = d.split(",")

doRecognize = False
counter = 1
for detector in detectors:
    worker = faces_worker.Worker()
    logging.debug("set db in worker")
    worker.set_db(db)
    worker.configure(config + ";detector_backend=" + detector)
    # +++++++++++++++++++
    # run
    # +++++++++++++++++++
    logging.debug("About to run worker using detector " + detector)
    if counter == len(detectors):
        doRecognize = True
    worker.run(args["imagespath"], args["procid"], channel_id, doRecognize)
    counter = counter + 1

db.close()
logging.info("OK, good by...")
