<?php
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

require "vendor/autoload.php";

// Do this once then store it somehows:
$key = Key::createNewRandomKey();
var_dump( $key->saveToAsciiSafeString());

$message = 'We are all living in a yellow submarine';

$ciphertext = Crypto::encrypt($message, $key);
$plaintext = Crypto::decrypt($ciphertext, $key);

var_dump($ciphertext);
var_dump($plaintext);
