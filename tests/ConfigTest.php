<?php

final class ConfigTest extends InitTests
{
    /**
     * Change pw to garble and then change it back to what is defined in config
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testReading():void {
        $sec = Config::getSecret('UNITTESTSECRET');
        $this->assertEquals('what', $sec);
        
    }
    
    
}
