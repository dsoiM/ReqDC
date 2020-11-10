<?php 

final class ScheduleServiceTest extends InitTests {
    
    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPOStartedMaxLifetime() {
        
        
        //This is actually difficult to test, as we need to have one thread that gets stuck in background and one thread that finds it and kills it
        $this->expectOutputRegex('/"requestId":"\w{24}"/is');
        $this->prepareMockupRequestForAPI();
        $_SERVER['MOCKUPBODY'] = '{"getStuckForever": true,"testPOStartedMaxLifetime":true}';
        $x = new MainAPI();
        $x->handle();
        //TODO Grep for php process running
        sleep(3);
       
    }
    
    
    
    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test30SimultaneousThreads() {
        
        
        $this->expectOutputRegex('/"requestId":"\w{24}"/is');
        $this->prepareMockupRequestForAPI();
        $_SERVER['MOCKUPBODY'] = '{"test30SimultaneousThreads":true,"abc":true}';
        $x = new MainAPI();
        db::impl()->updateOne(['id' => TestConfig::implementationTestID], ['$set' => ['maxPOIDNumber' => 300]]);
        
        $oldcount = db::exc()->countDocuments();
        $amount = 30;
        for($i=0;$i<$amount;$i++) {
            $x->handle();
        }
        sleep(6);
        
        $newcount = db::exc()->countDocuments();
        $this->assertEquals($amount, $newcount - $oldcount,'Execution amount did not increase expected amount');
        //Restore old value of 1
        db::impl()->updateOne(['implementationId' => TestConfig::implementationTestID], ['$set' => ['maxPOIDNumber' => 1]]);
        
        
    }
    
    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test5OverlappingPOIDSimultaneousThreads() {
        
        
        $this->expectOutputRegex('/"requestId":"\w{24}"/is');
        $this->prepareMockupRequestForAPI();
        $_SERVER['MOCKUPBODY'] = '{"test5OverlappingPOIDSimultaneousThreads":true,"abc":true}';
        $x = new MainAPI();
        db::impl()->updateOne(['id' => TestConfig::implementationTestID], ['$set' => ['maxPOIDNumber' => 5]]);
        
        $oldcount = db::exc()->countDocuments();
        $amount = 10;
        for($i=0;$i<$amount;$i++) {
            $x->handle();
        }
        sleep(8);
        
        $newcount = db::exc()->countDocuments();
        $this->assertEquals($amount, $newcount - $oldcount,'Execution amount did not increase expected amount');
        //Restore old value of 1
        db::impl()->updateOne(['implementationId' => TestConfig::implementationTestID], ['$set' => ['maxPOIDNumber' => 1]]);
        
        
    }
    
    
}