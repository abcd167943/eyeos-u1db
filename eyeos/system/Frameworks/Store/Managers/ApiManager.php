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

    public function getMetadata($token,$id,$path,$user)
    {
        $pathMetadata = $this->getPathU1db($path);
        $metadata = $this->apiProvider->getMetadata($token,false,$id,true);
        $this->addPathMetadata($metadata,$pathMetadata);
        $respuesta = json_encode($metadata);
        $files = array();
        if(!isset($metadata->error)) {
            if(array_key_exists('contents', $metadata) && count($metadata->contents) > 0) {
                $files = $metadata->contents;
                if ($id === 'root') {
                    unset($metadata->contents);
                    array_push($files,$metadata);
                }
            }
            //$this->addPathMetadata($files,$pathMetadata);
            $u1dbList = new stdClass();
            $u1dbList->id = $id == 'root'?'null':$id;
            $u1dbList->user_eyeos = $user;
            $u1dbList->path = $pathMetadata;
            $u1dbResult = $this->callProcessU1db('select',$u1dbList);
            if($u1dbResult === '[]') {
                foreach($files as $file) {
                    $insert = true;
                    if($file->id !== 'null') {
                        if ($file->status !== 'DELETED') {
                            $insert = $this->filesProvider->createFile($path . "/" . $file->filename, $file->is_folder);
                        } else {
                            $insert = false;
                        }
                    }
                    if($insert) {
                        $this->callProcessU1db('insert',$this->setUserEyeos($file,$user));
                    }
                }
            } else {
                $dataU1db = json_decode($u1dbResult);
                if ($dataU1db){
                    for($i = 0;$i < count($files);$i++) {
                        $delete = $files[$i]->status === 'DELETED'?true:false;
                        if($this->search($dataU1db,"id",$files[$i]->id) === false){
                            if(!$delete &&  $files[$i]->id !== 'null') {
                                if($this->filesProvider->createFile($path . "/" . $files[$i]->filename,$files[$i]->is_folder)) {
                                    $this->callProcessU1db('insert',$this->setUserEyeos($files[$i],$user));
                                }
                            }
                        } else {
                            if(!$delete) {
                                $filenameDb = $this->getValue($dataU1db,"id",$files[$i]->id,"filename");
                                if ($filenameDb !== $files[$i]->filename){
                                    if($this->filesProvider->renameFile($path . "/" . $filenameDb, $files[$i]->filename)) {
                                        $lista = array();
                                        array_push($lista,json_decode('{"parent_old":"' . $files[$i]->parent_id . '"}'));
                                        array_push($lista,$this->setUserEyeos($files[$i],$user));
                                        $this->callProcessU1db('update',$lista);
                                    }
                                }
                            } else {
                                $this->callProcessU1db('deleteFolder',$this->setUserEyeos($files[$i],$user));
                                $this->filesProvider->deleteFile($path . "/" . $files[$i]->filename, $files[$i]->is_folder);
                            }
                        }
                    }
                    for($i = 0;$i < count($dataU1db);$i++) {
                        if($this->search($files,"id",$dataU1db[$i]->id) === false && $metadata->id !== $dataU1db[$i]->id){
                            if($this->filesProvider->deleteFile($path . "/" . $dataU1db[$i]->filename, $dataU1db[$i]->is_folder)) {
                                 $this->callProcessU1db('deleteFolder',$dataU1db[$i]);
                            }
                        }
                    }
                }
            }
        }
        return $respuesta;
    }

    public function getSkel($token,$file,$id,&$metadatas,$path,$pathAbsolute) {
        $contents = $file == false?true:null;
        $metadata = $this->apiProvider->getMetadata($token,$file,$id,$contents);
        if(!isset($metadata->error)) {
            $metadata->pathAbsolute = $pathAbsolute;
            $metadata->path = $path;
            if($metadata->is_folder) {
                $path = $metadata->id == 'null'?'/':$path . $metadata->filename . '/';
                for ($i=0;$i<count($metadata->contents);$i++){
                    $this->getSkel($token,!$metadata->contents[$i]->is_folder,$metadata->contents[$i]->id,$metadatas,$path,null);
                }
            }
            unset($metadata->contents);
        }
        array_push($metadatas,$metadata);
    }

    public function createMetadata($token,$user,$file,$name,$parent_id,$path,$pathAbsolute=null)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;
        $metadata = $this->apiProvider->getMetadata($token,$file,$parent_id,true);
        if($metadata) {
            if(!isset($metadata->error)) {
                if(isset($metadata->contents)) {
                    $id = null;
                    foreach($metadata->contents as $data) {
                        if($data->filename == $name) {
                            $id = $data->id;
                            break;
                        }
                    }
                    if($id === null) {
                        $newMetadata = $this->apiProvider->createMetadata($token,$file,$name,$parent_id,$pathAbsolute);
                        if(!isset($newMetadata->error)) {
                            $this->addPathMetadata($newMetadata,$path);
                            if($this->callProcessU1db('insert',$this->setUserEyeos($newMetadata,$user)) == 'true') {
                                $result['status'] = 'OK';
                                unset($result['error']);
                            }
                        } else {
                            $result['error'] = $newMetadata->error;
                        }
                    } else {
                        if($file) {
                            $resp = $this->apiProvider->uploadMetadata($token,$id,$pathAbsolute);
                            if(isset($resp->status) && $resp->status == true) {
                                $changedMetadata = $this->apiProvider->getMetadata($token,$file,$id);
                                if(!isset($changedMetadata->error)) {
                                    $this->addPathMetadata($changedMetadata,$path);
                                    $metadataUpdate = array();
                                    $old = new stdClass();
                                    $old->parent_old = $changedMetadata->parent_id;
                                    array_push($metadataUpdate, $old);
                                    array_push($metadataUpdate, $this->setUserEyeos($changedMetadata,$user));
                                    if($this->callProcessU1db('update',$metadataUpdate) == 'true') {
                                        $result['status'] = 'OK';
                                        unset($result['error']);
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

    public function downloadMetadata($token,$id,$path)
    {
        $content = $this->apiProvider->downloadMetadata($token,$id,$path);
        $result['status'] = 'KO';
        $result['error'] = -1;
        if(!isset($content->error)) {
            $result['status'] = 'OK';
            unset($result['error']);
        } else {
            $result['error'] = $content->error;
        }

        return $result;
    }

    public function deleteMetadata($token,$file,$id,$user)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;
        $metadata = $this->apiProvider->deleteMetadata($token,$file,$id);
        if(!isset($metadata->error)) {
            $data = new stdClass();
            $data->id = $id;
            $data->user_eyeos = $user;
            $data->parent_id = $metadata->parent_id;
            $this->callProcessU1db('delete',$data);
        } else {
            $result['error'] = $metadata->error;
        }

        return $result;
    }


    public function renameMetadata($token,$file,$id,$name,$path,$user,$parent=NULL)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;
        $metadata = $this->apiProvider->updateMetadata($token,$file,$id,$name,$parent);
        if (!isset($metadata->error)) {
            $this->addPathMetadata($metadata,$path);
            if($this->callProcessU1db('rename',$this->setUserEyeos($metadata,$user)) == 'true') {
                $result['status'] = 'OK';
                unset($result['error']);
            }
        } else {
            $result['error'] = $metadata->error;
        }
        return $result;
    }

    public function moveMetadata($token,$file,$id,$pathOrig,$pathDest,$user,$parent,$filenameOld,$filenameNew = null)
    {
        $result['status'] = 'KO';
        $result['error'] = -1;
        $metadata = $this->apiProvider->updateMetadata($token,$file,$id,$filenameNew?$filenameNew:$filenameOld,$parent);

        if(!isset($metadata->error)) {
            $delete = new stdClass();
            $delete->id = $id;
            $delete->user_eyeos = $user;
            $delete ->path = $this->getPathU1db($pathOrig);

            if($this->callProcessU1db('deleteFolder',$delete) == 'true') {
                $delete = $this->filesProvider->deleteFile($pathOrig . '/' . $filenameOld, !$file);
                if($delete) {
                    $metadata = $this->setUserEyeos($metadata,$user);
                    $this->addPathMetadata($metadata,$this->getPathU1db($pathDest));
                    if($this->callProcessU1db('insert',$metadata) == 'true') {
                        $this->filesProvider->createFile($pathDest . '/' . $metadata->filename,!$file);
                        $result['status'] = 'OK';
                        unset($result['error']);
                    }
                }
            }
        }

        return $result;

    }

    public function callProcessU1db($type,$lista,$credentials=NULL)
    {
        $json = new stdClass();
        $json->type = $type;
        $json->lista = array();
        if ($type == 'update') {
            $json->lista = $lista;
        } else {
            array_push($json->lista,$lista);
        }
        if ($credentials) {
            $json->credentials = $credentials;
        }
        return $this->accessorProvider->getProcessDataU1db(json_encode($json));
    }

    private function setUserEyeos($metadata,$user)
    {
        $aux = new stdClass();
        $aux->user_eyeos = $user;
        $metadata = (object)array_merge((array)$aux,(array)$metadata);
        return $metadata;
    }

    private function search($array, $key, $value)
    {
        if (is_array($array)) {
            foreach($array as $data) {
                if($data->$key == $value){
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
                if($data->$key == $value){
                    $name = $data->$keyFind;
                    break;
                }
            }
        }
        return $name;
    }

    private function getPathU1db($path)
    {
        preg_match('/home:\/\/~(.*)\/Stacksync/',$path,$match);
        $username = $match[1];
        preg_match("/home:\/\/~$username\/Stacksync(.*)/", $path,$match);
        $pathnew = $match[1] . '/';
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
}


?>