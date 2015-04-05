<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28/05/14
 * Time: 10:43
 */

class ApiProvider
{
    private $accessorProvider;

    public function __construct(AccessorProvider $accessorProvider = NULL)
    {
        if(!$accessorProvider) $accessorProvider = new AccessorProvider();
        $this->accessorProvider = $accessorProvider;
    }

    public function getMetadata($cloud,$token, $file, $id, $contents = null)
    {
        $request = $this->getRequest('get',$token,$cloud);
        $request->metadata->file = $file;
        $request->metadata->id = "" . $id;
        $request->metadata->contents = $contents;
        return $this->exerciseMetadata($request);
    }

    public function updateMetadata($token, $file, $id, $name = null, $parent = null)
    {
        $request = $this->getRequest('update',$token);
        $request->metadata->file = $file;
        $request->metadata->id = "" . $id;
        $request->metadata->filename = $name;
        $request->metadata->parent_id = $parent === null?'null':"" . $parent;
        return $this->exerciseMetadata($request);
    }

    public function createMetadata($cloud,$token, $file, $name, $parent = null, $path = null)
    {
        $request = $this->getRequest('create', $token,$cloud);
        $request->metadata->file = $file;
        $request->metadata->filename = $name;
        $request->metadata->parent_id = $parent === null?'null':"" . $parent;
        $request->metadata->path = $path;
        return $this->exerciseMetadata($request);
    }

    public function uploadMetadata($token, $id, $path)
    {
        $request = $this->getRequest('upload', $token);
        $request->metadata->id = "" . $id;
        $request->metadata->path = $path;
        return $this->exerciseMetadata($request);
    }

    public function downloadMetadata($token, $id, $path)
    {
        $resp = json_decode('{"error":-1}');
        $request = $this->getRequest('download', $token);
        $request->metadata->id = "" . $id;
        $request->metadata->path = $path;
        $result = $this->accessorProvider->getProcessOauthCredentials(json_encode($request));

        if($result) {
            if(!($result === 'false' || $result === '403')) {
                $resp = $result;
            }  else if($result === '403'){
                $resp = json_decode('{"error":403}');
            }
        }

        return $resp;
    }

    public function deleteMetadata($token, $file, $id)
    {
        $request = $this->getRequest('delete', $token);
        $request->metadata->file = $file;
        $request->metadata->id = "" . $id;
        return $this->exerciseMetadata($request);
    }

    public function listVersions($token,$id)
    {
        $request = $this->getRequest('listVersions', $token);
        $request->metadata->id = "" . $id;
        return $this->exerciseMetadata($request,true);
    }

    public function getFileVersionData($token, $id, $version, $path)
    {
        $request = $this->getRequest("getFileVersion", $token);
        $request->metadata->id = "" . $id;
        $request->metadata->version = "" . $version;
        $request->metadata->path = $path;
        return $this->exerciseMetadata($request);
    }

    public function getListUsersShare($token,$id)
    {
        $request = $this->getRequest('listUsersShare', $token);
        $request->metadata->id = "" . $id;
        return $this->exerciseMetadata($request);
    }

    public function shareFolder($token,$id,$list)
    {
        $request = $this->getRequest('shareFolder', $token);
        $request->metadata->id = "" . $id;
        $request->metadata->list = $list;
        return $this->exerciseMetadata($request);
    }

    public function getCloudsList()
    {
        $request = $this->getRequest('cloudsList');
        return $this->exerciseMetadata($request);
    }

    public function getOauthUrlCloud($cloud)
    {
        $request = $this->getRequest('oauthUrl', null, $cloud);
        return $this->exerciseMetadata($request);
    }

    private function getRequest($type, $token = NULL, $cloud = NULL)
    {
        $request = new stdClass();
        $request->config = new stdClass();

        if ($token) {
            $request->token = new stdClass();
            $request->token->key = $token->key;
            $request->token->secret = $token->secret;
            $request->metadata = new stdClass();
            $request->metadata->type = $type;
        } else {
            $request->config->type = $type;
        }
        if($cloud) {
            $request->config->cloud = $cloud;
        }

        return $request;
    }

    private function exerciseMetadata($request, $versions = false)
    {
        $resp = json_decode('{"error":-1}');
        $result = $this->accessorProvider->getProcessOauthCredentials(json_encode($request));
        if($result) {
            if($result === 'true') {
                $resp = json_decode('{"status":true}');
            } else if($result !== 'false' && $result !== '403') {
                $resp = json_decode($result);
                if($versions === true) {
                    $resp = $resp->versions;
                }
            } else if($result === '403'){
                $resp = json_decode('{"error":403}');
            }
        }

        return $resp;
    }
}

?>