__author__ = 'root'

import json
from Metadata import Metadata
import sys

class Protocol:
    def __init__(self,db=None):
        self.metadata = Metadata(db)

    def protocol(self,params):
        aux = json.loads(params)
        type = aux["type"]
        lista = aux["lista"]
        result = False

        if type == "insert":
            result = self.insert(lista)
        elif type == "select":
            result = self.select(lista[0]["file_id"],lista[0]['user_eyeos'])
        elif type == "update":
            result = self.update(lista)
        elif type == "delete":
            result = self.delete(lista)
        elif type == "parent":
            result = self.getParent(lista[0]['path'],lista[0]["folder"],lista[0]['user_eyeos'])
        elif type == "deleteFolder":
            result = self.deleteFolder(lista[0]["file_id"],lista[0]['user_eyeos'])
        elif type == "deleteEvent":
            result = self.deleteEvent(lista)
        elif type == "updateEvent":
            result = self.updateEvent(lista)
        elif type == "selectEvent":
            result = self.selectEvent(lista[0]['type'],lista[0]['user_eyeos'],lista[0]['calendarid'])
        elif type == "insertEvent":
            result = self.insertEvent(lista)

        return json.dumps(result)

    def insert(self,lista):
        self.metadata.insert(lista)
        return True

    def select(self,id,user):
        return self.metadata.select(id,user)

    def update(self,lista):
        self.metadata.update(lista)
        return True

    def delete(self,lista):
        self.metadata.delete(lista)
        return True

    def getParent(self,path,folder,user):
        return self.metadata.getParent(path,folder,user)

    def deleteFolder(self,idFolder,user):
        self.metadata.deleteFolder(idFolder,user)
        return True

    def deleteEvent(self,lista):
        self.metadata.deleteEvent(lista)
        return True

    def updateEvent(self,lista):
        self.metadata.updateEvent(lista)
        return True

    def selectEvent(self,type,user,calendarid):
        self.metadata.selectEvent(type,user,calendarid)

    def insertEvent(self,lista):
        self.metadata.insert(lista)
        return True

if __name__ == "__main__":
    if len(sys.argv) == 2:
         protocol = Protocol()
         print (protocol.protocol(str(sys.argv[1])))
    else:
        print ('false')
