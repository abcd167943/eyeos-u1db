<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 23/05/14
 * Time: 13:32
 */

class OAuthManagerTest extends PHPUnit_Framework_TestCase
{
    private $oauthProviderMock;
    private $sut;
    private $token;

    public function setUp()
    {
        $this->oauthProviderMock = $this->getMock('OAuthProvider_');
        $this->sut = new OAuthManager($this->oauthProviderMock);
        $this->token = new stdClass();
        $this->token->token = new stdClass();
        $this->token->token->key = 'ABCD';
        $this->token->token->secret = 'EFG';
    }

    public function tearDown()
    {
        $this->oauthProviderMock = null;
        $this->sut = null;
    }

    /**
     * method: getRequestToken
     * when: called
     * with: noParams
     * should: returnToken
     */
    public function test_getRequestToken_called_noParams_returnToken()
    {
        $this->oauthProviderMock->expects($this->once())
            ->method('getRequestToken')
            ->will($this->returnValue($this->token));

        $this->sut->getRequestToken();
    }

    /**
     * method: getAccessToken
     * when: called
     * with: requestTokenAndVerifier
     * should: returnToken
     */
    public function test_getAccessToken_called_requestTokenAndVerifier_returnToken()
    {
        $this->token->verifier = 'verifier';
        $this->oauthProviderMock->expects($this->once())
            ->method('getAccessToken')
            ->with($this->token)
            ->will($this->returnValue(json_encode('{"key":"JKLM","secret":"JYLM"}')));
        $this->sut->getAccessToken($this->token);
    }
}


?>