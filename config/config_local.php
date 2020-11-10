<?php
class Config_Local extends Config
{

    const ENV = 'local';

    const MONGODBURL = 'mongodb://rootxx:examplqq@mongo:27017';
    const UIURL = 'http://127.0.0.1:12100';
    const APIURL = 'http://127.0.0.1:12100/api';
    
    const APIURLINCONTAINER="http://127.0.0.1:80/api"; //Dont change this
    const UIURLINCONTAINER="http://127.0.0.1:80/"; //Dont change this
    
    
    const MAINURL = 'http://127.0.0.1';
    const COOKIEDOMAIN = '127.0.0.1';
    const SENDMAILS = false;
    
    const MAILFROM = 'send.email@test.local';
    const MAILSERVER = 'smtp.mail.eu-west-1.awsapps.com';
    const MAILUSERNAME = 'mailUsername';
    
    //Must be encrypted using the encryption tool found in UI
    const MAILPASSWORD = 'def502002290c4fb146ea4850784940e773bc06b8cbcbf24b1c3a000a0942a12ec1a2cc0e1f0c88fce39cd5012d00dbdccc293e640bb6e342a760d875468f0284cbf663dc35765bdbb6927db2443b43aeb39fb50dcc301de';

    // Contains "what"
    const UNITTESTSECRET = "def502002290c4fb146ea4850784940e773bc06b8cbcbf24b1c3a000a0942a12ec1a2cc0e1f0c88fce39cd5012d00dbdccc293e640bb6e342a760d875468f0284cbf663dc35765bdbb6927db2443b43aeb39fb50dcc301de"; 

    const MAXSCHEDULESERVICES = 4;
}

