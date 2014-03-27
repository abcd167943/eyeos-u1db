#!/usr/bin/env python
# -*- coding: utf-8 -*-
__author__ = 'root'

import json
import u1db
import os

class Metadata:
    def __init__(self, db=None):
        if db != None:
            self.db = db
        else:
            db =  os.getcwd() + "/extern/u1db/metadata.u1db"
            self.db = u1db.open(db, create=True)
        self.url = "http://127.0.0.1:9000/server.u1db"

    def __del__(self):
        self.db.close()

    def insert(self,lista):
        for data in lista:
            self.db.create_doc_from_json(json.dumps(data))

    def select(self,id,user):
        results = []
        if id != "null":
            self.db.create_index("by-fileid", "file_id","user_eyeos")
            files = self.db.get_from_index("by-fileid",str(id),user)
            for file in files:
                results.append(file.content)

        self.db.create_index("by-parentfileid", "parent_file_id","user_eyeos")
        files = self.db.get_from_index("by-parentfileid",str(id),user)

        for file in files:
            results.append(file.content)

        return results

    def update(self,lista):
        self.db.create_index("by-fileid", "file_id","user_eyeos")
        for data in lista:
            id = str(data["file_id"])
            user = data['user_eyeos']
            files = self.db.get_from_index("by-fileid",id,user)
            if len(files) > 0:
                file = files[0];
                file.set_json(json.dumps(data))
                self.db.put_doc(file)

    def delete(self,lista):
        self.db.create_index("by-fileid", "file_id","user_eyeos")
        for data in lista:
            id = str(data["file_id"])
            user = data['user_eyeos']
            files = self.db.get_from_index("by-fileid",id,user)
            if len(files) > 0:
                self.db.delete_doc(files[0])

    def getParent(self,path,folderParent,user):
        results = []
        self.db.create_index("by-path", "path","user_eyeos")
        files = self.db.get_from_index("by-path",path,user)
        for file in files:
            if file.content['filename'] == folderParent:
                results.append(file.content)
                break
        return results

    def deleteFolder(self,idFolder,user):
        self.db.create_index("by-parentfileid", "parent_file_id","user_eyeos")
        files = self.db.get_from_index("by-parentfileid",str(idFolder),user)

        if len(files) > 0:
            for file in files:
                if file.content["is_folder"] == True:
                    self.deleteFolder(file.content['file_id'],user)
                else:
                    self.db.delete_doc(file)

        self.db.create_index("by-fileid", "file_id","user_eyeos")
        files = self.db.get_from_index("by-fileid",str(idFolder),user)
        if len(files) > 0:
            self.db.delete_doc(files[0])

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


    def sync(self):
        try:
            self.db.sync(self.url)
        except:
            pass

