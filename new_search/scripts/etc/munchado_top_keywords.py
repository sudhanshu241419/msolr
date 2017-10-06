import re
import urllib
import csv
import glob
from collections import defaultdict

# desired result to contain keywords and their frequncy
keywords = defaultdict(int)
pattern = re.compile(r'(rt=s).+(sq=[^&\+]+)')

datestring = raw_input("Pass date e.g.: 06/May/2015 for specific date else hit enter. ")

# listWithATuple =[('rt=s', 'q=pizza')]
def getQ(listWithATuple):
        qpart = listWithATuple[0][1]
        qlist = qpart.split("=", 1)
        query = urllib.unquote(qlist[1])
        return query.strip()

logfiles = glob.glob("*.log*")

# if date is supplied, process log for supplied date
if datestring != "":
    datepattern = re.compile(datestring)
    for logfile in logfiles:
        print 'Processing file: ', logfile
        datafile = open(logfile, "r")
        for line in datafile:
            datematch = re.findall(datepattern, line)
            if len(datematch) > 0:
                match = re.findall(pattern, line)
                if len(match) > 0:
                    keywords[getQ(match)] += 1
        datafile.close()

# process all logs
else :
    for logfile in logfiles:
        print 'Processing file: ', logfile
        datafile = open(logfile, "r")
        for line in datafile:
            match = re.findall(pattern, line)
            if len(match) > 0:
                keywords[getQ(match)] += 1
        datafile.close()


rawoutfilename = 'munchado_search_'+datestring+".csv"
outfilename = rawoutfilename.replace('/', '_')
outfile = open(outfilename, 'w')
fieldnames = ['keyword', 'frequency']
writer = csv.DictWriter(outfile, fieldnames=fieldnames)
writer.writeheader()
    
for w in sorted(keywords, key=keywords.get, reverse=True):
    writer.writerow({'keyword': w, 'frequency': keywords[w]})

outfile.close()

print 'Keywords and frequencies written to ', outfilename
