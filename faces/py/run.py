import worker
import argparse
import logging
import os
import json
import re

parser = argparse.ArgumentParser()
parser.add_argument("--imagespath", help="absolute path to image dir")
parser.add_argument("--loglevel")
parser.add_argument("--logfile")
parser.add_argument("--detectors")
parser.add_argument("--models")
parser.add_argument("--demography")
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
logging.debug("started logging")

logData = False
if loglevel >= 4:
    logData = True

# +++++++++++++++++++
# run parameters
# +++++++++++++++++++

imgdir = args["imagespath"]
if imgdir is None:
    imgdir = os.getcwd()
    logging.info("Missing parameter --imagespath ? Using current directory " + imgdir + " to find pictures")
logging.info("image directory = " + imgdir)

# +++++++++++++++++++
# create config
# +++++++++++++++++++

def read_config_file():
    config = ""
    addon_dir = worker.Worker().dir_addon
    conf_file = os.path.join(imgdir, addon_dir, "config.json")
    if not os.path.exists(conf_file) or not os.access(conf_file, os.R_OK):
        logging.debug("config file not found " + conf_file)
        return ""
    logging.debug("read config from file " + conf_file)
    with open(conf_file, "r") as f:
        dict_conf = json.load(f)
    no_list = ['statistics', 'enforce', 'history']
    for key in dict_conf:
        elements = dict_conf[key]
        param = ""
        for element in elements:
            name = element[0]
            value = element[1]
            if name in no_list:
                param = str(value)
            elif key == "min_face_width":
                param = str(value)
                config += ";min_face_width_" + name + "=" + param
            elif value:
                if len(param) > 0:
                    param += ","
                param += name
        if len(param) > 0:
            config += ";" + key + "=" + param
    return config


def get_default_config():
    if args["distance_metrics"]:
        config = "distance_metrics=" + args["distance_metrics"]
    else:
        config = "distance_metrics=" + "cosine,euclidean_l2,euclidean"

    if args["demography"]:
        config += ";analyse=" + args["demography"]
    else:
        # config += ";analyse=" + "emotion,age,gender,race"
        config += ";analyse=" + "emotion,age,gender,race"

    if args["detectors"]:
        config += ";detectors=" + args["detectors"]
    else:
        # config += ";detectors=" + "retinaface,mtcnn,ssd,opencv"
        config += ";detectors=" + "retinaface"

    if args["models"]:
        config += ";models=" + args["models"]
    else:
        # config += ";models=" + "Facenet512,ArcFace,VGG-Face,Facenet,OpenFace,DeepFace,SFace"
        config += ";models=" + "Facenet512"

    # stop after first match of a face (if more than model is used)
    if args["first_result"]:
        config += ";first_result=" + args["first_result"]
    else:
        config += ";first_result=" + "off"

    # opposite of first_result
    if args["enforce"]:
        config += ";enforce=" + args["enforce"]
    else:
        config += ";enforce=" + "off"

    # write statistics into csv files to compare detectors and models
    if args["statistics_mode"]:
        config += ";statistics_mode=" + args["statistics_mode"]
    else:
        config += ";statistics_mode=" + "on"

    # write a history of the recognition
    if args["history"]:
        config += ";history=" + args["history"]
    else:
        config += ";history=" + "on"

    # in percent of image
    if args["min_face_width_percent"]:
        config += ";min_face_width_percent=" + args["min_face_width_percent"]
    else:
        config += ";min_face_width_percent=" + "1"

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

    return config


config = read_config_file()
if config == "":
    config = get_default_config()

detectors = config.split("detectors=")[1].split(";")[0].split(",")
config = re.sub("detectors=.*?;", "", config)

do_recognize = False
counter = 1
for detector in detectors:
    w = worker.Worker()
    w.configure(config + ";detector_backend=" + detector)
    # +++++++++++++++++++
    # run
    # +++++++++++++++++++
    logging.debug("About to run worker using detector " + detector)
    if counter == len(detectors):
        do_recognize = True
    w.run(imgdir, do_recognize)
    counter = counter + 1
logging.info("OK, good by...")
