<?php
/*
*                 eyeos - The Open Source Cloud's Web Desktop
*                               Version 2.0
*                   Copyright (C) 2007 - 2010 eyeos Team 
* 
* This program is free software; you can redistribute it and/or modify it under
* the terms of the GNU Affero General Public License version 3 as published by the
* Free Software Foundation.
* 
* This program is distributed in the hope that it will be useful, but WITHOUT
* ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
* FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
* details.
* 
* You should have received a copy of the GNU Affero General Public License
* version 3 along with this program in the file "LICENSE".  If not, see 
* <http://www.gnu.org/licenses/agpl-3.0.txt>.
* 
* See www.eyeos.org for more details. All requests should be sent to licensing@eyeos.org
* 
* The interactive user interfaces in modified source and object code versions
* of this program must display Appropriate Legal Notices, as required under
* Section 5 of the GNU Affero General Public License version 3.
* 
* In accordance with Section 7(b) of the GNU Affero General Public License version 3,
* these Appropriate Legal Notices must retain the display of the "Powered by
* eyeos" logo and retain the original copyright notice. If the display of the 
* logo is not reasonably feasible for technical reasons, the Appropriate Legal Notices
* must display the words "Powered by eyeos" and retain the original copyright notice. 
*/

abstract class CalendarApplication extends EyeosApplicationExecutable {
	public static function __run(AppExecutionContext $context, MMapResponse $response) {
		//if ($context->getIncludeBody()) {
			$buffer = '';
			
			// Internal components
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/interfaces.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/constants.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/model.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/Controller.js');
			
			// Menu & Toolbar
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/Actions.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/menu/Items.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/toolbar/Items.js');
			
			// Dialogs
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/dialogs/EditEvent.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/dialogs/Settings.js');
			
			// GUI components
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/view/MiniGridCalendar.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/view/BlockCalendar.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/view/CalendarsList.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/view/ViewModeSelector.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/view/SimplePeriodDisplay.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/view/RibbonCalendar.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/view/Event.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/view/EventPopup.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/view/GridCalendar.js');
			$buffer .= file_get_contents(EYE_ROOT . '/' . APPS_DIR . '/calendar/classes/view/GridCalendar.EventsContainer.js');
			
			$response->appendToBody($buffer);
		//}
	}
	
	/**
	 * @param array $params(
	 * 		'name' => name,
	 * 		['...attribute...' => __value__,]
	 * 		['...' => ...]
	 * )
	 * @return Calendar The calendar with all missing field filed in.
	 */
	public static function createCalendar($params) {
		if (!isset($params['name']) || !is_string($params['name'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'name\'].');
		}
		
		$owner = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$fieldsDoNotSave = array('id','username','password');
		
		$newCalendar = CalendarManager::getInstance()->getNewCalendar(); 
		foreach($params as $attributeName => $attributeValue) {
			if (!in_array($attributeName, $fieldsDoNotSave)) {
				$setMethod = 'set' . ucfirst($attributeName);
				$newCalendar->$setMethod($attributeValue);
			}
		}
		$newCalendar->setOwnerId($owner->getId());
		CalendarManager::getInstance()->saveCalendar($newCalendar);

        $apiCalendarManager = new ApiCalendarManager();
        $apiCalendarManager->insertCalendar($owner->getName(),$newCalendar);
		
		// Use self::getCalendar() to retrieve the preferences at the same time
		return self::getCalendar(array('id' => $newCalendar->getId()));
	}
	public static function createRemoteCalendar($params) {
		if (!isset($params['id']) || !is_string($params['id'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'id\'].');
		}
        if (!isset($params['username']) || !is_string($params['username'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'username\'].');
		}
        if (!isset($params['password']) || !is_string($params['password'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'password\'].');
		}
        //$calendar = CalendarManager::getInstance();
        //$calendar->setProvider(true);
		$owner = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		
		CalendarManager::getInstance()->setProviderLocation('remote');
		$newCalendar = CalendarManager::getInstance()->getNewRemoteCalendar(); 
		foreach($params as $attributeName => $attributeValue) {
				$setMethod = 'set' . ucfirst($attributeName);
				$newCalendar->$setMethod($attributeValue);
		}
		$newCalendar->setOwnerId($owner->getId());
		CalendarManager::getInstance()->saveRemoteCalendar($newCalendar);

		// Use self::getCalendar() to retrieve the preferences at the same time
		return self::getCalendar(array('id' => $newCalendar->getId()));
		//return self::toArray(CalendarManager::getInstance()->getCalendarById($newCalendar->getId()));
	}
	/**
	 * @param array $params(
	 * 		'subject' => subject,
	 * 		'timeStart' => timeStart,
	 * 		'timeEnd' => timeEnd,
	 * 		'calendarId' => calendarId
	 * )
	 * @return Event The event with all missing field filed in.
	 */
	public static function createEvent($params) {
		if (!isset($params['subject']) || !is_string($params['subject'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'subject\'].');
		}
		if (!isset($params['timeStart']) || !is_numeric($params['timeStart'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'timeStart\'].');
		}
		if (!isset($params['timeEnd']) || !is_numeric($params['timeEnd'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'timeEnd\'].');
		}
		if (!isset($params['calendarId']) || !is_string($params['calendarId'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'calendarId\'].');
		}
        $maxEventLimit = CalendarManager::getInstance()->getMaxEventLimit();
        if($params['finalType'] == '3' && $params['finalType'] > $maxEventLimit ){
                $params['finalType'] = $maxEventLimit;
        }
        $creator = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();	
		
		if(self::isRemoteCalendar($params['calendarId'])){
			CalendarManager::getInstance()->setProviderLocation('remote');
		}else{
			CalendarManager::getInstance()->setProviderLocation('local');
		}	
		
		
		$newEvent = CalendarManager::getInstance()->getNewEvent();
		$newEvent->setSubject($params['subject']);
		$newEvent->setTimeStart($params['timeStart']);
		$newEvent->setTimeEnd($params['timeEnd']);
		$newEvent->setCalendarId($params['calendarId']);
		$newEvent->setIsAllDay($params['isAllDay']);
		$newEvent->setRepetition($params['repetition']);
		$newEvent->setRepeatType($params['repeatType']);
		$newEvent->setLocation($params['location']);
		$newEvent->setDescription($params['description']);
		$newEvent->setFinalType($params['finalType']);
		$newEvent->setFinalValue($params['finalValue']);
		$newEvent->setGmtTimeDiffrence($params['gmtTimeDiffrence']);
		$newEvent->setCreatorId($creator->getId());
		$newEventArr=array();

        $u1dbEvent = self::getEventU1db($params,"NEW");

		if($params['repeatType'] !='n' and !self::isRemoteCalendar($params['calendarId'])){
			$newEventArr = self::createRepeatEvent($params);		
		}else{		
			
			if(!self::isRemoteCalendar($params['calendarId'])){
				CalendarManager::getInstance()->saveEvent($newEvent);
            	$newEventArr[] = $newEvent;
			}else{
				$newEventArr=CalendarManager::getInstance()->saveEvent($newEvent); // we create the events for remote through RemoteCalendarProvider
			}

            $apiCalendarManager = new ApiCalendarManager();
            $apiCalendarManager->createEvent($u1dbEvent);
		}
				
		return self::toArray($newEventArr);
	}
    /**
	 * @param array $params(
	 * 		'subject' => subject,
	 * 		'timeStart' => timeStart,
	 * 		'timeEnd' => timeEnd,
	 * 		'calendarId' => calendarId
	 * )
	 */
     

         /**
	 * @param array $params(
	 * 		'calendarId' => calendarId
	 * )
	 */
	public static function deleteCalendar($params) {
		$params['calendarId']=$params['id'];
                $owner = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
                if($owner->getName() == $params['name'])
                {
                    return;
                }
		if (!isset($params['calendarId']) || !is_string($params['calendarId'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'calendarId\'].');
		}
		
		$cal = CalendarManager::getInstance()->getCalendarById($params['calendarId']);
		CalendarManager::getInstance()->deleteCalendar($cal);

        $apiCalendarManager = new ApiCalendarManager();
        $apiCalendarManager->deleteCalendar($owner->getName(),$cal->getName());

		return  self::getAllUserCalendars($params);
	}
	public static function deleteRemoteCalendar($params) {
		$params['calendarId']=$params['id'];                
		if (!isset($params['calendarId']) || !is_string($params['calendarId'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'calendarId\'].');
		}
		CalendarManager::getInstance()->setProviderLocation('remote');
		$cal = CalendarManager::getInstance()->getCalendarById($params['calendarId']);
		CalendarManager::getInstance()->deleteCalendar($cal);
		return  self::getAllUserCalendars($params);
	}
	/**
	 * @param array $params(
	 * 		'eventId' => eventId
	 * )
	 */
	public static function deleteEvent($params) {
		if (!isset($params['eventId']) || !is_string($params['eventId'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'eventId\'].');
		}
        
        if(self::isRemoteCalendar($params['eventId'])) {              
				CalendarManager::getInstance()->setProviderLocation('remote');
                return CalendarManager::getInstance()->deleteRemoteEvent($params);
        } else {
			  CalendarManager::getInstance()->setProviderLocation('local');		
              $event = CalendarManager::getInstance()->getEventById($params['eventId']);
              if ($params['isDeleteAll'] == '1'){
                      if (!isset($params['groupId']) || !is_string($params['groupId'])) {
                              throw new EyeMissingArgumentException('Missing or invalid $params[\'groupId\'].');
                      }
                      $events = CalendarManager::getInstance()->getGroupEvents($params['groupId']);
                      $eventIdArr = array();
                      foreach($events as $event){
                          $eventIdArr[] = $event->getId();
                      }

                      CalendarManager::getInstance()->deleteAllGroupEvents($event,$params['groupId']);
                      return $eventIdArr;
              } else {
                       CalendarManager::getInstance()->deleteEvent($event);

                       $apiCalendarManager = new ApiCalendarManager();
                       $eventU1db = self::getEventU1db($params,"DELETED");
                       $apiCalendarManager->deleteEvent($eventU1db);

                       if ($params['groupId']>0){
                           CalendarManager::getInstance()->deleteEventInEventGroup($params['eventId'],$params['groupId']);
                       }
              }
        }
        return;
		
	}

    /*public static function isRemoteCalendar($calendarId){
        $calendarIdArr = explode("_",$calendarId);
        if(in_array("eyeID", $calendarIdArr)) {
            return false;
        }
        return true;

    }*/

	/**
	 * @param array $params(
	 * 		'calendarId' => calendarId,
	 * 		'periodFrom' => periodFrom = null,
	 * 		'periodTo' => periodTo = null
	 * )
	 * @return array(Event)
	 */
	public static function getAllEventsFromPeriod($params) {
        if(!isset($params['calendar'])) {
            throw new EyeMissingArgumentException('Missing calendars');
        }

        $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
        $apiCalendarManager = new ApiCalendarManager();
        $results = array();

        foreach($params['calendar'] as $calendar) {
            array_push($results,self::toArray($apiCalendarManager->synchronizeCalendar($calendar['id'],$user->getName())));
        }

        return $results;
	}

    /**
	 * @param array $params(
	 * 		'calendarId' => calendarId,
	 * 		'periodFrom' => periodFrom = null,
	 * 		'periodTo' => periodTo = null
	 * )
	 * @return array(Event)
	 */
	public static function getAllEventsFromRemoteCalendar($params) {
       
        CalendarManager::getInstance()->setProviderLocation('remote');
		
		if (!isset($params['calendarId']) || !is_string($params['calendarId'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'calendarId\'].');
		}
		$from = null;
		if (is_numeric($params['periodFrom'])) {
			$from = (int) $params['periodFrom'];
		}
		$to = null;
		if (is_numeric($params['periodTo'])) {
			$to = (int) $params['periodTo'];
		}

		$cal = CalendarManager::getInstance()->getCalendarById($params['calendarId']);
        
		$result = CalendarManager::getInstance()->getAllEventsByPeriod($cal, $from, $to); 
		return self::toArray($result);
	}




	/**
	 * @param null
	 * @return array(Calendar)
	 */
	public static function getAllSharedCalendars($params) {
		$collaborator = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$shareType = CalendarManager::getInstance()->getNewCalendar();
		$result = SharingManager::getInstance()->getAllShareInfoFromCollaborator($collaborator, null, $shareType);
		
		return self::toArray($result);
	}
	
	/**
	 * @param null
	 * @return array(Calendar)
	 */
	public static function getAllUserCalendars($params) {
		$owner = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
        $apiCalendarManager = new ApiCalendarManager();
		//$results = CalendarManager::getInstance()->getAllCalendarsFromOwner($owner);
        $results = $apiCalendarManager->synchronizeCalendars($owner);
		$userId = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getid();
		$results = self::toArray($results);
		foreach($results as &$result) {
			$preferences = CalendarManager::getInstance()->getCalendarPreferences($userId, $result['id']);
			$result['preferences'] = self::toArray($preferences);

		}
		
		return $results;
	}
	
	/**
	 * @param array $params(
	 * 		'id' => calendarId
	 * )
	 * @return Calendar
	 */
	public static function getCalendar($params) {
		if (!isset($params['id']) || !is_string($params['id'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'id\'].');
		}
		
		$userId = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getid();
		
		$calendar = self::toArray(CalendarManager::getInstance()->getCalendarById($params['id']));
		$calendar['preferences'] = self::toArray(CalendarManager::getInstance()->getCalendarPreferences($userId, $params['id']));
		
		return $calendar;
	}
	
	/**
	 * @param array $params(
	 * 		'eventId' => eventId
	 * )
	 */
	public static function getEvent($params) {
		if (!isset($params['eventId']) || !is_string($params['eventId'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'eventId\'].');
		}
		
		return self::toArray(CalendarManager::getInstance()->getEventById($params['eventId']));
	}
	
	/**
	 * @see AbstractCalendarPermission for a complete list of available actions
	 * 
	 * @param array $params(
	 * 		'calendarId' => calendarId,
	 * 		'collaboratorId' => collaboratorId,
	 * 		'permissionActions' => 'share, edit, see, see_details'
	 * )
	 */
	public static function shareCalendar($params) {
		if (!isset($params['calendarId']) || !is_string($params['calendarId'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'calendarId\'].');
		}
		if (!isset($params['collaboratorId']) || !is_string($params['collaboratorId'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'collaboratorId\'].');
		}
		if (!isset($params['permissionActions']) || !is_string($params['permissionActions'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'permissionActions\'].');
		}
		
		$cal = CalendarManager::getInstance()->getCalendarById($params['calendarId']);
		$collaborator = UMManager::getPrincipalById($params['collaboratorId']);
		$perms = new AbstractCalendarPermission($params['permissionActions']);
		
		$cal->addCollaborator($collaborator, $perms);
	}
	
	/**
	 * Performs a PHP variable => JSON-compatible array conversion with ICalendars, ICalendarEvents,
	 * ICalendarPreferences and arrays of the previous types.
	 * 
	 * @param mixed $value
	 * @return array
	 */
	private static function toArray($value) {
		if ($value instanceof ICalendar || $value instanceof ICalendarEvent || $value instanceof ICalendarPrefs) {
			return $value->getAttributesMap();
		}
		if (!is_array($value)) {
			throw new EyeInvalidArgumentException('$value must be an ICalendar, ICalendarEvent, ICalendarPrefs, or an array of one of the previous classes (' . gettype($value) . ' given).');
		}
		
		foreach($value as &$v) {
			$v = self::toArray($v);
		}
		return $value;
	}
	
	/**
	 * @see ICalendar for the complete list of attributes
	 * 
	 * @param array $params(
	 * 		'id' => id,
	 * 		'...attributeToUpdate...' => __newValue__,
	 * 		'...' => ...
	 * )
	 */
	public static function updateCalendar($params) {
		if (!isset($params['id']) || !is_string($params['id'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'id\'].');
		}
		
		$userId = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getId();
		$cal = CalendarManager::getInstance()->getCalendarById($params['id']);
		
		foreach($params as $attributeName => $attributeValue) {
			if ($attributeName != 'id') {
				$setMethod = 'set' . ucfirst($attributeName);
				$cal->$setMethod($attributeValue);
			}
		}
		
		CalendarManager::getInstance()->saveCalendar($cal);
	}
	
	/**
	 * @see ICalendarPrefs for the complete list of attributes
	 * 
	 * @param array $params(
	 * 		'id' => calendarId,
	 * 		'color' => color,
	 * 		'visible' => visible,
	 * 		'...preferenceToUpdate...' => __newValue__,
	 * 		'...' => ...
	 * )
	 */
	public static function updateCalendarPreferences($params) {
		if (!isset($params['id']) || !is_string($params['id'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'id\'].');
		}
		
		$userId = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getId();
		// check validity of the calendar ID
		$cal = CalendarManager::getInstance()->getCalendarById($params['id']);
		
		$prefs = CalendarManager::getInstance()->getCalendarPreferences($userId, $params['id']);
		
		foreach($params as $attributeName => $attributeValue) {
			if ($attributeName != 'id') {
				$setMethod = 'set' . ucfirst($attributeName);
				$prefs->$setMethod($attributeValue);
			}
		}
		CalendarManager::getInstance()->saveCalendarPreferences($prefs);
	}
	
	/**
	 * @see ICalendarEvent for the complete list of attributes
	 * 
	 * @param array $params(
	 * 		'id' => id,
	 * 		'...attributeToUpdate...' => newValue,
	 * 		'...' => ...
	 */
	public static function updateEvent($params) {
        $apiCalendarManager = new ApiCalendarManager();
		if (!isset($params['id']) || !is_string($params['id'])) {
			throw new EyeMissingArgumentException('Missing or invalid $params[\'id\'].');
		}
        $maxEventLimit = CalendarManager::getInstance()->getMaxEventLimit();
        $paramsToNotSave = array('id','isEditAll','gmtTimeDiffrence');
        $eventsArr = array();
        if($params['finalType'] == '3' && $params['finalType'] > $maxEventLimit ){
                $params['finalType'] = $maxEventLimit;
        }
        if(self::isRemoteCalendar($params['id'])) {	// remote events
			CalendarManager::getInstance()->setProviderLocation('remote');
			$events = CalendarManager::getInstance()->updateRemoteEvent($params);
			return self::toArray($events);
        } else {
			CalendarManager::getInstance()->setProviderLocation('local');
			if ($params['isEditAll']){
				$events = CalendarManager::getInstance()->getGroupEvents($params['eventGroup']);
				$isNewEntry = self::checkForNewEntry($params,$events);
				if ($isNewEntry){
					foreach($events as $event){
					  if($event->getId() == $params['id']){
						  $timeStart = $params['timeStart'];
						  $timeEnd = $params['timeEnd'];
						  break;
					  }
					}
					$newTimeArr = self::getTimeOfFirstEventOfGroup($params);
					$params['isDeleteAll']=1;
					$params['eventId']=$params['id'];
					$params['groupId']=$params['eventGroup'];
					self::deleteEvent($params);

                    $eventU1db = self::getEventU1db($params,"DELETED",$timeStart,$timeEnd);
                    $apiCalendarManager->deleteEvent($eventU1db);

					$params['timeStart'] = $timeStart;
					$params['timeEnd'] = $timeEnd;
					$event = self::createEvent($params);
                    $eventU1db = self::getEventU1db($params,"NEW",$timeStart,$timeEnd);
                    $apiCalendarManager->createEvent($eventU1db);
                    return $event;
				} else {
					$paramsToNotSave[] = 'timeStart';
					$paramsToNotSave[] = 'timeEnd';
					$paramsToNotSave[] = 'repeatType';
					$paramsToNotSave[] = 'finalValue';
					$paramsToNotSave[] = 'finalType';
					foreach($events as $event){
						foreach($params as $attributeName => $attributeValue) {
							if (!in_array($attributeName,$paramsToNotSave)){
								$setMethod = 'set' . ucfirst($attributeName);
								$event->$setMethod($attributeValue);
							}
						}
						CalendarManager::getInstance()->saveAllEvent($event);
                        $eventU1db = self::getEventU1dbUpdate($event);
                        $apiCalendarManager->updateEvent($eventU1db);
						$eventsArr[] = $event;
					}
				}
			} else {
				$event = CalendarManager::getInstance()->getEventById($params['id']);
                if($event) {
                    $timeStart = $event->getTimeStart();
                    $timeEnd = $event->getTimeEnd();
                    $isAllDay = $event->getIsAllDay();
                }
				if ($params['repeatType'] !='n'){
					CalendarManager::getInstance()->deleteEvent($event);
                    $eventU1db = self::getEventU1db($params,"DELETED",$timeStart,$timeEnd,$isAllDay);
                    $apiCalendarManager->deleteEvent($eventU1db);
					$newEventArr = self::createRepeatEvent($params);
					return self::toArray($newEventArr);
				} else {
					foreach($params as $attributeName => $attributeValue) {
						if (!in_array($attributeName,$paramsToNotSave)){
							$setMethod = 'set' . ucfirst($attributeName);
							$event->$setMethod($attributeValue);
						}
					}

					CalendarManager::getInstance()->saveEvent($event);
					$eventsArr[] = $event;

                    $status = "CHANGED";
                    $method = "updateEvent";
                    if($params['timeStart'] != $timeStart || $params['timeEnd'] != $timeEnd || $params['isAllDay'] != $isAllDay) {
                        $eventU1db = self::getEventU1db($params,"DELETED",$timeStart,$timeEnd,$isAllDay);
                        $apiCalendarManager->deleteEvent($eventU1db);
                        $status = "NEW";
                        $method = "createEvent";
                    }
                    $eventU1db = self::getEventU1db($params,$status);
                    $apiCalendarManager->$method($eventU1db);
				}
			}
			return self::toArray($eventsArr);
		}
    }
    /**
	 * @param array $params(
	 * 		'id' => calendarId
	 * )
	 * @return Calendar
	 */
	public static function getAllGroupCalendars($params) {
		$owner = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$results = CalendarManager::getInstance()->getAllGroupCalendarsFromOwner($owner);		
		$userId = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getid();	
		$results = self::toArray($results);
		foreach($results as &$result) {
			$preferences = CalendarManager::getInstance()->getCalendarPreferences($userId, $result['id']);
			$result['preferences'] = self::toArray($preferences);
		}
		return $results;
	}

    public static function getAllRemoteCalendars($params) {
        CalendarManager::getInstance()->setProviderLocation('remote');
		$owner = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$results = CalendarManager::getInstance()->getAllRemoteCalendarsFromOwner($params);
        $userId = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getid();	
		$results = self::toArray($results);
		foreach($results as &$result) {
			$preferences = CalendarManager::getInstance()->getCalendarPreferences($userId, $result['id']);
			$result['preferences'] = self::toArray($preferences);
		}
		return $results;
	}

    /**
	 * @param array $params(
	 * 		'subject' => subject,
	 * 		'timeStart' => timeStart,
	 * 		'timeEnd' => timeEnd,
	 * 		'calendarId' => calendarId
	 * )
	 * @return Event The event array with all missing field filed in.
	 */
	private static function  createRepeatEvent($params){
		$maxRepeatLimit=CalendarManager::MAX_REPEAT_LIMIT;
		$finalType 	= $params['finalType'];
		$finalValue = $params['finalValue'];
		$repeatType = $params['repeatType'];
		$timeStart 	= $params['timeStart'];
		$timeEnd 	= $params['timeEnd'];
		$gmtTimeDiffrence=$params['gmtTimeDiffrence'];	
		$no=0;
		/** $finalType 	1= 	None
		*				2= 	On the date
		*				3=	After
		* $repeatType	n=	None
		*				w= 	every week
		*				m=	every month
		*				y=	eyery year
        */
		if(strtolower($finalType)=='2'){
			if($repeatType=='d'){
				$no=  round( abs( $params['timeStart'] - $finalValue ) / 86400 )+1;		// 86400 sec = One Day....
			}  else if($repeatType=='w'){
				$no=  round( abs( $params['timeStart'] - $finalValue ) / 604800 )+1; 	// 604800 sec = one week
			} else if($repeatType=='m'){				
				$timeStartArray=self::createDatesArray( $timeStart,$finalValue,$finalType,"+1 month");
				$timeEndArray=self::createDatesArray( $timeEnd,$finalValue,$finalType,"+1 month"); //echo $timeEnd.','.$finalValue.','.$finalType;
				$no=count($timeStartArray);
			} else if($repeatType=='y'){				
				$timeStartArray=self::createDatesArray( $timeStart,$finalValue,$finalType,"+1 year");
				$timeEndArray=self::createDatesArray( $timeEnd,$finalValue,$finalType,"+1 year");
				$no=count($timeStartArray);				
			}
		} else if(strtolower($finalType)=='3'){
			if($repeatType=='d'){
				$no=  $finalValue;
			}  else if($repeatType=='w'){
				$no=  $finalValue;
			} else if($repeatType=='m'){
				$no=  $finalValue;
				$timeStartArray=self::createDatesArray( $timeStart,$no,$finalType,"+1 month");
				$timeEndArray=self::createDatesArray( $timeEnd,$no,$finalType,"+1 month");
			} else if($repeatType=='y'){
				$no=  $finalValue;
				$timeStartArray=self::createDatesArray( $timeStart,$no,$finalType,"+1 year");
				$timeEndArray=self::createDatesArray( $timeEnd,$no,$finalType,"+1 year");
			}
		} else if(strtolower($finalType)=='1') {
			if($repeatType=='d'){
				$no=$maxRepeatLimit;
			}  else if($repeatType=='w'){
				$no=$maxRepeatLimit;
			} else if($repeatType=='m'){
				$no=$maxRepeatLimit;
				$timeStartArray=self::createDatesArray( $timeStart,$no,$finalType,"+1 month");
				$timeEndArray=self::createDatesArray( $timeEnd,$no,$finalType,"+1 month");
				//print_r($timeStartArray);
			} else if($repeatType=='y'){
				$no=$maxRepeatLimit;
				$timeStartArray=self::createDatesArray( $timeStart,$no,$finalType,"+1 year");
				$timeEndArray=self::createDatesArray( $timeEnd,$no,$finalType,"+1 year");
			}
		}
		$creator = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();		
		$newRepeatEventArr=array();
		$eventGroupId=CalendarManager::getInstance()->saveEventGroup($params['subject']);  
		for ($i = 0; $i<$no;$i++){
			if($params['repeatType']=='m'){
				$timeStart=$timeStartArray[$i]; 
				$timeEnd = 	$timeEndArray[$i]; 				
			}else if($params['repeatType']=='y'){
				$timeStart	=	$timeStartArray[$i];
				$timeEnd 	= 	$timeEndArray[$i]; 	
			}			
			$newEvent = CalendarManager::getInstance()->getNewEvent();
			$newEvent->setSubject($params['subject']);
			$newEvent->setTimeStart($timeStart);
			$newEvent->setTimeEnd($timeEnd);
			$newEvent->setCalendarId($params['calendarId']);
			$newEvent->setIsAllDay($params['isAllDay']);
			$newEvent->setRepetition($params['repetition']);
			$newEvent->setRepeatType($params['repeatType']);
			$newEvent->setLocation($params['location']);
			$newEvent->setDescription($params['description']);
			$newEvent->setFinalType($params['finalType']);
			$newEvent->setFinalValue($params['finalValue']);
            $newEvent->seteventGroup($eventGroupId);
			$newEvent->setCreatorId($creator->getId());
			$eventId=CalendarManager::getInstance()->saveEvent($newEvent);

            $u1dbEvent = self::getEventU1db($params,"NEW",$timeStart,$timeEnd);
            $apiCalendarManager = new ApiCalendarManager();
            $apiCalendarManager->createEvent($u1dbEvent);

			CalendarManager::getInstance()->saveEventInEventGroup($eventId,$eventGroupId);
            $newEvent->setEventGroup($eventGroupId);
			$newRepeatEventArr[] = $newEvent;
			if ($repeatType == 'n'){
					return $newRepeatEventArr;
			}
			if($params['repeatType']=='d'){
                $timeStart = self::addInterval($timeStart,"+1 day");
                $timeEnd = self::addInterval($timeEnd,"+1 day");
			} else if($params['repeatType']=='w'){
                $timeStart = self::addInterval($timeStart,"+1 weeks");
                $timeEnd = self::addInterval($timeEnd,"+1 weeks");
			}
		}
		return $newRepeatEventArr;
    }

    private static function addInterval($time,$interval)
    {
        $dateTimeZone = new DateTimeZone("Europe/Madrid");
        $date = '@' . $time;
        $dateInterval = new DateTime($date);
        $dateInterval->setTimezone($dateTimeZone);
        $dateInterval->modify($interval);
        return $dateInterval->getTimestamp();
    }

    /**
	 * @param
	 * 		$timeStamp = event start/end time,
	 * 		$finalType = On the Date/ After
	 * 		$n = Final date/final times,
	 * 		$gmtTimeDiffrence =  GMT time defference
	 * 
	 * @return timestamp array.
	 */
	private static function createDatesArray($timeStamp,$n,$finalType,$interval) {
        $dates = array();
        array_push($dates,$timeStamp);
        $time=$timeStamp;
		
		if(strtolower($finalType)=='2'){
            while ($time < $n){
                $time = self::addInterval($time,$interval);
                if($time <= $n) {
                    array_push($dates,$time);
                }
            }
		} else {
            $cont = 1;
            while ($cont < $n){
                $time = self::addInterval($time,$interval);
                array_push($dates,$time);
                $cont++;
            }
		}
		return $dates;
	}
    /**
	 * @param
	 * 		$params = Parameters Array,
	 * 		$events = Event Object array
	 *
	 * @return true or false.
	 */
    private static function checkForNewEntry($params,$events){
          foreach($events as $event){
               if ($event->getRepetition() != $params['repetition'] || $event->getRepeatType() != $params['repeatType'] || $event->getFinalType() != $params['finalType'] || $event->getFinalValue() != $params['finalValue']){
                      return true;
                } else if($event->getId() == $params['id']) {
                    if($event->getTimeEnd() != $params['timeEnd'] || $event->getTimeStart() != $params['timeStart']){
                      return true;
                    }
              }
          }
          return false;
      }
     /**
	 * @param
	 * 		$params = Parameters Array,
	 *
	 * @return timestamp array of new start and end event time .
	 */
    private static function getTimeOfFirstEventOfGroup($params){
                $events = CalendarManager::getInstance()->getFirstEventOfGroup($params['eventGroup']);
                $event = $events[0];
                $newTimeArr = array();
                if ($params['id'] == $event->getId()){
                    $newTimeArr[0] = true;
                }
                else {
                  $newTimeArr[0] = false;
                }
                $evStartTimeStamp = $event->getTimeStart();
                $evSEndTimeStamp = $event->getTimeEnd();
                $evStartDate = date("d-m-Y-H-i-s",$evStartTimeStamp);
                $evEndtDate = date("d-m-Y-H-i-s",$evSEndTimeStamp);
                $evStartDateArr = explode("-",$evStartDate);
                $evEndDateArr = explode("-",$evEndtDate);
                $paramStartDate = date("d-m-Y-H-i-s",$params['timeStart']);
                $paramEndtDate = date("d-m-Y-H-i-s",$params['timeEnd']);
                $paramStartDateArr = explode("-",$paramStartDate);
                $paramEndDateArr = explode("-",$paramEndtDate);
                $newTimeStart  = mktime($paramStartDateArr[3],$paramStartDateArr[4],$paramStartDateArr[5],$evStartDateArr[1],$evStartDateArr[0],$evStartDateArr[2]);
                $newTimeEnd  = mktime($paramEndDateArr[3],$paramEndDateArr[4],$paramEndDateArr[5],$evEndDateArr[1],$evEndDateArr[0],$evEndDateArr[2]);
                $newTimeArr[1] = $newTimeStart;
                $newTimeArr[2] = $newTimeEnd;
                return $newTimeArr;
         }

         public static function getMaxEventLimit($params){
              return CalendarManager::getInstance()->getMaxEventLimit();
         }
		 private static function isRemoteCalendar($calendarId){

			$ar=explode('_',$calendarId);
			if($ar[0] =='eyeID'){
				return false;		
			}else{
				return true;
			}

         }

         private static function getEventU1db($params,$status,$timestart = NULL, $timeend = NULL,$isallday = NULL)
         {
             $u1dbEvent = new stdClass();
             $u1dbEvent->type = 'event';
             $u1dbEvent->user_eyeos = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getName();
             $u1dbEvent->calendar = $params['calendar'];
             $u1dbEvent->status = $status;

             if($isallday) {
                 $u1dbEvent->isallday = (int)$isallday;
             } else {
                $u1dbEvent->isallday = $params['isAllDay'];
             }

             if($timestart) {
                 $u1dbEvent->timestart = (int)$timestart;
             } else {
                $u1dbEvent->timestart = $params['timeStart'];
             }

             if($timeend) {
                 $u1dbEvent->timeend = (int)$timeend;
             } else {
                $u1dbEvent->timeend = $params['timeEnd'];
             }

             $u1dbEvent->repetition = $params['repetition'];
             $u1dbEvent->finaltype = $params['finalType'];
             $u1dbEvent->finalvalue = $params['finalValue'];
             $u1dbEvent->subject = $params['subject'];
             $u1dbEvent->location = $params['location'];
             $u1dbEvent->repeattype = $params['repeatType'];
             $u1dbEvent->description = $params['description'];

             return $u1dbEvent;
         }

    private static function getEventU1dbUpdate(CalendarEvent $event)
    {
        $u1dbEvent = new stdClass();
        $u1dbEvent->type = 'event';
        $u1dbEvent->user_eyeos = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getName();
        $u1dbEvent->calendar = $event->getCalendar();
        $u1dbEvent->status = "CHANGED";
        $u1dbEvent->isallday = $event->getIsAllDay()?1:0;
        $u1dbEvent->timestart = (int)$event->getTimeStart();
        $u1dbEvent->timeend = (int)$event->getTimeEnd();
        $u1dbEvent->repetition = $event->getRepetition();
        $u1dbEvent->finaltype = (int)$event->getFinalType();
        $u1dbEvent->finalvalue = (int)$event->getFinalValue();
        $u1dbEvent->subject = $event->getSubject();
        $u1dbEvent->location = $event->getLocation();
        $u1dbEvent->repeattype = $event->getRepeatType();
        $u1dbEvent->description = $event->getDescription();

        return $u1dbEvent;
    }

}
?>