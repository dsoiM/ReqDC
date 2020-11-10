<?php

final class OutboundHttpRequestTest extends InitTests
{
    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRetry():void {
        //We allow smtp connect failure here due to sometimes tests are run without internet connection and then they cannot succeed in email sending
        //Yes its dangerous..
        
        $this->expectOutputRegex('/but after 3 tries over 5 seconds still failing|SMTP connect\(\) failed/is');
        $this->prepareMockupRequestForAPI();
        DataStorage::set('sendMessagesTestRetryCategory','URL','https://httpbin.org/status/501');
        DataStorage::set('sendMessagesTestRetryCategory','NotificationRecipients',Config::get('MAILFROM'));
        $_SERVER['MOCKUPBODY'] = '{"sendMessagesTestRetry":1,"abc":1}';
        $_REQUEST['synchronous'] = "true";
        $x = new MainAPI();
        $x->handle();
        
        /**
         * @var Execution $exec
         */
        $exec = session::getKeyRequestScope('mostRecentExecution');
        $i = 0;
        foreach ($exec->getLogs() as $l) {
            if (stripos($l->msg, 'Retry has been triggered') !== false) {
              $i++;  
            }
            
        }
        
        $this->assertEquals(3, $i,'Did not get three times "Retry has been triggered" in exec logs');
        
        
    }

}
