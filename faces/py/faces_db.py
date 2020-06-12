import mysql.connector
from mysql.connector import Error
import sys

class Database:

    def __init__(self, logger):
        self.logger = logger

    def connect(self, db_user, db_pass, db_host, db_db):
        self.conn = None
        try:
            self.conn = mysql.connector.connect(
                user=db_user,
                password=db_pass,
                host=db_host,
                database=db_db)
            if self.conn.is_connected():
                self.logger.log('Connected to MySQL database', 2)
            else:
                self.logger.log('Failed to connect to database. Exit...', 0)
                sys.exit(1)
        except Error as e:
            self.logger.log('Error connecting to connect to database. Exit...', 0)
            self.logger.log(e, 0)
            sys.exit(1)
        self.cursor = self.conn.cursor()

    def update(self, query, data):
        self.logger.log("query: " + str(query), 3)
        self.logger.log("data: " + str(data), 4)
        self.cursor.execute(query, data)
        self.conn.commit()

    def select(self, query, data):
        self.logger.log("query: " + str(query), 3)
        self.logger.log("data: " + str(data), 3)
        self.cursor.execute(query, data)
        rows = self.cursor.fetchall()
        self.logger.log("rows: " + str(rows), 4)
        return rows

    def close(self):
        self.cursor.close()
        self.conn.close()