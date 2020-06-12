import faces_worker
import logger
import faces_db
import argparse
import sys
import distutils

parser = argparse.ArgumentParser()
parser.add_argument("-t")
parser.add_argument("--host", help="db host url")
parser.add_argument("--user", help="db user")
parser.add_argument("--pass", help="db pass")
parser.add_argument("--db", help="db")
parser.add_argument("--limit")
parser.add_argument("--imagespath", help="absolute path to image dir")
parser.add_argument("--channelid", help="channel id, default is 0")
parser.add_argument("--loglevel")
parser.add_argument("--logfile")
parser.add_argument("--logconsole")
parser.add_argument("--finder1")
parser.add_argument("--finder2")
parser.add_argument("--procid")
args = vars(parser.parse_args())

if args["t"]:
    print(args["t"])
    sys.exit(0)

#+++++++++++++
# start logger
#+++++++++++++

logger = logger.Logger()
# logger.loglevel = logger.LOGGER_TRACE
logger.loglevel = int(args["loglevel"])
# logger.console = 1
logger.console = int(args["logconsole"])
# logger.setFile("/var/log/faces.log")
logger.setFile(args["logfile"])
logger.clear()
logger.log("Started logger", 2)

#+++++++++++++++++++
# load db connection
#+++++++++++++++++++

logger.log("db host = " + args["host"], 2)
logger.log("db user = " + args["user"], 2)
logger.log("db pass = " + args["pass"], 2)
logger.log("db db = " + args["db"], 2)
logger.log("db limit = " + args["limit"], 2)
logger.log("image dir = " + args["imagespath"], 2)
logger.log("channel id = " + args["channelid"], 2)
if args["finder1"] is not None:
    logger.log("finder1 = " + args["finder1"], 2)
if args["finder2"] is not None:
    logger.log("finder2 = " + args["finder2"], 2)
logger.log("proc id = " + args["procid"], 2)

db = faces_db.Database(logger)
# db.connect("hubzilla", ".", "127.0.0.1", "hubzilla")
db.connect(args["user"], args["pass"], args["host"], args["db"])

#++++++++++++++++++++
# load face detectors
#++++++++++++++++++++

worker = faces_worker.Worker()
logger.log("set logger in worker", 2)
worker.logger = logger
logger.log("set db in worker", 2)
worker.db = db
logger.log("set channel id = " + args["channelid"] + " in worker", 2)
worker.channel_id = int(args["channelid"])
logger.log("set db limit = " + args["limit"] + " in worker", 2)
worker.limit = int(args["limit"])
if args["finder1"] is not None:
    worker.setFinder1(args["finder1"])
if args["finder2"] is not None:
    worker.setFinder2(args["finder2"])


#+++++++++++++++++++
# run face detectors
#+++++++++++++++++++

logger.log("About to run worker", 2)
worker.run(args["imagespath"], int(args["procid"]))


db.close()