import subprocess


# from datetime import datetime


class ExifTool:

    def getDate(self, file):
        # exiftool -DateTimeOriginal file
        # exiftool -offsettime -DateTimeOriginal  file
        # exiftool -FileModifyDate file    
        exif_date = "1970-01-01T00:00:00"
        time_offset = "+00:00"
        proc = subprocess.Popen(['exiftool', '-DateTimeOriginal', '-offsettime', file], stdout=subprocess.PIPE)
        while True:
            line = proc.stdout.readline()
            if not line:
                break
            line = line.decode("utf-8")
            splittees = line.split(':', 1)
            if len(splittees) == 2:
                if "date" in splittees[0].lower():
                    exif_date = splittees[1]  # 2022:07:14 18:17:48.010
                    exif_date = exif_date.strip()
                    exif_date = exif_date.replace(":", "-", 2)
                    exif_date = exif_date.replace(" ", "T")
                    exif_date = exif_date.split(".")[0]  # 2022-07-14T18:17:48
                elif "offset" in splittees[0].lower():
                    time_offset = splittees[1]
                    time_offset = time_offset.strip()
        proc.kill()
        exif_date = exif_date + time_offset
        # d = datetime.strptime(exif_date, '%Y-%m-%dT%H:%M:%S%z')
        # return d
        return exif_date

    def getVersion(self):
        # This will sometimes result in the message
        #       ResourceWarning: unclosed file <_io.BufferedReader name=9>
        #       ResourceWarning: Enable tracemalloc to get the object allocation traceback
        # Monitoring the processes of the OS it seems that "proc.kill()" works but is slow.
        version = None
        proc = subprocess.Popen(['exiftool', '-ver'], stdout=subprocess.PIPE, shell=True)
        while True:
            line = proc.stdout.readline()
            if not line:
                break
            version = line.decode("utf-8").strip()
        proc.kill()
        return version
