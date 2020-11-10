<?php 
declare(strict_types=1);

class ControlApiTest extends InitTests
{

    /**
     * Change pw to garble and then change it back to what is defined in config
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testUserCategory() {
        $this->expectOutputString(' ');
        $this->prepareMockupRequestForAPI();
        $_SERVER['MOCKUPBODY'] = '[{"name":"oldpassword","value":"'.TestConfig::userPw.'"},{"name":"password","value":"asdf3210"},{"name":"repassword","value":"asdf3210"}]';
        $_SERVER['REQUEST_URI'] = '/'.TestConfig::testTenantID.'/control/user/passwordreset';
        $x = new MainAPI();
        $x->handle();
        
        $this->prepareMockupRequestForAPI();
        $_SERVER['PHP_AUTH_PW'] = 'asdf3210';
        $_SERVER['MOCKUPBODY'] = '[{"name":"oldpassword","value":"asdf3210"},{"name":"password","value":"'.TestConfig::userPw.'"},{"name":"repassword","value":"'.TestConfig::userPw.'"}]';
        $_SERVER['REQUEST_URI'] = '/'.TestConfig::testTenantID.'/control/user/passwordreset';
        $x = new MainAPI();
        $x->handle();
        
        
        
    }
}