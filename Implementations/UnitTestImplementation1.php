<?php

class UnitTestImplementation1 extends Implementation
{


    public function execute()
    {

        // $t = RequestHttp::getById('b66b5f6c-ce95-49e3-87eb-c3bf1ddb15a5');
        //$s->get();
        Log::info('Inside UnitTestImplementation1 execute');
        $reqVal = $this->getExecution()->getRequest()->getPayloadArr();

        if (su::gis($reqVal['makeBigMistake'])) {
            asd();
        }

        
        if ($this->getExecution()->getRequest()->getXMLObject()) {
            $valx = (string) $this->getExecution()->getRequest()->getXMLObject()->xpath('//item/text()')[0];
        } else {
            $valx = null;
        }
         
        if (su::gis($reqVal['respondsome']) || $this->getExecution()->getRequest()->getXMLObject()) {
            
            
            $this->getExecution()->setResponseByArray([ 
                'requestId' => $this->getExecution()->getRequest()->getId()->__toString(),
                'executionId' => $this->getExecution()->getId()->__toString(),
                'thisberespondedcustomstring' => date('c'),
                'b' => $this->getExecution()->getRequest()->getPayloadArr(),
                'c' => 1,
                'xmlitem' => $valx
            ]);
            Log::info('Responded done');
            return;
            
        }
        
        if (su::gis($reqVal['sleep1sec'])) {
            sleep(1);
            Log::info('Sleep done');
        }
        if (!su::gis($reqVal['abc'])) {
            throw new HaltableException('ABC field did not exist in request JSON body');
        }





        if (su::gis($reqVal['sendMessagesTestRetry'])) {
            $br = OutboundHttpRequest::post('[1,3]','sendMessagesTestRetryCategory');
            $br->maxRetryTime = 5;
            $returnValue = $br->send();
            

        }





    }
}