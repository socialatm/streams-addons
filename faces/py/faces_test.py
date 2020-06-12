import logger
import faces_db
import faces_worker

logger = logger.Logger()
logger.loglevel = logger.LOGGER_DATA
logger.console = True
logger.setFile("/var/www/html/log/faces.log")
logger.clear()
logger.log("Started logger", 4)

db = faces_db.Database(logger)
db.connect("hubzilla", ".", "127.0.0.1", "hubzilla")

worker = faces_worker.Worker()
worker.logger = logger
worker.db = db
worker.channel_id = 0
# worker.limit = 2
worker.setFinder1("confidence=0.5;minsize=20")
worker.setFinder2("tolarance=0.6")
worker.run("/var/www/html", 0)

db.close()