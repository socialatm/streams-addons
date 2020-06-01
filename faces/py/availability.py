import cv2
import os
dirpath = os.path.dirname(os.path.realpath(__file__))
print("starting test")
print("current directory is : " + dirpath)
print("loading model cv2.dnn.readNetFromCaffe(" + dirpath + "/deploy.prototxt, " + dirpath + "/res10_300x300_ssd_iter_140000_fp16.caffemodel")
cv2.dnn.readNetFromCaffe(dirpath + "/deploy.prototxt", dirpath + "/res10_300x300_ssd_iter_140000_fp16.caffemodel")
print("loading face recognizer cv2.dnn.readNetFromTorch(" + dirpath + "/openface.nn4.small2.v1.t7")
cv2.dnn.readNetFromTorch(dirpath + "/openface.nn4.small2.v1.t7")
print("ok")