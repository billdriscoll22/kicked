import util, random, math, sys
from util import Counter
import csv
import pdb

#######################################
#     Least-squares Regression        #
#Borrowing code from CS 221 Homework 7#
#######################################
numRounds = 10
initStepSize = 0.1
stepSizeReduction = 0.5
regularization = 1

features = ["VehYear","VehicleAge","Make","Color","Transmission","WheelTypeID","WheelType","Nationality","VehSize","TopThreeAmericanName","VNST","IsOnlineSale"]

def extractFeatures(sampleArray):
  featureVector = util.Counter()
  count = 0
  for value in sampleArray:
    featureVector[features[count] + " : " + value] = 1
    count += 1
  return featureVector


def LossGradient(featureVector, y, weights):
  dotProduct = 0
  for key, value in featureVector.items():
    dotProduct += weights[key] * value
  return featureVector * (dotProduct - y)


def learn(trainingData):
  weights = util.Counter()
  random.seed(678)
  for round in range(0, numRounds):
    random.shuffle(trainingData)
    numUpdates = 0  
    for x, y in trainingData:
      #print str(round) + " " + str(numUpdates)
      numUpdates += 1
      stepSize = initStepSize / pow(numUpdates, stepSizeReduction)
      scale = (regularization / float(len(trainingData)))
      penalty = weights * scale
      deltaLoss = LossGradient(extractFeatures(x), y, weights)
      for key, value in deltaLoss.items():
        weights[key] = weights[key] - value * stepSize - penalty[key] * stepSize
  return weights


def readSamples(path):
  samples = []
  for row in csv.reader(open(path)):
    x = list(row[2:])
    y = 0
    if(int(row[1]) == 0):    
      y = -1
    else:
      y = 1
    samples.append((x, y))
    #print '.'
  #print "%d samples from %s" % (len(samples), path)
  return samples

def predict(x, weights):
  featureVector = extractFeatures(x)
  loss = weights * featureVector;
  #print loss
  if(loss < 0):
    return -1
  else:
    return 1

trainingData = readSamples('./train.csv')
weights = learn(trainingData)

#string = ""
#for feature, value in weights.items():
#  string += str(value) + "," + str(feature) + "\n"
#print string
#sys.exit(0)

testData = readSamples('./test.csv')
tp = 0 #True positive
fp = 0 #False positive
tn = 0 #True negative
fn = 0 #False negative
falsePosEx = []
falseNegEx = []
for sample in testData:
  x = sample[0]
  y = sample[1]
  prediction = predict(x, weights)
  #print "Predicted " + str(prediction) + " when it was " + str(y)
  if(prediction == y):
    if y == 1:
      tp += 1
    else:
      tn += 1
  else:
    if y == 1:
      if(len(falseNegEx) < 5):
        falseNegEx.append(x)
      fn += 1
    else:
      if(len(falsePosEx) < 5):
        falsePosEx.append(x)
      fp += 1

if (len(testData) > 0):
  accuracy = float(tp + tn)/(tp + fp + tn + fn)
  precision = None
  if((tp + fp) > 0):
    precision = float(tp)/(tp + fp)
  recall = None
  if((tp + fn) > 0):
    recall = float(tp)/(tp + fn)
  negative = None
  if((tn + fp) > 0):
    negative = float(tn)/(tn + fp)
  print '====== Stats ======================================'
  #Measure overall correctness
  print 'Correct percentage: ' + str(accuracy)
  #Measure ability to predict positive results
  print 'Precision: ' + str(precision)
  #Measure of 'flexibility'
  print 'Recall: ' + str(recall)
  #Measure ability to predict negative results
  print 'Specificity: ' + str(negative)
  #Overall score
  if(precision and recall):
    print 'F1 score: ' + str(2*precision*recall/(precision + recall))
  print str(tp) + " tp"
  print str(tn) + " tn"
  print str(fp) + " fp"
  print str(fn) + " fn"

  outString = ""
  for x in falsePosEx:
    for y in x:
      outString += y + ","
    outString += '\n'
  print outString

  outString = ""
  for x in falseNegEx:
    for y in x:
      outString += y + ","
    outString += '\n'
  print outString

