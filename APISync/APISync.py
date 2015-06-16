from BaseHTTPServer import BaseHTTPRequestHandler, HTTPServer
from settings import settings
from mongodb import mongoDb
import time
from urlparse import urlparse
import json

class RequestHandler(BaseHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        self.comments = mongoDb("localhost",27017,"comments")
        self.calendars = mongoDb("localhost",27017,"calendars")
        BaseHTTPRequestHandler.__init__(self, *args, **kwargs)

    def do_POST(self):
        #print self.path
        #print self.headers
        postdata = self.getPostData()

        if self.path.startswith('/comment'):
            if postdata.has_key('id') and postdata.has_key('user') and postdata.has_key('text') and postdata.has_key('cloud'):
                time_created = time.strftime("%Y%m%d%H%M%S")
                response = self.comments.insertComment(postdata['id'],postdata['user'],postdata['text'],postdata['cloud'],time_created)
            else:
                response = {"error":400,"descripcion":"Parametros incorrectos"}
        elif self.path.startswith('/event'):
            if postdata.has_key('user') and postdata.has_key('calendar') and postdata.has_key('cloud') and \
               postdata.has_key('isallday') and postdata.has_key('timestart') and postdata.has_key('timeend') and \
               postdata.has_key('repetition') and postdata.has_key('finaltype') and postdata.has_key('finalvalue') and \
               postdata.has_key('subject') and postdata.has_key('location') and postdata.has_key('description'):
                response = self.calendars.insertEvent(postdata['user'],postdata['calendar'],postdata['cloud'],postdata['isallday'],
                                                 postdata['timestart'],postdata['timeend'],postdata['repetition'],
                                                 postdata['finaltype'],postdata['finalvalue'],postdata['subject'],postdata['location'],
                                                 postdata['description'])
            else:
                 response = {"error":400,"descripcion":"Parametros incorrectos"}
        elif self.path.startswith('/calendar'):
            if postdata.has_key('user') and postdata.has_key('name') and postdata.has_key('cloud') and postdata.has_key('description') and \
               postdata.has_key('timezone'):
                response = self.calendars.insertCalendar(postdata['user'],postdata['name'],postdata['cloud'],postdata['description'],postdata['timezone'])
            else:
                response = {"error":400,"descripcion":"Parametros incorrectos"}
        else:
            response = {"error":400,"descripcion":"Recurso no encontrado"}

        self.sendData(response)


    def do_DELETE(self):
        params = self.path.split('/')
        if self.path.startswith('/comment'):
            if len(params) == 6:
                id = params[2]
                user = params[3]
                cloud = params[4]
                time_created = params[5]
                response = self.comments.deleteComment(id,user,cloud,time_created)
            else:
                response = {"error":400,"descripcion":"Parametros incorrectos"}
        elif self.path.startswith('/event'):
            if len(params) == 8:
                user = params[2]
                calendar = params[3]
                cloud = params[4]
                timestart = params[5]
                timeend = params[6]
                isallday = params[7]
                response = self.calendars.deleteEvent(user,calendar,cloud,timestart,timeend,isallday)
            else:
                response = {"error":400,"descripcion":"Parametros incorrectos"}
        elif self.path.startswith('/calendar'):
            if len(params) == 5:
                user = params[2]
                name = params[3]
                cloud = params[4]
                response = self.calendars.deleteCalendar(user,name,cloud)
            else:
                response = {"error":400,"descripcion":"Parametros incorrectos"}
        elif self.path.startswith('/calUser'):
            if len(params) == 4:
                user = params[2]
                cloud = params[3]
                response = self.calendars.deleteCalendarsUser(user,cloud)
                self.sendDataArray(response)
                return
            else:
                response = {"error":400,"descripcion":"Parametros incorrectos"}
        else:
            response = {"error":400,"descripcion":"Recurso no encontrado"}

        self.sendData(response)

    def do_GET(self):
        params = self.path.split('/')
        if self.path.startswith('/comment'):
            if len(params) == 4:
                id = params[2]
                cloud = params[3]
                data = self.comments.getComments(id,cloud)
                self.sendDataArray(data)
            else:
                response = {"error":400,"descripcion":"Parametros incorrectos"}
                self.sendData(response)
        elif self.path.startswith('/event'):
            if len(params) == 5:
                user = params[2]
                calendar = params[3]
                cloud = params[4]
                data = self.calendars.getEvents(user,calendar,cloud)
                self.sendDataArray(data)
            else:
                response = {"error":400,"descripcion":"Parametros incorrectos"}
                self.sendData(response)
        elif self.path.startswith('/calendar'):
            if len(params) == 4:
                user = params[2]
                cloud = params[3]
                data = self.calendars.getCalendars(user,cloud)
                self.sendDataArray(data)
            else:
               response = {"error":400,"descripcion":"Parametros incorrectos"}
               self.sendData(response)
        elif self.path.startswith('/calEvents'):
            if len(params) == 4:
                user = params[2]
                cloud = params[3]
                data = self.calendars.getCalendarsAndEvents(user,cloud)
                self.sendDataArray(data)
            else:
                response = {"error":400,"descripcion":"Parametros incorrectos"}
                self.sendData(response)
        else:
            response = {"error":400,"descripcion":"Recurso no encontrado"}
            self.sendData(response)


    def do_PUT(self):
        postdata = self.getPostData()
        if self.path.startswith('/event'):
            if postdata.has_key('user') and postdata.has_key('calendar') and postdata.has_key('cloud') and \
               postdata.has_key('isallday') and postdata.has_key('timestart') and postdata.has_key('timeend') and \
               postdata.has_key('repetition') and postdata.has_key('finaltype') and postdata.has_key('finalvalue') and \
               postdata.has_key('subject') and postdata.has_key('location') and postdata.has_key('description'):
                response = self.calendars.updateEvent(postdata['user'],postdata['calendar'],postdata['cloud'],postdata['isallday'],
                                                 postdata['timestart'],postdata['timeend'],postdata['repetition'],
                                                 postdata['finaltype'],postdata['finalvalue'],postdata['subject'],postdata['location'],postdata['description'])
            else:
               response = {"error":400,"descripcion":"Parametros incorrectos"}
        elif self.path.startswith('/calendar'):
            if postdata.has_key('user') and postdata.has_key('name') and postdata.has_key('cloud') and postdata.has_key('description') and \
               postdata.has_key('timezone'):
                response = self.calendars.updateCalendar(postdata['user'],postdata['name'],postdata['cloud'],postdata['description'],postdata['timezone'])
            else:
               response = {"error":400,"descripcion":"Parametros incorrectos"}
        else:
            response = {"error":400,"descripcion":"Recurso no encontrado"}

        self.sendData(response)
    def getPostData(self):
        data = {}
        try:
             length = int(self.headers.getheader('content-length'))
             postdata = self.rfile.read(length)
             data = dict((itm.split('=')[0],itm.split('=')[1]) for itm in postdata.split('&'))
        except:
            pass
        return data

    def sendData(self,response):
         if response.has_key('error'):
             self.send_response(response['error'],response['descripcion'])
         else:
            self.send_response(200,"OK")
         self.end_headers()
         self.wfile.write(json.dumps(response))

    def sendDataArray(self,data):
        self.send_response(200,"OK")
        self.end_headers()
        self.wfile.write(json.dumps(data))



server = HTTPServer((settings['Server']['host'], settings['Server']['port']), RequestHandler)
print 'Test server running...'
server.serve_forever()
