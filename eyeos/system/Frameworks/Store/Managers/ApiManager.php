<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 5/03/14
 * Time: 9:55
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

    public function getProcessDataU1db($json)
    {
        return $this->accessorProvider->getProcessDataU1db($json);
    }

    public function getMetadata($path,$fileId = NULL)
    {
        $metadata = $this->apiProvider->getMetadata($this->getUrl(),$this->getToken(),$fileId);
        $respuesta = '';
        if($metadata) {
            $respuesta = json_encode($metadata);
            $files = array();
            if(isset($metadata->contents) && count($metadata->contents) > 0) {
                $files = $metadata->contents;
                if ($fileId === NULL) {
                    unset($metadata->contents);
                    array_push($files,$metadata);
                }
            }
            $file = array();
            $file['file_id'] = $fileId===NULL?'null':$fileId;
            $query = $this->callProcessU1db('select',$file);
            if($query == '[]') {
                foreach($files as $file) {
                    $insert = true;
                    if($file->file_id !== 'null') {
                        $insert = $this->filesProvider->createFile($path . "/" . $file->filename,$file->is_folder);
                    }
                    if($insert) {
                        $this->callProcessU1db('insert',$file);
                    }
                }
            } else {
                $dataU1db = json_decode($query);
                if ($dataU1db){
                    for($i = 0;$i < count($files);$i++) {
                        if($this->search($dataU1db,"file_id",$files[$i]->file_id) === false){
                            if($this->filesProvider->createFile($path . "/" . $files[$i]->filename,$files[$i]->is_folder)) {
                                $this->callProcessU1db('insert',$files[$i]);
                            }
                        } else {
                            $filenameDb = $this->getFilename($dataU1db,"file_id",$files[$i]->file_id,"filename");
                            if ($filenameDb !== $files[$i]->filename){
                                if($this->filesProvider->renameFile($path . "/" . $filenameDb, $files[$i]->filename)) {
                                    $this->callProcessU1db('update',$files[$i]);
                                }
                            }
                        }
                    }
                    for($i = 0;$i < count($dataU1db);$i++) {
                        if($this->search($files,"file_id",$dataU1db[$i]->file_id) === false && $metadata->file_id !== $dataU1db[$i]->file_id){
                            if($this->filesProvider->deleteFile($path . "/" . $dataU1db[$i]->filename, $dataU1db[$i]->is_folder)) {
                                $this->callProcessU1db('delete',$dataU1db[$i]);
                            }
                        }
                    }
                }
            }
        }
        return $respuesta;
    }

    public function createFile($filename,$file,$filesize,$pathParent,$folderParent = NULL)
    {
        $respuesta = '';
        $parentId = -1;
        if($folderParent !== NULL) {
            $lista = array();
            $lista['path'] = $pathParent;
            $lista['folder'] = $folderParent;
            $u1db = json_decode($this->callProcessU1db('parent',$lista));
            if($u1db !== NULL) {
                $parentId = $u1db[0]->file_id === "null"?NULL:$u1db[0]->file_id;
            }
        } else {
            $parentId = NULL;
        }
        if($parentId !== -1) {
            $metadata = $this->apiProvider->createFile($this->getUrl(),$this->getToken(),$filename,$file,$filesize,$parentId);

            if(array_key_exists("file_id",$metadata)) {
                $file = array();
                $file['file_id'] = $metadata->file_id;
                $query = $this->callProcessU1db('select',$file);

                if($query == '[]') {
                    $this->callProcessU1db('insert',$metadata);
                } else {
                    $dataU1db = json_decode($query);
                    if($dataU1db) {
                        $this->callProcessU1db("update",$metadata);
                    }
                }
                $respuesta = json_encode($metadata);
            }

        }

        return $respuesta;
    }

    public function createFolder($foldername,$idParent = NULL)
    {
        $metadata = $this->apiProvider->createFolder($this->getUrl(),$this->getToken(),$foldername,$idParent);
        $this->callProcessU1db('insert',$metadata);
        return json_encode($metadata);
    }

    public function deleteComponent($idComponent,$folder = false)
    {
        $result = false;
        if($this->apiProvider->deleteComponent($this->getUrl(),$this->getToken(),$idComponent)) {
            $file = array();
            $file['file_id'] = $idComponent;
            $type = $folder?'deleteFolder':'delete';
            $result = $this->callProcessU1db($type,$file) === 'true'?true:false;
        }
        return $result;
    }

    public function renameFile($idFile,$fileName,$file,$filesize,$idParent=NULL)
    {
        $result = '';
        if($this->deleteComponent($idFile)) {
            $metadata = $this->apiProvider->createFile($this->getUrl(),$this->getToken(),$fileName,$file,$filesize,$idParent);
            $this->callProcessU1db('insert',$metadata);
            $result = json_encode($metadata);
        }
        return $result;
    }

    public function downloadFile($idFile)
    {
        return $this->apiProvider->downloadFile($this->getUrl(),$this->getToken(),$idFile);
    }

    public function renameFolder($idFolder,$folderName,$idParent = NULL)
    {
        $result = '';
        if($this->deleteComponent($idFolder,true)) {
            $metadata = $this->apiProvider->createFolder($this->getUrl(),$this->getToken(),$folderName,$idParent);
            $this->callProcessU1db('insert',$metadata);
            $result = json_encode($metadata);
        }
        return $result;
    }

    public function search($array, $key, $value)
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

    public function getFilename($array, $key, $value, $keyFind)
    {
        $filename = '';
        if (is_array($array)) {
            foreach($array as $data) {
                if($data->$key == $value){
                    $filename = $data->$keyFind;
                    break;
                }
            }
        }
        return $filename;
    }

    public function callProcessU1db($type,$lista)
    {
        $json['type'] = $type;
        $json['lista'] = array();
        array_push($json['lista'],$lista);
        return $this->accessorProvider->getProcessDataU1db(json_encode($json));
    }

    public function  getDecryption($data)
    {
        $codeManager = new CodeManager();
        return $codeManager->getDecryption($data);
    }

    public function getUrl()
    {
        return $this->getDecryption($_SESSION['url']);
    }

    public function getToken()
    {
        return $this->getDecryption($_SESSION['token']);
    }
}

?>