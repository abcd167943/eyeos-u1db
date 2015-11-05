<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28/05/14
 * Time: 16:05
 */

class ApiManager
{
    private $accessorProvider;
    private $apiProvider;
    private $filesProvider;

    public function __construct(AccessorProvider $accessorProvider = NULL, ApiProvider $apiProvider = NULL, FilesProvider $filesProvider = NULL)
    {
        if(!$accessorProvider) $accessorProvider = new AccessorProvider();
        $this->accessorProvider = $accessorProvider;

        if(!$apiProvider) $apiProvider = new ApiProvider();
        $this->apiProvider = $apiProvider;

        if(!$filesProvider) $filesProvider = new FilesProvider();
        $this->filesProvider = $filesProvider;
    }

    public function getMetadata($cloud, $token, $id, $path, $user, $resourceUrl=NULL, $consumerKey = NULL, $consumerSecret = NULL)
    {
        $pathMetadata = $this->getPathU1db($path, $cloud);
        $contents = true;
        $metadata = $this->apiProvider->getMetadata($cloud, $token, false, $id, $contents,$resourceUrl,$consumerKey,$consumerSecret);
        $this->addPathMetadata($metadata, $pathMetadata);
        $this->addShareMetadata($metadata, $cloud, $token,$resourceUrl,$consumerKey,$consumerSecret);
        $this->addUrlMetadata($metadata, $cloud, $token, $resourceUrl, $consumerKey, $consumerSecret);
        $respuesta = json_encode($metadata);
        $files = array();
        if(!isset($metadata->error)) {
            if(array_key_exists('contents', $metadata) && count($metadata->contents) > 0) {
                $files = $metadata->contents;
                if ($id === 'root') {
                    unset($metadata->contents);
                    array_push($files, $metadata);
                }
            }
            $u1dbList = new stdClass();
            $u1dbList->id = $id == 'root'?'null':$id;
            $u1dbList->user_eyeos = $user;
            $u1dbList->cloud = $cloud;
            $u1dbList->path = $pathMetadata;

            $u1dbResult = $this->callProcessU1db('select', $u1dbList);
            if($u1dbResult === '[]') {
                foreach($files as $file) {
                    $insert = true;
                    if(isset($file->resource_url) || (isset($file->id) && $file->id !== 'null')) {
                        if ((isset($file->resource_url) && (!(isset($file->status) && $file->status == 'DELETED'))) || $file->status !== 'DELETED') {
                            $insert = $this->filesProvider->createFile($path . "/" . $this->fixValueFilename($file), $this->fixValueIsFolder($file));
                        } else {
                            $insert = false;
                        }
                    }
                    if($insert) {
                        $this->callProcessU1db('insert', $this->setUserEyeos($file, $user, $cloud));
                    }
                }
            } else {
                $dataU1db = json_decode($u1dbResult);
                if ($dataU1db){
                    for($i = 0; $i < count($files); $i++) {
                        $cloudFolder = isset($files[$i]->resource_url) ? true : false;
                        if (!$cloudFolder || isset($files[$i]->filename)) {
                            $delete = (isset($files[$i]->status) && $files[$i]->status === 'DELETED') ? true : false;
                            if($this->search($dataU1db, "id", $files[$i]->id) === false){
                                if(!$delete &&  $files[$i]->id !== 'null') {
                                    if($this->filesProvider->createFile($path . "/" . $files[$i]->filename, $files[$i]->is_folder)) {
                                        $this->callProcessU1db('insert', $this->setUserEyeos($files[$i], $user, $cloud));
                                    }
                                }
                            } else {
                                if(!$delete) {
                                    $filenameDb = $this->getValue($dataU1db, "id", $files[$i]->id, "filename");
                                    $sharedDb = $this->getValue($dataU1db, "id", $files[$i]->id, "is_shared");
                                    $updateRename = $filenameDb !== $files[$i]->filename;
                                    $updateShare = $sharedDb !== $files[$i]->is_shared;
                                    if ($updateRename || $updateShare){
                                        $lista = array();
                                        if($updateRename) {
                                            if($this->filesProvider->renameFile($path . "/" . $filenameDb, $files[$i]->filename)) {
                                                array_push($lista, json_decode('{"parent_old":"' . $files[$i]->parent_id . '"}'));
                                            }
                                        }
                                        array_push($lista, $this->setUserEyeos($files[$i], $user, $cloud));
                                        $this->callProcessU1db('update', $lista);
                                    }
                                } else {
                                    $this->callProcessU1db('deleteFolder', $this->setUserEyeos($files[$i], $user, $cloud));
                                    $this->filesProvider->deleteFile($path . "/" . $files[$i]->filename, $files[$i]->is_folder);
                                }
                            }
                        } else {
                            $id = $this->fixValueId($files[$i]);
                            $id = isset($files[$i]->id) ? $id : $id . '_' . $cloud;
                            if ($this->search($dataU1db, "id", $id) === false) {
                                if($this->filesProvider->createFile($path . "/" . $files[$i]->name, true)) {
                                    $this->callProcessU1db('insert', $this->setUserEyeos($files[$i], $user, $cloud));
                                }
                            } else {
                                $filenameDb = $this->getValue($dataU1db, "id", $id, "name");
                                if ($filenameDb !==$files[$i]->name) {
                                    if($this->filesProvider->renameFile($path . "/" . $filenameDb, $files[$i]->name)) {
                                        $lista = array();
                                        array_push($lista, $this->setUserEyeos($files[$i], $user, $cloud));
                                        $this->callProcessU1db('update', $lista);
                                    }
                                }
                            }
                        }
                    }
                    for($i = 0; $i < count($dataU1db); $i++) {
                        $id = substr($dataU1db[$i]->id,0,strpos($dataU1db[$i]->id,'_' . $cloud));
                        $delete = false;
                        if(count($files) > 0) {
                            if ($this->search($files, "id", $dataU1db[$i]->id) === false
                                && $metadata->id !== $dataU1db[$i]->id && $metadata->id !== $id
                            ) {
                                $delete = true;
                            }
                        } else {
                            $delete = true;
                        }

                        if($delete === true && $metadata->id !== $dataU1db[$i]->id && $metadata->id !== $id) {
                            if ($this->filesProvider->deleteFile($path . "/" . $this->fixValueFilename($dataU1db[$i]), $this->fixValueIsFolder($dataU1db[$i]))) {
                                $this->callProcessU1db('deleteFolder', $dataU1db[$i]);
                            }
                        }
                    }
                }
            }
        }
        return $respuesta;
    }

    public function getSkel($cloud, $token, $file, $id, &$metadatas, $path, $pathAbsolute, $pathEyeos, $resourceUrl = null, $consumerKey = null, $consumerSecret = null) {
        $contents = $file == false ? true : null;
        $metadata = $this->apiProvider->getMetadata($cloud, $token, $file, $id, $contents, $resourceUrl, $consumerKey, $consumerSecret);
        if(!isset($metadata->error)) {
            $metadata->pathAbsolute = $pathAbsolute;
            $metadata->path = $path;
            $metadata->pathEyeos = $pathEyeos . "/" . $metadata->filename;
            if($metadata->is_folder) {
                $path = $metadata->id == 'null' ? '/' : $path . $metadata->filename . '/';
                for ($i=0; $i<count($metadata->contents); $i++){
                    $this->getSkel($cloud, $token, !$metadata->contents[$i]->is_folder, $metadata->contents[$i]->id, $metadatas, $path, null, $metadata->pathEyeos, $resourceUrl, $consumerKey, $consumerSecret);
                }
            }
            unset($metadata->contents);
        }
        array_push($metadatas, $metadata);
    }

    public function createMetadata($cloud, $token, $user, $file, $name, $parent_id, $path, $pathAbsolute=NULL,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;
        $metadata = $this->apiProvider->getMetadata($cloud, $token, false, $parent_id, true, $resourceUrl, $consumerKey, $consumerSecret);
        if($metadata) {
            if(!isset($metadata->error)) {
                if(isset($metadata->contents)) {
                    $id = null;
                    foreach($metadata->contents as $data) {
                        if(!isset($data->resource_url) && $data->filename == $name) {
                            $id = $data->id;
                            break;
                        }
                    }
                    if($id === null) {
                        $newMetadata = $this->apiProvider->createMetadata($cloud, $token, $file, $name, $parent_id, $pathAbsolute,$resourceUrl, $consumerKey,$consumerSecret);
                        if(!isset($newMetadata->error)) {
                            $this->addPathMetadata($newMetadata, $path);
                            if($this->callProcessU1db('insert', $this->setUserEyeos($newMetadata, $user, $cloud,$resourceUrl,$token,$consumerKey,$consumerSecret)) == 'true') {
                                $ok = true;
                                if($file) {
                                    $lista = new stdClass();
                                    $lista->id = "" . $newMetadata->id;
                                    $lista->cloud = $cloud;
                                    $lista->user_eyeos = $user;
                                    $lista->version = $newMetadata->version;
                                    $lista->recover = false;
                                    $resultU1db = $this->callProcessU1db("insertDownloadVersion", $lista);
                                    if($resultU1db !== 'true') {
                                        $ok = false;
                                    }
                                }
                                if($ok) {
                                    $result['status'] = 'OK';
                                    unset($result['error']);
                                }
                            }
                        } else {
                            $result['error'] = $newMetadata->error;
                        }
                    } else {
                        if($file) {
                            $resp = $this->apiProvider->uploadMetadata($cloud, $token, $id, $pathAbsolute, $resourceUrl, $consumerKey, $consumerSecret);
                            if(isset($resp->status) && $resp->status == true) {
                                $changedMetadata = $this->apiProvider->getMetadata($cloud, $token, $file, $id,null,$resourceUrl,$consumerKey,$consumerSecret);
                                if(!isset($changedMetadata->error)) {
                                    $this->addPathMetadata($changedMetadata, $path);
                                    $metadataUpdate = array();
                                    $old = new stdClass();
                                    $old->parent_old = $changedMetadata->parent_id;
                                    array_push($metadataUpdate, $old);
                                    array_push($metadataUpdate, $this->setUserEyeos($changedMetadata, $user, $cloud,$resourceUrl,$token,$consumerKey,$consumerSecret));
                                    if($this->callProcessU1db('update', $metadataUpdate) == 'true') {
                                        $lista = new stdClass();
                                        $lista->id = "" . $changedMetadata->id;
                                        $lista->user_eyeos = $user;
                                        $lista->cloud = $cloud;
                                        $lista->version = $changedMetadata->version;
                                        $lista->recover = false;
                                        $resultU1db = $this->callProcessU1db("updateDownloadVersion", $lista);
                                        if($resultU1db === 'true') {
                                            $result['status'] = 'OK';
                                            unset($result['error']);
                                        }
                                    }
                                } else {
                                    $result['error'] = $changedMetadata->error;
                                }
                            } else {
                               if(isset($resp->error)) {
                                   $result['error'] = $resp->error;
                               }
                            }
                        }
                    }
                }
            } else {
                $result['error'] = $metadata->error;
            }
        }
        return $result;
    }

    public function downloadMetadata($token, $id, $path, $user, $isTmp=false, $cloud = NULL, $resourceUrl = NULL, $consumerKey = NULL, $consumerSecret = NULL)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;

        $controlVersion = $this->apiProvider->getControlVersionCloud($cloud);
        if($controlVersion->controlVersion === 'true') {
            $metadata = $this->apiProvider->getMetadata($cloud, $token, true, $id, null, $resourceUrl, $consumerKey, $consumerSecret);
            $insert = false;
            $type = '';

            if (!isset($metadata->error) && count($metadata) > 0) {
                $lista = new stdClass();
                $lista->id = "" . $id;
                $lista->user_eyeos = $user;
                $lista->cloud = $cloud;
                $metadataU1db = $this->callProcessU1db('getDownloadVersion', $lista);
                if ($metadataU1db !== "null") {
                    $metadataU1db = json_decode($metadataU1db);
                    if ($metadataU1db) {
                        if ($metadata->version != $metadataU1db->version && $metadataU1db->recover === false) {
                            $insert = true;
                            $type = 'updateDownloadVersion';
                        } else {
                            $result['status'] = 'OK';
                            $result['local'] = true;
                            unset($result['error']);
                        }
                    }
                } else {
                    $insert = true;
                    $type = 'insertDownloadVersion';
                }

                if ($insert) {
                    $content = $this->apiProvider->downloadMetadata($cloud, $token, $id, $path, $resourceUrl, $consumerKey, $consumerSecret);
                    if (!isset($content->error)) {
                        if ($isTmp == false) {
                            $lista = new stdClass();
                            $lista->id = "" . $id;
                            $lista->cloud = $cloud;
                            $lista->user_eyeos = $user;
                            $lista->version = $metadata->version;
                            $lista->recover = false;
                            $resultU1db = $this->callProcessU1db($type, $lista);
                            if ($resultU1db === 'true') {
                                $result['status'] = 'OK';
                                unset($result['error']);
                            }
                        } else {
                            $result['status'] = 'OK';
                            unset($result['error']);
                        }
                    } else {
                        $result['error'] = $content->error;
                    }
                }

            } else {
                $result['error'] = $metadata->error;
            }
        } else {
            $content = $this->apiProvider->downloadMetadata($cloud, $token, $id, $path, $resourceUrl, $consumerKey, $consumerSecret);
            if (!isset($content->error)){
                $result['status'] = 'OK';
                unset($result['error']);
            }
        }

        return $result;
    }

    public function deleteMetadata($cloud,$token,$file,$id,$user,$path,$resourceUrl = NULL,$consumerKey = NULL,$consumerSecret = NULL)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;
        $metadata = $this->apiProvider->deleteMetadata($cloud,$token,$file,$id,$resourceUrl,$consumerKey,$consumerSecret);
        if(!isset($metadata->error)) {
            $lista = new stdClass();
            $lista->id = "" . $id;
            $lista->user_eyeos = $user;
            $lista->cloud = $cloud;
            $resultU1db = $this->callProcessU1db("recursiveDeleteVersion",$lista);
            if($resultU1db === 'true') {
                $data = new stdClass();
                $data->id = "" . $id;
                $data->user_eyeos = $user;
                $data->cloud = $cloud;
                $data->path = $this->getPathU1db($path,$cloud);

                if($this->callProcessU1db('deleteFolder',$data) === 'true') {
                    $result['status'] = 'OK';
                    unset($result['error']);
                }
            }

        } else {
            $result['error'] = $metadata->error;
        }

        return $result;
    }

    public function renameMetadata($cloud, $token, $file, $id, $name, $path, $user, $parent=NULL,$resourceUrl = NULL,$consumerKey = NULL,$consumerSecret = NULL)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;
        $metadata = $this->apiProvider->updateMetadata($cloud, $token, $file, $id, $name, $parent, $resourceUrl, $consumerKey, $consumerSecret);
        if (!isset($metadata->error)) {
            $this->addPathMetadata($metadata, $path);
            if($this->callProcessU1db('rename', $this->setUserEyeos($metadata, $user, $cloud,$resourceUrl,$token,$consumerKey,$consumerSecret)) == 'true') {
                $result['status'] = 'OK';
                unset($result['error']);
            }
        } else {
            $result['error'] = $metadata->error;
        }
        return $result;
    }

    public function moveMetadata($cloud, $token, $file, $id, $pathOrig, $pathDest, $user, $parent, $filenameOld, $filenameNew = null, $resourceUrl = null, $consumerKey = null, $consumerSecret = null)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $metadata = $this->apiProvider->updateMetadata($cloud, $token, $file, $id, $filenameNew ? $filenameNew : $filenameOld, $parent, $resourceUrl, $consumerKey, $consumerSecret);

        if(!isset($metadata->error)) {
            $delete = new stdClass();
            $delete->id = $id;
            $delete->user_eyeos = $user;
            $delete->cloud = $cloud;
            $delete ->path = $this->getPathU1db($pathOrig, $cloud);

            if($this->callProcessU1db('deleteFolder', $delete) == 'true') {
                $delete = $this->filesProvider->deleteFile($pathOrig . '/' . $filenameOld, !$file);
                if($delete) {
                    $metadata = $this->setUserEyeos($metadata, $user, $cloud,$resourceUrl, $token,$consumerKey,$consumerSecret);
                    $this->addPathMetadata($metadata,$this->getPathU1db($pathDest, $cloud));
                    if($this->callProcessU1db('insert', $metadata) == 'true') {
                        $this->filesProvider->createFile($pathDest . '/' . $metadata->filename,!$file);
                        $result[ 'status' ] = 'OK';
                        unset($result[ 'error' ]);
                    }
                }
            }
        }
        return $result;
    }

    public function recursiveDeleteVersion($cloud, $id, $user) {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $lista = new stdClass();
        $lista->id = "" . $id;
        $lista->user_eyeos = $user;
        $lista->cloud = $cloud;
        $resultU1db = $this->callProcessU1db("recursiveDeleteVersion", $lista);
        if($resultU1db === 'true') {
            $result[ 'status' ] = 'OK';
            unset($result[ 'error' ]);
        }
        return $result;
    }

    public function callProcessU1db($type, $lista, $credentials=NULL)
    {
        $json = new stdClass();
        $json->type = $type;
        $json->lista = array();
        if ($type == 'update') {
            $json->lista = $lista;
        } else {
            array_push($json->lista, $lista);
        }
        if ($credentials) {
            $json->credentials = $credentials;
        }
        return $this->accessorProvider->getProcessDataU1db(json_encode($json));
    }

    public function deleteMetadataUser($user, $cloud=NULL)
    {
        $file = array();
        $file['user_eyeos'] = $user;
        if ($cloud) {
            $file['cloud'] = $cloud;
        }
        return json_decode($this->callProcessU1db("deleteMetadataUser",$file));
    }

    public function listVersions($cloud,$token,$id,$user,$resourceUrl=null,$consumerKey=null,$consumerSecret=null)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;
        $metadata = $this->apiProvider->listVersions($cloud,$token,$id,$resourceUrl,$consumerKey,$consumerSecret);
        if(!isset($metadata->error)) {
            $lista = new stdClass();
            $lista->id = "" . $id;
            $lista->user_eyeos = $user;
            $lista->cloud = $cloud;
            $metadataU1db = $this->callProcessU1db('getDownloadVersion',$lista);
            if($metadataU1db !== "null") {
                $metadataU1db = json_decode($metadataU1db);
                if($metadataU1db) {
                    foreach($metadata as $file) {
                        if($file->version == $metadataU1db->version) {
                            $file->enabled = true;
                            break;
                        }
                    }

                    $result = json_encode($metadata);
                }
            } else {
                if(count($metadata) > 0) {
                    $metadata[0]->enabled = true;
                    $result = json_encode($metadata);
                }
            }

        } else {
            $result['error'] = $metadata->error;
        }
        return $result;
    }

    public function getFileVersionData($cloud, $token, $id, $version, $path, $user, $resourceUrl = null, $consumerKey = null, $consumerSecret = null)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;
        $type = null;

        $lista = new stdClass();
        $lista->id = "" . $id;
        $lista->user_eyeos = $user;
        $lista->cloud = $cloud;
        $metadataU1db = $this->callProcessU1db('getDownloadVersion', $lista);
        if($metadataU1db !== "null") {
            $metadataU1db = json_decode($metadataU1db);
            if($metadataU1db) {
                $type = 'updateDownloadVersion';
            }
        } else {
            $type = 'insertDownloadVersion';
        }

        if($type) {
            $metadata = $this->apiProvider->getFileVersionData($cloud, $token, $id, $version, $path, $resourceUrl, $consumerKey, $consumerSecret);
            if(!isset($metadata->error)) {
                $lista = new stdClass();
                $lista->id = "" . $id;
                $lista->cloud = $cloud;
                $lista->user_eyeos = $user;
                $lista->version = $version;
                $lista->recover = true;
                $metadataU1db = $this->callProcessU1db($type, $lista);
                if($metadataU1db == "true") {
                    $result['status'] = 'OK';
                    unset($result['error']);
                }
            } else {
                $result['error'] = $metadata->error;
            }
        }
        return $result;
    }

    public function getListUsersShare($cloud, $token, $id, $resourceUrl = null, $consumerKey = null, $consumerSecret = null)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $metadata = $this->apiProvider->getListUsersShare($cloud, $token, $id, $resourceUrl, $consumerKey, $consumerSecret);
        if (!isset($metadata->error)){
            $result = json_encode($metadata);
        } else {
            $result[ 'error' ] = $metadata->error;
        }
        return $result;
    }

    public function shareFolder($cloud, $token, $id, $list,$shared, $resourceUrl = null, $consumerKey = null, $consumerSecret = null)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $metadata = $this->apiProvider->shareFolder($cloud, $token, $id, $list, $shared,$resourceUrl,$consumerKey,$consumerSecret);
        if (!isset($metadata->error)) {
            $result[ 'status' ] = 'OK';
            unset($result[ 'error' ]);
        } else {
            $result[ 'error' ] = $metadata->error;
        }
        return $result;
    }

    public function getCloudsList()
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $metadata = $this->apiProvider->getCloudsList();
        if (!isset($metadata->error)) {
            $result = json_encode($metadata);
        } else {
            $result[ 'error' ] = $metadata->error;
        }
        return $result;
    }

    public function getOauthUrlCloud($cloud)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $metadata = $this->apiProvider->getOauthUrlCloud($cloud);
        if (!isset($metadata->error)) {
            $result = $metadata;
        } else {
            $result[ 'error' ] = $metadata->error;
        }
        return $result;
    }

    public function getControlVersionCloud($cloud)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $metadata = $this->apiProvider->getControlVersionCloud($cloud);
        if (!isset($metadata->error)) {
            $result = $metadata;
        } else {
            $result[ 'error' ] = $metadata->error;
        }
        return $result;
    }

    public function unLockedFile($cloud,$token,$id,$user,$ipserver,$timeLimit,$dt_now,$resourceUrl = null,$consumerKey = null,$consumerSecret = null,$interop = null)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;
        $free = false;
        $metadata = $this->apiProvider->getMetadataFile($cloud,$token,$id,$resourceUrl,$consumerKey,$consumerSecret,$interop);

        if(is_array($metadata)) {
            if (count($metadata) == 0) {
                $free = true;
            } else {
                $file = $metadata[0];
                if ($file->status == 'close') {
                    $free = true;
                } else {
                    if ($file->user == $user && $file->ipserver == $ipserver) {
                        $free = true;
                    } else {
                        $dt_plus_timeLimit = DateTime::createFromFormat('Y-m-d H:i:s', $file->datetime);
                        $dt_plus_timeLimit->add(new DateInterval('PT' . $timeLimit . 'M'));
                        $dt_now = strtotime($dt_now->format('Y-m-d H:i:s'));
                        $dt_plus_timeLimit = strtotime($dt_plus_timeLimit->format('Y-m-d H:i:s'));
                        if ($dt_now > $dt_plus_timeLimit) {
                            $free = true;
                        } else {
                            $result['error'] = 'BLOCK';
                        }
                    }
                }
            }
        }

        if($free) {
            $result['status'] = 'OK';
            unset($result['error']);
        }

        return $result;
    }

    public function lockFile($cloud,$token,$id,$user,$ipserver,$timeLimit,$dt_now,$resourceUrl = null, $consumerKey = null, $consumerSecret = null, $interop = null)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $lock = $this->apiProvider->lockFile($cloud,$token,$id,$user,$ipserver,$dt_now,$timeLimit,$resourceUrl,$consumerKey,$consumerSecret,$interop);
        if($lock) {
            if(isset($lock->lockFile)) {
                $result['status'] = 'OK';
                unset($result['error']);
            } else {
                $result['error'] = "BLOCK";
            }
        }

        return $result;
    }

    public function updateDateTime($cloud,$token,$id,$user,$ipserver,$dt_now,$resourceUrl = null,$consumerKey = null,$consumerSecret=null)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $update = $this->apiProvider->updateDateTime($cloud,$token,$id,$user,$ipserver,$dt_now,$resourceUrl,$consumerKey,$consumerSecret);
        if($update) {
            if(isset($update->updateFile)) {
                $result['status'] = 'OK';
                unset($result['error']);
            } else {
                $result['error'] = "BLOCK";
            }
        }

        return $result;
    }

    public function unLockFile($cloud,$token,$id,$user,$ipserver,$dt_now,$resourceUrl = null,$consumerKey = null,$consumerSecret = null)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $unLock = $this->apiProvider->unLockFile($cloud,$token,$id,$user,$ipserver,$dt_now,$resourceUrl,$consumerKey,$consumerSecret);
        if($unLock) {
            if(isset($unLock->unLockFile)) {
                $result['status'] = 'OK';
                unset($result['error']);
            } else {
                $result['error'] = "BLOCK";
            }
        }
        return $result;
    }

    public function getMetadataFolder($cloud, $token, $id, $resourceUrl=NULL, $consumerKey = NULL, $consumerSecret = NULL)
    {
        return $this->apiProvider->getMetadata($cloud, $token, false, $id, true, $resourceUrl, $consumerKey, $consumerSecret);
    }

    public function insertComment($cloud,$token,$id,$user,$text,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        $metadata = $this->apiProvider->insertComment($cloud,$token,$id,$user,$text,$resourceUrl,$consumerKey,$consumerSecret);
        return $this->createResponse($metadata);
    }

    public function deleteComment($cloud,$token,$id,$user,$timeCreated,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        $metadata = $this->apiProvider->deleteComment($cloud,$token,$id,$user,$timeCreated,$resourceUrl,$consumerKey,$consumerSecret);
        return $this->createResponse($metadata);
    }

    public function getComments($cloud,$token,$id,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL,$interop=NULL)
    {
        return $this->apiProvider->getComments($cloud,$token,$id,$resourceUrl,$consumerKey,$consumerSecret,$interop);
    }

    public function getControlCommentsCloud($cloud)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $metadata = $this->apiProvider->getControlCommentsCloud($cloud);
        if($metadata) {
            if (!isset($metadata->error)) {
                $result = $metadata;
            } else {
                $result['error'] = $metadata->error;
            }
        }
        return $result;
    }

    public function insertEvent($cloud,$token,$user,$calendar,$isallday,$timestart,$timeend,$repetition,$finaltype,$finalvalue,$subject,$location,$description,$repeattype,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        $metadata = $this->apiProvider->insertEvent($cloud,$token,$user,$calendar,$isallday,$timestart,$timeend,$repetition,$finaltype,
                    $finalvalue,$subject,$location,$description,$repeattype,$resourceUrl,$consumerKey,$consumerSecret);

        return $this->createResponse($metadata);

    }

    public function deleteEvent($cloud,$token,$user,$calendar,$timestart,$timeend,$isallday,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        $metadata = $this->apiProvider->deleteEvent($cloud,$token,$user,$calendar,$timestart,$timeend,$isallday,$resourceUrl,$consumerKey,$consumerSecret);
        return $this->createResponse($metadata);
    }

    public function updateEvent($cloud,$token,$user,$calendar,$isallday,$timestart,$timeend,$repetition,$finaltype,$finalvalue,$subject,$location,$description,$repeattype,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        $metadata = $this->apiProvider->updateEvent($cloud,$token,$user,$calendar,$isallday,$timestart,$timeend,$repetition,$finaltype,$finalvalue,$subject,$location,$description,$repeattype,$resourceUrl,$consumerKey,$consumerSecret);
        return $this->createResponse($metadata);

    }

    public function getEvents($cloud,$token,$user,$calendar,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        return $this->apiProvider->getEvents($cloud,$token,$user,$calendar,$resourceUrl,$consumerKey,$consumerSecret);
    }


    public function insertCalendar($cloud,$token,$user,$name,$description,$timezone,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        $metadata = $this->apiProvider->insertCalendar($cloud,$token,$user,$name,$description,$timezone,$resourceUrl,$consumerKey,$consumerSecret);
        return $this->createResponse($metadata);
    }

    public function deleteCalendar($cloud,$token,$user,$name,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        $metadata = $this->apiProvider->deleteCalendar($cloud,$token,$user,$name,$resourceUrl,$consumerKey,$consumerSecret);
        return $this->createResponse($metadata);
    }

    public function updateCalendar($cloud,$token,$user,$name,$description,$timezone,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        $metadata = $this->apiProvider->updateCalendar($cloud,$token,$user,$name,$description,$timezone,$resourceUrl,$consumerKey,$consumerSecret);
        return $this->createResponse($metadata);
    }

    public function getCalendars($cloud,$token,$user,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        return $this->apiProvider->getCalendars($cloud,$token,$user,$resourceUrl,$consumerKey,$consumerSecret);
    }

    public function getCalendarsAndEvents($cloud,$token,$user,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        return $this->apiProvider->getCalendarsAndEvents($cloud,$token,$user,$resourceUrl,$consumerKey,$consumerSecret);
    }

    public function deleteCalendarsUser($cloud,$token,$user,$resourceUrl=NULL,$consumerKey=NULL,$consumerSecret=NULL)
    {
        $metadata = $this->apiProvider->deleteCalendarsUser($cloud,$token,$user,$resourceUrl,$consumerKey,$consumerSecret);
        return $this->createResponse($metadata);
    }

    public function getControlCalendarCloud($cloud)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $metadata = $this->apiProvider->getControlCalendarCloud($cloud);
        if($metadata) {
            if (!isset($metadata->error)) {
                $result = $metadata;
            } else {
                $result['error'] = $metadata->error;
            }
        }
        return $result;
    }

    private function setUserEyeos($metadata, $user, $cloud = NULL, $resourceUrl = NULL, $token = NULL, $consumerKey = NULL, $consumerSecret = null)
    {
        $aux = new stdClass();
        if($cloud) {
            $aux->cloud = $cloud;
        }

        $aux->user_eyeos = $user;

        if($resourceUrl) {
            $aux->resource_url = $resourceUrl;
            $aux->access_token_key = $token->key;
            $aux->access_token_secret = $token->secret;
            if($consumerKey && $consumerSecret) {
                $aux->consumer_key = $consumerKey;
                $aux->consumer_secret = $consumerSecret;
            }
        }

        $metadata = (object)array_merge((array)$aux, (array)$metadata);
        return $metadata;
    }

    private function search($array, $key, $value)
    {
        if (is_array($array)) {
            foreach($array as $data) {
                if(isset($data->$key) && $data->$key == $value){
                    return true;
                    break;
                }
            }
        }
        return false;
    }

    private function getValue($array, $key, $value, $keyFind)
    {
        $name = '';
        if (is_array($array)) {
            foreach($array as $data) {
                if(isset($data->$key) && $data->$key == $value){
                    $name = property_exists($data, $keyFind) ? $data->$keyFind : '';
                    break;
                }
            }
        }
        return $name;
    }

    private function getPathU1db($path,$cloud = NULL)
    {
        if($cloud) {
            preg_match('/home:\/\/~(.*)\/Cloudspaces\/' . $cloud . '/', $path, $match);
            $username = $match[1];
            preg_match("/home:\/\/~$username\/Cloudspaces\/$cloud(.*)/", $path, $match);
            $pathnew = $match[1] . '/';

        } else {
            preg_match('/home:\/\/~(.*)\/Stacksync/', $path, $match);
            $username = $match[1];
            preg_match("/home:\/\/~$username\/Stacksync(.*)/", $path, $match);
            $pathnew = $match[1] . '/';
        }

        return $pathnew;
    }

    private function addPathMetadata(&$metadata,$path)
    {
        if (!isset($metadata->error)) {
            $metadata->path = $metadata->id == 'null'?'null':$path;
            if(isset($metadata->contents)) {
                foreach($metadata->contents as $dato) {
                    $dato->path = $path;
                }
            }
        }
    }

    private function addShareMetadata(&$metadata, $cloud, $token,$resourceUrl = null,$consumerKey = null,$consumerSecret = null)
    {
        if (!isset($metadata->error)) {
            $metadata->is_shared = $this->validateFolderShared($cloud, $token, $this->fixValueId($metadata), $this->fixValueIsFolder($metadata), $this->fixValueStatus($metadata),$resourceUrl,$consumerKey,$consumerSecret);
            if(isset($metadata->contents)) {
                foreach($metadata->contents as $data) {
                    $data->is_shared = $this->validateFolderShared($cloud, $token, $this->fixValueId($data), $this->fixValueIsFolder($data), $this->fixValueStatus($data),$resourceUrl,$consumerKey,$consumerSecret);
                }
            }
        }
    }

    private function addUrlMetadata(&$metadata, $cloud, $token, $url, $consumerKey = null, $consumerSecret = null)
    {
        if ($url && !isset($metadata->error)) {
            $metadata->resource_url = $url;
            $metadata->access_token_key = $token->key;
            $metadata->access_token_secret = $token->secret;

            if($consumerKey && $consumerSecret) {
                $metadata->consumer_key = $consumerKey;
                $metadata->consumer_secret = $consumerSecret;
            }

            if(isset($metadata->contents)) {
                foreach($metadata->contents as $data) {
                    $data->resource_url = $url;
                    $data->access_token_key = $token->key;
                    $data->access_token_secret = $token->secret;
                    if($consumerKey && $consumerSecret) {
                        $data->consumer_key = $consumerKey;
                        $data->consumer_secret = $consumerSecret;
                    }
                }
            }
        } else {
            $parent_id = isset($metadata->parent_id) ? $metadata->parent_id : 'null';
            $this->fixValueAllIds($metadata, $cloud, $parent_id);
            $parent_id = isset($metadata->id) ? $metadata->id : 'null';
            if (isset($metadata->contents)) {
                foreach ($metadata->contents as $data) {
                    $this->fixValueAllIds($data, $cloud, $parent_id);
                }
            }
        }
    }

    private function fixValueAllIds(&$metadata, $cloud, $parent_id)
    {
        if (isset($metadata->resource_url)) {
            if (!isset($metadata->id)) {
                $metadata->id = $this->fixValueId($metadata) . '_' . $cloud;
                $metadata->resource_url = $this->getResourceUrl($metadata->resource_url);
            }
            $metadata->parent_id = $parent_id;
        }
    }

    private function getResourceUrlId($url)
    {
        preg_match('/\/folder\/(.*)/', $url, $match);
        if (count($match) == 2) {
            return $match[1];

        }
        return 'null';
    }

    private function getResourceUrl($url)
    {
        $resource_url = substr($url,0,strpos($url,"/folder")) . '/';
        return $resource_url;
    }

    private function fixValueId($metadata)
    {
        $idCloud = isset($metadata->resource_url) ? $this->getResourceUrlId($metadata->resource_url) : 'null';
        return isset($metadata->id) ? $metadata->id : $idCloud;
    }

    private function fixValueIsFolder($metadata)
    {
        $isFolder = isset($metadata->resource_url) ? true : false;
        return isset($metadata->is_folder) ? $metadata->is_folder : $isFolder;
    }

    private function fixValueStatus($metadata)
    {
        return isset($metadata->status) ? $metadata->status : 'DELETED';
    }

    private function fixValueFilename($metadata)
    {
        $filename = isset($metadata->name) ? $metadata->name : 'null';
        return isset($metadata->filename) ? $metadata->filename : $filename;
    }

    private function validateFolderShared($cloud, $token, $id, $isFolder, $status, $resourceUrl = null, $consumerKey = null, $consumerSecret = null)
    {
        $result = false;
        if ($isFolder && $id !== 'null' && $status !== 'DELETED') {
            $data = $this->getListUsersShare($cloud, $token, $id, $resourceUrl, $consumerKey, $consumerSecret);
            try {
                $data = json_decode($data);
                if (!isset($data->error) && is_array($data) && count($data) > 1) {
                    $result = true;
                }
            } catch(Exception $e){}
        }
        return $result;
    }

    private function exerciseLockFile($id,$cloud,$user,$IpServer,$timeLimit,$dt_now,$type)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;
        $lista = new stdClass();
        $lista->id = "" . $id;
        $lista->cloud = $cloud;
        $lista->username = $user;
        $lista->IpServer = $IpServer;
        $lista->datetime = $dt_now->format("Y-m-d H:i:s");
        if($type == 'unLockFile') {
            $lista->status = 'close';
        } else {
            $lista->status = 'open';
        }
        if($type == 'lockFile') {
            $lista->timeLimit = $timeLimit;
        }
        $metadataU1db = $this->callProcessU1db($type, $lista);
        if($metadataU1db === 'true') {
            $result['status'] = 'OK';
            unset($result['error']);
        } else {
            $result['error'] = "BLOCK";
        }
        return $result;
    }

    private function createResponse($metadata)
    {
        $result[ 'status' ] = 'KO';
        $result[ 'error' ] = -1;

        if($metadata) {
            if (!isset($metadata->error)) {
                $result['status'] = 'OK';
                unset($result['error']);
            } else {
                $result['error'] = $metadata->error;
            }
        }
        return $result;
    }
}


?>