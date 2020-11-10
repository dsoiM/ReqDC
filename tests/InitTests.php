<?php 

use PHPUnit\Framework\TestCase;

class InitTests extends TestCase {
    
    public function setUp():void {
        require_once 'src/autoload.php';
        $user = user::getById(TestConfig::userId);
        $user->selectTenantAndSaveToSession(TestConfig::testTenantID);
        
    }
    
    public function tearDown():void {
    }
    
    public function prepareMockupRequestForAPI() {
        $_SERVER['MOCKUPHEADERS']['CONTENT-TYPE'] = 'JSON';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['PHP_AUTH_USER'] = TestConfig::userId;
        $_SERVER['PHP_AUTH_PW'] = TestConfig::userPw;
        $_SERVER['REQUEST_URI'] = '/'.TestConfig::testTenantID.'/'.TestConfig::implementationTestID;
    }
    
}
