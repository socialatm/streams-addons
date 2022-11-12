import worker
import argparse
import logging
import os

parser = argparse.ArgumentParser()
parser.add_argument("--imagespath", help="absolute path to image dir")
parser.add_argument("--loglevel")
parser.add_argument("--logfile")
parser.add_argument("--recognize")
parser.add_argument("--probe")
parser.add_argument("--rm_detectors")
parser.add_argument("--rm_models")
parser.add_argument("--rm_names")

args = vars(parser.parse_args())

# +++++++++++++
# start logger
# +++++++++++++
frm = logging.Formatter("{asctime} {levelname} {process} {filename} {lineno}: {message}", style="{")
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

# +++++++++++++++++++
# run parameters
# +++++++++++++++++++

imgdir = args["imagespath"]
if imgdir is None:
    imgdir = os.getcwd()
    logging.info("Missing parameter --imagespath ? Using current directory " + imgdir + " to find pictures")
logging.info("image directory = " + imgdir)

is_recognize = False
if args["recognize"]:
    is_recognize = True
logging.debug("recognize  = " + str(is_recognize))

is_probe = False
if args["probe"]:
    is_probe = True
logging.debug("probe  = " + str(is_probe))

worker = worker.Worker()
if args["rm_models"]:
    worker.remove_models = args["rm_models"]
if args["rm_detectors"]:
    worker.remove_detectors = args["rm_detectors"]
if args["rm_names"]:
    worker.is_remove_names = True

# +++++++++++++++++++
# run
# +++++++++++++++++++
worker.run(imgdir, is_recognize, is_probe)

logging.info("OK, good by...")
