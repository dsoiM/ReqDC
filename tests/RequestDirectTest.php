<?php


final class RequestDirectTest extends InitTests
{
/**
  
    public function testDirectWithoutUser() {
        
        session::setUser(null);
        
        $this->expectExceptionMessage('User session must be set for direct request handling');

        RequestDirect::create(TestConfig::testTenantID, TestConfig::implementationTestID, [
            'abc' => 123
        ]);

    }
**/

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testStandaloneExecution()
    {
        $user = user::getById(TestConfig::userId);
        $user->selectTenantAndSaveToSession(TestConfig::testTenantID);

        $req = RequestDirect::create(TestConfig::testTenantID, TestConfig::implementationTestID, [
            'abc' => 123
        ]);
        
        $this->assertInstanceOf('RequestDirect', $req);
        $exc = Execution::newFromRequest($req);
        $this->assertInstanceOf('Execution', $exc);
        $exc->start();
    }


}