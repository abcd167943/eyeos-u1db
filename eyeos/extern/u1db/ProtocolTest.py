#!/usr/bin/env python
# -*- coding: utf-8 -*-
__author__ = 'root'

import unittest
from mock import Mock
from Protocol import Protocol
import json
import u1db
import os

class ProtocolTest (unittest.TestCase):
    def setUp(self):
        self.sut = ''
        self.db =  u1db.open("test.u1db", create=True)
        self.protocol = Protocol(self.db)

    def tearDown(self):
        self.db.close()
        os.remove("test.u1db")


    """
    method: protocol
    when: called
    with: typeInsertAndList
    should: insertCorrect
    """
    def test_protocol_called_typeInsertAndList_insertCorrect(self):
        params = '{"type":"insert","lista":[{"user_eyeos":"eyeos","file_id":"null","parent_file_id":"null","filename":"root","path":null,"is_folder":true,"status":null,"user":null,"version":null,"checksum":null,"size":null,"mimetype":null,"is_root":true},{"user_eyeos":"eyeos","file_id":-7755273878059615652,"parent_file_id":"null","filename":"helpFile","path":"/","is_folder":true,"status":"NEW","server_modified":"2013-11-11 15:40:45.784","client_modified":"2013-11-11 15:40:45.784","user":"web","version":1,"checksum":0,"size":0,"mimetype":"inode/directory","is_root":false}]}'
        aux = json.loads(params)
        self.protocol.insert = Mock()
        self.protocol.insert.return_value = True
        result = self.protocol.protocol(params)
        self.protocol.insert.assert_called_once_with(aux['lista'])
        self.assertEquals('true',result)


    """
    method: protocol
    when: called
    with: typeSelectAndList
    should: returnArray
    """
    def test_protocol_called_typeSelectAndList_returnArray(self):
        params = '{"type":"select","lista":[{"file_id":"124568","user_eyeos":"eyeos"}]}'
        self.protocol.select = Mock()
        self.protocol.select.return_value = []
        result = self.protocol.protocol(params)
        self.protocol.select.assert_called_once_with("124568","eyeos")
        self.assertEquals('[]',result)

    """
    method: protocol
    when: called
    with: typeUpdateAndList
    should: updateCorrect
    """
    def test_protocol_called_typeUpdateAndList_updateCorrect(self):
        params = '{"type":"update","lista":[{"user_eyeos":"eyeos","file_id":"null","parent_file_id":"null","filename":"root","path":null,"is_folder":true,"status":null,"user":null,"version":null,"checksum":null,"size":null,"mimetype":null,"is_root":true},{"user_eyeos":"eyeos","file_id":-7755273878059615652,"parent_file_id":"null","filename":"helpFile","path":"/","is_folder":true,"status":"NEW","server_modified":"2013-11-11 15:40:45.784","client_modified":"2013-11-11 15:40:45.784","user":"web","version":1,"checksum":0,"size":0,"mimetype":"inode/directory","is_root":false}]}'
        aux = json.loads(params)
        self.protocol.update = Mock()
        self.protocol.update.return_value = True
        result = self.protocol.protocol(params)
        self.protocol.update.assert_called_once_with(aux['lista'])
        self.assertEquals('true',result)

    """
    method: protocol
    when: called
    with: typeDeleteAndList
    should: deleteCorrect
    """
    def test_protocol_called_typeDeleteAndList_deleteCorrect(self):
        params = '{"type":"delete","lista":[{"file_id":1234,"user_eyeos":"eyeos"},{"file_id":8907,"user_eyeos":"eyeos"}]}'
        aux = json.loads(params)
        self.protocol.delete = Mock()
        self.protocol.delete.return_value = True
        result = self.protocol.protocol(params)
        self.protocol.delete.assert_called_once_with(aux['lista'])
        self.assertEquals('true',result)


    """
    method: protocol
    when: called
    with: typeGetParentAndPath
    should: returnArray
    """
    def test_protocol_called_typeGetParentAndList_returnArray(self):
        params = '{"type":"parent","lista":[{"path":"/Documents/prueba/","folder":"hola","user_eyeos":"eyeos"}]}'
        self.protocol.getParent = Mock()
        self.protocol.getParent.return_value = []
        result = self.protocol.protocol(params)
        self.protocol.getParent.assert_called_once_with("/Documents/prueba/","hola","eyeos")
        self.assertEquals('[]',result)

    """
    method: protocol
    when: called
    with: typeDeleteFolderAndList
    should: deleteCorrect
    """
    def test_protocol_called_typeDeleteFolderAndList_deleteCorrect(self):
        params = '{"type":"deleteFolder","lista":[{"file_id":"1234","user_eyeos":"eyeos"}]}'
        self.protocol.deleteFolder = Mock()
        self.protocol.deleteFolder.return_value = True
        result = self.protocol.protocol(params)
        self.protocol.deleteFolder.assert_called_once_with("1234","eyeos")
        self.assertEquals('true',result)

    """
    method: protocol
    when: called
    with: typeDeleteEventAndList
    should: deleteCorrect
    """
    def test_protocol_called_typeDeleteEventAndList_deleteCorrect(self):
        params = '{"type":"deleteEvent","lista":[{"type":"event","user_eyeos":"eyeos","calendarid":"eyeID_Calendar_2b","timestart":"201419160000","timeend":"201419170000","isallday":"0"}]}'
        aux = json.loads(params)
        self.protocol.deleteEvent = Mock()
        self.protocol.deleteEvent.return_value = True
        result = self.protocol.protocol(params)
        self.protocol.deleteEvent.assert_called_once_with(aux['lista'])
        self.assertEquals("true",result)

    """
    method: protocol
    when: called
    with: typeUpdateEventAndList
    should: updateCorrect
    """
    def test_protocol_called_typeUpdateEventAndList_updateCorrect(self):
        params = '{"type":"updateEvent","lista":[{"type":"event","user_eyeos":"eyeos","calendarid":"eyeID_Calendar_2b","timestartOld":"201419160000","timestart":"201419173000","timeendOld":"201419170000","timeend":"201419193000","isalldayOld":"0","isallday":"0"}]}'
        aux = json.loads(params)
        self.protocol.updateEvent = Mock()
        self.protocol.updateEvent.return_value = True
        result = self.protocol.protocol(params)
        self.protocol.updateEvent.assert_called_once_with(aux['lista'])
        self.assertEquals("true",result)

    """
    method: protocol
    when: called
    with: typeSelectEventAndList
    should: return Array
    """
    def test_protocol_called_typeSelectEventAndList_returnArray(self):
        params = '{"type":"selectEvent","lista":[{"type":"event","user_eyeos":"eyeos","calendarid":"eyeID_Calendar_2b"}]}'
        aux = json.loads(params)
        self.protocol.selectEvent = Mock()
        self.protocol.selectEvent.return_value = []
        result = self.protocol.protocol(params)
        self.protocol.selectEvent.assert_called_once_with("event","eyeos","eyeID_Calendar_2b")
        self.assertEquals("[]",result)

    """
    method: protocol
    when: called
    with: typeInsertEventAndList
    should: insertCorrect
    """
    def test_protocol_called_typeInsertEventAndList_insertCorrect(self):
        params = '{"type":"insertEvent" , "lista":[{"type":"event","user_eyeos": "eyeos","calendarid": "eyeID_Calendar_2b", "isallday":"0", "timestart": "201419160000", "timeend":"201419170000", "repetition": "None", "finaltype": "1", "finalvalue": "0", "subject": "Visita Médico", "location": "Barcelona", "description": "Llevar justificante"},{"type":"event","user_eyeos": "eyeos","calendarid": "eyeID_Calendar_2b", "isallday": "1", "timestart": "201420160000", "timeend":"201420170000", "repetition": "None", "finaltype": "1", "finalvalue": "0", "subject": "Excursión", "location": "Girona", "description": "Mochila"}]}'
        aux = json.loads(params)
        self.protocol.insertEvent = Mock()
        self.protocol.insertEvent.return_value = True
        result = self.protocol.protocol(params)
        self.protocol.insertEvent.assert_called_once_with(aux['lista'])
        self.assertEquals("true",result)