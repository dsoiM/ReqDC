<?php

use Curl\Curl;

/**
 * Here we test high level stuff and do curl post to the api directly
 *
 *
 * @author vehja
 *
 */
final class CurlToAPITest extends InitTests
{

    private $reqidsuccess;

    private $excidsuccess;


    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSendSynchronousJSONRequestwithMass(): void
    {
        $apiURL = Config::get('APIURLINCONTAINER');

        
        $user = user::getById(TestConfig::userId);
        $user->selectTenantAndSaveToSession(TestConfig::testTenantID);

        $this->assertIsString($apiURL);
        
        

        $curl = new Curl();
        $curl->setBasicAuthentication(TestConfig::userId, TestConfig::userPw);
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setOpt(CURLOPT_RETURNTRANSFER, true);
        $curl->setDefaultJsonDecoder(true);
        $apiURLAccess = $apiURL . '/' . TestConfig::testTenantID . '/' . TestConfig::implementationTestID . '?synchronous=true';
        $curl->post($apiURLAccess, '{"a":"b"}');
        $this->assertEquals('ABC field did not exist in request JSON body', $curl->response["errorMessage"]);

        $apiURLNOaccess = $apiURL . '/' . TestConfig::testTenantID . '/UnitTestImplementation2';
        $curl->post($apiURLNOaccess, '{"a":"b"}');

        // Testimplementation DOES exist, it just is not permitted for this customerid
        $this->assertEquals("Implementation UnitTestImplementation2 not found", $curl->response["errorMessage"]);

        // Performance testing
        for ($i = 0; $i < 5; $i ++) {
            $apiURLAccess = $apiURL . '/' . TestConfig::testTenantID . '/' . TestConfig::implementationTestID . '?synchronous=true';

            $curl->post($apiURLAccess, '{"abc":"b"}');
            $respArr = $curl->response;

            $this->reqidsuccess = $respArr['requestId'];
            $this->excidsuccess = $respArr['executionId'];

            $this->assertFalse($curl->error, 'Curl made error');
            $this->assertRegExp('/\w{24}/is', $this->reqidsuccess, 'Didnt get request id');
            $this->assertRegExp('/\w{24}/is', $this->excidsuccess, 'Didnt get execution id');
            
            $res = log::getForExecution(su::strToObjectId($this->excidsuccess));
            
            $this->assertInstanceOf('Execution', Execution::getById(su::strToObjectId($this->excidsuccess)));
            $this->assertInstanceOf('Request', Request::getById(su::strToObjectId($this->reqidsuccess)));

            $this->assertEquals(TestConfig::implementationTestID, $res[0]->implementationId);
            $this->assertArrayHasKey(2, $res);
        }
    }

    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @Depends \MainAPITest::testMainAPIPostJSONScheduled
     */
    public function testSendScheduledJSONRequestMassConsecutive(): void
    {
        $apiURL = Config::get('APIURLINCONTAINER');
        
        //This test needs max to be one
        db::impl()->updateOne(['implementationId' => TestConfig::implementationTestID], ['$set' => ['maxPOIDNumber' => 1]]);
        $user = user::getById(TestConfig::userId);
        $user->selectTenantAndSaveToSession(TestConfig::testTenantID);
        $apiURLAccess = $apiURL . '/' . TestConfig::testTenantID . '/' . TestConfig::implementationTestID;

        $origExecCount = db::exc()->countDocuments([
            'implementationId' => TestConfig::implementationTestID
        ]);
        $curl = new Curl();
        $curl->setBasicAuthentication(TestConfig::userId, TestConfig::userPw);
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setDefaultJsonDecoder(true);
        $amount = 5;
        for ($i = 0; $i < $amount ; $i ++) {

            $curl->post($apiURLAccess, '{"abc":"b","sleep1sec":true}');
            $respArr = $curl->response;
            $this->reqidsuccess = $respArr['requestId'];
            $this->assertFalse($curl->error, 'Curl made error');
            $this->assertRegExp('/\w{24}/is', $this->reqidsuccess, 'Didnt get request id');
        }

        $poid = TestConfig::implementationTestID.'_1';
        $res = db::sched()->find([
            'preserveOrderId' => $poid ,
            'POStarted' => null
        ])->toArray();


        sleep((int)(round($amount * 2.5)));

        $res = db::sched()->find([
            'preserveOrderId' => $poid ,
            'lastRun' => null ,
            'cronExpression' => null
        ])->toArray();
        $this->assertEquals(0 , count($res),'After sleep pending schedules should be empty, it seems schedules are not being processed or sleep was too short'  );

        $newExecCount = db::exc()->countDocuments([
            'implementationId' => TestConfig::implementationTestID
        ]);

        $this->assertEquals($origExecCount, $newExecCount - $amount,'Executions did not increase the expected amount: '. $amount);

    }
}
