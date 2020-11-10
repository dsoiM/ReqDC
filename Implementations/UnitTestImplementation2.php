<?php

class UnitTestImplementation2 extends Implementation
{

    public function getName()
    {
        return 'Unit test implementation 2';
    }

    public function getDescription()
    {
        return 'Unit test impl descr
Part 2';
    }

    public function execute()
    {

        Log::fatal('This code should never be executed as this implementation tests access rights');

    }
}