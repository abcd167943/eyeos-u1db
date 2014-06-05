<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28/05/14
 * Time: 10:20
 */

class ApiProviderTest extends PHPUnit_Framework_TestCase
{
    private $accessorProviderMock;
    private $sut;
    private $token;
    private $exception;
    private $permission;

    public function setUp()
    {
        $this->accessorProviderMock = $this->getMock('AccessorProvider');
        $this->sut = new ApiProvider($this->accessorProviderMock);
        $this->token = new stdClass();
        $this->token->key = "ABCD";
        $this->token->secret = "EFGH";
        $this->exception = '{"error":-1}';
        $this->permission = '{"error":403}';
    }

    public function tearDown()
    {
        $this->accessorProviderMock = null;
        $this->token = null;
    }

    /**
     * method: getMetadata
     * when: called
     * with: tokenAndFileAndId
     * should: returnCorrectData
     */
    public function test_getMetadata_called_tokenAndfileAndId_returnCorrectData()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"get","file":true,"id":123456,"contents":null}}';
        $metadataOut = '{"name":"Client1.pdf","path":"/documents/clients/Client1.pdf","id":32565632156,"size":775412,"mimetype":"application/pdf","status":"DELETED","version":3,"parent":-348534824681,"user":"eyeos","client_modified":"2013-03-08 10:36:41.997","server_modified":"2013-03-08 10:36:41.997"}';
        $this->exerciseGetMetadata($metadataIn,$metadataOut,$metadataOut,true,123456);
    }

    /**
     * method: getMetadata
     * when: called
     * with: tokenAndFolderAndId
     * should: returnCorrectData
     */
    public function test_getMetadata_called_tokenAndFolderAndId_returnCorrectData()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"get","file":false,"id":9873615,"contents":null}}';
        $metadataOut = '{"name":"clients","path":"/documents/clients","id":9873615,"status":"NEW","version":1,"parent":-348534824681,"user":"eyeos","client_modified":"2013-03-08 10:36:41.997","server_modified":"2013-03-08 10:36:41.997","is_root":false}';
        $this->exerciseGetMetadata($metadataIn,$metadataOut,$metadataOut,false,9873615);
    }

    /**
     * method: getMetadata
     * when: called
     * with: tokenAndFolderAndIdAndContents
     * should: returnCorrectData
     */
    public function test_getMetadata_called_tokenAndFolderAndIdAndContents_returnCorrectData()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"get","file":false,"id":9873615,"contents":true}}';
        $metadataOut = '{"name":"clients","path":"/documents/clients","id":9873615,"status":"NEW","version":1,"parent":-348534824681,"user":"eyeos","client_modified":"2013-03-08 10:36:41.997","server_modified":"2013-03-08 10:36:41.997","is_root":false,"contents":[{"name":"Client1.pdf","path":"/documents/clients/Client1.pdf","id":32565632156,"size":775412,"mimetype":"application/pdf","status":"DELETED","version":3,"parent":-348534824681,"user":"eyeos","client_modified":"2013-03-08 10:36:41.997","server_modified":"2013-03-08 10:36:41.997","is_root":false}]}';
        $this->exerciseGetMetadata($metadataIn,$metadataOut,$metadataOut,false,9873615,true);

    }

    /**
     * method: getMetadata
     * when: called
     * with: tokenAndFolderAndId
     * should: returnException
     */
    public function test_getMetadata_called_tokenAndFolderAndId_returnException()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"get","file":false,"id":9873615,"contents":null}}';
        $metadataOut = 'false';
        $this->exerciseGetMetadata($metadataIn,$metadataOut,$this->exception,false,9873615);
    }

    /**
     * method: getMetadata
     * when: called
     * with: tokenAndFolderAndId
     * should: returnPermissionDenied
     */
    public function test_getMetadata_called_tokenAndFolderAndId_returnPermissionDenied()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"get","file":false,"id":9873615,"contents":null}}';
        $metadataOut = '403';
        $this->exerciseGetMetadata($metadataIn,$metadataOut,$this->permission,false,9873615);
    }

    /**
     * method: updateMetadata
     * when: called
     * with: tokenAndFileAndIdAndNameAndParent
     * should: returnMetadataRename
     */
    public function test_updateMetadata_called_tokenAndFileAndIdAndNameAndParent_returnMetadataRename()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"update","file":true,"id":32565632156,"name":"Winter2012_renamed.jpg","parent":12386548974}}';
        $metadataOut = '{"name":"Winter2012_renamed.jpg","path":"/documents/clients/Client1.pdf","id":32565632156,"size":775412,"mimetype":"application/pdf","status":"CHANGED","version":2,"parent":12386548974,"user":"eyeos","client_modified":"2013-03-08 10:36:41.997","server_modified":"2013-03-08 10:36:41.997"}';
        $this->exerciseUpdateMetadata($metadataIn,$metadataOut,$metadataOut,true,32565632156,"Winter2012_renamed.jpg",12386548974);
    }

    /**
     * method: updateMetadata
     * when: called
     * with: tokenAndFileAndIdAndParent
     * should: returnMetadataMove
     */
    public function test_updateMetadata_called_tokenAndFileAndIdAndParent_returnMetadataMove()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"update","file":true,"id":32565632156,"name":null,"parent":123456}}';
        $metadataOut = '{"name":"Winter2012_renamed.jpg","path":"/documents/clients/Client1.pdf","id":32565632156,"size":775412,"mimetype":"application/pdf","status":"CHANGED","version":2,"parent":123456,"user":"eyeos","client_modified":"2013-03-08 10:36:41.997","server_modified":"2013-03-08 10:36:41.997"}';
        $this->exerciseUpdateMetadata($metadataIn,$metadataOut,$metadataOut,true,32565632156,null,123456);
    }

    /**
     * method: updateMetadata
     * when: called
     * with: tokenAndFileAndId
     * should: returnMetadataMove
     */
    public function test_updateMetadata_called_tokenAndFileAndId_returnMetadataMove()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"update","file":true,"id":32565632156,"name":null,"parent":null}}';
        $metadataOut = '{"name":"Winter2012_renamed.jpg","path":"/documents/clients/Client1.pdf","id":32565632156,"size":775412,"mimetype":"application/pdf","status":"CHANGED","version":2,"parent":null,"user":"eyeos","client_modified":"2013-03-08 10:36:41.997","server_modified":"2013-03-08 10:36:41.997"}';
        $this->exerciseUpdateMetadata($metadataIn,$metadataOut,$metadataOut,true,32565632156);
    }

    /**
     * method: updateMetadata
     * when: called
     * with: tokenAndFolderAndIdAndNameAndParent
     * should: returnMetadataRename
     */
    public function test_updateMeta_called_tokenAndFolderAndIdAndNameAndParent_returnMetadataRename()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"update","file":false,"id":32565632156,"name":"Winter2012_renamed","parent":12386548974}}';
        $metadataOut = '{"name":"Winter2012_renamed","path":"/documents/clients/Winter2012_renamed","id":32565632156,"status":"CHANGED","version":2,"parent":12386548974,"user":"eyeos","client_modified":"2013-03-08 10:36:41.997","server_modified":"2013-03-08 10:36:41.997","is_root":false}';
        $this->exerciseUpdateMetadata($metadataIn,$metadataOut,$metadataOut,false,32565632156,"Winter2012_renamed",12386548974);
    }

    /**
     * method: updateMetadata
     * when: called
     * with: tokenAndFolderAndIdAndParent
     * should: returnMetadataMove
     */
    public function test_updateMetadata_called_tokenAndFolderAndIdAndParent_returnMetadataMove()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"update","file":false,"id":32565632156,"name":null,"parent":123456}}';
        $metadataOut = '{"name":"Winter2012_renamed","path":"/documents/clients/Winter2012_renamed","id":32565632156,"status":"CHANGED","version":2,"parent":123456,"user":"eyeos","client_modified":"2013-03-08 10:36:41.997","server_modified":"2013-03-08 10:36:41.997","is_root":false}';
        $this->exerciseUpdateMetadata($metadataIn,$metadataOut,$metadataOut,false,32565632156,null,123456);
    }

    /**
     * method: updateMetadata
     * when: called
     * with: tokenAndFolderAndId
     * should: returnMetadataMove
     */
    public function test_updateMetadata_called_tokenAndFolderAndId_returnMetadataMove()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"update","file":false,"id":32565632156,"name":null,"parent":null}}';
        $metadataOut = '{"name":"Winter2012_renamed","path":"/documents/clients/Winter2012_renamed","id":32565632156,"status":"CHANGED","version":2,"parent":null,"user":"eyeos","client_modified":"2013-03-08 10:36:41.997","server_modified":"2013-03-08 10:36:41.997","is_root":false}';
        $this->exerciseUpdateMetadata($metadataIn,$metadataOut,$metadataOut,false,32565632156);
    }

    /**
     * method: updateMetadata
     * when: called
     * with: tokenAndFolderAndId
     * should: returnException
     */
    public function test_updateMetadata_called_tokenAndFolderAndId_returnException()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"update","file":false,"id":32565632156,"name":null,"parent":null}}';
        $metadataOut = 'false';
        $this->exerciseUpdateMetadata($metadataIn,$metadataOut,$this->exception,false,32565632156);
    }

    /**
     * method: updateMetadata
     * when: called
     * with: tokenAndFolderAndId
     * should: returnPermissionDenied()
     */
    public function test_updateMetadata_called_tokenAndFolderAndId_returnPermissionDenied()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"update","file":false,"id":32565632156,"name":null,"parent":null}}';
        $metadataOut = '403';
        $this->exerciseUpdateMetadata($metadataIn,$metadataOut,$this->permission,false,32565632156);
    }

    /**
     * method: createMetadata
     * when: called
     * with: tokenAndFileAndNameAndParentAndPathAbsolute
     * should: returnCorrectData
     */
    public function test_createMetadata_called_tokenAndFileAndNameAndParentAndPathAbsolute_returnCorrectData()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"create","file":true,"filename":"Client1.pdf","parent_id":-348534824681,"path":"\/home\/eyeos\/Client1.pdf"}}';
        $metadataOut = '{"filename":"Client1.pdf","id":32565632156,"parent_id":-348534824681,"user":"eyeos"}';
        $pathAbsolute = '/home/eyeos/Client1.pdf';
        $this->exerciseCreateMetadata($metadataIn,$metadataOut,$metadataOut,true,'Client1.pdf',-348534824681,$pathAbsolute);
    }

    /**
     * method: createMetadata
     * when: called
     * with: tokenAndFileAndNameAndPathAbsolute
     * should: returnCorrectData
     */
    public function test_createMetadata_called_tokenAndFileAndNameAndPathAbsolute_returnCorrectData()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"create","file":true,"filename":"Client1.pdf","parent_id":null,"path":"\/home\/eyeos\/Client1.pdf"}}';
        $metadataOut = '{"filename":"Client1.pdf","id":32565632156,"parent_id":"null","user":"eyeos"}';
        $pathAbsolute = '/home/eyeos/Client1.pdf';
        $this->exerciseCreateMetadata($metadataIn,$metadataOut,$metadataOut,true,'Client1.pdf',null,$pathAbsolute);
    }

    /**
     * method: createMetadata
     * when: called
     * with: tokenAndFolderAndNameAndParentAndPathAbsolute
     * should: returnCorrectData
     */
    public function test_createMetadata_called_tokenAndFolderAndNameAndParentAndPathAbsolute_returnCorrectData()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"create","file":false,"filename":"clients","parent_id":-348534824681,"path":null}}';
        $metadataOut = '{"filename":"clients","id":9873615,"parent_id":-348534824681,"user":"eyeos","is_root":false}';
        $this->exerciseCreateMetadata($metadataIn,$metadataOut,$metadataOut,false,"clients",-348534824681);
    }

    /**
     * method: createMetadata
     * when: called
     * with: tokenAndFolderAndName
     * should: returnCorrectData
     */
    public function test_createMetadata_called_tokenAndFolderAndName_returnCorrectData()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"create","file":false,"filename":"clients","parent_id":null,"path":null}}';
        $metadataOut = '{"filename":"clients","id":9873615,"parent_id":null,"user":"eyeos","is_root":false}';
        $this->exerciseCreateMetadata($metadataIn,$metadataOut,$metadataOut,false,"clients");
    }

    /**
     * method: createMetadata
     * when: called
     * with: tokenAndFolderAndName
     * should: returnException
     */
    public function test_createMetadata_called_tokenAndFolderAndName_returnException()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"create","file":false,"filename":"clients","parent_id":null,"path":null}}';
        $metadataOut = 'false';
        $this->exerciseCreateMetadata($metadataIn,$metadataOut,$this->exception,false,"clients");
    }

    /**
     * method: createMetadata
     * when: called
     * with: tokenAndFolderAndName
     * should: returnPermissionDenied
     */
    public function test_createMetadata_called_tokenAndFolderAndName_returnPermissionDenied()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"create","file":false,"filename":"clients","parent_id":null,"path":null}}';
        $metadataOut = '403';
        $this->exerciseCreateMetadata($metadataIn,$metadataOut,$this->permission,false,"clients");
    }

    /**
     * method: uploadMetadata
     * when: called
     * with: tokenAndIdAndPath
     * should: returnCorrect
     */
    public function test_uploadMetadata_called_tokenAndIdAndPath_returnCorrect()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"upload","id":1234561,"path":"\/var\/www\/eyeos\/client.pdf"}}';
        $metadataOut = '{"status":true}';
        $this->exerciseUploadMetadata($metadataIn,$metadataOut,$metadataOut,1234561,"/var/www/eyeos/client.pdf");
    }

    /**
     * method: uploadMetadata
     * when: called
     * with: tokenAndIdAndPath
     * should: returnException
     */
    public function test_uploadMetadata_called_tokenAndIdAndPath_returnException()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"upload","id":1234561,"path":"\/var\/www\/eyeos\/client.pdf"}}';
        $metadataOut = 'false';
        $this->exerciseUploadMetadata($metadataIn,$metadataOut,$this->exception,1234561,"/var/www/eyeos/client.pdf");
    }

    /**
     * method: uploadMetadata
     * when: called
     * with: tokenAndIdAndPath
     * should: returnPermissionDenied
     */
    public function test_uploadMetadata_called_tokenAndIdAndPath_returnPermissionDenied()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"upload","id":1234561,"path":"\/var\/www\/eyeos\/client.pdf"}}';
        $metadataOut = '403';
        $this->exerciseUploadMetadata($metadataIn,$metadataOut,$this->permission,1234561,"/var/www/eyeos/client.pdf");
    }

    /**
     * method: downloadMetadata
     * when: called
     * with: tokenAndIdAndPath
     * should: returnCorrectDonwloadFile
     */
    public function test_downloadMetadata_called_tokenAndIdAndPath_returnCorrectDownloadFile()
    {
        $path = "/home/eyeos/prueba1.pdf";
        $metadataOut = 'true';
        $this->exerciseDownloadMetadata($metadataOut,$metadataOut,1234561,$path);
    }

    /**
     * method: downloadMetadata
     * when: called
     * with: tokenAndIdAndPath
     * should: returnException
     */
    public function test_downloadMetadata_called_tokenAndIdAndPath_returnException()
    {
        $path = "/home/eyeos/prueba2.pdf";
        $metadataOut = 'false';
        $this->exerciseDownloadMetadata($metadataOut,json_decode($this->exception),1234561,$path);
    }

    /**
     * method: downloadMetadata
     * when: called
     * with: tokenAndIdAndPath
     * should: returnPermissionDenied
     */
    public function test_downloadMetadata_called_tokenAndIdAndPath_returnPermisssionDenied()
    {
        $path = "/home/eyeos/prueba3.pdf";
        $metadataOut = '403';
        $this->exerciseDownloadMetadata($metadataOut,json_decode($this->permission),1234561,$path);
    }

    /**
     * method: deleteMetadata
     * when: called
     * with: tokenAndFileAndId
     * should: returnCorrectData
     */
    public function test_deleteMetadata_called_tokenAndFileAndId_returnCorrectData()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"delete","file":true,"id":32565632156}}';
        $metadataOut = '{"name":"Client1.pdf","path":"/documents/clients/Client1.pdf","id":32565632156,"size":775412,"mimetype":"application/pdf","status":"DELETED","version":3,"parent":-348534824681,"user":"eyeos","client_modified":"2013-03-08 10:36:41.997","server_modified":"2013-03-08 10:36:41.997"}';
        $this->exerciseDeleteMetadata($metadataIn,$metadataOut,$metadataOut,true,32565632156);
    }

    /**
     * method: deleteMetadata
     * when: called
     * with: tokenAndFolderAndId
     * should: returnCorrectData
     */
    public function test_deleteMetadata_called_tokenAndFolderAndId_returnCorrectData()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"delete","file":false,"id":9873615}}';
        $metadataOut = '{"name":"clients","path":"/documents/clients","id":9873615,"status":"DELETED","version":3,"parent":-348534824681,"user":"eyeos","client_modified":"2013-03-08 10:36:41.997","server_modified":"2013-03-08 10:36:41.997","is_root":false}';
        $this->exerciseDeleteMetadata($metadataIn,$metadataOut,$metadataOut,false,9873615);
    }

    /**
     * method: deleteMetadata
     * when: called
     * with: tokenAndFolderAndId
     * should: returnException
     */
    public function test_deleteMetadata_called_tokenAndFolderAndId_returnException()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"delete","file":false,"id":9873615}}';
        $metadataOut = 'false';
        $this->exerciseDeleteMetadata($metadataIn,$metadataOut,$this->exception,false,9873615);
    }

    /**
     * method: deleteMetadata
     * when: called
     * with: tokenAndFolderAndId
     * should: returnPermissionDenied
     */
    public function test_deleteMetadata_called_tokenAndFolderAndId_returnPermissionDenied()
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"delete","file":false,"id":9873615}}';
        $metadataOut = '403';
        $this->exerciseDeleteMetadata($metadataIn,$metadataOut,$this->permission,false,9873615);
    }

    private function exerciseGetMetadata($metadataIn,$metadataOut,$check,$file,$id,$contents = null)
    {
        $this->exerciseMockMetadata($metadataIn,$metadataOut);
        $result = $this->sut->getMetadata($this->token,$file,$id,$contents);
        $this->assertEquals(json_decode($check),$result);
    }

    private function exerciseUpdateMetadata($metadataIn,$metadataOut,$check,$file,$id,$name = null,$parent = null)
    {
        $this->exerciseMockMetadata($metadataIn,$metadataOut);
        $result = $this->sut->updateMetadata($this->token,$file,$id,$name,$parent);
        $this->assertEquals(json_decode($check),$result);
    }

    private function exerciseCreateMetadata($metadataIn,$metadataOut,$check,$file,$name,$parent = null,$pathAbsolute = null)
    {
        $this->exerciseMockMetadata($metadataIn,$metadataOut);
        $result = $this->sut->createMetadata($this->token,$file,$name,$parent,$pathAbsolute);
        $this->assertEquals(json_decode($check),$result);
    }

    private function exerciseUploadMetadata($metadataIn,$metadataOut,$check,$id,$path)
    {
        $this->exerciseMockMetadata($metadataIn,$metadataOut);
        $result = $this->sut->uploadMetadata($this->token,$id,$path);
        $this->assertEquals(json_decode($check),$result);
    }

    private function exerciseDownloadMetadata($metadataOut,$check,$id,$path)
    {
        $metadataIn = '{"token":{"key":"ABCD","secret":"EFGH"},"metadata":{"type":"download","id":1234561,"path":"' . $path . '"}}';
        $metadataIn = json_decode($metadataIn);
        $metadataIn = json_encode($metadataIn);
        $this->exerciseMockMetadata($metadataIn,$metadataOut);
        $result = $this->sut->downloadMetadata($this->token,$id,$path);
        $this->assertEquals($check,$result);
    }

    private function exerciseDeleteMetadata($metadataIn,$metadataOut,$check,$file,$id)
    {
        $this->exerciseMockMetadata($metadataIn,$metadataOut);
        $result = $this->sut->deleteMetadata($this->token,$file,$id);
        $this->assertEquals(json_decode($check),$result);
    }

    private function exerciseMockMetadata($metadataIn,$metadataOut)
    {
        $this->accessorProviderMock->expects($this->once())
            ->method('getProcessOauthCredentials')
            ->with($metadataIn)
            ->will($this->returnValue($metadataOut));
    }
}
?>