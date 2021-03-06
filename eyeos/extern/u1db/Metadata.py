__author__ = 'root'

import json
import u1db
import os
from settings import settings
import datetime

class Metadata:
    db1 = None
    db2 = None
    def __init__(self, name, creds=None,name2 = None):
        if name == "test.u1db":
            db = name
        else:
            db =  os.getcwd() + "/extern/u1db/" + name
        self.db = u1db.open(db, create=True)

        db2 = None
        if name2 == "test1.u1db":
            db2 = name2
        elif name2 != None:
            db2 =  os.getcwd() + "/extern/u1db/" + name2

        if db2 != None:
            self.db2 = u1db.open(db2, create=True)

        #self.url = "http://192.168.3.118:8080/" + name
        self.url = settings['Oauth']['sync'] + settings['Oauth']['server'] + ":" + str(settings['Oauth']['port']) + "/" + name
        self.creds = creds

    def __del__(self):
        self.db.close()
        if self.db2 != None:
            self.db2.close()

    def insert(self, lista):
        for data in lista:
            self.db.create_doc_from_json(json.dumps(data))

    def select(self, lista):
        results = []
        if id != "null":
            if settings[ 'NEW_CODE' ] == "true":
                self.db.create_index("by-id-cloud-path", "id", "user_eyeos", "cloud", "path")
                files = self.db.get_from_index("by-id-cloud-path", str(lista[ 'id' ]), lista[ 'user_eyeos' ], lista[ 'cloud' ], lista[ 'path' ])
            else:
                self.db.create_index("by-id-path", "id", "user_eyeos", "path")
                files = self.db.get_from_index("by-id-path", str(lista[ 'id' ]), lista[ 'user_eyeos' ], lista[ 'path' ])
            for file in files:
                results.append(file.content)

        if settings[ 'NEW_CODE' ] == "true":
            self.db.create_index("by-parent-cloud-path", "parent_id", "user_eyeos", "cloud", "path")
            files = self.db.get_from_index("by-parent-cloud-path", str(lista[ 'id' ]), lista[ 'user_eyeos' ], lista[ 'cloud' ], lista[ 'path' ] + "*")
        else:
            self.db.create_index("by-parent-path", "parent_id", "user_eyeos", "path")
            files = self.db.get_from_index("by-parent-path", str(lista[ 'id' ]), lista[ 'user_eyeos' ], lista[ 'path' ] + "*")
        for file in files:
            results.append(file.content)
        return results

    def update(self, lista):
        if settings[ 'NEW_CODE' ] == "true":
            self.db.create_index("by-id-cloud-parent", "id", "user_eyeos", "cloud", "parent_id")
        else:
            self.db.create_index("by-id-parent", "id", "user_eyeos", "parent_id")
        parent = ''
        for data in lista:
            if data.has_key( 'parent_old' ):
                parent = str(data[ 'parent_old' ])
            else:
                id = str(data[ 'id' ])
                user = data[ 'user_eyeos' ]
                if data.has_key( 'cloud' ):
                    cloud = data[ 'cloud' ]
                if settings[ 'NEW_CODE' ] == "true":
                    files = self.db.get_from_index("by-id-cloud-parent", id, user, cloud, parent)
                else:
                    files = self.db.get_from_index("by-id-parent", id, user, parent)
                if len(files) > 0:
                    file = files[0]
                    file.set_json(json.dumps(data))
                    self.db.put_doc(file)

    def delete(self, lista):
        if settings[ 'NEW_CODE' ] == "true":
            self.db.create_index("by-id-cloud-parent", "id", "user_eyeos", "cloud", "parent_id")
        else:
            self.db.create_index("by-id-parent", "id", "user_eyeos", "parent_id")
        for data in lista:
            id = str(data[ 'id' ])
            user = data[ 'user_eyeos' ]
            parent = str(data[ 'parent_id' ])
            if data.has_key( 'cloud' ):
                cloud = data[ 'cloud' ]
            if settings[ 'NEW_CODE' ] == "true":
                files = self.db.get_from_index("by-id-cloud-parent", id, user, cloud, parent)
            else:
                files = self.db.get_from_index("by-id-parent", id, user, parent)
            if len(files) > 0:
                self.db.delete_doc(files[0])

    def getParent(self, lista):
        results = []
        if settings[ 'NEW_CODE' ] == "true":
            self.db.create_index("by-path-cloud-filename", "cloud", "path", "filename", "user_eyeos")
            files = self.db.get_from_index("by-path-cloud-filename", lista[ 'cloud' ], lista[ 'path' ], lista[ 'filename' ], lista[ 'user_eyeos' ])
        else:
            self.db.create_index("by-path-filename", "path", "filename", "user_eyeos")
            files = self.db.get_from_index("by-path-filename", lista[ 'path' ], lista[ 'filename' ], lista[ 'user_eyeos' ])
        if len(files) > 0:
            results.append(files[0].content)
        else:
             self.db.create_index("by-path-cloud-name", "cloud", "path", "name", "user_eyeos")
             files = self.db.get_from_index("by-path-cloud-name", lista[ 'cloud' ], lista[ 'path' ], lista[ 'filename' ], lista[ 'user_eyeos' ])
             if len(files) > 0:
                 id = str(files[0].content['id'])
                 try:
                     pos = id.index("_" + lista['cloud'])
                     id = id[:pos]
                     files[0].content['id'] = id
                 except:
                     pass

                 results.append(files[0].content)

        return results

    def deleteFolder(self, lista):
        if settings[ 'NEW_CODE' ] == "true":
            self.db.create_index("by-parent-cloud-path", "parent_id", "user_eyeos", "cloud", "path")
            files = self.db.get_from_index("by-parent-cloud-path", str(lista[ 'id' ]), lista[ 'user_eyeos' ], lista[ 'cloud' ], lista[ 'path' ] + "*")
        else:
            self.db.create_index("by-parent-path", "parent_id", "user_eyeos", "path")
            files = self.db.get_from_index("by-parent-path",str(lista[ 'id' ]), lista[ 'user_eyeos' ], lista[ 'path' ] + "*")

        if len(files) > 0:
            for file in files:
                if file.content[ "is_folder" ] == True:
                    self.deleteFolder(file.content)
                else:
                    self.db.delete_doc(file)

        if settings[ 'NEW_CODE' ] == "true":
            self.db.create_index("by-id-cloud-path", "id", "user_eyeos", "cloud", "path")
            files = self.db.get_from_index("by-id-cloud-path", str(lista[ 'id' ]), lista[ 'user_eyeos' ], lista[ 'cloud' ], lista[ 'path' ])
        else:
            self.db.create_index("by-id-path", "id", "user_eyeos", "path")
            files = self.db.get_from_index("by-id-path", str(lista[ 'id' ]), lista[ 'user_eyeos' ], lista[ 'path' ])
        if len(files) > 0:
            self.db.delete_doc(files[0])

    def deleteMetadataUser(self, lista):
        for data in lista:
            user = data["user_eyeos"]
            if (data.has_key('cloud')):
                cloud = data["cloud"]
            else:
                cloud = ''
        if len(cloud) > 0:
            self.db.create_index("by-user-cloud", "user_eyeos", "cloud")
            files = self.db.get_from_index("by-user-cloud", user, cloud)
        else:
            self.db.create_index("by-usereyeos", "user_eyeos")
            files = self.db.get_from_index("by-usereyeos", user)
        if len(files) > 0:
            for file in files:
                id = None
                if file.content.has_key('id'):
                    id = str(file.content['id'])
                self.db.delete_doc(file)

                if id != None:
                    if  len(cloud) > 0:
                        self.db2.create_index("by-id-user-cloud", "id", "user_eyeos", "cloud")
                        versions = self.db2.get_from_index("by-id-user-cloud", id, user, cloud)
                    else:
                        self.db2.create_index("by-id-user", "id", "user_eyeos")
                        versions = self.db2.get_from_index("by-id-user", id, user)
                    if len(versions) > 0:
                        self.db2.delete_doc(versions[0])

    def selectMetadataUser(self,user):
        result = []
        self.db.create_index("by-usereyeos", "user_eyeos")
        files = self.db.get_from_index("by-usereyeos", user)
        if len(files) > 0:
            for file in files:
                result.append(file.content)
        return result

    def renameMetadata(self, metadata):
        if settings[ 'NEW_CODE' ] == "true":
            self.db.create_index("by-id-cloud-path", "id", "user_eyeos", "cloud", "path")
            files = self.db.get_from_index("by-id-cloud-path", str(metadata[ 'id' ]), metadata[ 'user_eyeos' ], metadata[ 'cloud' ], metadata[ 'path' ])
        else:
            self.db.create_index("by-id-path", "id", "user_eyeos", "path")
            files = self.db.get_from_index("by-id-path", str(metadata[ 'id' ]), metadata[ 'user_eyeos' ], metadata[ 'path' ])
        if len(files) > 0:
            filenameOld = files[0].content[ 'filename' ]
            files[0].set_json(json.dumps(metadata))
            self.db.put_doc(files[0])
            if files[0].content[ 'is_folder' ] == True:
                pathOld = metadata[ 'path' ] + filenameOld + '/'
                pathNew = metadata[ 'path' ] + metadata[ 'filename' ] + '/'
                self.renamePath(metadata, pathOld, pathNew)

    def renamePath(self, metadata, pathOld, pathNew):
        if settings[ 'NEW_CODE' ] == "true":
            self.db.create_index("by-parent-cloud-path", "parent_id", "user_eyeos", "cloud", "path")
            files = self.db.get_from_index("by-parent-cloud-path", str(metadata[ 'id' ]), metadata[ 'user_eyeos' ], metadata[ 'cloud' ], pathOld)
        else:
            self.db.create_index("by-parent-path", "parent_id","user_eyeos","path")
            files = self.db.get_from_index("by-parent-path", str(metadata[ 'id' ]), metadata[ 'user_eyeos' ], pathOld)
        if len(files) > 0:
            for file in files:
                file.content[ 'path' ] = pathNew
                self.db.put_doc(file)
                if file.content[ 'is_folder' ] == True:
                    _pathOld = pathOld + file.content[ 'filename' ] + '/'
                    _pathNew = pathNew + file.content[ 'filename' ] + '/'
                    self.renamePath(file.content, _pathOld, _pathNew)

    def insertDownloadVersion(self, metadata):
        self.db.create_doc_from_json(json.dumps(metadata))

    def updateDownloadVersion(self, metadata):
        if settings[ 'NEW_CODE' ] == "true":
            self.db.create_index("by-id-user-cloud", "id", "user_eyeos", "cloud")
            files = self.db.get_from_index("by-id-user-cloud", metadata[ 'id' ], metadata[ 'user_eyeos' ], metadata[ 'cloud' ])
        else:
            self.db.create_index("by-id-user", "id", "user_eyeos")
            files = self.db.get_from_index("by-id-user", metadata[ 'id' ], metadata[ 'user_eyeos' ])
        if len(files) > 0:
            files[0].set_json(json.dumps(metadata))
            self.db.put_doc(files[0])

    def deleteDownloadVersion(self,id,user):
        self.db.create_index("by-id-user","id","user_eyeos")
        files = self.db.get_from_index("by-id-user",id,user)
        if len(files) > 0:
            self.db.delete_doc(files[0])

    def getDownloadVersion(self, lista):
        result = None
        if settings[ 'NEW_CODE' ] == "true":
            self.db.create_index("by-id-user-cloud", "id", "user_eyeos", "cloud")
            files = self.db.get_from_index("by-id-user-cloud", lista[ 'id' ], lista[ 'user_eyeos' ], lista[ 'cloud' ])
        else:
            self.db.create_index("by-id-user", "id", "user_eyeos")
            files = self.db.get_from_index("by-id-user", lista[ 'id' ], lista[ 'user_eyeos' ])
        if len(files) > 0:
            result = files[0].content
        return result

    def recursiveDeleteVersion(self, lista):
        if settings[ 'NEW_CODE' ] == "true":
            self.db.create_index("by-parent-cloud", "parent_id", "cloud")
            files = self.db.get_from_index("by-parent-cloud", str(lista[ 'id' ]), lista[ 'cloud' ])
        else:
            self.db.create_index("by-parent", "parent_id")
            files = self.db.get_from_index("by-parent", str(lista[ 'id' ]))
        for file in files:
            if file.content[ 'is_folder' ] == True:
                self.recursiveDeleteVersion(file.content)
            if settings[ 'NEW_CODE' ] == "true":
                self.db2.create_index("by-id-user-cloud","id","user_eyeos","cloud")
                files = self.db2.get_from_index("by-id-user-cloud", str(file.content[ 'id' ]), lista[ 'user_eyeos' ], lista[ 'cloud' ])
            else:
                self.db2.create_index("by-id-user","id","user_eyeos")
                files = self.db2.get_from_index("by-id-user", str(file.content[ 'id' ]), lista[ 'user_eyeos' ])
            for file in files:
                self.db2.delete_doc(file)


    """
    ##################################################################################################################################################
                                                                    CALENDAR
    ##################################################################################################################################################
    """

    def deleteEvent(self,lista):
        self.updateEvent(lista)

    def updateEvent(self,lista):
        for data in lista:
            files = self.getEvents(data)
            if len(files) > 0:
                file = files[0]
                file.set_json(json.dumps(data))
                self.db.put_doc(file)
        self.sync()

    def selectEvent(self,type,user,idCalendar):
        self.sync()
        results = []
        self.db.create_index("by-event", "type","user_eyeos","calendar")
        files = self.db.get_from_index("by-event",type,user,idCalendar)
        for file in files:
            results.append(file.content)
        return results

    def getEvents(self,data):
        self.db.create_index("by-event2", "type","user_eyeos","calendar","timestart","timeend","isallday")
        timestart = str(data['timestart'])
        timeend = str(data['timeend'])
        isallday = str(data['isallday'])
        files = self.db.get_from_index("by-event2",data['type'],data['user_eyeos'],data['calendar'],timestart,timeend, isallday)
        return files

    def insertEvent(self,lista):
        #self.insert(lista)
        for data in lista:
            files = self.getEvents(data)
            if len(files) > 0:
                file = files[0]
                file.set_json(json.dumps(data))
                self.db.put_doc(file)
            else:
                self.db.create_doc_from_json(json.dumps(data))
        self.sync()

    def insertCalendar(self,lista):
        for data in lista:
            self.sync()
            calendar = self.getCalendar(data)
            if len(calendar) == 0:
                self.db.create_doc_from_json(json.dumps(data))
            elif calendar[0].content['status'] == 'DELETED':
                file = calendar[0]
                file.set_json(json.dumps(data))
                self.db.put_doc(file)



    def getCalendar(self,data):
        self.db.create_index("by-calendar", "type","user_eyeos","name")
        calendar = self.db.get_from_index("by-calendar",data['type'],data['user_eyeos'],data['name'])
        return calendar

    def deleteCalendar(self,lista):
        for data in lista:
            calendar = self.getCalendar(data)
            if len(calendar) > 0:
                file = calendar[0]
                file.content['status'] = 'DELETED'
                self.db.put_doc(file)
                self.db.create_index("by-event", "type","user_eyeos","calendar")
                events = self.db.get_from_index("by-event","event",data['user_eyeos'],data['name'])
                if len(events) > 0:
                    for event in events:
                        event.content['status'] = 'DELETED'
                        self.db.put_doc(event)
        self.sync()

    def selectCalendar(self,data):
        self.sync()
        self.db.create_index("by-calendar2", "type","user_eyeos")
        calendar = self.db.get_from_index("by-calendar2",data['type'],data['user_eyeos'])
        results = []
        if len(calendar) > 0:
            for cal in calendar:
                results.append(cal.content)
        return results

    def updateCalendar(self,lista):
        for data in lista:
            calendar = self.getCalendar(data)
            if len(calendar) > 0:
                file = calendar[0]
                file.set_json(json.dumps(data))
                self.db.put_doc(file)

    def sync(self):
        """try:
            print(self.db.sync(self.url,creds=self.creds))
        except:
            pass"""

    def deleteCalendarUser(self, user):
        list = [{'user_eyeos' : user}]
        self.deleteMetadataUser(list)

    def selectCalendarsAndEvents(self,user):
        result = []
        self.db.create_index("by-userStatus", "user_eyeos","status")
        files = self.db.get_from_index("by-userStatus",user,"NEW")
        if len(files) > 0:
            for file in files:
                result.append(file.content)
        return result

    """
    ##################################################################################################################################################
                                                                    LOCK FILE
    ##################################################################################################################################################
    """

    def getMetadataFile(self,id,cloud):
        self.sync()
        result = []
        self.db.create_index("by-id-cloud", "id", "cloud")
        files = self.db.get_from_index("by-id-cloud", id, cloud)
        if len(files) > 0:
            for file in files:
                result.append(file.content)
        return result

    def lockFile(self,data):
        self.sync()
        self.db.create_index("by-id-cloud", "id", "cloud")
        files = self.db.get_from_index("by-id-cloud", data['id'], data['cloud'])
        timeLimit = data['timeLimit']
        del data['timeLimit']
        result = True
        if len(files) == 0:
            self.db.create_doc_from_json(json.dumps(data))
        else:
             file = files[0]
             if file.content['status'] == 'close':
                 file.set_json(json.dumps(data))
                 self.db.put_doc(file)
             else:
                 if file.content['username'] == data['username'] and file.content['IpServer'] == data['IpServer']:
                     file.set_json(json.dumps(data))
                     self.db.put_doc(file)
                 else:
                     dt=datetime.datetime.strptime(file.content['datetime'],'%Y-%m-%d %H:%M:%S')
                     dt_plus_timeLimit = dt + datetime.timedelta(minutes = timeLimit)
                     dt_now = datetime.datetime.strptime(data['datetime'],'%Y-%m-%d %H:%M:%S')
                     if dt_now > dt_plus_timeLimit:
                         file.set_json(json.dumps(data))
                         self.db.put_doc(file)
                     else:
                         result = False
        return result

    def updateDateTime(self,data):
        self.sync()
        self.db.create_index("by-id-cloud-username-IpServer", "id", "cloud","username","IpServer")
        result = True
        files = self.db.get_from_index("by-id-cloud-username-IpServer", data['id'],data['cloud'],data['username'],data['IpServer'])
        if len(files) > 0:
            file = files[0]
            file.set_json(json.dumps(data))
            self.db.put_doc(file)
        else:
            result = False
        return result

    def unLockFile(self,data):
         self.sync()
         self.db.create_index("by-id-cloud-username-IpServer", "id", "cloud","username","IpServer")
         files = self.db.get_from_index("by-id-cloud-username-IpServer", data['id'],data['cloud'],data['username'],data['IpServer'])
         if len(files) > 0:
            file = files[0]
            file.set_json(json.dumps(data))
            self.db.put_doc(file)