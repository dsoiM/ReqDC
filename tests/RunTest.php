<?php

final class RunTest extends InitTests
{
    /**
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExecRun():void {
        $_SERVER['argv'][0] = 'src/run.php';
        $_SERVER['argv'][1] = 'Implementation';
        $_SERVER['argv'][2] = TestConfig::implementationTestID;
        $schedid = Schedule::setFromCronExpression(TestConfig::implementationTestID,'@daily' );
        $_SERVER['argv'][3] = $schedid->__toString();
        
        Run::main();
        $this->assertEquals(get_class($schedid) , MongoDB\BSON\ObjectId::class);
        //TODO: Check that stuff was done

    }
    
    
}
