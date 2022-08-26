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
parser.add_argument("--log_data")
parser.add_argument("--detectors")
parser.add_argument("--models")
parser.add_argument("--demography")
parser.add_argument("--distance_metrics")
parser.add_argument("--min_face_width_percent")
parser.add_argument("--min_face_width_pixel")
parser.add_argument("--training")
parser.add_argument("--result")
parser.add_argument("--css_position")
parser.add_argument("--enforce")
parser.add_argument("--statistics")
parser.add_argument("--history")
parser.add_argument("--rm_detectors")
parser.add_argument("--rm_models")
parser.add_argument("--rm_names")
parser.add_argument("--ram")

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
    csv = ""
    addon_dir = worker.Worker().dir_addon
    conf_file = os.path.join(imgdir, addon_dir, "config.json")
    if not os.path.exists(conf_file) or not os.access(conf_file, os.R_OK):
        logging.debug("config file not found " + conf_file)
        return ""
    logging.debug("read config from file " + conf_file)
    with open(conf_file, "r") as f:
        dict_conf = json.load(f)
    no_list = ['statistics', 'enforce', 'history', 'training', 'result', 'ram']
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
                csv += ";min_face_width_" + name + "=" + param
            elif value:
                if len(param) > 0:
                    param += ","
                param += name
        if len(param) > 0:
            csv += ";" + key + "=" + param
    return csv


def check_param(param):
    name = param[0]
    default_value = param[1]
    global config
    if args[name]:
        if config.find(name) != -1:
            config = re.sub(name + "=.*?;", name + "=" + args[name] + ";", config)
        else:
            config += ";" + name + "=" + args[name]
    else:
        if config.find(name) == -1 and default_value != "":
            config += ";" + name + "=" + default_value


def read_config_params():
    param_list = [["distance_metrics", "euclidean_l2,cosine,euclidean"],
                  ["demography", "emotion,age,gender,race"],  # off
                  ["detectors", "retinaface"],  # "retinaface,mtcnn,ssd,opencv,mediapipe"
                  ["models", "Facenet512"],  # "Facenet512,ArcFace,VGG-Face,Facenet,OpenFace,DeepFace,SFace"
                  ["enforce", "on"],  # "on|off"
                  ["statistics", "on"],  # "on|off"
                  ["history", "on"],  # "on|off"
                  ["min_face_width_percent", "1"],
                  ["min_face_width_pixel", "50"],
                  ["training", "224"],
                  ["result", "50"],
                  ["css_position", "on"],  # "on|off" position of face in image, css is used by browsers
                  ["log_data", "on"],  # "on|off"
                  ["rm_detectors", ""],  # list of detectors to remove
                  ["rm_models", ""]  # list of models to remove
                  ["rm_names", "off"],  # "on|off" remove all names either set or recognized
                  ["ram", "90"]]  # max allowed ram in percent
    for param in param_list:
        check_param(param)


config = read_config_file()
read_config_params()

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
