<?php

abstract class FilesApplication extends EyeosApplicationExecutable {
	public static function __run(AppExecutionContext $context, MMapResponse $response) {

        if(self::singleInstanceCheck()) exit;

        self::initClouds();

		//if ($context->getIncludeBody()) {
			$buffer = '';

			$itemsPath = EYE_ROOT . '/' . APPS_DIR . '/files';
			$dir = new DirectoryIterator($itemsPath);
			foreach($dir as $file) {
				$fileName = $file->getBasename();
				if (!$file->isDot() && $fileName{0} != '.' && strchr($fileName, '.js') && $fileName != 'eyeos.js') {
					$buffer .= file_get_contents($itemsPath . '/' . $fileName);
				}
			}

			$buffer .= file_get_contents($itemsPath . '/SocialBarUpdater/interfaces.js');
			$buffer .= file_get_contents($itemsPath . '/SocialBarUpdater/eyeos.files.ASocialBarHandler.js');
			$buffer .= file_get_contents($itemsPath . '/SocialBarUpdater/eyeos.files.SUHandlerManager.js');
			$buffer .= file_get_contents($itemsPath . '/SocialBarUpdater/eyeos.files.SUManager.js');
			$buffer .= file_get_contents($itemsPath . '/SocialBarUpdater/eyeos.files.SUPathManager.js');
			$response->appendToBody($buffer);
		//}
	}

    private static function singleInstanceCheck() {
        $result = false;
        $procList = ProcManager::getInstance()->getProcessesList();
        $counter = 0;
        foreach($procList as $proc) {
            if($proc == 'files') {
                $counter++;
            }
        }

        if($counter > 1) {
            $result = true;
            $currentProc = ProcManager::getInstance()->getCurrentProcess();
            ProcManager::getInstance()->kill($currentProc);
        }
        return $result;
    }

	private static final function object_to_array($mixed) {
		if(is_object($mixed)) $mixed = (array) $mixed;
		if(is_array($mixed)) {
			$new = array();
			foreach($mixed as $key => $val) {
				$key = preg_replace("/^\\0(.*)\\0/",'',$key);
				$new[$key] = self::object_to_array($val);
			}
		}
		else $new = $mixed;
		return $new;
	}
	
	/**
	 * TODO: Will need to be moved/merged to/with FileSystemExecModule
	 */
	public static final function browsePath($params) {
		
		if (isset($params[0]) && $params[0] !== null) {
			$path = $params[0];
		} else {
			$path = 'home:///';
		}
		if (isset($params[1]) && $params[1] !== null) {
			$pattern = $params[1];
		} else {
			$pattern = '*';
		}
		if (isset($params[2]) && $params[2] !== null) {
			$options = (int) $params[2] | AdvancedPathLib::GLOB_DIR_FIRST;
		} else {
			$options = AdvancedPathLib::GLOB_DIR_FIRST;
		}

		$leafFolder = FSI::getFile($path);
		
		//files list
		$filesList = array();
		$currentUser = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$settings = MetaManager::getInstance()->retrieveMeta($currentUser);

		foreach($leafFolder->listFiles($pattern, $options) as $currentFile) {
			$filesList[] = self::getFileInfo($currentFile, $settings);
		}

		$return = array(
			'absolutepath' => $leafFolder->getAbsolutePath(),
			'files' => $filesList
		);

		return $return;
	}
	
	/**
	 * TODO: Will need to be moved/merged to/with FileSystemExecModule
	 */
	public static function getMyFiles($params) {
		$currentUser = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$settings = MetaManager::getInstance()->retrieveMeta($currentUser);
		$filesList = array();
		for ($i = 0; $i < count($params); $i++) {
			$currentFile = FSI::getFile($params[$i]);
			$filesList[] = self::getFileInfo($currentFile, $settings);
		}
		return $filesList;
	}

	/**
	 * TODO: Will need to be moved/merged to/with FileSystemExecModule
	 */
	public static function getFileInfo ($currentFile, $settings) {
		$shared = '0';
		if ($currentFile instanceof IShareableFile) {
			$temp = $currentFile->getAllShareInfo();
			if (count($temp) >= 1) {
				$shared = self::object_to_array($temp);
			}
		}

		// META (rating, tags, dates, tags and sizes)
		$meta = $currentFile->getMeta();
		$size = $currentFile->getSize();

		if ($meta === null) {
			$rating = 0;
			$fileTags = null;
			$created = 0;
			$modified = 0;
		} else {
			if($meta->exists('rating')) {
				$rating = $meta->get('rating');
			} else {
				$rating = 0;
			}
	
			if($meta->exists('tags')) {
				$fileTags = $meta->get('tags');
			} else {
				$fileTags = null;
			}
	
			if($meta->exists('creationTime')) {
				$created = $meta->get('creationTime');
				$created = date('j/n/Y',$created);
			} else {
				$created = 0;
			}
	
			if($meta->exists('modificationTime')) {
				$modified = $meta->get('modificationTime');
				$modified = date('j/n/Y',$modified);
			} else {
				$modified = 0;
			}
		}

		if($settings->exists('tagNames')) {
			$tags = array();
			$tagNames = $settings->get('tagNames');
			$tagColors = $settings->get('tagColors');
			foreach($tagNames as $key => $value) {
				$tags[] = array($value, $tagColors[$key]);
			}
		} else {
			$tags = null;
		}

//		$unim = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
//		$c = 0;
//		while ($size>= 1024) {
//			$c++;
//			$size = $size / 1024;
//		}
//		$size = number_format($size, ($c ? 2 : 0), ',', '.') . ' ' . $unim[$c];

		$return = array(
			'type' => $currentFile->isDirectory() ? 'folder' : 'file',
			'name' => $currentFile->getName(),
			'extension' => utf8_strtoupper($currentFile->getExtension()),
			'size' => $size,
			'permissions' => $currentFile->getPermissions(false),
			'owner' => $currentFile->getOwner(),
			'rating' => $rating,
			'created' => $created,
			'modified' => $modified,
			'tags' => $fileTags,
			'allTags' => $tags,
			'path' => $currentFile->getParentPath(),
			'shared' => $shared,
			'absolutepath' => $currentFile->getAbsolutePath()
		);
                
                if($return['extension'] == 'LNK') {
                    if($return['extension'] == 'LNK') {
                        $return['content'] = $currentFile->getContents();
                    }
                }
		
		if ($return['type'] == 'folder') {
			$return['contentsize'] = count($currentFile->listFiles());
		}
		
		return $return;
	}

	public static function getUserTags($path) {
		$currentUser = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$settings = MetaManager::getInstance()->retrieveMeta($currentUser);

		if($settings->exists('tagNames')) {
			$tags = array();
			$tagNames = $settings->get('tagNames');
			$tagColors = $settings->get('tagColors');
			foreach($tagNames as $key => $value) {
				$tags[] = array($value, $tagColors[$key]);
			}
		} else {
			$tags = null;
		}

		return $tags;
	}

	public static function setFileTag($tag) {
		if(!$tag[0]) {
			return;
		}
		$file = FSI::getFile($tag[0]);
		$meta = $file->getMeta();
		$tags = $meta->get('tags');
		$tags[] = intval($tag[1][1]);
		$meta->set('tags', $tags);
		$file->setMeta($meta);
	}

	public static function removeFileTag($tag) {
		if(!$tag[0]) {
			return;
		}

		$file = FSI::getFile($tag[0]);
		$meta = $file->getMeta();
		$tags = $meta->get('tags');

		foreach($tags as $key=>$value) {
			if($value == $tag[1][1]) {
				unset($tags[$key]);
			}
		}

		$meta->set('tags', $tags);
		$file->setMeta($meta);
	}

	public static function setUserTag($tag) {
		$currentUser = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$settings = MetaManager::getInstance()->retrieveMeta($currentUser);
		$tagNames = $settings->get('tagNames');
		$tagColors = $settings->get('tagColors');
		$tagNames[] = $tag[0];
		$tagColors[] = $tag[1];
		$settings->set('tagNames', $tagNames);
		$settings->set('tagColors', $tagColors);
		MetaManager::getInstance()->storeMeta($currentUser, $settings);
	}
	
	/**
	 * TODO: Will need to be moved/merged to/with FileSystemExecModule
	 */
	/*public static function copy($params) {
		$currentUser = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$settings = MetaManager::getInstance()->retrieveMeta($currentUser);
		$target = FSI::getFile($params['folder']);
		$results = array();
        $apiManager = new ApiManagerOld();
        $userName = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getName();
        $stacksync = false;

        $pathStackSync = "home://~" . $userName . "/Stacksync";

        if(strpos($params['folder'],$pathStackSync) !== false) {
            $stacksync = true;
        }

		for($i = 0; $i < count($params['files']); $i++) {
            if(is_array($params['files'][$i])) {
                self::verifyToken();
                $source = FSI::getFile($params['files'][$i]['path']);
                $content = $apiManager->downloadFile($params['files'][$i]['id']);
                if(strlen($content) > 0) {
                    $source->getRealFile()->putContents($content);
                }
            } else {
			    $source = FSI::getFile($params['files'][$i]);
            }

			if (!$source->isDirectory()) {
				$name = explode(".", $source->getName());
				$extension = (string) $name[count($name) - 1];
				$theName = substr($source->getName(), 0, strlen($source->getName()) - strlen($extension) - 1);

                $nameForCheck = $theName;

                if (!$source->isDirectory()) {
                    $nameForCheck .= '.' . $extension;
                }

                $number = 1;
                $newFile = FSI::getFile($params['folder'] . "/" . $nameForCheck);

                while ($newFile->exists()) {
                    $futureName = Array($theName, $number);
                    $nameForCheck = implode(' ', $futureName);
                    if (!$source->isDirectory()) {
                        $nameForCheck .= '.' . $extension;
                    }
                    $number++;
                    $newFile = FSI::getFile($params['folder'] . "/" . $nameForCheck);
                }

                $isCopy = $source->copyTo($newFile);

                if($isCopy === true && $stacksync === true) {
                    $pathReal =  AdvancedPathLib::parse_url($newFile->getRealFile()->getPath());
                    $file = fopen($pathReal['path'],"r");
                    if($file !== false) {
                        $len = strlen($pathStackSync);
                        $pathU1db = substr($newFile->getAbsolutePath(),$len);
                        $lenfinal = strrpos($pathU1db, $newFile->getName());
                        $posfinal = $lenfinal > 1?$lenfinal-strlen($pathU1db)-1:$lenfinal-strlen($pathU1db);
                        $pathParent = substr($pathU1db,0,$posfinal);
                        $folder = NULL;
                        if ($pathParent !== '/') {
                            $pos=strrpos($pathParent,'/');
                            $folder = substr($pathParent,$pos+1);
                            $pathParent = substr($pathParent,0,$pos+1);
                        }
                        self::verifyToken();
                        $apiManager->createFile($nameForCheck,$file,filesize($pathReal['path']),$pathParent,$folder);
                        fclose($file);
                    }
                }
                $results[] = self::getFileInfo($newFile, $settings);
            } else {
                //self::copyDirectory($stacksync,$params['files'][$i]);
            }
		}
		return $results;




	}*/

	/**
	 * TODO: Will need to be moved/merged to/with FileSystemExecModule
	 */
	public static function createNewFile($params) {
		$currentUser = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$settings = MetaManager::getInstance()->retrieveMeta($currentUser);
		$newFile = FSI::getFile($params[0]);
		$name = explode(".", $newFile->getName());
		$extension = (string) $name[count($name) - 1];
		if ($newFile->exists()) {
			$name = explode(".", $newFile->getName());
			$path = str_replace($newFile->getName(), '', $newFile->getPath());
			$extension = (string) $name[count($name) - 1];
			$theName = substr($newFile->getName(), 0, strlen($newFile->getName()) - strlen($extension) - 1);
			$futureName = Array($theName, 1);
			$nameForCheck = implode(' ', $futureName);
			$nameForCheck .= '.' . $extension;
			$newFile = FSI::getFile($path . "/" . $nameForCheck);
			while ($newFile->exists()) {
				$futureName[1] += 1;
				$nameForCheck = implode(' ', $futureName);
				$nameForCheck .= '.' . $extension;
				$newFile = FSI::getFile($path . "/" . $nameForCheck);
			}
		}
		
		if ($extension == 'edoc') {
			$rand = md5(uniqid(time()));
			mkdir('/tmp/'.$rand);
			$uniqid = uniqid();
			shell_exec('touch /tmp/'.$rand.'/document.html');
			file_put_contents('/tmp/'.$rand.'/duid', $uniqid);
			$myFile = FSI::getFile($params[0] . '_tmp');
			$myFile->checkWritePermission();
			$myRealFile = $myFile->getRealFile();
			$fileNameOriginal = AdvancedPathLib::getPhpLocalHackPath($myRealFile->getPath());
			//this is REALLY annoying to be forced to do this, but zip command line util is a mess
			$oldDir = getcwd();
			chdir('/tmp/'.$rand);
			$cmd = 'zip -r '.escapeshellarg($fileNameOriginal).' ./';
			shell_exec($cmd);
			//we return into the normal directory...this is ugly
			chdir($oldDir);
			AdvancedPathLib::rmdirs('/tmp/'.$rand);
			// creating a fake file trought FSI, so we can have our nice xml :)
			$newFile->createNewFile(true);
			$newFile->putContents($myFile->getContents());
			unlink($fileNameOriginal); // FIXME!!!!!
		} else {
			$newFile->createNewFile();
		}
		return self::getFileInfo($newFile, $settings);
	}

	/**
	 * TODO: Will need to be moved/merged to/with FileSystemExecModule
	 */
	public static function delete($params) {
		$currentUser = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$filesInfo = array();
        $apiManager = new ApiManager();
		foreach ($params as $param) {
			$fileToRemove = FSI::getFile($param['file']);
			$filesInfo[] = $fileToRemove->getAbsolutePath();
            $isFile = !$fileToRemove->isDirectory();
			if($fileToRemove->delete(true)) {
			    self::removeUrlShareInfo($param['file']);
                if(isset($param['id'])) {
                    $cloud = $param['cloud'];
                    $token = $_SESSION['access_token_' . $cloud . '_v2'];
                    $resourceUrl = null;
                    $consumer_key = null;
                    $consumer_secret = null;
                    if(isset($param['resource_url'])) {
                        $token = new stdClass();
                        $token->key = $param['access_token_key'];
                        $token->secret = $param['access_token_secret'];
                        $resourceUrl = $param['resource_url'];
                        if(isset($params['consumer_key']) && isset($params['consumer_secret'])) {
                            $consumer_key = $params['consumer_key'];
                            $consumer_secret = $params['consumer_secret'];
                        }
                    }
                    $result = $apiManager->deleteMetadata($cloud, $token, $isFile, $param['id'], $currentUser->getId(), $fileToRemove->getParentPath(),$resourceUrl,$consumer_key,$consumer_secret);
                    if($result) {
                        if(isset($result['error']) && $result['error'] == 403) {
                            $denied = self::permissionDeniedCloud($cloud);
                            $result['path'] = $denied['path'];
                            return $result;
                        }
                    }
                }
            }
		}
		return $filesInfo;
	}

	/**
	 * Remove urlShare info (if any) when we delete a file
	 * @param <String> $filename
	 */
	protected static function removeUrlShareInfo ($filename) {
		/**
		 * Execute a search to detect if this file has Url
		 */
		$urlShareController = UrlShareController::getInstance();
		$filepath = $filename;

		$shareFile = new UrlFile();
		$shareFile->setPath($filepath);

		$founded = $urlShareController->searchFile($shareFile);
		if ($founded) {
			$founded = current($founded);
		} else {
			return;
		}

		$urlFileId = $founded->getId();
		$urlShareController->deleteFile($founded);
	}

	public static function mkdir($params) {
		$currentUser = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$settings = MetaManager::getInstance()->retrieveMeta($currentUser);
		$dirToCreate = FSI::getFile($params[0] . '/' . $params[1]);
		if ($dirToCreate->exists()) {
			$name = Array($params[1], 1);
			$futureName = implode(' ', $name);
			$dirToCreate = FSI::getFile($params[0] . '/' . $futureName);
			while ($dirToCreate->exists()) {
				$name[1] += 1;
				$futureName = implode(' ', $name);
				$dirToCreate = FSI::getFile($params[0] . '/' . $futureName);
			}
		}

		$dirToCreate->mkdir();

        if(count($params) === 4 || count($params) == 7 || count($params) == 9) {
            $apiManager = new ApiManager();
            $idParent = $params[2]; //$params[2] === 0?'null':$params[2];
            $cloud = $params[3];
            $path = self::getPathCloud($dirToCreate,$cloud);
            $token = $_SESSION['access_token_' . $cloud . '_v2'];
            $resourceUrl = null;
            $consumer_key = null;
            $consumer_secret = null;
            if(count($params) == 7 || count($params) == 9) {
                $token = new stdClass();
                $resourceUrl = $params[4];
                $token->key = $params[5];
                $token->secret = $params[6];
                if(count($params) == 9) {
                    $consumer_key = $params[7];
                    $consumer_secret = $params[8];
                }
            }

            //$result = $apiManager->createMetadata($_SESSION['access_token_v2'],$currentUser->getId(),false,$dirToCreate->getName(),$idParent,$path);
            $result = $apiManager->createMetadata($cloud,$token,$currentUser->getId(),false,$dirToCreate->getName(),$idParent,$path,null,$resourceUrl,$consumer_key,$consumer_secret);

            if($result) {
                if (isset($result['error']) && $result['error'] === 403) {
                    //$dirToCreate->delete();
                    //self::permissionDeniedStackSync($currentUser->getId());
                    if($idParent !== 0) {
                        $denied = self::permissionDeniedCloud($cloud);
                        $result['path'] = $denied['path'];
                    } else {
                        $result['error'] = -1;
                        $dirToCreate->delete();
                    }
                }
                return $result;
            } else {
                $dirToCreate->delete();
            }

        }
		$return = self::getFileInfo($dirToCreate, $settings);
		return $return;

	}

	/**
	 * TODO: Will need to be moved/merged to/with FileSystemExecModule
	 */
	public static function move($params) {
        $apiManager = new ApiManager();
        $currentUser = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
        $settings = MetaManager::getInstance()->retrieveMeta($currentUser);
        $pathOrig = $params[ 'orig' ];
        $pathDest = $params[ 'dest' ];
        $cloudDest = $params[ 'cloudDest' ];
        $cloudOrig = $params[ 'cloudOrig' ];

        if ($cloudOrig === $cloudDest) {

            $file = null;
            $cloud = (isset($params[ 'cloud' ][ 'origin' ]) && strlen($params[ 'cloud' ][ 'origin']) > 0) ? $params[ 'cloud' ][ 'origin' ] :
                (isset($params[ 'cloud' ][ 'destination' ]) && strlen($params[ 'cloud' ][ 'destination' ]) > 0) ? $params[ 'cloud' ][ 'destination' ] : null;

            if(is_array($params[ 'file' ]) && array_key_exists('pathAbsolute', $params['file']))  {
                $file = FSI::getFile($params[ 'file' ][ 'pathAbsolute' ]);
            } else {
                $file = FSI::getFile($params[ 'file' ][ 'path' ]);
            }

            $isDirectory = $file->isDirectory();

            if (!$isDirectory) {
                $name = explode(".", $file->getName());
                $extension = (string) $name[count($name) - 1];
                $theName = substr($file->getName(), 0, strlen($file->getName()) - strlen($extension) - 1);
            } else {
                $theName = $file->getName();
            }

            $nameForCheck = $theName;

            if (!$isDirectory) {
                $nameForCheck .= '.' . $extension;
            }

            $number = 1;
            $newFile = FSI::getFile($pathDest . "/" . $nameForCheck);
            $change = false;

            while ($newFile->exists()) {
                $futureName = Array($theName, $number);
                $nameForCheck = implode(' ', $futureName);
                if (!$isDirectory) {
                    $nameForCheck .= '.' . $extension;
                }
                $number++;
                $newFile = FSI::getFile($pathDest . "/" . $nameForCheck);
                $change = true;
            }

            if($cloudOrig) {
                $apiManager->recursiveDeleteVersion($cloud, $params[ 'file' ][ 'id' ], $currentUser->getId());
            }

            if($cloudOrig && $cloudDest) {
                if($isDirectory) {
                    $filename = $theName;
                } else {
                    $filename = $theName . '.' . $extension;
                }
                $token = $_SESSION['access_token_' . $cloud . '_v2'];
                $resourceUrl = null;
                if(isset($params[ 'file' ]['resource_url'])) {
                    $token = new stdClass();
                    $token->key = $params['file']['access_token_key'];
                    $token->secret = $params['file']['access_token_secret'];
                    $resourceUrl = $params['file']['resource_url'];
                }

                $result = $apiManager->moveMetadata($cloud, $token, !$isDirectory, $params[ 'file' ][ 'id' ], $pathOrig, $pathDest, $currentUser->getId(), $params[ 'idParent' ], $filename, $change == true ? $nameForCheck : null,$resourceUrl);
                if($result[ 'status' ] == 'KO') {
                    if($result[ 'error' ] == 403) {
                        $denied = self::permissionDeniedCloud($params[ 'cloud' ]);
                        $result[ 'path' ] = $denied[ 'path' ];
                    }
                    return $result;
                }
            } else {
                $file->moveTo($newFile);
            }

            self::updateUrlShare($file->getPath(), $newFile->getPath());
            $return = self::getFileInfo($newFile, $settings);
            return $return;

        } else {
            $cloud = (isset($params[ 'cloud' ][ 'origin' ]) && strlen($params[ 'cloud' ][ 'origin' ]) > 0) ? $params[ 'cloud' ][ 'origin' ] : null;
            if($cloudOrig) {
                $apiManager->recursiveDeleteVersion($cloud, $params[ 'file' ][ 'id' ], $currentUser->getId());
            }
            $result = self::copyFile($params);
            if (array_key_exists('error', $result)) {
                if($result[ 'error' ] == 403) {
                    $denied = self::permissionDeniedCloud($cloud);
                    $result[ 'path' ] = $denied[ 'path' ];
                }
            }
            return $result;
        }

	}

	/**
	 * Update urlShare info (if any) when we move/rename a file
	 * @param <String> $filename
	 */
	protected static function updateUrlShare ($source, $target) {
		/**
		 * Execute a search to detect if this file has Url
		 */
		$urlShareController = UrlShareController::getInstance();
		$filepath = $source;
		$filename = basename($filepath);

		$shareFile = new UrlFile();
		$shareFile->setPath($filepath);

		$founded = $urlShareController->searchFile($shareFile);
		if ($founded) {
			$founded = current($founded);
		} else {
			return;
		}

		$founded->setPath($target);
		$urlShareController->updateFile($founded);
	}
	
	/**
	 * TODO: Will need to be moved/merged to/with FileSystemExecModule
	 */
	public static function rename($params) {
		$currentUser = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
		$settings = MetaManager::getInstance()->retrieveMeta($currentUser);
		$fileToRename = FSI::getFile($params[0]);
        $apiManager = new ApiManager();
        $cloudspace = count($params) == 6 ? true : false;

        if($cloudspace || count($params) === 9 || count($params) === 11) {
            $cloud = $params[5];
            $parent = $params[4] === 0? 'null' : $params[4];
            if(!$fileToRename->isDirectory()) {
                if($params[3] !== -1) {
                    $pathAbsolute = AdvancedPathLib::getPhpLocalHackPath($fileToRename->getRealFile()->getAbsolutePath());
                    $token = $_SESSION['access_token_' . $cloud . '_v2'];
                    $resourceUrl = null;
                    $consumer_key = null;
                    $consumer_secret = null;
                    if(count($params) === 9 || count($params) === 11) {
                        $token = new stdClass();
                        $resourceUrl = $params[6];
                        $token->key = $params[7];
                        $token->secret = $params[8];
                        if(count($params) == 11) {
                            $consumer_key = $params[9];
                            $consumer_secret = $params[10];
                        }
                    }

                    $metadata = $apiManager->downloadMetadata($token, $params[3], $pathAbsolute, $currentUser->getId(), false, $cloud, $resourceUrl,$consumer_key,$consumer_secret);
                    if (isset($metadata['error'])) {
                        if ($metadata['error'] == 403) {
                            $denied = self::permissionDeniedCloud($cloud);
                            $metadata['path'] = $denied['path'];
                        }
                        return $metadata;
                    }
                }
            }

            $i = 1;
            $nameForCheck = $params[2];
            $renamed = FSI::getFile($params[1] . '/' . $params[2]);
            while ($renamed->exists()) {
                $name = explode(".", $params[2]);
                $extension = (string) $name[count($name) - 1];
                $futureName = Array($name[0], $i);
                $nameForCheck = implode(' ', $futureName);

                if (!$fileToRename->isDirectory()) {
                    $nameForCheck .= '.' . $extension;
                }
                $i++;
                $renamed = FSI::getFile($params[1] . '/' . $nameForCheck);
            }

            if($fileToRename->renameTo($nameForCheck)) {
                $path = self::getPathCloud($renamed, $cloud);
                if($params[3] !== -1) {
                    $token = $_SESSION['access_token_' . $cloud . '_v2'];
                    $resourceUrl = null;
                    $consumer_key = null;
                    $consumer_secret = null;
                    if(count($params) === 9 || count($params) === 11) {
                        $token = new stdClass();
                        $resourceUrl = $params[6];
                        $token->key = $params[7];
                        $token->secret = $params[8];
                        if(count($params) === 11) {
                            $consumer_key = $params[9];
                            $consumer_secret = $params[10];
                        }
                    }

                    $resultado = $apiManager->renameMetadata($cloud, $token, !$fileToRename->isDirectory(), $params[3], $renamed->getName(), $path, $currentUser->getId(), $parent,$resourceUrl,$consumer_key,$consumer_secret);
                    if (isset($resultado['error'])) {
                        if ($resultado['error'] == 403) {
                            $denied = self::permissionDeniedCloud($cloud);
                            $resultado['path'] = $denied['path'];
                        }
                        return $resultado;
                    }
                } else {
                    $event = new FileEvent($fileToRename);
                    $fileToRename->fireEvent('fileWritten',$event);
                }
            }
        } else {
            $i = 1;
            $nameForCheck = $params[2];
            $renamed = FSI::getFile($params[1] . '/' . $params[2]);
            while ($renamed->exists()) {
                $name = explode(".", $params[2]);
                $extension = (string) $name[count($name) - 1];
                $futureName = Array($name[0], $i);
                $nameForCheck = implode(' ', $futureName);

                if (!$fileToRename->isDirectory()) {
                    $nameForCheck .= '.' . $extension;
                }
                $i++;
                $renamed = FSI::getFile($params[1] . '/' . $nameForCheck);
            }
            $fileToRename->renameTo($nameForCheck);
        }

        self::updateUrlShare($params[0], $renamed->getPath());
        $return = self::getFileInfo($fileToRename, $settings);
        return $return;
	}

	

	/**
	 * Return an associative Array with tree file structure of socialBarUpdater
	 * handlers
	 * @return array
	 */
	public static function getSocialUpdaterHandlers () {
		$handlersPath = self::getSocialUpdaterHandlersPath();
		$arrayTree = Array();
		self::createStructFromDir($handlersPath, $arrayTree);
		$arrayTree = self::simplifyStruct($arrayTree);
		return $arrayTree;
	}

    public static function getMetadata($params)
    {
        $cloud = $params[ 'cloud' ];

        if(isset($_SESSION[ 'access_token_' . $cloud . '_v2' ])) {
            $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getId();
            $path = $params[ 'path' ];
            $id = $params[ 'id' ];
            $resource_url = null;
            $consumer_key = null;
            $consumer_secret = null;

            $token = new stdClass();
            if(isset($params['resource_url'])) {
                $token->key = $params['access_token_key'];
                $token->secret = $params['access_token_secret'];
                $resource_url = $params['resource_url'];
                if(isset($params['consumer_key']) && isset($params['consumer_secret'])) {
                    $consumer_key = $params['consumer_key'];
                    $consumer_secret = $params['consumer_secret'];
                }

            } else {
                $token = $_SESSION[ 'access_token_' . $cloud . '_v2' ];
            }

            $apiManager = new ApiManager();
            $result = $apiManager->getMetadata($cloud,$token,$id,$path,$user,$resource_url,$consumer_key,$consumer_secret);
            if($result) {
                if(isset($result[ 'error' ]) && $result[ 'error' ] == 403) {
                    $denied = self::permissionDeniedCloud($cloud);
                    $result[ 'path' ] = $denied[ 'path' ];
                }
            }
        } else {
            $result[ 'error' ] = -1;
            $result[ 'description' ] = "Access token not exists";
        }
        return $result;
    }

	/**
	 * SimplifyStruct flat array data struct to just 2 level and remove all not
	 * javascript file
	 * A = (
	 *		B = (
	 *			1.js,
	 *			2.c,
	 *			C = (
	 *				3.js,
	 *				4.js,
	 *				5.js,
	 *				D = (
	 *					null
	 *				)
	 *			)
	 *		)
	 * )
	 *
	 * Will Become
	 *
	 * A= (
	 *	B = (
	 *		1.js,
	 *		3.js,
	 *		4.js,
	 *		5.js
	 *	)
	 * )
	 *
	 *
	 * @param <Array> $arrayTree
	 * @return <Array>
	 */
	private static function simplifyStruct($arrayTree) {
		$newStruct = Array();
		foreach ($arrayTree as $key => $leaf) {
			if (is_array($leaf)) {
				$newStruct[$key] = Array();
				self::array_values_recursive($leaf, $newStruct[$key]);
				$newStruct[$key] = array_filter($newStruct[$key], 'self::filterJavascript');
			}
		}
		return $newStruct;
	}
	/**
	 * Callback for array_filter
	 * 
	 * @param <mixed> $item
	 * @return <Boolean>
	 */
	private static function filterJavascript ($item) {
		if ($item == null || !is_string($item) || !substr(strrchr($item, '.'), 1) == 'js') {
			return false;
		} else {
			return true;
		}
	}
	/**
	 * Flat an array to just on level
	 * 
	 * @param <Array> $array
	 * @param <Array> $result
	 */
	private static function array_values_recursive($array, &$result) {
		foreach ($array as $element) {
			if (is_array($element)) {
				self::array_values_recursive($element, $result);
			} else {
				$result[] = $element;
			}
		}
	}

	/**
	 * Create an associative array with the struct of the directory tree
	 *
	 * @param <String> $dirPath
	 * @return Array
	 */
	private static function createStructFromDir ($dirPath, &$arrayTree) {
		$iterator = new DirectoryIterator($dirPath);
		foreach ($iterator as $fileInfo) {
			if ($fileInfo->getFilename() == '.' || $fileInfo->getFilename() == '..' || $fileInfo->getFilename() == '.svn') {
				continue;
			}

			if ($fileInfo->isDir()) {
				self::createStructFromDir($dirPath . $fileInfo->getFilename() . '/', $arrayTree[$fileInfo->getFilename()]);
			}

			if ($fileInfo->isFile()) {
				$arrayTree[] = $fileInfo->getFilename();
			}

		}
	}

	/**
	 * Return the path of the correct handler.
	 * Priority:
	 *  1) Some custom handler
	 *	2) default handler
	 * @return string
	 */
	private static function getSocialUpdaterHandlersPath () {
		$handlersPath = EYE_ROOT . '/' . APPS_DIR . '/files/SocialBarUpdater/handlers/';
		$directory = new DirectoryIterator($handlersPath);
		foreach ($directory as $fileInfo) {
			if ($fileInfo->isDir()) {
				if ($fileInfo->getFilename() == '..' || $fileInfo->getFilename() == '.' || $fileInfo->getFilename() == '.svn') {
					continue;
				}

				if ($fileInfo->getFilename() == 'default') {
					$return = $handlersPath . 'default/';
				} else {
					return $handlersPath . $fileInfo->getFilename() . '/';
				}
			}
		}
		if ($return) {
			return $return;
		} else {
			throw new EyeFileNotFoundException('No default Handler present in ' . $directory);
		}

	}

    /*public static function downloadFileStacksync($params)
    {
        if(isset($_SESSION['access_token_v2'])) {
            if( !isset($params['id']) || !isset($params['path'])) {
                throw new EyeMissingArgumentException('Missing or invalid file.');
            }
            $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getId();
            $file = FSI::getFile($params['path']);
            $path = AdvancedPathLib::getPhpLocalHackPath($file->getRealFile()->getAbsolutePath());
            $apiManager = new ApiManager();
            $result = $apiManager->downloadMetadata($_SESSION['access_token_v2'],$params['id'],$path,$user);
            if($result) {
                if (isset($result['error']) && $result['error'] === 403) {
                    $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getId();
                    self::permissionDeniedStackSync($user);
                    return $result;
                }
            }
            return $params['path'];
        } else {
            $result['error'] = -1;
            return $result;
        }
    }*/

    public static function downloadFileCloud($params)
    {
        if( !isset($params['id']) || !isset($params['path']) || !isset($params['cloud'])) {
            throw new EyeMissingArgumentException('Missing or invalid file.');
        }

        $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getId();
        $file = FSI::getFile($params['path']);
        $path = AdvancedPathLib::getPhpLocalHackPath($file->getRealFile()->getAbsolutePath());
        $id = $params['id'];
        $cloud = $params['cloud'];
        $apiManager = new ApiManager();
        $token = $_SESSION[ 'access_token_' . $cloud . '_v2'];
        $resourceUrl = null;
        $consumer_key = null;
        $consumer_secret = null;
        if(isset($params['resource_url'])) {
            $token = new stdClass();
            $token->key = $params['access_token_key'];
            $token->secret = $params['access_token_secret'];
            $resourceUrl = $params['resource_url'];
            if(isset($params['consumer_key']) && isset($params['consumer_secret'])) {
                $consumer_key = $params['consumer_key'];
                $consumer_secret = $params['consumer_secret'];
            }
        }

        $result = $apiManager->downloadMetadata($token, $id, $path, $user, false, $cloud,$resourceUrl,$consumer_key,$consumer_secret);
        if($result[ 'status' ] == 'KO') {
            if($result[ 'error' ] == 403) {
                $denied = self::permissionDeniedCloud($cloud);
                $result[ 'path' ] = $denied[ 'path' ];
            }
            return $result;
        } else {
            return $params['path'];
        }
    }

    public static function startProgress($params)
    {
        $apiManager = new ApiManager();
        $metadatas = array();
        $result = array();
        $cloud = isset($params[ 'cloud' ][ 'origin' ]) && strlen($params[ 'cloud' ][ 'origin' ]) > 0 ? $params[ 'cloud' ]['origin'] : null;
        $cloudDestination = isset($params[ 'cloud' ][ 'destination' ]) && strlen($params[ 'cloud' ][ 'destination' ]) > 0 ? $params[ 'cloud' ]['destination'] : null;
        $resourceUrl = null;
        $token = $_SESSION[ 'access_token_' . $cloud . '_v2' ];

        if($params[ 'action' ] == 'copy' ||
            ($params[ 'action' ] == 'move' && $params[ 'cloudOrig' ] && $params[ 'cloudDest' ] && $cloud != $cloudDestination) ||
            !(($params[ 'cloudOrig' ] == true && $params[ 'cloudDest' ] == true) || ($params[ 'cloudOrig' ] == false && $params[ 'cloudDest' ] == false))) {

            for($i = 0; $i < count($params[ 'files' ]); $i++) {
                if(is_array($params[ 'files' ][ $i ])) {
                    if(isset($params[ 'files' ][ $i ]['resource_url'])) {
                        $token = new stdClass();
                        $token->key = $params['access_token_key'];
                        $token->secret = $params['access_token_secret'];
                        $resourceUrl = $params['resource_url'];
                    }

                    $component = FSI::getFile($params[ 'files' ][ $i ][ 'path' ]);
                    $path = self::getPathCloud($component, $cloud);
                    $apiManager->getSkel($cloud, $token, $params[ 'files' ][ $i ][ 'is_file' ], $params[ 'files' ][ $i ][ 'id' ], $metadatas, $path, $params[ 'files' ][ $i ][ 'path' ], $component->getParentPath(),$resourceUrl);
                } else {
                    self::getSkelLocal($params[ 'files' ][ $i ], $metadatas, null);
                }
            }

            for($i = 0; $i < count($metadatas); $i++) {
                if(isset($metadatas[ $i ]->error)) {
                    if($metadatas[ $i ]->error == 403) {
                        $denied = self::permissionDeniedCloud($cloud);
                        $result[ 'path' ] = $denied[ 'path' ];
                    }
                    $result[ 'error' ] = $metadatas[ $i ]->error;
                    return $result;
                }
            }
        } else {
            for($i = 0; $i < count($params[ 'files' ]); $i++) {
                $object = new stdClass();
                if(is_array($params[ 'files' ][ $i ]) && array_key_exists('id', $params[ 'files' ][ $i ])){
                    $object->id = $params[ 'files' ][ $i ][ 'id' ];
                    $object->is_folder = $params[ 'files' ][ $i ][ 'is_file' ] ? false : true;
                    $component = FSI::getFile($params[ 'files' ][ $i ][ 'path' ]);
                    $path = self::getPathCloud($component, $cloud);
                    $object->path = $path;
                    $object->pathAbsolute = $params[ 'files' ][ $i ][ 'path' ];
                } else {
                    $object->path = $params[ 'files' ][ $i ];
                }

                array_push($metadatas, $object);
            }
        }

        $result[ 'metadatas' ] = $metadatas;
        return $result;
    }

    public static function getSkelLocal($path,&$metadatas,$parent)
    {
        $file = FSI::getFile($path);

        if($file->isDirectory()) {
            if ($parent == NULL) {
                $parentFolder =  "/" . $file->getName();
            }
            else {
                $parentFolder = $parent . "/" . $file->getName();
            }

            $files = $file->listFiles();

            foreach($files as $auxFile) {
                self::getSkelLocal($auxFile->getPath(),$metadatas,$parentFolder);
            }
        }

        $metadata = new stdClass();
        $metadata->path = $path;
        $metadata->size = $file->getSize();
        $metadata->parent = $parent;
        $metadata->is_folder = $file->isDirectory();
        $metadata->filename = $file->getName();

        array_push($metadatas,$metadata);

    }

    public static function copyFile($params)
    {
        $apiManager = new ApiManager();
        $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
        $cloudspace = false;
        $cloudOrig = isset($params[ 'cloud' ]['origin']) && strlen($params[ 'cloud' ]['origin']) > 0 ? $params[ 'cloud' ][ 'origin' ] : null;
        $cloudDestination = isset($params[ 'cloud' ]['destination']) && strlen($params[ 'cloud' ]['destination']) > 0 ? $params[ 'cloud' ]['destination'] : null;

        $pathCloud = "home://~" . $user->getName() . "/Cloudspaces/";
        $pathOrig = null;

        if($cloudOrig) {
            if ($pathCloud . $cloudOrig == $params[ 'orig' ]) {
                $pathOrig = "/";
            } else {
                $pathOrig = substr($params[ 'orig' ], strlen($pathCloud . $cloudOrig)) . "/";
            }
        }

        if($cloudDestination) {
            $cloudspace = true;
        }

        $file = null;
        $isFolder = true;
        $tmpFile = null;
        $filename = null;
        $pathinfo = null;
        $pathAbsolute = null;

        if(!$params[ 'file' ][ 'is_folder' ]) {
            $isFolder = false;
            $tmpFile = new LocalFile('/var/tmp/' . date('Y_m_d_H_i_s') . '_' . $user->getId());
            $pathAbsolute = AdvancedPathLib::getPhpLocalHackPath($tmpFile->getAbsolutePath());
            if(array_key_exists('id', $params[ 'file' ])) {
                $token = $_SESSION[ 'access_token_' . $cloudOrig . '_v2'];
                $resourceUrl = null;
                if(isset( $params[ 'file' ]['resource_url'])) {
                    $token = new stdClass();
                    $token->key = $params['access_token_key'];
                    $token->secret = $params['access_token_secret'];
                    $resourceUrl = $params['resource_url'];
                }
                $metadata = $apiManager->downloadMetadata($token, $params[ 'file' ][ 'id' ], $pathAbsolute, $user->getId(), true, $cloudOrig, $resourceUrl);
                if($metadata[ 'status' ] == 'KO') {
                    if($metadata[ 'error' ] == 403) {
                        $denied = self::permissionDeniedCloud($cloudOrig);
                        $metadata[ 'path' ] = $denied[ 'path' ];
                    }
                    return $metadata;
                } else {
                    if(isset($metadata[ 'local' ])) {
                        $file = FSI::getFile($params[ 'file' ][ 'pathEyeos' ]);
                        $tmpFile->putContents($file->getContents());
                    }
                }
            } else {
                $file = FSI::getFile($params[ 'file' ][ 'path' ]);
                $tmpFile->putContents($file->getContents());
            }
        }

        if($pathOrig) {
            if($params[ 'file' ][ 'path' ] == $pathOrig) {
                $pathinfo = pathinfo($params[ 'file' ][ 'filename' ]);
            }
        } else {
            if($params[ 'file' ][ 'parent' ] == null) {
                $pathinfo = pathinfo($params[ 'file' ][ 'filename' ]);
            }
        }

        if($pathinfo) {
            $nameForCheck = $pathinfo[ 'filename' ];
            $extension = null;
            if(isset($pathinfo[ 'extension' ])) {
                $extension = $pathinfo[ 'extension' ];
                $nameForCheck .=  '.' . $extension;
            }

            $number = 1;
            $newFile = FSI::getFile($params[ 'dest' ] . "/" . $nameForCheck);

            while ($newFile->exists()) {
                $futureName = Array($pathinfo[ 'filename' ], $number);
                $nameForCheck = implode(' ', $futureName);
                if ($extension) {
                    $nameForCheck .= '.' . $extension;
                }
                $number++;
                $newFile = FSI::getFile($params[ 'dest' ] . "/" . $nameForCheck);
                $params[ 'filenameChange' ] = $nameForCheck;

                if(!array_key_exists('parent', $params[ 'file' ])) {
                    $params[ 'pathChange' ] = substr($params[ 'orig' ], strlen($pathCloud . $cloudOrig));
                }
            }

            $filename = $newFile->getName();

        } else {
            $filename = $params[ 'file' ][ 'filename' ];
        }

        if ($cloudspace) {
            $pathParent = substr($params[ 'dest' ], strlen($pathCloud . $cloudDestination));
            if (array_key_exists('parent', $params[ 'file' ])) {
                if (strlen($pathParent) == 0 && !$params[ 'file' ][ 'parent' ]) {
                    $pathParent = '/';
                }
                if($params[ 'file' ][ 'parent' ]) {
                    $pathParent .= $params[ 'file' ][ 'parent' ];
                }

            } else {
                $pathParent .= '/' . substr($params[ 'file' ][ 'path' ], strlen($pathOrig));
                if(strlen($pathParent) > 1) {
                    $pathParent = substr($pathParent, 0, -1);
                }
            }

            $folder = NULL;
            if ($pathParent !== '/') {
                $pos=strrpos($pathParent, '/');
                $folder = substr($pathParent, $pos+1);
                $pathParent = substr($pathParent, 0, $pos+1);
            }

            $parentId = false;

            if($folder) {
                $path = $pathParent . $folder . '/';
                $lista = new stdClass();
                $lista->cloud = $cloudDestination;
                $lista->path = $pathParent;
                $lista->filename = $folder;
                $lista->user_eyeos = $user->getId();
                $u1db = json_decode($apiManager->callProcessU1db('parent', $lista));

                if($u1db !== NULL && count($u1db) > 0) {
                    $parentId = $u1db[0]->id;
                    if($parentId === 'null') {
                        $parentId = '0';
                    }
                }
            } else {
                $parentId = '0';
                $path = $pathParent;
            }

            if($parentId !== false) {
                $token = $_SESSION[ 'access_token_' . $cloudDestination . '_v2' ];
                $resourceUrl = null;

                if(isset($params['resource_url'])) {
                    $token = new stdClass();
                    $token->key = $params['access_token_key'];
                    $token->secret = $params['access_token_secret'];
                    $resourceUrl = $params['resource_url'];
                }

                $metadata = $apiManager->createMetadata($cloudDestination, $token, $user->getId(), !$isFolder, $filename, $parentId, $path, $pathAbsolute, $resourceUrl);
                if($metadata[ 'status' ] == 'KO') {
                    if($metadata[ 'error' ] == 403) {
                        $denied = self::permissionDeniedCloud($cloudDestination);
                        $metadata[ 'path' ] = $denied[ 'path' ];
                    }
                    return $metadata;
                }
            }
        }

        $pathDest = null;
        if (array_key_exists('parent', $params[ 'file' ])) {
            if ($params[ 'file' ][ 'parent' ]) {
                $pathDest = $params[ 'dest' ] . $params ['file' ][ 'parent' ] . '/';
            } else {
                $pathDest = $params[ 'dest' ] . '/';
            }
        } else {
            if ($pathOrig == $params[ 'file' ][ 'path' ]) {
                $pathDest = $params[ 'dest' ] . '/';
            } else {
                $pathDest = $params[ 'dest' ] . '/' . substr($params[ 'file' ][ 'path' ], strlen($pathOrig));
            }
        }

        $pathDest .= $filename;
        $newFile = FSI::getFile($pathDest);
        if($isFolder) {
            $newFile->mkdir();
        } else {
            $tmpFile->copyTo($newFile);
        }

        if($tmpFile) {
            $tmpFile->delete();
        }

        return $params;
    }

    public static function getToken()
    {
        $result = 0;
        try {
            if(!isset( $_SESSION['access_token_v2'])) {
                $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getId();
                $oauthManager =  new OAuthManager();
                $token = $oauthManager->getToken($user);

                if(strlen($token->getTkey()) > 0) {
                    $aux = new stdClass();
                    $aux->key = $token->getTkey();
                    $aux->secret = $token->getTsecret();
                    $_SESSION['access_token_v2'] = $aux;
                    $result = true;
                }
            } else {
                $result = true;
            }

        } catch (Exception $e){
            throw new Exception($e->getMessage());
        }

        return $result;
    }

    public static function getTokenCloud($cloud) {
        $oautManager = new OAuthManager();
        $result[ 'status' ] = false;
        try {
            $oauth_url = self::getOauthUrlCloud($cloud);
            $request_token = $oautManager->getRequestToken($cloud);
            if($request_token) {
                $_SESSION['request_token_'. $cloud . '_v2'] = $request_token;
                 $result[ 'status' ] = true;
                 if (property_exists($oauth_url, "error")) {
                     $result[ 'url' ] = null;
                 } else {
                     $result[ 'url' ] = $oauth_url . $request_token->key;
                 }
                 $result[ 'token' ] = $request_token->key;
            }
        } catch (Exception $e) {}

        return $result;
    }

    public static function getAccessCloud($params)
    {
        try {
            $oauthManager = new OAuthManager();
            $token = new stdClass();
            $token->key = $_SESSION['request_token_' . $params[ 'cloud' ] . '_v2']->key;
            $token->secret = $_SESSION['request_token_' . $params[ 'cloud' ] . '_v2']->secret;
            $access_token = $oauthManager->getAccessToken($params[ 'cloud' ], $token, $params[ 'verifier' ]);

            if($access_token) {
                $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getId();
                $tokenDB = new Token();
                $tokenDB->setCloudspaceName($params[ 'cloud' ]);
                $tokenDB->setUserID($user);
                $tokenDB->setTkey($access_token->key);
                $tokenDB->setTsecret($access_token->secret);

                if($oauthManager->insertToken($tokenDB)) {
                    $_SESSION['access_token_' . $params[ 'cloud' ] . '_v2'] = $access_token;
                    $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
                    $path = "home://~" . $user->getName() . "/Cloudspaces/" . $params[ 'cloud' ];
                    $filesProvider = new FilesProvider();
                    $filesProvider->createFile($path, true);
                    return true;
                }
            }
        } catch (Exception $e) {}
        return 0;
    }

    public static function getPathStacksync($component)
    {
        $path = $component->getPath();
        $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
        $userName = $user->getName();
        $len = strlen("home://~" . $userName . "/Cloudspaces/Stacksync");
        $pathU1db = substr($path,$len);
        $lenfinal = strrpos($pathU1db,$component->getName());
        $posfinal = $lenfinal > 1?$lenfinal-strlen($pathU1db)-1:$lenfinal-strlen($pathU1db);
        $pathParent = substr($pathU1db,0,$posfinal);
        $folder = NULL;
        if ($pathParent !== '/') {
            $pos=strrpos($pathParent,'/');
            $folder = substr($pathParent,$pos+1);
            $pathParent = substr($pathParent,0,$pos+1);
        }

        if($folder !== NULL) {
            $result = $pathParent . $folder . '/';
        } else {
            $result = $pathParent;
        }

        return $result;
    }

    public static function cleanCloud($cloud)
    {
        $result[ 'status' ] = false;
        $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
        $oauthManager = new OAuthManager();
        $apiManager = new ApiManager();
        $token = new Token();
        $token->setCloudspaceName($cloud);
        $token->setUserID($user->getId());
        if ($oauthManager->deleteToken($token)) {
            if ($apiManager->deleteMetadataUser($user->getId(), $cloud)) {
                unset($_SESSION['request_token_' . $cloud . '_v2']);
                unset($_SESSION['access_token_' . $cloud . '_v2']);
                $pathOrg = "home://~" . $user->getName() . "/Cloudspaces/" . $cloud;
                $pathDest = "home://~" . $user->getName() . "/Cloudspaces/." . $cloud;
                $folderToRename1 = FSI::getFile($pathOrg);
                $folderToRename2 = FSI::getFile($pathDest);
                shell_exec('mv ' . AdvancedPathLib::getPhpLocalHackPath($folderToRename1->getRealFile()->getAbsolutePath()) . ' ' .
                    AdvancedPathLib::getPhpLocalHackPath($folderToRename2->getRealFile()->getAbsolutePath()));
                $result['status'] = true;
                $result['path'] = AdvancedPathLib::getPhpLocalHackPath($folderToRename2->getRealFile()->getAbsolutePath());
                return $result;
            } else {
                $result[ 'error' ] = 'User metadata table and cloud not deleted';
            }
        } else {
            $result[ 'error' ] = 'Token table not deleted';
        }
        return $result;
    }

    public static function permissionDeniedCloud($cloud)
    {
        return self::cleanCloud($cloud);
    }

    public static function createComment($params)
    {
        $id = $params['id'];
        $user = $params['user'];
        $text = $params['text'];
        $commentsManager = new CommentsManager();
        return $commentsManager->createComment($id,$user,$text);
    }

    public static function deleteComment($params)
    {
        $id = $params['id'];
        $user = $params['user'];
        $time_created = $params['time_created'];
        $commentsManager = new CommentsManager();
        return $commentsManager->deleteComment($id,$user,$time_created);
    }

    public static function getComments($params)
    {
        $id = $params['id'];
        $commentsManager = new CommentsManager();
        return $commentsManager->getComments($id);
    }

    public static function listVersions($params)
    {
        if( !isset($params['id']) || !isset($params['cloud'])) {
            throw new EyeMissingArgumentException('Missing or invalid file.');
        }

        $id = $params['id'];
        $cloud = $params['cloud'];

        if(isset($_SESSION['access_token_' . $cloud . '_v2'])) {
            $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getId();
            $apiManager = new ApiManager();
            $token = $_SESSION['access_token_' . $cloud . '_v2'];
            $resourceUrl = null;
            $consumer_key = null;
            $consumer_secret = null;

            if(isset($params['resource_url'])) {
                $token = new stdClass();
                $resourceUrl = $params['resource_url'];
                $token->key = $params['access_token_key'];
                $token->secret = $params['access_token_secret'];
                if(isset($params['consumer_key']) && isset($params['consumer_secret'])) {
                    $consumer_key = $params['consumer_key'];
                    $consumer_secret = $params['consumer_secret'];
                }

            }

            $result = $apiManager->listVersions($cloud, $token, $id, $user, $resourceUrl,$consumer_key,$consumer_secret);

            if ($result) {
                if (isset($result['error']) && $result['error'] == 403) {
                    $denied = self::permissionDeniedCloud($cloud);
                    $result['path'] = $denied['path'];
                }
            }
        } else {
            $result[ 'error' ] = -1;
            $result[ 'description' ] = "Access token not exists";
        }

        return $result;
    }

    public static function getFileVersionData($params)
    {
        if (isset($params[ 'cloud' ]) && isset($_SESSION[ 'access_token_' . $params[ 'cloud' ] . '_v2'])) {
            $cloud = $params[ 'cloud' ];
            $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getId();
            $id = $params[ 'id' ];
            $version = $params[ 'version' ];
            $file = FSI::getFile($params[ 'path' ]);
            $apiManager = new ApiManager();
            $path = AdvancedPathLib::getPhpLocalHackPath($file->getRealFile()->getAbsolutePath());
            $token = $_SESSION['access_token_' . $cloud . '_v2'];
            $resourceUrl = null;
            $consumer_key = null;
            $consumer_secret = null;
            if(isset($params['resource_url'])) {
                $token = new stdClass();
                $resourceUrl = $params['resource_url'];
                $token->key = $params['access_token_key'];
                $token->secret = $params['access_token_secret'];
                if(isset($params['consumer_key']) && isset($params['consumer_secret'])) {
                    $consumer_key = $params['consumer_key'];
                    $consumer_secret = $params['consumer_secret'];
                }
            }
            $result = $apiManager->getFileVersionData($cloud, $token, $id, $version, $path, $user, $resourceUrl,$consumer_key,$consumer_secret);
            if($result) {
                if(isset($result[ 'error' ]) && $result[ 'error' ] == 403) {
                    $denied = self::permissionDeniedCloud($cloud);
                    $result[ 'path' ] = $denied[ 'path' ];
                }
            }
        } else {
            $result[ 'error' ] = -1;
            $result[ 'description' ] = "Access token not exists";
        }
        return $result;
    }

    public static function listUsersShare($params)
    {
        if(isset($params[ 'cloud' ]) && isset($_SESSION[ 'access_token_' . $params[ 'cloud' ] . '_v2' ])) {
            $cloud = $params[ 'cloud' ];
            $id = $params['id'];
            $apiManager = new ApiManager();
            $token = $_SESSION[ 'access_token_' . $cloud . '_v2' ];
            $resourceUrl = null;
            $consumer_key = null;
            $consumer_secret = null;
            if(isset($params['resource_url'])) {
                $token = new stdClass();
                $resourceUrl = $params['resource_url'];
                $token->key = $params['access_token_key'];
                $token->secret = $params['access_token_secret'];
                if(isset($params['consumer_key']) && $params['consumer_secret']) {
                    $consumer_key = $params['consumer_key'];
                    $consumer_secret = $params['consumer_secret'];
                }
            }

            $result = $apiManager->getListUsersShare($cloud, $token, $id,$resourceUrl,$consumer_key,$consumer_secret);
            if($result) {
                if(isset($result['error']) && $result['error'] == 403) {
                    $denied = self::permissionDeniedCloud($cloud);
                    $result[ 'path' ] = $denied[ 'path' ];
                }
            }
        } else {
            $result[ 'error' ] = -1;
            $result[ 'description' ] = "Access token not exists";
        }
        return $result;
    }

    public static function shareFolder($params)
    {
        if(isset($params[ 'cloud' ]) && isset($_SESSION['access_token_' . $params[ 'cloud' ] . '_v2'])) {
            $cloud = $params[ 'cloud' ];
            $id = $params[ 'id' ];
            $list = $params[ 'list' ];
            $shared = $params['shared'];
            $apiManager = new ApiManager();
            $token = $_SESSION[ 'access_token_' . $cloud . '_v2' ];
            $resourceUrl = null;
            if(isset($params['resource_url'])) {
                $token = new stdClass();
                $resourceUrl = $params['resource_url'];
                $token->key = $params['access_token_key'];
                $token->secret = $params['access_token_secret'];
            }

            $result = $apiManager->shareFolder($cloud,$token , $id, $list, $shared, $resourceUrl);
            if($result) {
                if(isset($result[ 'error' ]) && $result[ 'error' ] == 403) {
                    $denied = self::permissionDeniedCloud($cloud);
                    $result[ 'path' ] = $denied[ 'path' ];
                }
            }
        } else {
            $result[ 'error' ] = -1;
            $result[ 'description' ] = "Access token not exists";
        }
        return $result;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getCloudsList(){
        $clouds = array();
        try {
            $apiManager =  new ApiManager();
            $oauthManager = new OAuthManager();
            $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getId();
            $response = json_decode($apiManager->getCloudsList());
            foreach ($response as $cloud) {
                $isActive = false;
                $token = $oauthManager->getTokenUserCloud($user, $cloud);
                if ($token) {
                    if (strlen($token->getTsecret()) > 0) {
                        $isActive = true;
                        $tokenAux = new stdClass();
                        $tokenAux->key = $token->getTKey();
                        $tokenAux->secret =$token->getTSecret();
                        $_SESSION['access_token_' . $cloud . '_v2'] = $tokenAux;
                    }
                }
                array_push($clouds, array("name" => $cloud, "isActive" => $isActive));
            }
        } catch (Exception $e){
            throw new Exception($e->getMessage());
        }
        return $clouds;
    }


    /**
     * @param $cloud
     * @return array|mixed|string
     * @throws Exception
     */
    private function getOauthUrlCloud($cloud){
        $oauthUrl = "";
        try {
            $apiManager =  new ApiManager();
            $oauthUrl = $apiManager->getOauthUrlCloud($cloud);

        } catch (Exception $e){
            throw new Exception($e->getMessage());
        }
        return $oauthUrl;
    }

    public static function deleteFolderCloud($path)
    {
        if(file_exists($path)) {
            shell_exec('rm -r ' . $path);
        }
    }

    public static function initClouds()
    {
       self::getCloudsList();
    }


    public static function getPathCloud($component, $cloud)
    {
        $path = $component->getPath();
        $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser();
        $userName = $user->getName();
        $len = strlen("home://~" . $userName . "/Cloudspaces/$cloud");
        $pathU1db = substr($path,$len);
        $lenfinal = strrpos($pathU1db, $component->getName());
        $posfinal = $lenfinal > 1 ? $lenfinal-strlen($pathU1db)-1 : $lenfinal-strlen($pathU1db);
        $pathParent = substr($pathU1db, 0, $posfinal);
        $folder = NULL;
        if ($pathParent !== '/') {
            $pos=strrpos($pathParent, '/');
            $folder = substr($pathParent, $pos+1);
            $pathParent = substr($pathParent, 0, $pos+1);
        }

        if($folder !== NULL) {
            $result = $pathParent . $folder . '/';
        } else {
            $result = $pathParent;
        }

        return $result;
    }

    public static function getControlVersionCloud($cloud)
    {
        $apiManager = new ApiManager();
        $controlVersion = $apiManager->getControlVersionCloud($cloud);
        return $controlVersion;
    }

    public static function getStatusComments($params)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;

        if(isset($params[ 'cloud' ]) && isset($_SESSION['access_token_' . $params[ 'cloud' ] . '_v2'])) {
            $cloud = $params['cloud'];
            $apiManager = new ApiManager();
            $controlComments = $apiManager->getControlCommentsCloud($cloud);
            if(isset($controlComments->comments)) {
                $result['status'] = 'OK';
                $result['comments'] = $controlComments->comments;
                unset($result['error']);
                $result['is_shared'] = false;
                if(isset($params['id']) && $params['id'] !== 0) {
                    $id = $params['id'];
                    $token = $_SESSION['access_token_' . $params[ 'cloud' ] . '_v2'];
                    $data = $apiManager->getListUsersShare($cloud, $token, $id);
                    try {
                        $data = json_decode($data);
                        if (!isset($data->error) && is_array($data) && count($data) > 1) {
                            $result['is_shared'] = true;
                        }
                    } catch(Exception $e){}
                }
            }
        }
        return $result;
    }

    public static function getCommentsCloud($params)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;

        if(isset($params['id']) && isset($params['cloud']) && isset($_SESSION['access_token_' . $params[ 'cloud' ] . '_v2'])) {
            $id = $params['id'];
            $cloud = $params['cloud'];
            $resourceUrl = self::getResourceUrl();
            $interop=isset($params['interop'])?"true":null;
            $apiManager = new ApiManager();
            $token = $_SESSION['access_token_' . $params[ 'cloud' ] . '_v2'];
            $data = $apiManager->getComments($cloud,$token,$id,$resourceUrl,null,null,$interop);
            if(!isset($data->error)) {
                $data = self::sortDate($data);
                $result['status'] = 'OK';
                $result['comments'] = $data;
                unset($result['error']);
            }
        }
        return $result;
    }

    public static function insertCommentCloud($params)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;

        if(isset($params['id']) && isset($params['text']) && isset($params['cloud']) && isset($_SESSION['access_token_' . $params[ 'cloud' ] . '_v2'])) {
            $id = $params['id'];
            $text = bin2hex($params['text']);
            $cloud = $params['cloud'];
            $token = $_SESSION['access_token_' . $params[ 'cloud' ] . '_v2'];
            $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getName();
            $resourceUrl = self::getResourceUrl();
            $apiManager = new ApiManager();
            $result = $apiManager->insertComment($cloud,$token,$id,$user,$text,$resourceUrl);
        }
        return $result;
    }

    public static function deleteCommentCloud($params)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;

        if(isset($params['id']) && isset($params['time_created']) && isset($params['cloud']) && isset($_SESSION['access_token_' . $params[ 'cloud' ] . '_v2'])) {
            $id = $params['id'];
            $time_created = $params['time_created'];
            $cloud = $params['cloud'];
            $token = $_SESSION['access_token_' . $params[ 'cloud' ] . '_v2'];
            $user = ProcManager::getInstance()->getCurrentProcess()->getLoginContext()->getEyeosUser()->getName();
            $resourceUrl = self::getResourceUrl();
            $apiManager = new ApiManager();
            $result = $apiManager->deleteComment($cloud,$token,$id,$user,$time_created,$resourceUrl);
        }
        return $result;

    }

    private static function base256ToBase10($string) {
        $result = "0";
        for ($i = strlen($string)-1; $i >= 0; $i--) {
            $result = bcadd($result,
                bcmul(ord($string[$i]), bcpow(256, $i)));
        }
        return $result;
    }

    private static function decodeString($comments) {
        foreach($comments as $comment) {
            $comment->text = self::base10ToBase256($comment->text);
        }
        return $comments;
    }
    private static function base10ToBase256($number) {
        $result = "";
        $n = $number;
        do {
            $remainder = bcmod($n, 256);
            $n = bcdiv($n, 256);
            $result .= chr($remainder);
        } while ($n > 0);

        return $result;
    }

    private static function sortDate($comments)
    {
        function sortFunction( $a, $b ) {
            if(strtotime($a->time_created) == strtotime($b->time_created)) return 0;
            return strtotime($a->time_created) < strtotime($b->time_created)?1:-1;
        }

        usort($comments, "sortFunction");
        return $comments;
    }

    private static function getResourceUrl()
    {
        $resourceUrl = null;

        if(defined('API_SYNC') && strlen(API_SYNC) > 0) {
            $resourceUrl = API_SYNC;
        }

        return $resourceUrl;
    }
}
?>
