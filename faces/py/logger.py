import os
import datetime

class Logger:

    def __init__(self):
        self.LOGGER_NORMAL = 0
        self.LOGGER_TRACE = 1
        self.LOGGER_DEBUG = 2
        self.LOGGER_DATA = 3
        self.LOGGER_ALL = 4
        self.loglevel = 0
        self.console = 0
        self.name = ""
        self.file = None

    def setFile(self, file):
        if file is not None:
            if os.access(file, os.W_OK):
                self.file = file
        else:
            self.file = None

    def clear(self):
        if self.file is not None:
            open(self.file, "w").close()

    def log(self, message, level):
        if level <= self.loglevel:
            self.out(message, level)

    def out(self, message, level):
        if level == 0:
            levelString = "[NORMAL]"
        if level == 1:
            levelString = "[TRACE]"
        if level == 2:
            levelString = "[DEBUG]"
        if level == 3:
            levelString = "[DATA]"
        if level == 4:
            levelString = "[ALL]"
        time_string = datetime.datetime.now()
        msg = str(time_string) + " " + levelString + " " + self.name + " " + message + "\n"
        if self.console >= 1:
            print(msg)
        if self.file is not None:
            open(self.file, "a").write(msg)