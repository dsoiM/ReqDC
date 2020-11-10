<?php

class MainImplementation1 extends Implementation
{

    public function getName()
    {
        return 'Main implementation 1';
    }

    public function getDescription()
    {
        return 'Do your first things here';
    }

    public function execute()
    {

        $rawPayload = $this->getCurrentRawPayload();
        $xmlPayload = $this->getExecution()->getRequest()->getXMLObject();
        $arrayConvertedFromJsonPayload = $this->getExecution()->getRequest()->getPayloadArr();
        
        log::info('You can do things here!');
        
        log::warn('And set response to synchronous execution like this:');

        

       
        #Save something to data storage:
        DataStorage::set('httpbintest', 'URL', 'https://httpbin.org/post');
        
        #Do outbound request, url and credentials are always in datastorage when using it so configuration is easy
        $out = OutboundHttpRequest::post('[1,3]','httpbintest');
        $out->maxRetryTime = 5;
        $out->send();
        #Maybe then later get the same value:
        $value = DataStorage::get('httpbintest', 'URL');
        $this->getExecution()->setResponseByArray([
            'requestId' => $this->getExecution()->getRequest()->getId()->__toString(),
            'executionId' => $this->getExecution()->getId()->__toString(),
            'thisberespondedcustomstring' => date('c'),
            'c' => 1,
            'httpBinResponse' => $out->curl->response
        ]);
        
        
        log::info('And then use the value: '. $value);
        
        
    }
}